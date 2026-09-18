<?php

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Events\StudentProfileChanged;
use App\Models\AcademicStatusHistory;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ExecutesMongoAtomically;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UpsertStudentProfile
{
    use ExecutesMongoAtomically;

    public function execute(array $data, ?User $student = null, ?User $actor = null): User
    {
        $newPhotoPath = ($data['photo'] ?? null) instanceof UploadedFile
            ? $data['photo']->store('student-photos', 'public')
            : null;
        $operation = $student?->exists ? 'updated' : 'created';
        $student ??= new User;
        $oldPhotoPath = $student->studentProfile?->photo_path;
        $student->fill(Arr::only($data, ['name', 'email']));
        if (! $student->exists) {
            $student->password = null;
            $student->account_activation_pending = true;
        }
        $student->save();

        $profile = $student->studentProfile ?? new StudentProfile(['user_id' => $student->getKey()]);
        $isNewProfile = ! $profile->exists;
        $previousStatus = $profile->academic_status?->value;
        $profile->fill(Arr::only($data, [
            'enrollment_number', 'campus_id', 'academic_program_id', 'current_semester',
            'group_name', 'personal_email', 'phone',
            'preferred_contact_channel', 'locale',
        ]));
        if ($isNewProfile) {
            $profile->academic_status = $data['academic_status'];
        }
        if ($newPhotoPath) {
            $profile->photo_path = $newPhotoPath;
        }
        $changedFields = array_values(array_unique(array_merge(
            array_keys($student->getDirty()), array_keys($profile->getDirty())
        )));
        $nextStatus = StudentStatus::from($data['academic_status']);
        if ($isNewProfile) {
            $this->mongoTransaction(function () use ($profile, $previousStatus, $nextStatus, $data, $operation, $actor): void {
                $profile->save();
                AcademicStatusHistory::create([
                    'student_profile_id' => (string) $profile->getKey(),
                    'from_status' => $previousStatus,
                    'to_status' => $nextStatus->value,
                    'reason' => $data['status_reason'] ?? ($operation === 'created' ? 'Alta inicial' : 'Alta de perfil académico'),
                    'changed_by' => $actor?->getKey(),
                    'changed_at' => now(),
                ]);
            });
        } else {
            $profile->save();
        }

        if (! $isNewProfile && $previousStatus !== $nextStatus->value) {
            app(ChangeStudentAcademicStatus::class)->execute(
                $profile,
                $nextStatus->value,
                $data['status_reason'] ?? 'Actualización académica',
                $actor ?? $student,
            );
        }
        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        StudentProfileChanged::dispatch((string) $student->getKey(), $operation, $changedFields, $actor?->getKey() ? (string) $actor->getKey() : null);
        return $student->fresh();
    }
}
