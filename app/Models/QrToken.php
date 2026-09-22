<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Coleccion Mongo: qr_tokens (Modulo 1.6 - Identidad QR)
 *
 * type:
 *   - identification: QR "fijo" de identificacion visible en el perfil.
 *   - dynamic: QR rotativo de corta duracion para validar operaciones
 *              sensibles (servicios, eventos, cajas).
 *
 * El token nunca contiene saldo ni datos sensibles: solo un codigo
 * opaco (code) que el backend resuelve contra la identidad real.
 */
class QrToken extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'qr_tokens';

    protected $fillable = [
        'user_id',
        'code',
        'code_encrypted',
        'code_hash',
        'short_code',
        'short_code_hash',
        'type',
        'purpose',
        'expires_at',
        'consumed_at',
        'revoked_at',
    ];

    protected $hidden = ['code', 'code_encrypted', 'short_code', 'code_hash', 'short_code_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getCodeAttribute($value): ?string
    {
        if ($value !== null) {
            return $value;
        }

        $encrypted = $this->attributes['code_encrypted'] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function generateCode(): string
    {
        return Str::upper(Str::random(8)).'-'.now()->format('His').'-'.random_int(100, 999);
    }

    public static function hashPresentedCode(string $code): string { return hash('sha256', $code); }
    public static function hashShortCode(string $code): string { return hash('sha256', $code); }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function secondsRemaining(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        $diff = $this->expires_at->getTimestamp() - now()->getTimestamp();

        return max(0, $diff);
    }
}
