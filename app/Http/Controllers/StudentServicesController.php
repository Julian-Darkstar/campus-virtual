<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\StudentPrivacyService;
use App\Services\StudentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentServicesController extends Controller
{
    public function __construct(
        private readonly StudentStatusService $statusService,
        private readonly StudentPrivacyService $privacyService,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()?->studentProfile;

        return Inertia::render('StudentServices/Index', [
            'student' => $profile
                ? $this->statusService->resolve($request->user())
                : [
                    'known' => false,
                    'student_id' => null,
                    'user_id' => (string) $request->user()->getKey(),
                    'name' => $request->user()->name,
                    'enrollment' => null,
                    'program' => null,
                    'semester' => null,
                    'campus' => null,
                    'status' => null,
                    'status_label' => null,
                    'effective_from' => null,
                    'restrictions' => [],
                ],
            'consents' => $profile ? $this->privacyService->consents($profile) : [],
            'preferences' => $profile ? $this->privacyService->preferences($profile) : [
                'email' => true,
                'push' => true,
                'sms' => false,
            ],
        ]);
    }

    public function status(Request $request, string $studentId): JsonResponse
    {
        return $this->success($this->statusService->resolveByIdentifier($studentId));
    }

    public function statusHistory(Request $request, string $studentId): JsonResponse
    {
        $profile = $this->statusService->profileByIdentifier($studentId);

        if (! $profile) {
            return $this->success(['student_id' => $studentId, 'items' => []]);
        }

        return $this->success([
            'student_id' => (string) $profile->getKey(),
            'items' => $this->statusService->history($profile),
        ]);
    }

    public function consents(Request $request, string $studentId): JsonResponse
    {
        $profile = $this->profileOrFail($studentId);

        return $this->success([
            'student_id' => (string) $profile->getKey(),
            'items' => $this->privacyService->consents($profile),
        ]);
    }

    public function acceptConsent(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'consent_id' => ['required', 'string', Rule::in(array_keys(StudentPrivacyService::CONSENTS))],
            'consent_version' => ['required', 'string'],
        ]);

        $profile = $this->profileOrFail($studentId);
        $this->assertCanMutateStudent($request, $profile);

        $record = $this->privacyService->accept(
            $profile,
            $validated['consent_id'],
            $validated['consent_version'],
            $request->user(),
        );

        return $this->success([
            'student_id' => (string) $profile->getKey(),
            'consent_id' => $record->consent_id,
            'consent_version' => $record->version,
            'status' => $record->status,
            'accepted_at' => optional($record->accepted_at)->toISOString(),
        ], 201);
    }

    public function revokeConsent(Request $request, string $studentId, string $consentId): JsonResponse
    {
        $profile = $this->profileOrFail($studentId);
        $this->assertCanMutateStudent($request, $profile);

        $record = $this->privacyService->revoke($profile, $consentId, $request->user());

        return $this->success([
            'student_id' => (string) $profile->getKey(),
            'consent_id' => $record->consent_id,
            'status' => $record->status,
            'revoked_at' => optional($record->revoked_at)->toISOString(),
        ]);
    }

    public function preferences(Request $request, string $studentId): JsonResponse
    {
        $profile = $this->profileOrFail($studentId);

        return $this->success([
            'student_id' => (string) $profile->getKey(),
            'preferences' => $this->privacyService->preferences($profile),
        ]);
    }

    public function updatePreferences(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['sometimes', 'boolean'],
            'push' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
        ]);

        $profile = $this->profileOrFail($studentId);
        $this->assertCanMutateStudent($request, $profile);
        $record = $this->privacyService->updatePreferences($profile, $validated, $request->user());

        return $this->success([
            'student_id' => (string) $profile->getKey(),
            'preferences' => [
                'email' => (bool) $record->email,
                'push' => (bool) $record->push,
                'sms' => (bool) $record->sms,
            ],
            'updated_at' => optional($record->updated_at)->toISOString(),
        ]);
    }

    private function profileOrFail(string $studentId): StudentProfile
    {
        return $this->statusService->profileByIdentifier($studentId)
            ?? abort(404, 'El estudiante no existe.');
    }

    private function assertCanMutateStudent(Request $request, StudentProfile $profile): void
    {
        // Navegación web: un estudiante solo puede modificar su propia
        // privacidad. Los administradores/gestores podrán operar sobre
        // perfiles desde el módulo de administración cuando corresponda.
        if ($request->user()) {
            $user = $request->user();
            $isOwner = (string) $profile->user_id === (string) $user->getKey();
            $isManager = $user->can('update', $profile);

            abort_unless($isOwner || $isManager, 403);
        }
    }

    private function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => request()->attributes->get('correlation_id', request()->header('X-Request-Id', (string) str()->uuid())),
                'api_version' => 'v1',
            ],
        ], $status);
    }
}
