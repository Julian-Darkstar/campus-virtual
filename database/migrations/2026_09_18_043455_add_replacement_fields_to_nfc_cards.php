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

        Schema::connection('mongodb')->table('nfc_cards', function (Blueprint $collection) {
            $collection->index('replacement_of_card_id');
            $collection->index('replaced_by_card_id');
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Schema::connection('mongodb')->table('nfc_cards', function (Blueprint $collection) {
            $collection->dropIndex('replacement_of_card_id_1');
            $collection->dropIndex('replaced_by_card_id_1');
        });
    }
};