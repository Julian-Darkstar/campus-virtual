<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $schema = Schema::connection('mongodb');

        if (! $schema->hasTable('student_consents')) {
            $schema->create('student_consents', function (Blueprint $collection) {
                $collection->index('student_profile_id');
                $collection->index('user_id');
                $collection->index('consent_id');
                $collection->unique(['student_profile_id', 'consent_id']);
            });
        }

        if (! $schema->hasTable('student_preferences')) {
            $schema->create('student_preferences', function (Blueprint $collection) {
                $collection->unique('student_profile_id');
                $collection->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('student_consents');
        Schema::connection('mongodb')->dropIfExists('student_preferences');
    }
};
