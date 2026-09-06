<?php

namespace Database\Seeders;

use App\Models\AcademicProgram;
use App\Models\Campus;
use Illuminate\Database\Seeder;

class AcademicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'CEN' => ['name' => 'Campus Centro', 'programs' => [
                'ISC' => 'Ingeniería en Sistemas Computacionales',
                'LAD' => 'Licenciatura en Administración',
                'LCI' => 'Licenciatura en Comercio Internacional',
            ]],
            'NOR' => ['name' => 'Campus Norte', 'programs' => [
                'IIN' => 'Ingeniería Industrial',
                'ARQ' => 'Arquitectura',
                'LDE' => 'Licenciatura en Derecho',
            ]],
            'SUR' => ['name' => 'Campus Sur', 'programs' => [
                'LPS' => 'Licenciatura en Psicología',
                'LNU' => 'Licenciatura en Nutrición',
                'ICV' => 'Ingeniería Civil',
            ]],
        ];

        foreach ($catalog as $code => $data) {
            $campus = Campus::updateOrCreate(['code' => $code], ['name' => $data['name'], 'is_active' => true]);
            foreach ($data['programs'] as $programCode => $name) {
                AcademicProgram::updateOrCreate(
                    ['campus_id' => $campus->id, 'code' => $programCode],
                    ['name' => $name, 'is_active' => true],
                );
            }
        }
    }
}

