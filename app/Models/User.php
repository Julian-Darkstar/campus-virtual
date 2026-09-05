<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use Notifiable, TwoFactorAuthenticatable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
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
            'roles' => 'array',
        ];
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