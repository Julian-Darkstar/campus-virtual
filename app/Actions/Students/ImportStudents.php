<?php

namespace App\Actions\Students;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Models\AcademicProgram;
use App\Models\Campus;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class ImportStudents
{
    private const HEADERS = [
        'matricula', 'nombre', 'correo_institucional', 'campus', 'carrera',
        'semestre', 'grupo', 'estatus', 'correo_personal', 'telefono', 'canal_preferido',
    ];

    public function __construct(private readonly UpsertStudentProfile $upsertStudentProfile) {}

    /** @return array{created: int, updated: int} */
    public function execute(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $campuses = Campus::with('academicPrograms')->get();
        $errors = [];
        $normalizedRows = [];
        $seenEnrollmentNumbers = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $enrollmentNumber = Str::upper($row['matricula']);
            $campus = $campuses->first(fn (Campus $item) => $this->matches($item->code, $row['campus']) || $this->matches($item->name, $row['campus']));
            $program = $campus?->academicPrograms->first(fn (AcademicProgram $item) => $this->matches($item->code, $row['carrera']) || $this->matches($item->name, $row['carrera']));
            $existing = StudentProfile::with('user')->where('enrollment_number', $enrollmentNumber)->first();

            $data = [
                'name' => $row['nombre'],
                'email' => Str::lower($row['correo_institucional']),
                'enrollment_number' => $enrollmentNumber,
                'campus_id' => $campus?->id,
                'academic_program_id' => $program?->id,
                'current_semester' => $row['semestre'],
                'group_name' => $row['grupo'] ?: null,
                'academic_status' => Str::lower($row['estatus']),
                'personal_email' => $row['correo_personal'] ? Str::lower($row['correo_personal']) : null,
                'phone' => $row['telefono'] ?: null,
                'preferred_contact_channel' => Str::lower($row['canal_preferido']),
                'locale' => 'es-MX',
                'status_reason' => 'Importación CSV',
            ];

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email:rfc', Rule::unique('users', 'email')->ignore($existing?->user_id)],
                'enrollment_number' => ['required', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
                'campus_id' => ['required', 'exists:campuses,id'],
                'academic_program_id' => ['required', 'exists:academic_programs,id'],
                'current_semester' => ['required', 'integer', 'between:1,20'],
                'academic_status' => ['required', Rule::enum(StudentStatus::class)],
                'personal_email' => ['nullable', 'email:rfc', 'different:email'],
                'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+() .-]+$/'],
                'preferred_contact_channel' => ['required', Rule::enum(PreferredContactChannel::class)],
            ]);

            if (isset($seenEnrollmentNumbers[$data['enrollment_number']])) {
                $validator->errors()->add('enrollment_number', 'La matrícula está duplicada dentro del archivo.');
            }
            $seenEnrollmentNumbers[$data['enrollment_number']] = true;

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Fila {$line}: {$message}";
                }
                continue;
            }

            $normalizedRows[] = [$data, $existing?->user];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        $result = ['created' => 0, 'updated' => 0];
        DB::transaction(function () use ($normalizedRows, $actor, &$result): void {
            foreach ($normalizedRows as [$data, $existingUser]) {
                $result[$existingUser ? 'updated' : 'created']++;
                $this->upsertStudentProfile->execute($data, $existingUser, $actor);
            }
        });

        return $result;
    }

    /** @return array<int, array<string, string>> */
    private function readRows(UploadedFile $file): array
    {
        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(',', '"', '\\');

        $headers = $csv->fgetcsv();
        if ($headers === false) {
            throw ValidationException::withMessages(['file' => 'El archivo está vacío.']);
        }

        $headers = array_map(fn ($header) => Str::lower(trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B")), $headers);
        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'Los encabezados no coinciden. Usa la plantilla incluida en docs/student_import_template.csv.',
            ]);
        }

        $rows = [];
        foreach ($csv as $index => $values) {
            if ($index === 0) {
                continue;
            }

            if (! is_array($values) || count($values) === 1 && $values[0] === null) {
                continue;
            }
            $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
            $rows[] = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene estudiantes.']);
        }

        return $rows;
    }

    private function matches(string $left, string $right): bool
    {
        return Str::lower(trim($left)) === Str::lower(trim($right));
    }
}
