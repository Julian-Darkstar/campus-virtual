<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('enrollment_number', 30)->unique();
            $table->foreignId('campus_id')->constrained()->noActionOnDelete();
            $table->foreignId('academic_program_id')->constrained()->noActionOnDelete();
            $table->unsignedTinyInteger('current_semester');
            $table->string('group_name', 30)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('academic_status', 24)->default('active')->index();
            $table->string('personal_email')->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('preferred_contact_channel', 30)->default('institutional_email');
            $table->string('locale', 10)->default('es-MX');
            $table->timestamps();
            $table->index(['campus_id', 'academic_status']);
            $table->index(['academic_program_id', 'current_semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};

