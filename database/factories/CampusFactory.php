<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CampusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('C-###'),
            'name' => 'Campus '.fake()->city(),
            'is_active' => true,
        ];
    }
}

