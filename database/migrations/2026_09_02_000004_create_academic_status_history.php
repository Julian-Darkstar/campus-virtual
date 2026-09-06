<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('reason', 300)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('changed_at');
            $table->index(['student_profile_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_status_history');
    }
};

