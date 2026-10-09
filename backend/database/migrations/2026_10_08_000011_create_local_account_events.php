<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up():void {
        DB::schema()->create('local_account_events',function(Blueprint $table){
            $table->id();$table->foreignId('actor_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('target_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('operation',['created','deactivated','reactivated','password_reset','password_changed']);
            $table->unsignedBigInteger('operation_key')->unique();$table->dateTime('recorded_at',6);
        });
    }
    public function down():void {if(DB::table('local_account_events')->exists())throw new RuntimeException('Account evidence must be retained.');DB::schema()->drop('local_account_events');}
};
