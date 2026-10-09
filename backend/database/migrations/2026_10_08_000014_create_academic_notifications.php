<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
 public function up():void {
  DB::schema()->create('academic_notifications',function(Blueprint $t){
   $t->id();$t->foreignId('recipient_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
   $t->enum('event',['activity_published','delivery_accepted','delivery_updated','delivery_assessed','assessment_corrected']);
   $t->foreignId('activity_id')->nullable()->constrained('activity_references')->restrictOnDelete()->restrictOnUpdate();
   $t->foreignId('submission_id')->nullable()->constrained('submission_references')->restrictOnDelete()->restrictOnUpdate();
   $t->unsignedBigInteger('operation_key');$t->dateTime('created_at',6);$t->dateTime('read_at',6)->nullable();
   $t->unique(['operation_key','recipient_id']);$t->index(['recipient_id','id']);$t->index(['recipient_id','read_at']);
  });
  DB::connection()->statement("ALTER TABLE academic_notifications ADD CONSTRAINT notification_target CHECK ((event='activity_published' AND activity_id IS NOT NULL AND submission_id IS NULL) OR (event<>'activity_published' AND activity_id IS NULL AND submission_id IS NOT NULL)), ADD CONSTRAINT notification_read_time CHECK (read_at IS NULL OR read_at>=created_at)");
  DB::connection()->unprepared("CREATE TRIGGER academic_notifications_retain BEFORE DELETE ON academic_notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain own notification history'");
  DB::connection()->unprepared("CREATE TRIGGER academic_notifications_immutable BEFORE UPDATE ON academic_notifications FOR EACH ROW BEGIN IF NOT (NEW.id <=> OLD.id) OR NOT (NEW.recipient_id <=> OLD.recipient_id) OR NOT (NEW.event <=> OLD.event) OR NOT (NEW.activity_id <=> OLD.activity_id) OR NOT (NEW.submission_id <=> OLD.submission_id) OR NOT (NEW.operation_key <=> OLD.operation_key) OR NOT (NEW.created_at <=> OLD.created_at) OR (OLD.read_at IS NOT NULL AND NOT (NEW.read_at <=> OLD.read_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain original notice and receipt'; END IF; END");
 }
 public function down():void {throw new RuntimeException('Retain notification history; use reviewed forward repair.');}
};
