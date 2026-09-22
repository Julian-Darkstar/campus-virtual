<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;
return new class extends Migration {
 protected $connection='mongodb';
 public function up(): void {
  $s=Schema::connection('mongodb');
  $s->table('qr_tokens',function(Blueprint $c){$c->index('code_hash');$c->index('short_code_hash');$c->index(['user_id','expires_at','revoked_at','consumed_at']);});
  $s->table('qr_validations',function(Blueprint $c){$c->index(['user_id','created_at']);$c->index('context_id');$c->index('correlation_id');});
  $s->table('devices',function(Blueprint $c){$c->index(['user_id','last_seen_at']);$c->index('revoked_at');});
  $s->table('sessions',function(Blueprint $c){$c->index(['user_id','device_id','revoked_at']);$c->index('revoked_at');});
  $s->table('security_events',function(Blueprint $c){$c->index('device_id');$c->index('session_id');$c->index('correlation_id');});
 }
 public function down(): void {}
};
