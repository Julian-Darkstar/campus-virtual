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
    protected $connection = 'mongodb';
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public function devices()
    {
        return $this->hasMany(Device::class, 'user_id');
    }

    public function qrTokens()
    {
        return $this->hasMany(QrToken::class, 'user_id');
    }

    public function sessions()
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
     * Forma minima que consumen otros dominios al validar un QR o
     * una sesion (modulo 1.10 - contrato de identidad).
     */
    public function displayIdentity(): array
    {
        return [
            'id' => (string) $this->_id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
