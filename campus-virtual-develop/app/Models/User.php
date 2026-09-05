<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Modulo 1.6 - Identidad QR.
     */
    public function qrTokens()
    {
        return $this->hasMany(\App\Models\QrToken::class, 'user_id');
    }

    /**
     * Modulo 1.7 - Dispositivos y sesiones confiables.
     */
    public function devices()
    {
        return $this->hasMany(\App\Models\Device::class, 'user_id');
    }

    public function sessions()
    {
        return $this->hasMany(\App\Models\UserSession::class, 'user_id');
    }

    public function securityEvents()
    {
        return $this->hasMany(\App\Models\SecurityEvent::class, 'user_id');
    }

    /**
     * Modulo 1.10 (contrato de identidad) - forma minima que consumen
     * otros dominios (Equipos 2, 5, 6) al validar un QR o una sesion.
     */
    public function displayIdentity(): array
    {
        return [
            'id' => (string) $this->_id,
            'name' => $this->name,
            'matricula' => $this->matricula ?? null,
            'role' => $this->role ?? 'estudiante',
        ];
    }
}
