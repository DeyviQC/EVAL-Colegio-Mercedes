<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up():void
    {
        DB::schema()->create('submission_references',function(Blueprint $table){
            $table->id();foreach(['student_id'=>'retained_identities','activity_id'=>'activity_references','teaching_assignment_id'=>'teaching_assignments','accepted_under_enrollment_id'=>'student_enrollments'] as $field=>$parent){
                $table->foreignId($field)->constrained($parent)->restrictOnDelete()->restrictOnUpdate();}
            $table->dateTime('accepted_at',6);$table->unsignedBigInteger('acceptance_operation_key');
        });
        DB::connection()->statement('ALTER TABLE submission_references ADD CONSTRAINT acceptance_key_range CHECK (acceptance_operation_key BETWEEN 1 AND 9223372036854775807)');
        DB::connection()->unprepared("CREATE TRIGGER submission_reference_no_update BEFORE UPDATE ON submission_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Accepted context is immutable'");
        DB::connection()->unprepared("CREATE TRIGGER submission_reference_no_delete BEFORE DELETE ON submission_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain accepted context'");
    }
    public function down():void{throw new RuntimeException('Retain accepted context; use reviewed forward repair.');}
};
