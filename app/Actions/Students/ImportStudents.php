<?php

namespace App\Actions\Students;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Models\AcademicProgram;
use App\Models\Campus;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class ImportStudents
{
    private const HEADERS = ['matricula', 'nombre', 'correo_institucional', 'campus', 'carrera', 'semestre', 'grupo', 'estatus', 'correo_personal', 'telefono', 'canal_preferido'];

    public function __construct(private readonly UpsertStudentProfile $upsert) {}

    public function execute(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $campuses = Campus::with('academicPrograms')->get();
        $normalized = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $enrollment = Str::upper($row['matricula']);
            $campus = $campuses->first(fn ($item) => strtolower($item->code) === strtolower($row['campus']) || strtolower($item->name) === strtolower($row['campus']));
            $program = $campus?->academicPrograms->first(fn ($item) => strtolower($item->code) === strtolower($row['carrera']) || strtolower($item->name) === strtolower($row['carrera']));
            $data = [
                'name' => $row['nombre'], 'email' => Str::lower($row['correo_institucional']), 'enrollment_number' => $enrollment,
                'campus_id' => $campus?->getKey(), 'academic_program_id' => $program?->getKey(), 'current_semester' => $row['semestre'],
                'group_name' => $row['grupo'] ?: null, 'academic_status' => Str::lower($row['estatus']), 'personal_email' => $row['correo_personal'] ? Str::lower($row['correo_personal']) : null,
                'phone' => $row['telefono'] ?: null, 'preferred_contact_channel' => Str::lower($row['canal_preferido']), 'locale' => 'es-MX', 'status_reason' => 'Importación CSV',
            ];
            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email'], 'enrollment_number' => ['required', 'regex:/^[A-Za-z0-9-]+$/'],
                'campus_id' => ['required'], 'academic_program_id' => ['required'], 'current_semester' => ['required', 'integer', 'between:1,20'],
                'academic_status' => ['required'], 'preferred_contact_channel' => ['required'],
            ]);
            if (isset($seen[$enrollment])) $validator->errors()->add('enrollment_number', 'La matrícula está duplicada dentro del archivo.');
            $seen[$enrollment] = true;
            if (! in_array($data['academic_status'], array_column(StudentStatus::options(), 'value'), true)) $validator->errors()->add('academic_status', 'Estatus inválido.');
            if (! in_array($data['preferred_contact_channel'], array_column(PreferredContactChannel::options(), 'value'), true)) $validator->errors()->add('preferred_contact_channel', 'Canal inválido.');
            if ($validator->fails()) foreach ($validator->errors()->all() as $message) $errors[] = "Fila {$line}: {$message}";
            else $normalized[] = [$data, StudentProfile::where('enrollment_number', $enrollment)->first()?->user];
        }
        if ($errors) throw ValidationException::withMessages(['file' => $errors]);
        $result = ['created' => 0, 'updated' => 0];
        foreach ($normalized as [$data, $existing]) { $result[$existing ? 'updated' : 'created']++; $this->upsert->execute($data, $existing, $actor); }
        return $result;
    }

    private function readRows(UploadedFile $file): array
    {
        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $headers = array_map(fn ($header) => Str::lower(trim((string) $header, " \t\n\r\0\x0B\xEF\xBB\xBF")), $csv->fgetcsv());
        if ($headers !== self::HEADERS) throw ValidationException::withMessages(['file' => 'Los encabezados no coinciden con la plantilla.']);
        $rows = [];
        foreach ($csv as $index => $values) { if ($index === 0 || ! is_array($values) || ($values[0] ?? null) === null) continue; $values = array_pad(array_slice($values, 0, count($headers)), count($headers), ''); $rows[] = array_combine($headers, array_map('trim', $values)); }
        if (! $rows) throw ValidationException::withMessages(['file' => 'El archivo no contiene estudiantes.']);
        return $rows;
    }
}
