<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up():void {
        DB::schema()->create('course_materials',function(Blueprint $table){
            $table->id();$table->foreignId('assignment_id')->constrained('teaching_assignments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('author_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->string('title',255);$table->text('description')->nullable();$table->string('filename',255);
            $table->char('storage_key',64)->unique();$table->string('mime',128);$table->unsignedInteger('bytes');$table->char('sha256',64);
            $table->unsignedBigInteger('publication_key')->unique();$table->dateTime('published_at',6);$table->index(['assignment_id','id']);
        });
        DB::connection()->statement('ALTER TABLE course_materials ADD CONSTRAINT material_bounds CHECK (bytes BETWEEN 1 AND 26214400 AND publication_key BETWEEN 1 AND 9223372036854775807)');
        foreach(['UPDATE','DELETE'] as $operation)DB::connection()->unprepared("CREATE TRIGGER materials_no_".strtolower($operation)." BEFORE $operation ON course_materials FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain original material context'");
    }
    public function down():void{throw new RuntimeException('Retain published materials; forward repair only.');}
};
