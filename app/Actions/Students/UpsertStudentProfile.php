<?php

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Events\StudentProfileChanged;
use App\Models\AcademicStatusHistory;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpsertStudentProfile
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?User $student = null, ?User $actor = null): User
    {
        $newPhotoPath = null;
        $oldPhotoPath = $student?->studentProfile?->photo_path;

        if (($data['photo'] ?? null) instanceof UploadedFile) {
            $newPhotoPath = $data['photo']->store('student-photos', 'public');
        }

        try {
            [$student, $changedFields, $operation] = DB::transaction(function () use ($data, $student, $actor, $newPhotoPath): array {
                $operation = $student ? 'updated' : 'created';
                $student ??= new User;
                $student->fill(Arr::only($data, ['name', 'email']));

                if (! $student->exists) {
                    $student->password = null;
                    $student->is_platform_admin = false;
                    $student->account_activation_pending = true;
                }

                $userChangedFields = array_keys($student->getDirty());
                $student->save();

                $profile = $student->studentProfile ?? new StudentProfile(['user_id' => $student->id]);
                $previousStatus = $profile->exists ? $profile->academic_status?->value : null;

                $profileData = Arr::only($data, [
                    'enrollment_number', 'campus_id', 'academic_program_id', 'current_semester',
                    'group_name', 'academic_status', 'personal_email', 'phone',
                    'preferred_contact_channel', 'locale',
                ]);

                if ($newPhotoPath) {
                    $profileData['photo_path'] = $newPhotoPath;
                }

                $profile->fill($profileData);
                $changedFields = array_values(array_unique(array_merge(
                    $userChangedFields,
                    array_keys($profile->getDirty()),
                )));
                $profile->save();

                $nextStatus = StudentStatus::from($data['academic_status']);
                if ($previousStatus !== $nextStatus->value) {
                    AcademicStatusHistory::create([
                        'student_profile_id' => $profile->id,
                        'from_status' => $previousStatus,
                        'to_status' => $nextStatus,
                        'reason' => $data['status_reason'] ?? ($operation === 'created' ? 'Alta inicial' : null),
                        'changed_by' => $actor?->id,
                        'changed_at' => now(),
                    ]);
                }

                return [$student, $changedFields, $operation];
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }
            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        StudentProfileChanged::dispatch($student->id, $operation, $changedFields, $actor?->id);

        return $student->fresh(['studentProfile.campus', 'studentProfile.academicProgram']);
    }
}
