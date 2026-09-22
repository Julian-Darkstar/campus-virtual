<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'roles';

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Catálogo cerrado de roles válidos en la plataforma.
     *
     * Cualquier asignación de rol (App\Models\User::assignRole) debe
     * validarse contra este catálogo para evitar que se persistan
     * nombres de rol arbitrarios enviados desde el cliente.
     *
     * Alineado con "Matriz Operativa de Roles, Permisos y Restricciones
     * - Campus Digital". Cada rol indica su ámbito de activación tal
     * como lo define ese documento; el ámbito técnico (scope_type que
     * se guarda junto al rol en User::assignRole) es:
     *   - null                -> ámbito general/global (ninguno en particular)
     *   - 'business'          -> ámbito por negocio
     *   - 'association'       -> ámbito orgánico (asociación/Consejo)
     *   - 'service'           -> ámbito operativo/servicio
     */

    // 1. Estudiantes y comunidad base (ámbito general)
    public const ESTUDIANTE = 'estudiante';

    // 2. Comercio y negocios virtuales (ámbito por negocio)
    public const PROPIETARIO_NEGOCIO = 'propietario_negocio';
    public const GERENTE_NEGOCIO = 'gerente_negocio';
    public const CAJERO_NEGOCIO = 'cajero_negocio';

    // 3. Operación, abastecimiento e inventario (ámbito logístico/negocio)
    public const RESPONSABLE_INVENTARIO = 'responsable_inventario';
    public const COMPRADOR_ABASTECIMIENTO = 'comprador_abastecimiento';

    // 4. Asociaciones estudiantiles y Consejo (ámbito orgánico/periodo definido)
    public const PRESIDENCIA_ASOCIACION = 'presidencia_asociacion';
    public const TESORERIA_ASOCIACION = 'tesoreria_asociacion';
    public const AGENTE_RECARGA_RETIRO = 'agente_recarga_retiro';
    public const COMUNICACION_ASOCIACION = 'comunicacion_asociacion';
    public const COMITE_BECAS = 'comite_becas';

    // 5. Servicios universitarios (ámbito operativo/servicios)
    public const BIBLIOTECARIO = 'bibliotecario';
    public const AGENTE_SOPORTE = 'agente_soporte';

    // 6. Administración, gobierno y control transversal (ámbito global)
    public const ADMINISTRADOR_RECOMPENSAS = 'administrador_recompensas';
    public const ADMIN = 'admin'; // Administrador de Plataforma
    public const AUDITOR = 'auditor';

    /**
     * Roles heredados de una etapa anterior del proyecto que NO
     * aparecen en la matriz oficial de roles. Se conservan por
     * compatibilidad (ya hay código/pruebas que los usan) pero no
     * deben tomarse como fuente de verdad de negocio: si el equipo
     * decide que ya no aplican, quitarlos de aquí y de RoleSeeder.
     */
    public const MAESTRO = 'maestro';
    public const SERVICIO_CAFETERIA = 'servicio_cafeteria';

    public const VALID_ROLES = [
        self::ESTUDIANTE,
        self::PROPIETARIO_NEGOCIO,
        self::GERENTE_NEGOCIO,
        self::CAJERO_NEGOCIO,
        self::RESPONSABLE_INVENTARIO,
        self::COMPRADOR_ABASTECIMIENTO,
        self::PRESIDENCIA_ASOCIACION,
        self::TESORERIA_ASOCIACION,
        self::AGENTE_RECARGA_RETIRO,
        self::COMUNICACION_ASOCIACION,
        self::COMITE_BECAS,
        self::BIBLIOTECARIO,
        self::AGENTE_SOPORTE,
        self::ADMINISTRADOR_RECOMPENSAS,
        self::ADMIN,
        self::AUDITOR,
        self::MAESTRO,
        self::SERVICIO_CAFETERIA,
    ];

    /**
     * Módulo 1.6 (Identidad QR): roles que pueden operar un punto de
     * validación (leer el QR de OTRA persona), no solo generar/ver el
     * propio. Un estudiante normal nunca debe poder validar el QR de
     * otro estudiante.
     *
     * Ver App\Policies\QrValidationPolicy, que es el único punto de
     * verdad que consulta esta lista (no se repite en controladores).
     */
    public const VALIDATOR_ROLES = [
        self::BIBLIOTECARIO,
        self::AGENTE_SOPORTE,
        self::CAJERO_NEGOCIO,
        self::AGENTE_RECARGA_RETIRO,
        self::ADMIN,
    ];

    /**
     * Subconjunto de VALIDATOR_ROLES que además puede pedir el nivel
     * "full" (nombre sin enmascarar) al validar. Soporte, por ejemplo,
     * no necesita ver el nombre completo para cerrar un ticket.
     */
    public const FULL_IDENTITY_ROLES = [
        self::BIBLIOTECARIO,
        self::CAJERO_NEGOCIO,
        self::AGENTE_RECARGA_RETIRO,
        self::ADMIN,
    ];

    /**
     * Roles que, además de "admin", pueden gestionar el perfil y
     * ciclo de vida de estudiantes (alta, edición, importación).
     */
    public const STUDENT_MANAGEMENT_ROLES = [
        self::ADMIN,
        self::MAESTRO,
    ];

    public const ROLE_SCOPE_TYPES = [
        self::ESTUDIANTE => null,
        self::PROPIETARIO_NEGOCIO => 'business',
        self::GERENTE_NEGOCIO => 'business',
        self::CAJERO_NEGOCIO => 'business',
        self::RESPONSABLE_INVENTARIO => 'business',
        self::COMPRADOR_ABASTECIMIENTO => 'business',
        self::PRESIDENCIA_ASOCIACION => 'association',
        self::TESORERIA_ASOCIACION => 'association',
        self::AGENTE_RECARGA_RETIRO => 'association',
        self::COMUNICACION_ASOCIACION => 'association',
        self::COMITE_BECAS => null,
        self::BIBLIOTECARIO => 'service',
        self::AGENTE_SOPORTE => 'service',
        self::ADMINISTRADOR_RECOMPENSAS => null,
        self::ADMIN => null,
        self::AUDITOR => null,
        self::MAESTRO => null,
        self::SERVICIO_CAFETERIA => 'business',
    ];
}