<?php

namespace App\Http\Controllers;

use App\Events\StudentConsentChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentServicesController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $this->student($request);

        return Inertia::render('StudentServices/Index', [
            'student' => $student,
            'consents' => $this->consentItems(),
            'preferences' => $this->preferenceValues(),
        ]);
    }

    public function status(Request $request, string $studentId): JsonResponse
    {
        return $this->success($this->student($request, $studentId));
    }

    public function statusHistory(Request $request, string $studentId): JsonResponse
    {
        return $this->success([
            'student_id' => $studentId,
            'items' => [
                [
                    'status' => 'active',
                    'reason' => 'initial_registration',
                    'effective_from' => '2026-01-15T00:00:00Z',
                    'recorded_at' => '2026-01-15T00:00:00Z',
                ],
            ],
        ]);
    }

    public function consents(Request $request, string $studentId): JsonResponse
    {
        return $this->success([
            'student_id' => $studentId,
            'items' => $this->consentItems(),
        ]);
    }

    public function acceptConsent(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'consent_id' => ['required', 'string'],
            'consent_version' => ['required', 'string'],
        ]);

        StudentConsentChanged::dispatch(
            $studentId,
            $validated['consent_id'],
            'accepted',
            $validated['consent_version'],
            $request->user()?->getKey() ? (string) $request->user()->getKey() : null,
        );

        return $this->success([
            'student_id' => $studentId,
            'consent_id' => $validated['consent_id'],
            'consent_version' => $validated['consent_version'],
            'status' => 'accepted',
            'accepted_at' => now()->toISOString(),
        ], 201);
    }

    public function revokeConsent(string $studentId, string $consentId): JsonResponse
    {
        StudentConsentChanged::dispatch($studentId, $consentId, 'revoked', 'current');

        return $this->success([
            'student_id' => $studentId,
            'consent_id' => $consentId,
            'status' => 'revoked',
            'revoked_at' => now()->toISOString(),
        ]);
    }

    public function preferences(Request $request, string $studentId): JsonResponse
    {
        return $this->success([
            'student_id' => $studentId,
            'preferences' => $this->preferenceValues(),
        ]);
    }

    public function updatePreferences(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['sometimes', 'boolean'],
            'push' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
        ]);

        return $this->success([
            'student_id' => $studentId,
            'preferences' => array_merge($this->preferenceValues(), $validated),
            'updated_at' => now()->toISOString(),
        ]);
    }

    private function student(Request $request, ?string $studentId = null): array
    {
        return [
            'student_id' => $studentId ?? 'stu_demo_001',
            'name' => $request->user()?->name ?? 'Estudiante de demostración',
            'enrollment' => 'CV-2026-001',
            'program' => 'Ingeniería de Software',
            'semester' => 4,
            'campus' => 'Campus principal',
            'status' => 'active',
            'status_label' => 'Activo',
            'effective_from' => '2026-01-15T00:00:00Z',
            'restrictions' => [],
        ];
    }

    private function consentItems(): array
    {
        return [
            [
                'id' => 'terms',
                'name' => 'Términos de uso',
                'description' => 'Reglas de uso de Campus Virtual.',
                'version' => '2026.1',
                'required' => true,
                'status' => 'accepted',
                'accepted_at' => '2026-01-15T10:30:00Z',
            ],
            [
                'id' => 'privacy',
                'name' => 'Aviso de privacidad',
                'description' => 'Tratamiento de datos personales y académicos.',
                'version' => '2026.1',
                'required' => true,
                'status' => 'accepted',
                'accepted_at' => '2026-01-15T10:30:00Z',
            ],
            [
                'id' => 'marketing',
                'name' => 'Comunicaciones institucionales',
                'description' => 'Novedades, eventos y beneficios del campus.',
                'version' => '2026.1',
                'required' => false,
                'status' => 'pending',
                'accepted_at' => null,
            ],
        ];
    }

    private function preferenceValues(): array
    {
        return [
            'email' => true,
            'push' => true,
            'sms' => false,
        ];
    }

    private function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => request()->header('X-Request-Id', (string) str()->uuid()),
                'api_version' => 'v1',
            ],
        ], $status);
    }
}