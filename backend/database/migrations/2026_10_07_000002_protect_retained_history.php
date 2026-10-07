<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $triggers = [
            'retain_enrollment_scope' => "BEFORE UPDATE ON student_enrollments FOR EACH ROW BEGIN IF NEW.student_id<>OLD.student_id OR NEW.academic_period_id<>OLD.academic_period_id OR NEW.grade_id<>OLD.grade_id OR NEW.section_id<>OLD.section_id OR NEW.effective_from<>OLD.effective_from OR NEW.operational_start_key<>OLD.operational_start_key THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Enrollment identity and scope are immutable'; END IF; END",
            'retain_assignment_scope' => "BEFORE UPDATE ON teaching_assignments FOR EACH ROW BEGIN IF NEW.teacher_id<>OLD.teacher_id OR NEW.academic_period_id<>OLD.academic_period_id OR NEW.instructional_entry_id<>OLD.instructional_entry_id OR NEW.grade_id<>OLD.grade_id OR NEW.section_id<>OLD.section_id OR NEW.effective_from<>OLD.effective_from OR NOT (NEW.replaces_assignment_id <=> OLD.replaces_assignment_id) OR (OLD.operational_start_key IS NOT NULL AND NOT (NEW.operational_start_key <=> OLD.operational_start_key)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assignment identity and scope are immutable'; END IF; END",
            'retain_entry_classification' => "BEFORE UPDATE ON instructional_entries FOR EACH ROW BEGIN IF NEW.classification<>OLD.classification AND EXISTS(SELECT 1 FROM teaching_assignments WHERE instructional_entry_id=OLD.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Referenced classification is immutable'; END IF; END",
            'append_only_ledger_update' => "BEFORE UPDATE ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lifecycle evidence is append-only'",
            'append_only_ledger_delete' => "BEFORE DELETE ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lifecycle evidence is append-only'",
        ];
        foreach ($triggers as $name => $body) {
            if (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $name)->exists()) {
                DB::unprepared('CREATE TRIGGER '.$name.' '.$body);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Retained history protections require a reviewed forward repair.');
    }
};
