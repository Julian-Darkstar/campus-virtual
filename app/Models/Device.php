<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Device extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'devices';

    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'platform',
        'browser',
        'mac_address',
        'uuid',
        'ip_address',
        'status',
        'is_trusted',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}