<?php

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Events\StudentProfileChanged;
use App\Models\AcademicStatusHistory;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UpsertStudentProfile
{
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
        $previousStatus = $profile->academic_status?->value;
        $profile->fill(Arr::only($data, [
            'enrollment_number', 'campus_id', 'academic_program_id', 'current_semester',
            'group_name', 'academic_status', 'personal_email', 'phone',
            'preferred_contact_channel', 'locale',
        ]));
        if ($newPhotoPath) {
            $profile->photo_path = $newPhotoPath;
        }
        $changedFields = array_values(array_unique(array_merge(
            array_keys($student->getDirty()), array_keys($profile->getDirty())
        )));
        $profile->save();

        $nextStatus = StudentStatus::from($data['academic_status']);
        if ($previousStatus !== $nextStatus->value) {
            AcademicStatusHistory::create([
                'student_profile_id' => $profile->getKey(),
                'from_status' => $previousStatus,
                'to_status' => $nextStatus->value,
                'reason' => $data['status_reason'] ?? ($operation === 'created' ? 'Alta inicial' : null),
                'changed_by' => $actor?->getKey(),
                'changed_at' => now(),
            ]);
        }

        // Matriz de roles: "Estudiante (Rol Base Default) — Activación:
        // asignación automática al validar matrícula activa". Se asigna
        // aquí (no en el registro/Fortify) porque la matrícula activa es
        // justo lo que este Action valida; assignRole() ya es idempotente
        // por sí mismo si el usuario ya tiene el rol.
        if ($nextStatus === StudentStatus::Active) {
            $student->assignRole(\App\Models\Role::ESTUDIANTE);
        }
        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        StudentProfileChanged::dispatch((string) $student->getKey(), $operation, $changedFields, $actor?->getKey() ? (string) $actor->getKey() : null);
        return $student->fresh();
    }
}
