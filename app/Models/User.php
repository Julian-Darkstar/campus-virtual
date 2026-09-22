<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    protected $connection = 'mongodb';

    protected $collection = 'users';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'account_activation_pending',
        'roles',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'two_factor_enabled',
    ];

    public function getTwoFactorEnabledAttribute(): bool
    {
        return ! empty($this->two_factor_secret);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_activation_pending' => 'boolean',
        ];
    }

    protected function roles(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_string($value) ? (json_decode($value, true) ?: []) : ($value ?: []),
            set: fn ($value) => is_string($value) ? json_decode($value, true) : ($value ?: []),
        );
    }
    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class, 'user_id');
    }

    public function userSessions()
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    public function securityEvents()
    {
        return $this->hasMany(SecurityEvent::class, 'user_id');
    }

    public function qrTokens()
    {
        return $this->hasMany(QrToken::class, 'user_id');
    }

    /**
     * Identidad resumida que se expone cuando otro dominio valida un
     * QR del estudiante (Modulo 1.6). Minimiza lo expuesto por
     * defecto ("basic"): el consumidor solo ve nombre enmascarado +
     * roles, salvo que explicitamente pida "full" (y tenga permiso
     * para ello: ver App\Policies\QrValidationPolicy::viewFull).
     *
     * 'roles' refleja los roles REALES asignados al usuario (antes
     * era un string fijo 'estudiante' sin importar quien fuera).
     */
    public function displayIdentity(string $level = 'basic'): array
    {
        return [
            'name' => $level === 'full' ? $this->name : $this->maskedName(),
            'matricula' => $this->studentProfile?->enrollment_number,
            'roles' => $this->roleNames(),
        ];
    }

    /**
     * Nombres únicos de los roles asignados (sin duplicar por scope:
     * "cajero_negocio" en dos negocios distintos aparece una sola vez
     * aquí; si se necesita el detalle por scope, usar $this->roles).
     */
    public function roleNames(): array
    {
        return array_values(array_unique(array_map(
            fn ($role) => $role['name'] ?? null,
            $this->roles ?? []
        )));
    }

    /**
     * "Juan Perez Lopez" -> "Juan Perez L."
     */
    private function maskedName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        if (count($parts) < 2) {
            return (string) $this->name;
        }

        $last = array_pop($parts);

        return trim(implode(' ', $parts).' '.mb_substr($last, 0, 1).'.');
    }

    /**
     * Tarjetas NFC pertenecientes al usuario.
     */
    public function nfcCards()
    {
        return $this->hasMany(NfcCard::class, 'user_id');
    }

    /**
     * Tarjetas NFC registradas por el usuario.
     */
    public function registeredNfcCards()
    {
        return $this->hasMany(NfcCard::class, 'registered_by');
    }

    /**
     * Eventos del historial de credenciales realizados por el usuario.
     */
    public function credentialEvents()
    {
        return $this->hasMany(CredentialEvent::class, 'performed_by');
    }

    public function assignRole(string $roleName, ?string $scopeType = null, ?string $scopeId = null): void
    {
        // Defensa en profundidad: nunca persistir un rol que no exista en el
        // catálogo oficial, sin importar qué controlador llame a este método.
        if (! in_array($roleName, Role::VALID_ROLES, true)) {
            throw new \InvalidArgumentException("El rol '{$roleName}' no es un rol válido.");
        }

        $expectedScope = Role::ROLE_SCOPE_TYPES[$roleName] ?? null;
        if ($expectedScope !== null && $scopeType !== $expectedScope) {
            throw new \InvalidArgumentException("El rol '{$roleName}' requiere el ámbito '{$expectedScope}'.");
        }
        if ($expectedScope === null && $scopeType !== null) {
            throw new \InvalidArgumentException("El rol '{$roleName}' es global y no admite un ámbito contextual.");
        }
        if ($expectedScope !== null && blank($scopeId)) {
            throw new \InvalidArgumentException("El rol '{$roleName}' requiere un scope_id.");
        }

        $roles = $this->roles ?? [];

        foreach ($roles as $role) {
            if (
                ($role['name'] ?? null) === $roleName &&
                ($role['scope_type'] ?? null) === $scopeType &&
                ($role['scope_id'] ?? null) === $scopeId
            ) {
                return;
            }
        }

        $roles[] = [
            'name' => $roleName,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'assigned_at' => now()->toDateTimeString(),
        ];

        $this->roles = $roles;
        $this->save();
    }

    public function hasRole(string $roleName, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        $roles = $this->roles ?? [];

        foreach ($roles as $role) {
            if (($role['name'] ?? null) === $roleName) {
                if (is_null($scopeType) && is_null($role['scope_type'] ?? null)) {
                    return true;
                }
                if (
                    ($role['scope_type'] ?? null) === $scopeType &&
                    ($role['scope_id'] ?? null) === $scopeId
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Igual que hasRole() pero SIN importar el scope: "¿tiene este rol
     * en cualquier negocio/asociación/servicio?". hasRole() exige el
     * scope exacto (o ninguno) a propósito para autorizar acciones
     * sobre UN negocio/asociación puntual; esta variante es para
     * preguntas de "pertenece a este tipo de personal en general"
     * (ej. ¿es cajero de negocio en algún lado?), como en
     * QrValidationPolicy.
     */
    public function hasRoleAnyScope(string $roleName): bool
    {
        foreach ($this->roles ?? [] as $role) {
            if (($role['name'] ?? null) === $roleName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Módulo 1.6: ¿puede este usuario validar el QR de OTRA persona?
     * Ver Role::VALIDATOR_ROLES.
     */
    public function isQrValidator(): bool
    {
        foreach (Role::VALIDATOR_ROLES as $roleName) {
            if ($this->hasRoleAnyScope($roleName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Módulo 1.6: ¿puede pedir el nivel "full" (nombre sin
     * enmascarar) al validar el QR de otra persona? Ver
     * Role::FULL_IDENTITY_ROLES (subconjunto de VALIDATOR_ROLES).
     */
    public function canRequestFullQrIdentity(): bool
    {
        foreach (Role::FULL_IDENTITY_ROLES as $roleName) {
            if ($this->hasRoleAnyScope($roleName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Etiqueta legible del rol "validador" de este usuario, para
     * dejar en la bitácora de qr_validations QUIÉN validó de verdad
     * (derivado del servidor, nunca de texto libre que mande el
     * cliente). Si tiene más de un rol validador, usa el primero que
     * encuentre según el orden de Role::VALIDATOR_ROLES.
     */
    public function validatorLabel(): string
    {
        foreach (Role::VALIDATOR_ROLES as $roleName) {
            foreach ($this->roles ?? [] as $role) {
                if (($role['name'] ?? null) !== $roleName) {
                    continue;
                }

                $displayName = Role::where('name', $roleName)->value('display_name') ?? $roleName;
                $scope = $role['scope_id'] ?? null;

                return $this->name.' · '.$displayName.($scope ? " ({$scope})" : '');
            }
        }

        return $this->name;
    }
}