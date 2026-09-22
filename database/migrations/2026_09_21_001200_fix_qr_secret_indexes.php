<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $schema = Schema::connection('mongodb');
        $collection = DB::connection('mongodb')->getCollection('qr_tokens');

        // 1. Eliminar el índice antiguo sobre `code`. El código ya no se
        // persiste en texto plano; ese índice causaba E11000 cuando varios
        // documentos tenían code=null.
        try {
            $schema->table('qr_tokens', function (Blueprint $blueprint) {
                $blueprint->dropIndex('code_1');
            });
        } catch (Throwable) {
        }

        // 2. Compatibilidad: tokens históricos que todavía tengan `code`
        // se convierten a hash y, en identificación, a copia cifrada.
        // Registros antiguos sin secreto recuperable reciben un hash
        // aleatorio único y se consideran no presentables; así no bloquean
        // la creación del índice único ni se expone ningún secreto.
        foreach ($collection->find(['code_hash' => ['$exists' => false]]) as $document) {
            $code = isset($document->code) ? (string) $document->code : '';
            $hash = $code !== ''
                ? hash('sha256', $code)
                : hash('sha256', (string) $document->_id.'|'.bin2hex(random_bytes(16)));

            $set = ['code_hash' => $hash];
            if ($code !== '' && (($document->type ?? null) === 'identification')) {
                $set['code_encrypted'] = encrypt($code);
            }

            $collection->updateOne(
                ['_id' => $document->_id],
                [
                    '$set' => $set,
                    '$unset' => ['code' => ''],
                ],
            );
        }

        // 3. Los códigos cortos se consultan por hash cuando existe y por
        // el índice auxiliar histórico cuando no. No se exige unicidad
        // global porque tokens expirados/consumidos pueden reutilizar el
        // mismo número en el futuro.
        try {
            $schema->table('qr_tokens', function (Blueprint $blueprint) {
                $blueprint->unique('code_hash');
            });
        } catch (Throwable) {
        }

        try {
            $schema->table('qr_tokens', function (Blueprint $blueprint) {
                $blueprint->index('short_code_hash');
            });
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        try {
            Schema::connection('mongodb')->table('qr_tokens', function (Blueprint $blueprint) {
                $blueprint->dropIndex('code_hash_1');
                $blueprint->dropIndex('short_code_hash_1');
                $blueprint->unique('code');
            });
        } catch (Throwable) {
        }
    }
};
