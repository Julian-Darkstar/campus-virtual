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
    public function displayIdentity(): array
{
    return [
        'name' => $this->name,
        'email' => $this->email,
    ];
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