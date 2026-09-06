<?php

namespace Database\Factories;

use App\Enums\PreferredContactChannel;
use App\Enums\StudentStatus;
use App\Models\AcademicProgram;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
    public function definition(): array
    {
        $campus = Campus::query()->first() ?? Campus::factory()->create();
        $program = AcademicProgram::query()->where('campus_id', $campus->id)->first()
            ?? AcademicProgram::create(['campus_id' => $campus->id, 'code' => fake()->unique()->lexify('PROG-???'), 'name' => fake()->jobTitle(), 'is_active' => true]);

        return [
            'user_id' => User::factory(),
            'enrollment_number' => fake()->unique()->numerify('2026####'),
            'campus_id' => $campus->id,
            'academic_program_id' => $program->id,
            'current_semester' => fake()->numberBetween(1, 10),
            'group_name' => fake()->randomElement(['A', 'B', 'C']),
            'academic_status' => StudentStatus::Active,
            'personal_email' => fake()->safeEmail(),
            'phone' => fake()->numerify('55########'),
            'preferred_contact_channel' => PreferredContactChannel::InstitutionalEmail,
            'locale' => 'es-MX',
        ];
    }
}

