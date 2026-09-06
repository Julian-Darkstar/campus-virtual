<?php

namespace Tests\Feature;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Models\AcademicProgram;
use App\Models\Campus;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AcademicCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicCatalogSeeder::class);
    }

    public function test_platform_admin_can_create_a_student_profile(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $program = AcademicProgram::firstOrFail();

        $response = $this->actingAs($admin)->post(route('students.store'), [
            'name' => 'Andrea Mendoza',
            'email' => 'andrea.mendoza@campusdigital.edu.mx',
            'enrollment_number' => '20260099',
            'campus_id' => $program->campus_id,
            'academic_program_id' => $program->id,
            'current_semester' => 4,
            'group_name' => 'B',
            'academic_status' => StudentStatus::Active->value,
            'personal_email' => 'andrea@example.com',
            'phone' => '5512345678',
            'preferred_contact_channel' => PreferredContactChannel::InstitutionalEmail->value,
            'locale' => 'es-MX',
        ]);

        $student = User::where('email', 'andrea.mendoza@campusdigital.edu.mx')->firstOrFail();
        $response->assertRedirect(route('students.edit', $student));
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'enrollment_number' => '20260099']);
        $this->assertDatabaseHas('academic_status_history', ['student_profile_id' => $student->studentProfile->id, 'to_status' => 'active']);
    }

    public function test_non_admin_cannot_access_student_directory(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('students.index'))->assertForbidden();
    }

    public function test_program_must_belong_to_selected_campus(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $campuses = Campus::with('academicPrograms')->get();

        $this->actingAs($admin)->post(route('students.store'), [
            'name' => 'Cuenta inválida',
            'email' => 'invalida@campusdigital.edu.mx',
            'enrollment_number' => '20260100',
            'campus_id' => $campuses[0]->id,
            'academic_program_id' => $campuses[1]->academicPrograms->first()->id,
            'current_semester' => 1,
            'academic_status' => 'active',
            'preferred_contact_channel' => 'institutional_email',
            'locale' => 'es-MX',
        ])->assertSessionHasErrors('academic_program_id');

        $this->assertDatabaseMissing('student_profiles', ['enrollment_number' => '20260100']);
    }
}

