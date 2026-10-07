<?php

namespace App\Academic;

use App\Education\EducationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicService
{
    private object $actor;

    private int $key;

    private string $correlation;

    public function execute(int $actorId, string $command, array $input): int
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new AcademicError('database_required', 'Esta operación requiere MySQL.', 503);
        }

        return DB::transaction(function () use ($actorId, $command, $input) {
            $guard = DB::table('academic_write_guard')->where('id', 1)->lockForUpdate()->first();
            if (! $guard) {
                throw new AcademicError('integrity', 'No existe el control de escritura.', 503);
            }
            // One shared identity table: lock its union once, in fixed table rank and ID order.
            foreach (['academic_periods', 'instructional_entries', 'grades', 'sections', 'users',
                'student_enrollments', 'teaching_assignments', 'activity_references', 'submission_references'] as $table) {
                DB::table($table)->orderBy('id')->lockForUpdate()->get();
            }
            $this->actor = $this->record('users', $actorId);
            if (! $this->actor->is_active) {
                throw new AcademicError('forbidden', 'La cuenta está desactivada.', 403);
            }
            $this->key = (int) $guard->next_ordinal;
            if ($this->key >= PHP_INT_MAX) {
                throw new AcademicError('ordinal_exhausted', 'El orden de operaciones requiere mantenimiento antes de continuar.', 503);
            }
            $this->correlation = (string) Str::uuid();
            DB::table('academic_write_guard')->where('id', 1)->update(['next_ordinal' => $this->key + 1]);

            return match ($command) {
                'create_period' => $this->createPeriod($input),
                'activate_period' => $this->periodState($input, 'active'),
                'close_period' => $this->periodState($input, 'closed'),
                'create_catalog' => $this->createCatalog($input),
                'update_catalog' => $this->updateCatalog($input),
                'enroll' => $this->enroll($input),
                'transfer' => $this->transfer($input),
                'close_enrollment' => $this->closeEnrollment($input),
                'create_assignment' => $this->createAssignment($input),
                'activate_assignment' => $this->activateAssignment($input),
                'close_assignment' => $this->closeAssignment($input),
                'replace_teacher' => $this->replaceTeacher($input),
                'create_activity' => $this->createActivity($input),
                'accept_submission' => $this->acceptSubmission($input),
                default => throw new AcademicError('unknown_command', 'Operación desconocida.'),
            };
        }, 1); // No uncertain-commit replay. Explicit retries must re-enter this protocol.
    }

    private function role(array $roles): void
    {
        if (! in_array($this->actor->role, $roles, true)) {
            throw new AcademicError('forbidden', 'Tu perfil no permite esta operación.', 403);
        }
    }

    private function record(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->first() ?? throw new AcademicError('not_found', 'Registro no encontrado.', 404);
    }

    private function today(): string
    {
        return now(config('academic.school_timezone'))->toDateString();
    }

    private function period(int $id): object
    {
        $period = $this->record('academic_periods', $id);
        if ($period->state !== 'active') {
            throw new AcademicError('decision_required', 'Las operaciones sobre períodos no actuales están pendientes de definición.', 409);
        }

        return $period;
    }

    private function nameKey(string $name): string
    {
        return hash('sha256', $name); // Exact Unicode scalar sequence; no case/accent folding.
    }

    private function event(string $kind, int $id, string $type, ?string $before, ?string $after, array $metadata = []): void
    {
        DB::table('academic_lifecycle_events')->insert([
            $kind.'_id' => $id, 'actor_id' => $this->actor->id, 'event_type' => $type,
            'previous_state' => $before, 'new_state' => $after, 'effective_on' => $this->today(),
            'operation_key' => $this->key, 'correlation_id' => $this->correlation,
            'recorded_at' => now('UTC')->format('Y-m-d H:i:s.u'), 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function createPeriod(array $input): int
    {
        $this->role(['director']);
        if ($input['start_on'] > $input['end_on']) {
            throw new AcademicError('invalid_dates', 'La fecha final debe ser igual o posterior al inicio.');
        }
        $id = DB::table('academic_periods')->insertGetId([
            'name' => $input['name'], 'name_key' => $this->nameKey($input['name']),
            'start_on' => $input['start_on'], 'end_on' => $input['end_on'], 'state' => 'planned',
        ]);
        $this->event('period', $id, 'created', null, 'planned');

        return $id;
    }

    private function periodState(array $input, string $state): int
    {
        $this->role(['director']);
        $period = $this->record('academic_periods', (int) $input['id']);
        if ($period->state !== ($state === 'active' ? 'planned' : 'active')) {
            throw new AcademicError('invalid_transition', 'Transición de período no válida.');
        }
        if ($state === 'active' && DB::table('academic_periods')->where('state', 'active')->exists()) {
            throw new AcademicError('conflict', 'Ya existe un período activo.', 409);
        }
        DB::table('academic_periods')->where('id', $period->id)->update(['state' => $state]);
        $this->event('period', $period->id, 'state_changed', $period->state, $state);

        return $period->id;
    }

    private function catalogType(string $type): array
    {
        return match ($type) {
            'entry' => ['instructional_entries', 'entry'], 'grade' => ['grades', 'grade'], 'section' => ['sections', 'section'],
            default => throw new AcademicError('invalid_catalog', 'Catálogo desconocido.'),
        };
    }

    private function createCatalog(array $input): int
    {
        $this->role(['director']);
        [$table, $kind] = $this->catalogType($input['type']);
        $values = ['name' => $input['name'], 'name_key' => $this->nameKey($input['name']), 'is_active' => true];
        if ($kind === 'entry') {
            $values['classification'] = $input['classification'];
        }
        if ($kind === 'section') {
            $grade = $this->record('grades', (int) $input['grade_id']);
            if (! $grade->is_active) {
                throw new AcademicError('inactive_catalog', 'El grado está inactivo.');
            }
            $values['grade_id'] = $grade->id;
        }
        $id = DB::table($table)->insertGetId($values);
        $this->event($kind, $id, 'created', null, 'active');

        return $id;
    }

    private function updateCatalog(array $input): int
    {
        $this->role(['director']);
        [$table, $kind] = $this->catalogType($input['type']);
        $record = $this->record($table, (int) $input['id']);
        $values = ['name' => $input['name'], 'name_key' => $this->nameKey($input['name']), 'is_active' => $input['is_active']];
        DB::table($table)->where('id', $record->id)->update($values);
        $this->event($kind, $record->id, 'updated', $record->is_active ? 'active' : 'inactive', $input['is_active'] ? 'active' : 'inactive', ['previous_name' => $record->name, 'new_name' => $input['name']]);

        return $record->id;
    }

    private function scope(array $input): array
    {
        $period = $this->period((int) $input['academic_period_id']);
        $grade = $this->record('grades', (int) $input['grade_id']);
        $section = $this->record('sections', (int) $input['section_id']);
        if (! $grade->is_active || ! $section->is_active) {
            throw new AcademicError('inactive_catalog', 'El grado o sección está inactivo.');
        }
        if ($section->grade_id !== $grade->id) {
            throw new AcademicError('scope_mismatch', 'La sección no pertenece al grado.');
        }

        return ['academic_period_id' => $period->id, 'grade_id' => $grade->id, 'section_id' => $section->id];
    }

    private function enroll(array $input): int
    {
        $this->role(['director']);
        $scope = $this->scope($input);
        $student = $this->record('users', (int) $input['student_id']);
        if ($student->role !== 'estudiante' || ! $student->is_active) {
            throw new AcademicError('invalid_student', 'Estudiante no válida.');
        }
        if (DB::table('student_enrollments')->where('student_id', $student->id)->where('academic_period_id', $scope['academic_period_id'])->where('state', 'active')->exists()) {
            throw new AcademicError('conflict', 'La estudiante ya tiene matrícula activa en ese período.', 409);
        }
        $id = DB::table('student_enrollments')->insertGetId($scope + ['student_id' => $student->id, 'effective_from' => $this->today(), 'operational_start_key' => $this->key, 'state' => 'active']);
        $this->event('enrollment', $id, 'created', null, 'active');

        return $id;
    }

    private function transfer(array $input): int
    {
        $this->role(['director']);
        $prior = $this->record('student_enrollments', (int) $input['id']);
        if ($prior->state !== 'active') {
            throw new AcademicError('invalid_transition', 'La matrícula no está activa.');
        }
        $scope = $this->scope(['academic_period_id' => $prior->academic_period_id] + $input);
        if ($scope['grade_id'] === $prior->grade_id && $scope['section_id'] === $prior->section_id) {
            throw new AcademicError('no_scope_change', 'El traslado debe cambiar el grado o sección.');
        }
        $this->endEnrollment($prior, 'transferred');
        $id = DB::table('student_enrollments')->insertGetId($scope + ['student_id' => $prior->student_id, 'effective_from' => $this->today(), 'operational_start_key' => $this->key, 'state' => 'active', 'transferred_from_id' => $prior->id]);
        $this->event('enrollment', $id, 'created', null, 'active', ['transferred_from_id' => (string) $prior->id]);

        return $id;
    }

    private function endEnrollment(object $prior, string $state): void
    {
        if ($this->today() < $prior->effective_from) {
            throw new AcademicError('invalid_dates', 'La fecha efectiva no puede ser anterior al inicio.');
        }
        DB::table('student_enrollments')->where('id', $prior->id)->update(['state' => $state, 'effective_until' => $this->today(), 'operational_end_key' => $this->key]);
        $this->event('enrollment', $prior->id, 'state_changed', $prior->state, $state);
    }

    private function closeEnrollment(array $input): int
    {
        $this->role(['director']);
        $prior = $this->record('student_enrollments', (int) $input['id']);
        $this->period($prior->academic_period_id);
        if ($prior->state !== 'active') {
            throw new AcademicError('invalid_transition', 'La matrícula no está activa.');
        }
        $this->endEnrollment($prior, 'closed');

        return $prior->id;
    }

    private function assignmentValues(array $input): array
    {
        $scope = $this->scope($input);
        $period = $this->record('academic_periods', $scope['academic_period_id']);
        if ($this->today() < $period->start_on || $this->today() > $period->end_on) {
            throw new AcademicError('invalid_dates', 'La fecha efectiva debe estar dentro del período.');
        }
        $teacher = $this->record('users', (int) $input['teacher_id']);
        $entry = $this->record('instructional_entries', (int) $input['instructional_entry_id']);
        if ($teacher->role !== 'docente' || ! $teacher->is_active || ! $entry->is_active) {
            throw new AcademicError('inactive_reference', 'Docente o área no válida.');
        }

        return $scope + ['teacher_id' => $teacher->id, 'instructional_entry_id' => $entry->id];
    }

    private function assignmentConflict(array $scope, ?int $except = null): void
    {
        $rows = DB::table('teaching_assignments')->where($scope)->when($except, fn ($query) => $query->where('id', '<>', $except))->get();
        foreach ($rows as $row) {
            if ($row->state === 'planned' || OperationalInterval::overlaps($this->key, null, (int) $row->operational_start_key, $row->operational_end_key === null ? null : (int) $row->operational_end_key)) {
                throw new AcademicError('conflict', 'Existe una asignación superpuesta para ese docente y alcance.', 409);
            }
        }
    }

    private function createAssignment(array $input): int
    {
        $this->role(['director', 'subdirector']);
        $scope = $this->assignmentValues($input);
        $this->assignmentConflict($scope);
        $id = DB::table('teaching_assignments')->insertGetId($scope + ['effective_from' => $this->today(), 'state' => 'planned']);
        $this->event('assignment', $id, 'created', null, 'planned');

        return $id;
    }

    private function activateAssignment(array $input): int
    {
        $this->role(['director', 'subdirector']);
        $prior = $this->record('teaching_assignments', (int) $input['id']);
        if ($prior->state !== 'planned') {
            throw new AcademicError('invalid_transition', 'Solo una asignación planificada puede activarse.');
        }
        $scope = $this->assignmentValues((array) $prior);
        $this->assignmentConflict($scope, $prior->id);
        DB::table('teaching_assignments')->where('id', $prior->id)->update(['state' => 'active', 'operational_start_key' => $this->key]);
        $this->event('assignment', $prior->id, 'state_changed', 'planned', 'active');

        return $prior->id;
    }

    private function endAssignment(object $prior): void
    {
        $period = $this->period($prior->academic_period_id);
        if ($this->today() < $prior->effective_from || $this->today() > $period->end_on) {
            throw new AcademicError('invalid_dates', 'Fecha de cierre fuera de los límites permitidos.');
        }
        DB::table('teaching_assignments')->where('id', $prior->id)->update(['state' => 'closed', 'effective_until' => $this->today(), 'operational_end_key' => $this->key]);
        $this->event('assignment', $prior->id, 'state_changed', 'active', 'closed');
    }

    private function closeAssignment(array $input): int
    {
        $this->role(['director', 'subdirector']);
        $prior = $this->record('teaching_assignments', (int) $input['id']);
        if ($prior->state !== 'active') {
            throw new AcademicError('invalid_transition', 'La asignación no está activa.');
        }
        $this->endAssignment($prior);

        return $prior->id;
    }

    private function replaceTeacher(array $input): int
    {
        $this->role(['director', 'subdirector']);
        $prior = $this->record('teaching_assignments', (int) $input['id']);
        if ($prior->state !== 'active') {
            throw new AcademicError('invalid_transition', 'La asignación no está activa.');
        }
        $scope = $this->assignmentValues(['teacher_id' => $input['teacher_id']] + (array) $prior);
        $this->endAssignment($prior);
        $this->assignmentConflict($scope);
        $id = DB::table('teaching_assignments')->insertGetId($scope + ['effective_from' => $this->today(), 'operational_start_key' => $this->key, 'state' => 'active', 'replaces_assignment_id' => $prior->id]);
        $this->event('assignment', $id, 'created', null, 'active', ['replaces_assignment_id' => (string) $prior->id]);

        return $id;
    }

    private function createActivity(array $input): int
    {
        $this->role(['docente']);
        if (array_diff(array_keys($input), ['assignment_id', 'title', 'description', 'due_date'])) {
            throw new AcademicError('unexpected_fields', 'El alcance se deriva de la asignación.');
        }
        $assignment = $this->record('teaching_assignments', (int) $input['assignment_id']);
        if ($assignment->teacher_id !== $this->actor->id) {
            throw new AcademicError('forbidden', 'La asignación pertenece a otro docente.', 403);
        }
        $this->period($assignment->academic_period_id);
        if ($assignment->state !== 'active') {
            throw new AcademicError('inactive_assignment', 'No puedes crear actividades en una asignación no activa.');
        }

        $id = DB::table('activity_references')->insertGetId(['teaching_assignment_id' => $assignment->id, 'title' => $input['title'], 'description' => $input['description'], 'created_at' => now(), 'updated_at' => now()]);
        if (! empty($input['due_date'])) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $input['due_date']);
            if (! $date || $date->format('Y-m-d') !== $input['due_date'] || $input['due_date'] < $this->today()) {
                throw new AcademicError('invalid_dates', 'Selecciona una fecha de entrega desde hoy.');
            }
        }
        DB::table('activity_settings')->insert(['activity_id' => $id, 'due_date' => $input['due_date'] ?? null, 'state' => 'open']);
        foreach (DB::table('student_enrollments')->where('academic_period_id', $assignment->academic_period_id)->where('grade_id', $assignment->grade_id)->where('section_id', $assignment->section_id)->where('state', 'active')->pluck('student_id') as $studentId) {
            EducationService::notify((int) $studentId, 'Nueva actividad: '.$input['title'], 'activities');
        }

        return $id;
    }

    private function acceptSubmission(array $input): int
    {
        $this->role(['estudiante']);
        if (array_diff(array_keys($input), ['activity_id', 'answer'])) {
            throw new AcademicError('unexpected_fields', 'El destinatario se deriva de la actividad.');
        }
        $activity = $this->record('activity_references', (int) $input['activity_id']);
        if (DB::table('activity_settings')->where('activity_id', $activity->id)->where('state', 'closed')->exists()) {
            throw new AcademicError('activity_closed', 'La actividad está cerrada.', 409);
        }
        $assignment = $this->record('teaching_assignments', $activity->teaching_assignment_id);
        $this->period($assignment->academic_period_id);
        $enrollment = DB::table('student_enrollments')->where('student_id', $this->actor->id)->where('academic_period_id', $assignment->academic_period_id)->where('state', 'active')->first();
        if (! $enrollment || $enrollment->grade_id !== $assignment->grade_id || $enrollment->section_id !== $assignment->section_id) {
            throw new AcademicError('scope_mismatch', 'La actividad no corresponde a tu matrícula activa.', 403);
        }

        return DB::table('submission_references')->insertGetId(['student_id' => $this->actor->id, 'activity_id' => $activity->id, 'teaching_assignment_id' => $assignment->id, 'accepted_under_enrollment_id' => $enrollment->id, 'accepted_at' => now('UTC')->format('Y-m-d H:i:s.u'), 'acceptance_operation_key' => $this->key, 'answer' => $input['answer']]);
    }
}
