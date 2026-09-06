<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AcademicCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_students_from_csv(): void
    {
        $this->seed(AcademicCatalogSeeder::class);
        $admin = User::factory()->platformAdmin()->create();
        $csv = implode("\n", [
            'matricula,nombre,correo_institucional,campus,carrera,semestre,grupo,estatus,correo_personal,telefono,canal_preferido',
            '20260999,Lucía Prado,lucia.prado@campusdigital.edu.mx,CEN,ISC,3,A,active,lucia@example.com,5511223344,institutional_email',
        ]);

        $file = UploadedFile::fake()->createWithContent('estudiantes.csv', $csv);
        $this->actingAs($admin)->post(route('students.import'), ['file' => $file])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_profiles', ['enrollment_number' => '20260999', 'current_semester' => 3]);
    }
}

