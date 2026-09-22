<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Catálogo de roles vigente para esta etapa del proyecto académico.
     * Debe mantenerse en sincronía con App\Models\Role::VALID_ROLES,
     * que es la fuente de verdad que valida las asignaciones de rol.
     *
     * display_name/description tomados de "Matriz Operativa de Roles,
     * Permisos y Restricciones - Campus Digital".
     */
    public function run(): void
    {
        $roles = [
            // 1. Estudiantes y comunidad base
            ['name' => Role::ESTUDIANTE, 'display_name' => 'Estudiante', 'description' => 'Rol base por defecto. Perfil, wallet, credencial NFC/QR, compras, reservas, becas y votaciones.'],

            // 2. Comercio y negocios virtuales
            ['name' => Role::PROPIETARIO_NEGOCIO, 'display_name' => 'Propietario de Negocio', 'description' => 'Administra tienda virtual, catálogo, personal (gerentes/cajeros), métodos de cobro y adhesión a Recompensas.'],
            ['name' => Role::GERENTE_NEGOCIO, 'display_name' => 'Gerente de Negocio', 'description' => 'Gestiona pedidos, promociones, reembolsos y consulta métricas del negocio. No puede reasignar al propietario ni tocar cuentas de cobro.'],
            ['name' => Role::CAJERO_NEGOCIO, 'display_name' => 'Cajero de Negocio', 'description' => 'Cobra ventas validando QR/NFC, genera tickets y procesa devoluciones directas. Sin acceso a catálogo ni reportes consolidados.'],

            // 3. Operación, abastecimiento e inventario
            ['name' => Role::RESPONSABLE_INVENTARIO, 'display_name' => 'Responsable de Inventario', 'description' => 'Existencias, Kardex, conteos cíclicos y reservas de stock para Recompensas. Ajustes grandes requieren autorización dual.'],
            ['name' => Role::COMPRADOR_ABASTECIMIENTO, 'display_name' => 'Comprador / Abastecimiento', 'description' => 'Proveedores, órdenes de compra y recepción de mercancía. No autoriza pagos directos desde cuentas bancarias.'],

            // 4. Asociaciones estudiantiles y Consejo
            ['name' => Role::PRESIDENCIA_ASOCIACION, 'display_name' => 'Presidencia de Asociación / Consejo', 'description' => 'Estructura, miembros y cargos directivos; autoriza operaciones operativas y financieras institucionales. Acceso revocado automáticamente al vencer el periodo.'],
            ['name' => Role::TESORERIA_ASOCIACION, 'display_name' => 'Tesorería de Asociación', 'description' => 'Ledger, caja y conciliaciones de la organización; autoriza aperturas de caja. Retiros grandes exigen aprobación dual y reautenticación.'],
            ['name' => Role::AGENTE_RECARGA_RETIRO, 'display_name' => 'Agente de Recarga / Retiro (Cajero)', 'description' => 'Abre/cierra turnos de caja física, acredita dinero digital contra efectivo recibido y entrega efectivo contra cash-out validado. Sujeto a arqueo obligatorio.'],
            ['name' => Role::COMUNICACION_ASOCIACION, 'display_name' => 'Comunicación de Asociación', 'description' => 'Diseña y envía campañas de mensajería segmentada. Sin acceso a módulos financieros, inventarios o cajas.'],
            ['name' => Role::COMITE_BECAS, 'display_name' => 'Comité de Becas / Apoyos', 'description' => 'Revisa postulaciones y expedientes, emite dictámenes, asigna bonos no retirables y beneficios de servicio. No puede depositar saldo retirable.'],

            // 5. Servicios universitarios
            ['name' => Role::BIBLIOTECARIO, 'display_name' => 'Bibliotecario / Responsable de Servicios', 'description' => 'Catálogo, préstamos y devoluciones por NFC/QR, lockers, salas y equipos; genera multas cobrables vía Wallet. Limitado al servicio encomendado.'],
            ['name' => Role::AGENTE_SOPORTE, 'display_name' => 'Agente de Soporte', 'description' => 'Atiende, canaliza y cierra tickets de incidencia. Sin privilegios sobre cuentas, saldos o contraseñas.'],

            // 6. Administración, gobierno y control transversal
            ['name' => Role::ADMINISTRADOR_RECOMPENSAS, 'display_name' => 'Administrador de Recompensas', 'description' => 'Motor de puntos globales, equivalencias, expiración y monitoreo de fraude. Sin intervención en cajas ni saldos de dinero digital.'],
            ['name' => Role::ADMIN, 'display_name' => 'Administrador de Plataforma', 'description' => 'Superusuario institucional: aprueba/suspende negocios y organizaciones, catálogos maestros y asignación de roles. 2FA obligatorio, bitácora inmutable.'],
            ['name' => Role::AUDITOR, 'display_name' => 'Auditor', 'description' => 'Acceso de solo lectura a ledgers, logs, inventarios y reportes. Cero capacidad de edición.'],

            // Roles heredados sin equivalencia directa en la matriz oficial
            // (ver comentario en App\Models\Role) — se conservan por
            // compatibilidad, no por venir del documento de referencia.
            ['name' => Role::MAESTRO, 'display_name' => 'Maestro', 'description' => 'Personal académico con capacidad de consulta y gestión de estudiantes. Rol heredado, no está en la matriz oficial.'],
            ['name' => Role::SERVICIO_CAFETERIA, 'display_name' => 'Servicio de Cafetería', 'description' => 'Personal autorizado del negocio de cafetería. Rol heredado, no está en la matriz oficial; considerar migrarlo a cajero_negocio con scope de ese negocio.'],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(['name' => $roleData['name']], $roleData);
        }
    }
}
