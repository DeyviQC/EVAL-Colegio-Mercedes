<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up():void {
        DB::schema()->create('retained_identity_profiles',function(Blueprint $table){
            $table->foreignId('identity_id')->primary()->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->string('display_name',255);
        });
        DB::connection()->statement("ALTER TABLE retained_identity_profiles ADD CONSTRAINT profile_name_present CHECK (CHAR_LENGTH(TRIM(display_name))>0)");
    }
    public function down():void{throw new RuntimeException('Retain profiles; reviewed forward repair only.');}
};
