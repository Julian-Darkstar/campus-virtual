<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Modulo 1.6 - Identidad QR: contextos de validación creados por los
 * propios validadores (ver App\Models\QrValidationContext).
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('qr_validation_contexts', function (Blueprint $collection) {
            $collection->index('created_by');
            $collection->index('ends_at');
            $collection->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('qr_validation_contexts');
    }
};
