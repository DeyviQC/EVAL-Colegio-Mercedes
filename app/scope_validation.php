<?php
declare(strict_types=1);

/** Unsupported academic selectors must never override authoritative scope. */
function rejectAcademicOverrides(array $request): void
{
    foreach (['period_id', 'academic_period_id', 'grade_id', 'section_id', 'group_id',
        'teacher_id', 'recipient_id', 'recipient_teacher_id', 'assignment_id',
        'teaching_assignment_id', 'enrollment_id', 'student_id', 'instructional_entry_id',
        'period', 'grade', 'section', 'recipient', 'assignment'] as $field) {
        if (array_key_exists($field, $request)) {
            throw new RuntimeException('El alcance académico y el destinatario se determinan en el servidor.');
        }
    }
}
