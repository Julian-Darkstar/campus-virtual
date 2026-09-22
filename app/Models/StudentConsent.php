<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class StudentConsent extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'student_consents';

    protected $fillable = [
        'student_profile_id',
        'user_id',
        'consent_id',
        'version',
        'status',
        'accepted_at',
        'revoked_at',
        'accepted_by',
        'revoked_by',
        'updated_by',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
