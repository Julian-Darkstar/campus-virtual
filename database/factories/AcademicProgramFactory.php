<?php

namespace Database\Factories;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'code' => fake()->unique()->lexify('PROG-???'),
            'name' => 'Licenciatura en '.fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
