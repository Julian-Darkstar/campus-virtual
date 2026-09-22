<?php

namespace Database\Seeders;

use App\Actions\Students\UpsertStudentProfile;
use App\Models\AcademicProgram;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas de prueba para construir un perfil por cada rol de la
 * "Matriz Operativa de Roles, Permisos y Restricciones - Campus
 * Digital", con contraseña única 'password' para poder iniciar
 * sesión y verificar permisos manualmente.
 *
 * Convención de correos (ver notas donde no hay ejemplo dado):
 *  - Estudiantes (incl. roles de negocio, que en la matriz los
 *    ocupa un alumno autorizado): matrícula@estudiante.com
 *  - Maestros: apellido.nombre@maestro.com
 *  - Asociaciones / Consejo: cuenta compartida por cargo,
 *    identificador@consejo.com (ej. asociacionSistemas@consejo.com)
 *  - Personal institucional sin ejemplo dado en la matriz
 *    (administración, auditoría, servicios): nombre.apellido@
 *    administracion.com o @servicios.com — CONVENCIÓN PROPUESTA,
 *    ajustar si la universidad ya tiene un dominio real para ellos.
 *
 * No crea entidades de "negocio" o "asociación" reales (eso es
 * dominio de Equipo 3 y Equipo 6): scope_id usa un identificador de
 * demostración en texto plano, tal como lo permite el diseño actual
 * de scope_type/scope_id en User::assignRole().
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $campus = Campus::where('code', 'CENTRAL')->first();
        $isc = AcademicProgram::where('code', 'ISC')->where('campus_id', $campus?->getKey())->first();
        $adm = AcademicProgram::where('code', 'ADM')->where('campus_id', $campus?->getKey())->first();

        if (! $campus || ! $isc || ! $adm) {
            $this->command?->warn('DemoAccountsSeeder: corre primero StudentCatalogSeeder (campus/carreras).');

            return;
        }

        $upsert = app(UpsertStudentProfile::class);

        // ------------------------------------------------------------
        // Cuentas de estudiante (perfil académico real vía el mismo
        // Action que usa el alta manual y la importación CSV, para
        // que también disparen el auto-assign de "estudiante").
        // ------------------------------------------------------------
        $students = [
            // [matricula, nombre, programa, semestre, role adicional, scope_type, scope_id]
            ['2203165', 'Ana Sofía Ramírez Torres', $isc, 5, null, null, null],
            ['2101987', 'Luis Fernando Herrera Ponce', $adm, 7, Role::PROPIETARIO_NEGOCIO, 'business', 'negocio-cafeteria-central'],
            ['2154302', 'Diana Paola Cruz Medina', $isc, 6, Role::GERENTE_NEGOCIO, 'business', 'negocio-cafeteria-central'],
            ['2199981', 'Carlos Iván Mendoza Ruiz', $adm, 3, Role::CAJERO_NEGOCIO, 'business', 'negocio-cafeteria-central'],
            ['2087765', 'Regina Paola Salinas Ibarra', $isc, 8, Role::RESPONSABLE_INVENTARIO, 'business', 'tienda-souvenirs-oficial'],
            ['2076554', 'Emmanuel Osorio Vega', $adm, 9, Role::COMPRADOR_ABASTECIMIENTO, 'business', 'tienda-souvenirs-oficial'],
            ['2145578', 'Ximena Beltrán Solís', $isc, 4, Role::SERVICIO_CAFETERIA, 'business', 'negocio-cafeteria-central'],
        ];

        foreach ($students as [$matricula, $nombre, $programa, $semestre, $extraRole, $scopeType, $scopeId]) {
            $email = "{$matricula}@estudiante.com";
            $existing = User::where('email', $email)->first();

            $student = $upsert->execute([
                'name' => $nombre,
                'email' => $email,
                'enrollment_number' => $matricula,
                'campus_id' => $campus->getKey(),
                'academic_program_id' => $programa->getKey(),
                'current_semester' => $semestre,
                'group_name' => null,
                'academic_status' => 'active',
                'personal_email' => null,
                'phone' => null,
                'preferred_contact_channel' => 'institutional_email',
                'locale' => 'es-MX',
                'status_reason' => 'Cuenta de demostración (DemoAccountsSeeder)',
            ], $existing);

            // assignRole ya tiene contraseña null porque UpsertStudentProfile
            // deja account_activation_pending=true para altas nuevas; para
            // poder iniciar sesión en pruebas se fuerza aquí una contraseña.
            $student->forceFill(['password' => Hash::make('password'), 'account_activation_pending' => false])->save();

            if ($extraRole) {
                $student->assignRole($extraRole, $scopeType, $scopeId);
            }
        }

        // ------------------------------------------------------------
        // Cuentas institucionales / de personal (sin perfil académico).
        // ------------------------------------------------------------
        $staff = [
            // [email, nombre, role, scope_type, scope_id]
            ['vazquez.jorge@maestro.com', 'Jorge Vázquez', Role::MAESTRO, null, null],
            ['asociacionSistemas@consejo.com', 'Presidencia · Asociación de Sistemas', Role::PRESIDENCIA_ASOCIACION, 'association', 'asociacion-sistemas'],
            ['tesoreria.sistemas@consejo.com', 'Tesorería · Asociación de Sistemas', Role::TESORERIA_ASOCIACION, 'association', 'asociacion-sistemas'],
            ['caja.sistemas@consejo.com', 'Caja · Asociación de Sistemas', Role::AGENTE_RECARGA_RETIRO, 'association', 'asociacion-sistemas'],
            ['comunicacion.sistemas@consejo.com', 'Comunicación · Asociación de Sistemas', Role::COMUNICACION_ASOCIACION, 'association', 'asociacion-sistemas'],
            ['comite.becas@administracion.com', 'Comité de Becas y Apoyos', Role::COMITE_BECAS, null, null],
            ['martha.jimenez@servicios.com', 'Martha Jiménez', Role::BIBLIOTECARIO, 'service', 'biblioteca-central'],
            ['soporte.ti@servicios.com', 'Soporte TI Campus', Role::AGENTE_SOPORTE, 'service', 'soporte-ti'],
            ['monica.fuentes@administracion.com', 'Mónica Fuentes', Role::ADMINISTRADOR_RECOMPENSAS, null, null],
            ['patricia.gomez@administracion.com', 'Patricia Gómez', Role::ADMIN, null, null],
            ['ricardo.paredes@administracion.com', 'Ricardo Paredes', Role::AUDITOR, null, null],
        ];

        foreach ($staff as [$email, $nombre, $role, $scopeType, $scopeId]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $nombre,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole($role, $scopeType, $scopeId);
        }

        $this->command?->info('DemoAccountsSeeder: '.count($students).' cuentas de estudiante + '.count($staff).' cuentas institucionales creadas (contraseña: password).');

        // ------------------------------------------------------------
        // Cliente OAuth de servicio para probar el contrato real
        // /api/v1/identity/qr-validate (módulo 1.10 / integración).
        // Secreto fijo SOLO para desarrollo local: en un cliente real
        // se genera con `php artisan oauth:client` y el secreto no se
        // vuelve a mostrar.
        // ------------------------------------------------------------
        $client = \App\Models\ServiceClient::firstOrCreate(
            ['client_id' => 'svc_demo_biblioteca'],
            [
                'name' => 'Biblioteca Central (demo)',
                'secret_hash' => Hash::make('demo-secret-biblioteca'),
                'scopes' => ['identity.qr.validate', 'identity.qr.validate.full', 'students:read'],
                'active' => true,
            ]
        );

        $this->command?->info("DemoAccountsSeeder: cliente OAuth de prueba client_id={$client->client_id} secret=demo-secret-biblioteca (scopes: identity.qr.validate, identity.qr.validate.full).");

        // ------------------------------------------------------------
        // Contextos de validación de ejemplo (módulo 1.6), para que
        // martha.jimenez (bibliotecaria) ya tenga uno vigente sin
        // necesidad de crear uno antes de poder validar un QR.
        // ------------------------------------------------------------
        $librarian = User::where('email', 'martha.jimenez@servicios.com')->first();
        if ($librarian) {
            \App\Models\QrValidationContext::firstOrCreate(
                ['name' => 'Turno biblioteca (demo)', 'created_by' => (string) $librarian->_id],
                ['ends_at' => now()->addDays(7)]
            );
        }
    }
}
