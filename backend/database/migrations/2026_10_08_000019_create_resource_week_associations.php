<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up(?Connection $connection=null):void {
  $db=$connection??DB::connection();$schema=$db->getSchemaBuilder();
  foreach(['material'=>'course_materials','activity'=>'activity_references'] as $kind=>$resource){
   $changes=$kind.'_week_changes';$states=$kind.'_week_states';
   $schema->create($changes,function(Blueprint $t)use($resource){
    $t->id();$t->foreignId('resource_id')->constrained($resource)->restrictOnDelete()->restrictOnUpdate();
    $t->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete()->restrictOnUpdate();
    $t->unsignedBigInteger('calendar_revision_id')->nullable();$t->string('week_id',32)->nullable();
    $t->foreign(['calendar_revision_id','period_id'])->references(['id','period_id'])->on('school_calendar_revisions')->restrictOnDelete()->restrictOnUpdate();
    $t->foreignId('actor_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
    $t->unsignedInteger('version');$t->unsignedBigInteger('operation_key');$t->dateTime('recorded_at',6);
    $t->unique(['resource_id','version']);$t->unique(['id','resource_id']);
   });
   $schema->create($states,function(Blueprint $t)use($changes,$resource){
    $t->unsignedBigInteger('resource_id')->primary();$t->unsignedBigInteger('change_id');
    $t->foreign('resource_id')->references('id')->on($resource)->restrictOnDelete()->restrictOnUpdate();
    $t->foreign(['change_id','resource_id'])->references(['id','resource_id'])->on($changes)->restrictOnDelete()->restrictOnUpdate();
   });
   $db->statement("ALTER TABLE $changes ADD CONSTRAINT {$kind}_week_bounds CHECK (version BETWEEN 1 AND 2147483647 AND operation_key BETWEEN 1 AND 9223372036854775807 AND ((calendar_revision_id IS NULL AND week_id IS NULL) OR (calendar_revision_id IS NOT NULL AND week_id IS NOT NULL AND week_id REGEXP '^[a-f0-9]{32}$')))");
   foreach(['UPDATE','DELETE'] as $op)$db->unprepared("CREATE TRIGGER {$changes}_no_".strtolower($op)." BEFORE $op ON $changes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain resource week changes'");
   $db->unprepared("CREATE TRIGGER {$states}_retain_context BEFORE UPDATE ON $states FOR EACH ROW BEGIN IF OLD.resource_id<>NEW.resource_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain resource week context'; END IF; END");
   $db->unprepared("CREATE TRIGGER {$states}_no_delete BEFORE DELETE ON $states FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain resource week pointer'");
  }
 }
 public function down():void {throw new RuntimeException('Retain resource week associations; forward repair only.');}
};
