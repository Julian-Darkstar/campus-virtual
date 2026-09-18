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
     * rol, salvo que explicitamente pida "full" (por ejemplo, un
     * validador que si necesita el nombre completo para emitir un
     * comprobante).
     */
    public function displayIdentity(string $level = 'basic'): array
    {
        return [
            'name' => $level === 'full' ? $this->name : $this->maskedName(),
            'matricula' => $this->studentProfile?->enrollment_number,
            'role' => 'estudiante',
        ];
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
}