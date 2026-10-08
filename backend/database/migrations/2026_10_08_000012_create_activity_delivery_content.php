<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up():void {
  DB::schema()->create('activity_contents',function(Blueprint $t){$t->foreignId('activity_id')->primary()->constrained('activity_references')->restrictOnDelete()->restrictOnUpdate();$t->string('title',200);$t->text('instructions');$t->date('due_on')->nullable();$t->enum('state',['open','closed']);$t->unsignedBigInteger('publication_key')->unique();$t->dateTime('published_at',6);});
  DB::schema()->create('activity_deliveries',function(Blueprint $t){$t->foreignId('submission_id')->primary()->constrained('submission_references')->restrictOnDelete()->restrictOnUpdate();$t->foreignId('student_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();$t->foreignId('activity_id')->constrained('activity_references')->restrictOnDelete()->restrictOnUpdate();$t->unsignedBigInteger('current_version_id')->nullable();$t->unique(['student_id','activity_id']);});
  DB::schema()->create('delivery_versions',function(Blueprint $t){$t->id();$t->foreignId('submission_id')->constrained('activity_deliveries','submission_id')->restrictOnDelete()->restrictOnUpdate();$t->text('answer');$t->string('filename',255)->nullable();$t->string('storage_key',64)->nullable()->unique();$t->string('mime',128)->nullable();$t->unsignedBigInteger('bytes')->nullable();$t->string('sha256',64)->nullable();$t->unsignedBigInteger('operation_key')->unique();$t->dateTime('recorded_at',6);});
  DB::schema()->table('activity_deliveries',function(Blueprint $t){$t->foreign('current_version_id')->references('id')->on('delivery_versions')->restrictOnDelete()->restrictOnUpdate();});
  DB::connection()->statement("ALTER TABLE delivery_versions ADD CONSTRAINT delivery_file_group CHECK ((filename IS NULL AND storage_key IS NULL AND mime IS NULL AND bytes IS NULL AND sha256 IS NULL AND CHAR_LENGTH(TRIM(answer))>0) OR (filename IS NOT NULL AND storage_key IS NOT NULL AND mime IS NOT NULL AND bytes>0 AND bytes<=10485760 AND sha256 IS NOT NULL))");
  foreach(['delivery_versions','activity_deliveries','activity_contents'] as $table)DB::connection()->unprepared("CREATE TRIGGER {$table}_retain BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain educational evidence'");
  DB::connection()->unprepared("CREATE TRIGGER delivery_versions_immutable BEFORE UPDATE ON delivery_versions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain original delivery version'");
 }
 public function down():void {throw new RuntimeException('Retain educational content; use reviewed forward repair.');}
};
