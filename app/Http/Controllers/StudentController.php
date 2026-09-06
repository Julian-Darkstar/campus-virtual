<?php

namespace App\Http\Controllers;

use App\Actions\Students\UpsertStudentProfile;
use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Campus;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StudentProfile::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'campus' => ['nullable', 'integer'],
        ]);

        $query = StudentProfile::query()
            ->with(['user', 'campus', 'academicProgram'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('enrollment_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('academic_status', $status))
            ->when($filters['campus'] ?? null, fn ($query, int $campus) => $query->where('campus_id', $campus))
            ->latest('updated_at');

        $students = $query->paginate(10)->withQueryString()->through(fn (StudentProfile $profile) => [
            'id' => $profile->user_id,
            'name' => $profile->user->name,
            'email' => $profile->user->email,
            'enrollment_number' => $profile->enrollment_number,
            'campus' => $profile->campus->name,
            'program' => $profile->academicProgram->name,
            'semester' => $profile->current_semester,
            'group' => $profile->group_name,
            'status' => ['value' => $profile->academic_status->value, 'label' => $profile->academic_status->label()],
            'photo_url' => $profile->photo_path ? Storage::disk('public')->url($profile->photo_path) : null,
            'updated_at' => $profile->updated_at->diffForHumans(),
        ]);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $filters,
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => StudentStatus::options(),
            'statistics' => [
                'total' => StudentProfile::count(),
                'active' => StudentProfile::where('academic_status', StudentStatus::Active->value)->count(),
                'incomplete' => StudentProfile::where(fn ($query) => $query->whereNull('photo_path')->orWhereNull('phone'))->count(),
                'recent' => StudentProfile::where('updated_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StudentProfile::class);

        return Inertia::render('Students/Create', $this->formOptions());
    }

    public function store(StoreStudentRequest $request, UpsertStudentProfile $action): RedirectResponse
    {
        $student = $action->execute($request->validated(), actor: $request->user());

        return to_route('students.edit', $student)->with('success', 'La cuenta del estudiante se creó correctamente.');
    }

    public function edit(User $student): Response
    {
        $student->load(['studentProfile.campus', 'studentProfile.academicProgram', 'studentProfile.statusHistory.changedBy']);
        abort_unless($student->studentProfile, 404);
        $this->authorize('update', $student->studentProfile);
        $profile = $student->studentProfile;

        return Inertia::render('Students/Edit', array_merge($this->formOptions(), [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'enrollment_number' => $profile->enrollment_number,
                'campus_id' => $profile->campus_id,
                'academic_program_id' => $profile->academic_program_id,
                'current_semester' => $profile->current_semester,
                'group_name' => $profile->group_name,
                'academic_status' => $profile->academic_status->value,
                'personal_email' => $profile->personal_email,
                'phone' => $profile->phone,
                'preferred_contact_channel' => $profile->preferred_contact_channel->value,
                'locale' => $profile->locale,
                'photo_url' => $profile->photo_path ? Storage::disk('public')->url($profile->photo_path) : null,
                'activation_pending' => $student->account_activation_pending,
                'status_history' => $profile->statusHistory->map(fn ($item) => [
                    'id' => $item->id,
                    'from' => $item->from_status?->label(),
                    'to' => $item->to_status->label(),
                    'reason' => $item->reason,
                    'changed_by' => $item->changedBy?->name ?? 'Sistema',
                    'changed_at' => $item->changed_at->format('d/m/Y H:i'),
                ]),
            ],
        ]));
    }

    public function update(UpdateStudentRequest $request, User $student, UpsertStudentProfile $action): RedirectResponse
    {
        $action->execute($request->validated(), $student, $request->user());

        return back()->with('success', 'Los cambios del perfil se guardaron correctamente.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'campuses' => Campus::query()->with(['academicPrograms' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->where('is_active', true)->orderBy('name')->get()->map(fn (Campus $campus) => [
                    'id' => $campus->id,
                    'code' => $campus->code,
                    'name' => $campus->name,
                    'programs' => $campus->academicPrograms->map->only(['id', 'code', 'name']),
                ]),
            'statuses' => StudentStatus::options(),
            'contactChannels' => PreferredContactChannel::options(),
        ];
    }
}
