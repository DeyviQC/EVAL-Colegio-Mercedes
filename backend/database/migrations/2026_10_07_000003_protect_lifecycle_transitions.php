<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $triggers = [
            'period_lifecycle_transitions' => "BEFORE UPDATE ON academic_periods FOR EACH ROW BEGIN IF NEW.state<>OLD.state AND NOT ((OLD.state='planned' AND NEW.state='active') OR (OLD.state='active' AND NEW.state='closed')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid period lifecycle transition'; END IF; END",
            'enrollment_terminal_history' => "BEFORE UPDATE ON student_enrollments FOR EACH ROW BEGIN IF NOT (NEW.transferred_from_id <=> OLD.transferred_from_id) OR (OLD.state IN ('closed','transferred') AND (NEW.state<>OLD.state OR NOT (NEW.effective_until <=> OLD.effective_until) OR NOT (NEW.operational_end_key <=> OLD.operational_end_key))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Terminal enrollment history cannot change'; END IF; END",
            'assignment_terminal_history' => "BEFORE UPDATE ON teaching_assignments FOR EACH ROW BEGIN IF (OLD.state='active' AND NEW.state='planned') OR (OLD.state='closed' AND (NEW.state<>'closed' OR NOT (NEW.effective_until <=> OLD.effective_until) OR NOT (NEW.operational_end_key <=> OLD.operational_end_key))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid assignment lifecycle transition'; END IF; END",
            'activity_stable_identity' => "BEFORE UPDATE ON activity_references FOR EACH ROW BEGIN IF NEW.id<>OLD.id OR NEW.created_at<>OLD.created_at THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Activity identity is immutable'; END IF; END",
            'submission_stable_identity' => "BEFORE UPDATE ON submission_references FOR EACH ROW BEGIN IF NEW.id<>OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission identity is immutable'; END IF; END",
        ];
        foreach ($triggers as $name => $body) {
            if (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $name)->exists()) {
                DB::unprepared('CREATE TRIGGER '.$name.' '.$body);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Retained lifecycle protections require a reviewed forward repair.');
    }
};
