<?php

namespace App\Http\Requests;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Models\AcademicProgram;
use App\Models\StudentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StudentProfile::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'enrollment_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', 'unique:student_profiles,enrollment_number'],
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'academic_program_id' => ['required', 'integer', 'exists:academic_programs,id'],
            'current_semester' => ['required', 'integer', 'between:1,20'],
            'group_name' => ['nullable', 'string', 'max:30'],
            'academic_status' => ['required', Rule::enum(StudentStatus::class)],
            'status_reason' => ['nullable', 'string', 'max:300'],
            'personal_email' => ['nullable', 'email:rfc', 'max:255', 'different:email'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+() .-]+$/'],
            'preferred_contact_channel' => ['required', Rule::enum(PreferredContactChannel::class)],
            'locale' => ['required', Rule::in(['es-MX', 'en-US'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $programMatchesCampus = AcademicProgram::query()
                ->whereKey($this->integer('academic_program_id'))
                ->where('campus_id', $this->integer('campus_id'))
                ->exists();

            if (! $programMatchesCampus) {
                $validator->errors()->add('academic_program_id', 'La carrera no pertenece al campus seleccionado.');
            }

            if ($this->input('preferred_contact_channel') === PreferredContactChannel::PersonalEmail->value && ! $this->filled('personal_email')) {
                $validator->errors()->add('personal_email', 'Captura el correo personal seleccionado como canal preferido.');
            }

            if ($this->input('preferred_contact_channel') === PreferredContactChannel::Phone->value && ! $this->filled('phone')) {
                $validator->errors()->add('phone', 'Captura el teléfono seleccionado como canal preferido.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre completo', 'email' => 'correo institucional',
            'enrollment_number' => 'matrícula', 'campus_id' => 'campus',
            'academic_program_id' => 'carrera', 'current_semester' => 'semestre',
            'group_name' => 'grupo', 'academic_status' => 'estatus',
            'personal_email' => 'correo personal', 'phone' => 'teléfono', 'photo' => 'fotografía',
        ];
    }
}

