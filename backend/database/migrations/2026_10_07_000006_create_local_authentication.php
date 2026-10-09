<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up():void
    {
        DB::schema()->create('local_credentials',function(Blueprint $table){
            $table->foreignId('id')->primary()->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            // Provisioned opaque login identifier, separate from academic display-name keys.
            $table->binary('login',255)->unique();
            $table->string('password',255)->nullable();
        });
        DB::schema()->create('local_role_grants',function(Blueprint $table){
            $table->foreignId('identity_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('role',['director_admin','vice_principal','teacher','student']);
            $table->primary(['identity_id','role']);
        });
        DB::schema()->create('local_sessions',function(Blueprint $table){
            $table->string('id',40)->collation('ascii_bin')->primary();
            $table->text('payload');
            $table->integer('last_activity')->index();
            $table->dateTime('revoked_at',6)->nullable();
        });
        DB::schema()->create('local_login_limits',function(Blueprint $table){
            $table->char('id',64)->collation('ascii_bin')->primary();
            $table->unsignedInteger('attempts');
            $table->unsignedBigInteger('window_started');
        });
    }
    public function down():void { throw new RuntimeException('Use a reviewed credential/session repair; never delete retained identities.'); }
};
