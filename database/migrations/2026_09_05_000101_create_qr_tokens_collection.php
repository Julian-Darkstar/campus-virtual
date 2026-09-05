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

        Schema::connection('mongodb')->create('qr_tokens', function (Blueprint $collection) {
            $collection->unique('code');
            $collection->index('user_id');
            $collection->index('expires_at');
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Schema::connection('mongodb')->dropIfExists('qr_tokens');
    }
};