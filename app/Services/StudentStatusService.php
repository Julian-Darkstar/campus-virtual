<?php

namespace App\Services;

use App\Contracts\StudentStatusServiceInterface;
use App\Models\AcademicStatusHistory;
use App\Models\StudentProfile;
use App\Models\User;

class StudentStatusService implements StudentStatusServiceInterface
{
    public function resolve(User $user): array
    {
        $profile = $user->studentProfile;

        return $this->resolveProfile($profile, $user);
    }

    public function resolveByIdentifier(string $identifier): array
    {
        $profile = StudentProfile::find($identifier);

        if (! $profile) {
            $profile = StudentProfile::where('user_id', $identifier)->first();
        }

        return $this->resolveProfile($profile);
    }

    public function profileByIdentifier(string $identifier): ?StudentProfile
    {
        return StudentProfile::find($identifier)
            ?? StudentProfile::where('user_id', $identifier)->first();
    }

    public function history(StudentProfile $profile): array
    {
        $items = AcademicStatusHistory::where('student_profile_id', (string) $profile->getKey())
            ->orderByDesc('changed_at')
            ->get()
            ->map(fn (AcademicStatusHistory $item) => [
                'id' => (string) $item->getKey(),
                'from_status' => $item->from_status?->value,
                'to_status' => $item->to_status?->value,
                'status' => $item->to_status?->value,
                'status_label' => $item->to_status?->label(),
                'reason' => $item->reason,
                'changed_by' => $item->changed_by ? (string) $item->changed_by : null,
                'effective_from' => optional($item->changed_at)->toISOString(),
            ]);

        return $items->values()->all();
    }

    public function isOperationAllowed(array $student): bool
    {
        return ! $student['known'] || $student['status'] === 'active';
    }

    private function resolveProfile(?StudentProfile $profile, ?User $user = null): array
    {
        if (! $profile) {
            return [
                'student_id' => null,
                'user_id' => null,
                'name' => $user?->name,
                'enrollment' => null,
                'program' => null,
                'semester' => null,
                'campus' => null,
                'status' => null,
                'status_label' => null,
                'effective_from' => null,
                'restrictions' => [],
                'known' => false,
            ];
        }

        $status = $profile->academic_status;
        $latest = AcademicStatusHistory::where('student_profile_id', (string) $profile->getKey())
            ->orderByDesc('changed_at')
            ->first();

        $resolvedUser = $user ?? $profile->user;

        return [
            'student_id' => (string) $profile->getKey(),
            'user_id' => (string) $profile->user_id,
            'name' => $resolvedUser?->name,
            'enrollment' => $profile->enrollment_number,
            'program' => $profile->academicProgram?->name,
            'semester' => $profile->current_semester,
            'campus' => $profile->campus?->name,
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'effective_from' => optional($latest?->changed_at)->toISOString(),
            'restrictions' => $this->restrictionsFor($status?->value),
            'known' => true,
        ];
    }

    private function restrictionsFor(?string $status): array
    {
        return match ($status) {
            'suspended' => ['student_operations' => 'restricted'],
            'inactive', 'leave', 'graduated' => ['student_operations' => 'limited'],
            default => [],
        };
    }
}
