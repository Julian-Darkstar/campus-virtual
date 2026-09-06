<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('academic_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnUpdate()->noActionOnDelete();
            $table->string('code', 30);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['campus_id', 'code']);
            $table->index(['campus_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_programs');
        Schema::dropIfExists('campuses');
    }
};

