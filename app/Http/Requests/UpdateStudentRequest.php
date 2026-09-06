<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends StoreStudentRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student?->studentProfile instanceof StudentProfile
            && ($this->user()?->can('update', $student->studentProfile) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $student = $this->route('student');

        $rules['email'] = ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($student?->id)];
        $rules['enrollment_number'] = [
            'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/',
            Rule::unique('student_profiles', 'enrollment_number')->ignore($student?->studentProfile?->id),
        ];

        return $rules;
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $originalStatus = $this->route('student')?->studentProfile?->academic_status?->value;
                if ($originalStatus !== $this->input('academic_status') && ! $this->filled('status_reason')) {
                    $validator->errors()->add('status_reason', 'Indica el motivo del cambio de estatus.');
                }
            },
        ];
    }
}
