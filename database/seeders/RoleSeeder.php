<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'display_name' => 'Administrador de Plataforma', 'description' => 'Gestión y auditoría global'],
            ['name' => 'student', 'display_name' => 'Estudiante', 'description' => 'Acceso general al campus y servicios'],
            ['name' => 'student_manager', 'display_name' => 'Gestor de estudiantes', 'description' => 'Administra perfiles y condición académica'],
            ['name' => 'business_owner', 'display_name' => 'Propietario de Negocio', 'description' => 'Administra tiendas del campus'],
            ['name' => 'cashier', 'display_name' => 'Cajero de Negocio', 'description' => 'Cobros y validación de pagos'],
            ['name' => 'association_treasurer', 'display_name' => 'Tesorería de Asociación', 'description' => 'Manejo de caja y recargas'],
            ['name' => 'council_member', 'display_name' => 'Consejo Estudiantil', 'description' => 'Administración de apoyos y becas'],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(['name' => $roleData['name']], $roleData);
        }
    }
}
