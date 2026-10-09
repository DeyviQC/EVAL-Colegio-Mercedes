<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up():void {
  DB::schema()->table('delivery_versions',function(Blueprint $t){$t->unique(['submission_id','id'],'delivery_version_identity');});
  DB::schema()->create('delivery_assessments',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('submission_id');$t->unsignedBigInteger('version_id');
   $t->foreign(['submission_id','version_id'])->references(['submission_id','id'])->on('delivery_versions')->restrictOnDelete()->restrictOnUpdate();
   $t->foreignId('teacher_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
   $t->enum('grade',['AD','A','B','C']);$t->text('feedback');$t->unsignedBigInteger('operation_key')->unique();$t->dateTime('recorded_at',6);
   $t->unique(['submission_id','id'],'delivery_assessment_identity');
  });
  DB::schema()->table('activity_deliveries',function(Blueprint $t){$t->unsignedBigInteger('current_assessment_id')->nullable();$t->foreign(['submission_id','current_assessment_id'],'delivery_current_assessment')->references(['submission_id','id'])->on('delivery_assessments')->restrictOnDelete()->restrictOnUpdate();});
  DB::connection()->statement("ALTER TABLE delivery_assessments ADD CONSTRAINT assessment_feedback_required CHECK (CHAR_LENGTH(TRIM(feedback)) BETWEEN 1 AND 5000)");
  foreach(['UPDATE','DELETE'] as $operation){$name=strtolower($operation);DB::connection()->unprepared("CREATE TRIGGER delivery_assessments_{$name}_retain BEFORE {$operation} ON delivery_assessments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain assessment revision'");}
 }
 public function down():void {throw new RuntimeException('Retain assessment history; use reviewed forward repair.');}
};
