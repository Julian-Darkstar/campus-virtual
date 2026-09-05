<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Schema::connection('mongodb')->create('devices', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index(['user_id', 'uuid']);
            $collection->index('status');
        });

        Schema::connection('mongodb')->create('qr_validations', function (Blueprint $collection) {
            $collection->index('qr_token_id');
            $collection->index('user_id');
            $collection->index('created_at');
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Schema::connection('mongodb')->dropIfExists('qr_validations');
        Schema::connection('mongodb')->dropIfExists('devices');
    }
};