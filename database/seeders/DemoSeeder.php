<?php

namespace Database\Seeders;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Models\AcademicProgram;
use App\Models\AcademicStatusHistory;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@campusdigital.edu.mx'],
            ['name' => 'Administración Escolar', 'password' => Hash::make('CampusDigital2026!'), 'is_platform_admin' => true, 'account_activation_pending' => false, 'email_verified_at' => now()],
        );

        $names = ['Ana Martínez', 'Diego Hernández', 'Sofía Ramírez', 'Mateo García', 'Valentina López', 'Emiliano Torres', 'Camila Flores', 'Santiago Cruz', 'Renata Ortiz', 'Leonardo Morales', 'Mariana Vega', 'Sebastián Reyes'];
        $statuses = [StudentStatus::Active, StudentStatus::Active, StudentStatus::Active, StudentStatus::Leave, StudentStatus::Suspended, StudentStatus::Graduated];
        $programs = AcademicProgram::with('campus')->get();

        foreach ($names as $index => $name) {
            $program = $programs[$index % $programs->count()];
            $student = User::updateOrCreate(
                ['email' => 'alumno'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'@campusdigital.edu.mx'],
                ['name' => $name, 'password' => null, 'is_platform_admin' => false, 'account_activation_pending' => true],
            );
            $status = $statuses[$index % count($statuses)];
            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                [
                    'enrollment_number' => '2026'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'campus_id' => $program->campus_id,
                    'academic_program_id' => $program->id,
                    'current_semester' => ($index % 8) + 1,
                    'group_name' => chr(65 + ($index % 3)),
                    'academic_status' => $status,
                    'personal_email' => 'estudiante'.($index + 1).'@example.com',
                    'phone' => $index % 4 === 0 ? null : '55'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'preferred_contact_channel' => PreferredContactChannel::InstitutionalEmail,
                    'locale' => 'es-MX',
                ],
            );
            AcademicStatusHistory::firstOrCreate(
                ['student_profile_id' => $profile->id, 'to_status' => $status->value],
                ['from_status' => null, 'reason' => 'Carga de demostración', 'changed_by' => $admin->id, 'changed_at' => now()->subDays($index)],
            );
        }
    }
}

