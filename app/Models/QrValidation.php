<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class QrValidation extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'qr_validations';

    protected $fillable = ['qr_token_id', 'user_id', 'validated_by_user_id', 'result', 'context', 'ip_address'];
}