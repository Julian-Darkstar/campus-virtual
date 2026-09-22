<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class StudentPreference extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'student_preferences';

    protected $fillable = [
        'student_profile_id',
        'user_id',
        'email',
        'push',
        'sms',
        'updated_by',
    ];

    protected $casts = [
        'email' => 'boolean',
        'push' => 'boolean',
        'sms' => 'boolean',
    ];
}
