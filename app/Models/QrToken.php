<?php

namespace App\Models;

use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;

class QrToken extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'qr_tokens';

    protected $fillable = ['user_id', 'device_id', 'code', 'type', 'purpose', 'expires_at', 'consumed_at', 'revoked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function generateCode(): string
    {
        return Str::upper(Str::random(48));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null
            && $this->consumed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}