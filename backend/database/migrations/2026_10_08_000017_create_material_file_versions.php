<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up():void {
  DB::connection()->statement('ALTER TABLE material_revisions DROP CHECK material_revision_bounds');
  DB::connection()->statement("ALTER TABLE material_revisions MODIFY operation ENUM('edited','withdrawn','restored','file_replaced') NOT NULL");
  DB::connection()->statement("ALTER TABLE material_revisions ADD CONSTRAINT material_revision_bounds CHECK (operation_key BETWEEN 1 AND 9223372036854775807 AND CHAR_LENGTH(TRIM(title)) BETWEEN 1 AND 255 AND (description IS NULL OR CHAR_LENGTH(description)<=10000) AND ((operation='withdrawn' AND state='withdrawn') OR (operation IN ('edited','restored','file_replaced') AND state='active')))");
  DB::schema()->create('material_file_versions',function(Blueprint $t){$t->id();$t->foreignId('material_id')->constrained('course_materials')->restrictOnDelete()->restrictOnUpdate();$t->foreignId('revision_id')->unique()->constrained('material_revisions')->restrictOnDelete()->restrictOnUpdate();$t->string('filename',255);$t->string('mime',100);$t->unsignedBigInteger('bytes');$t->char('sha256',64);$t->char('storage_key',64)->unique();$t->index(['material_id','revision_id']);});
  DB::connection()->statement('ALTER TABLE material_file_versions ADD CONSTRAINT material_file_bounds CHECK (bytes BETWEEN 1 AND 26214400 AND CHAR_LENGTH(filename) BETWEEN 1 AND 255)');
  foreach(['UPDATE','DELETE'] as $op)DB::connection()->unprepared("CREATE TRIGGER material_files_no_".strtolower($op)." BEFORE $op ON material_file_versions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain material file versions'");
  DB::connection()->unprepared("CREATE TRIGGER material_file_context BEFORE INSERT ON material_file_versions FOR EACH ROW BEGIN DECLARE target BIGINT UNSIGNED; DECLARE kind VARCHAR(30); SELECT material_id,operation INTO target,kind FROM material_revisions WHERE id=NEW.revision_id; IF target<>NEW.material_id OR kind<>'file_replaced' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Material file revision context'; END IF; END");
  foreach(['INSERT','UPDATE'] as $op)DB::connection()->unprepared("CREATE TRIGGER material_file_pointer_".strtolower($op)." BEFORE $op ON material_states FOR EACH ROW BEGIN DECLARE kind VARCHAR(30); DECLARE files INT; SELECT operation INTO kind FROM material_revisions WHERE id=NEW.current_revision_id; IF kind='file_replaced' THEN SELECT COUNT(*) INTO files FROM material_file_versions WHERE revision_id=NEW.current_revision_id AND material_id=NEW.material_id; IF files<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Material file must precede pointer'; END IF; END IF; END");
 }
 public function down():void {throw new RuntimeException('Retain material files; forward repair only.');}
};
