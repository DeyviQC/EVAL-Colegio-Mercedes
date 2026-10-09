<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up(?Connection $connection=null):void {
  $db=$connection??DB::connection();$schema=$db->getSchemaBuilder();
  $schema->create('school_calendar_revisions',function(Blueprint $t){
   $t->id();$t->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete()->restrictOnUpdate();
   $t->foreignId('actor_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
   $t->string('source',255);$t->json('blocks');$t->unsignedBigInteger('operation_key');$t->dateTime('recorded_at',6);$t->unique(['id','period_id']);
  });
  $schema->create('school_calendar_states',function(Blueprint $t){
   $t->unsignedBigInteger('period_id')->primary();$t->unsignedBigInteger('revision_id');
   $t->foreign('period_id')->references('id')->on('academic_periods')->restrictOnDelete()->restrictOnUpdate();
   $t->foreign(['revision_id','period_id'])->references(['id','period_id'])->on('school_calendar_revisions')->restrictOnDelete()->restrictOnUpdate();
  });
  $schema->create('school_calendar_drafts',function(Blueprint $t){
   $t->id();$t->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete()->restrictOnUpdate();
   $t->foreignId('actor_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
   $t->foreignId('base_revision_id')->nullable()->constrained('school_calendar_revisions')->restrictOnDelete()->restrictOnUpdate();
   $t->json('blocks');$t->unsignedInteger('version');$t->enum('state',['draft','published']);$t->index(['period_id','id']);
  });
  $db->statement('ALTER TABLE school_calendar_drafts ADD CONSTRAINT calendar_draft_version CHECK (version BETWEEN 1 AND 2147483647)');
  $db->statement('ALTER TABLE school_calendar_revisions ADD CONSTRAINT calendar_revision_bounds CHECK (CHAR_LENGTH(TRIM(source)) BETWEEN 1 AND 255 AND operation_key BETWEEN 1 AND 9223372036854775807)');
  foreach(['UPDATE','DELETE'] as $op)$db->unprepared("CREATE TRIGGER calendar_revisions_no_".strtolower($op)." BEFORE $op ON school_calendar_revisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain published calendar revisions'");
  $db->unprepared("CREATE TRIGGER calendar_draft_retention BEFORE UPDATE ON school_calendar_drafts FOR EACH ROW BEGIN IF OLD.state='published' OR OLD.period_id<>NEW.period_id OR OLD.actor_id<>NEW.actor_id OR NOT(OLD.base_revision_id <=> NEW.base_revision_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain calendar draft context'; END IF; END");
  $db->unprepared("CREATE TRIGGER calendar_drafts_no_delete BEFORE DELETE ON school_calendar_drafts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain calendar drafts'");
  $db->unprepared("CREATE TRIGGER calendar_states_no_delete BEFORE DELETE ON school_calendar_states FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain calendar publication pointer'");
 }
 public function down():void {throw new RuntimeException('Retain school calendars; forward repair only.');}
};
