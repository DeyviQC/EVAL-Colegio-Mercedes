<?php

namespace App\Education;

use App\Academic\AcademicError;
use App\Academic\AcademicService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EducationService
{
    public function execute(int $actorId, string $action, array $input): int
    {
        return DB::transaction(function () use ($actorId, $action, $input) {
            if (! DB::table('academic_write_guard')->where('id', 1)->lockForUpdate()->first()) {
                throw new AcademicError('integrity', 'No existe el control de escritura.', 503);
            }
            foreach (['academic_periods', 'instructional_entries', 'grades', 'sections', 'users', 'student_enrollments', 'teaching_assignments', 'activity_references', 'submission_references'] as $table) {
                DB::table($table)->orderBy('id')->lockForUpdate()->get();
            }
            $actor = DB::table('users')->where('id', $actorId)->first();
            if (! $actor || ! $actor->is_active) {
                throw new AcademicError('forbidden', 'La cuenta no está activa.', 403);
            }

            return match ($action) {
                'publish_material' => $this->material($actor, $input),
                'edit_material' => $this->editMaterial($actor, $input),
                'delete_material' => $this->deleteMaterial($actor, $input),
                'submit' => $this->submit($actor, $input),
                'assess' => $this->assess($actor, $input),
                'create_user' => $this->createUser($actor, $input),
                'update_user' => $this->updateUser($actor, $input),
                'change_password' => $this->password($actor, $input),
                'read_notifications' => $this->readNotifications($actor),
                'close_activity' => $this->closeActivity($actor, $input),
                default => throw new AcademicError('unknown_command', 'Operación desconocida.'),
            };
        }, 1);
    }

    private function closeActivity(object $actor, array $input): int
    {
        $activity = DB::table('activity_references')->find($input['id']) ?? throw new AcademicError('not_found', 'Actividad no encontrada.', 404);
        $this->owner($actor, $this->assignment($activity->teaching_assignment_id), false);
        DB::table('activity_settings')->updateOrInsert(['activity_id' => $activity->id], ['state' => 'closed']);
        $this->event($actor, 'activity_closed', $activity->id);

        return $activity->id;
    }

    private function role(object $actor, array $roles): void
    {
        if (! in_array($actor->role, $roles, true)) {
            throw new AcademicError('forbidden', 'Tu perfil no permite esta operación.', 403);
        }
    }

    private function assignment(int $id): object
    {
        return DB::table('teaching_assignments')->where('id', $id)->first() ?? throw new AcademicError('not_found', 'Asignación no encontrada.', 404);
    }

    private function openPeriod(object $assignment): void
    {
        if (! DB::table('academic_periods')->where('id', $assignment->academic_period_id)->where('state', 'active')->exists()) {
            throw new AcademicError('read_only_period', 'El período está cerrado o no está activo; su historial es de consulta.', 409);
        }
    }

    private function owner(object $actor, object $assignment, bool $active = true): void
    {
        $this->role($actor, ['docente']);
        if ($assignment->teacher_id !== $actor->id) {
            throw new AcademicError('forbidden', 'La asignación pertenece a otro docente.', 403);
        }
        $this->openPeriod($assignment);
        if ($active && $assignment->state !== 'active') {
            throw new AcademicError('inactive_assignment', 'La asignación no está activa.', 409);
        }
    }

    public static function notify(int $user, string $message, string $page): void
    {
        DB::table('education_notifications')->insert(['user_id' => $user, 'message' => $message, 'page' => $page, 'created_at' => now()]);
    }

    private function event(object $actor, string $action, int $id, array $meta = []): void
    {
        DB::table('education_events')->insert(['actor_id' => $actor->id, 'action' => $action, 'resource_id' => $id, 'metadata' => json_encode($meta, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function students(object $assignment, string $message, string $page): void
    {
        foreach (DB::table('student_enrollments')->join('users', 'users.id', '=', 'student_enrollments.student_id')->where('student_enrollments.academic_period_id', $assignment->academic_period_id)->where('grade_id', $assignment->grade_id)->where('section_id', $assignment->section_id)->where('state', 'active')->where('users.is_active', true)->pluck('student_id') as $id) {
            self::notify((int) $id, $message, $page);
        }
    }

    private function material(object $actor, array $input): int
    {
        $assignment = $this->assignment((int) $input['assignment_id']);
        $this->owner($actor, $assignment);
        $id = DB::table('educational_materials')->insertGetId(['teaching_assignment_id' => $assignment->id, 'title' => $input['title'], 'body' => $input['body'] ?? '', 'file_path' => $input['file_path'] ?? null, 'file_name' => $input['file_name'] ?? null, 'file_mime' => $input['file_mime'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        $this->event($actor, 'material_published', $id);
        $this->students($assignment, 'Nuevo material: '.$input['title'], 'materials');

        return $id;
    }

    private function editMaterial(object $actor, array $input): int
    {
        $material = DB::table('educational_materials')->where('id', $input['id'])->whereNull('deleted_at')->first() ?? throw new AcademicError('not_found', 'Material no encontrado.', 404);
        $this->owner($actor, $this->assignment($material->teaching_assignment_id));
        DB::table('educational_materials')->where('id', $material->id)->update(['title' => $input['title'], 'body' => $input['body'], 'updated_at' => now()]);
        $this->event($actor, 'material_edited', $material->id);

        return $material->id;
    }

    private function deleteMaterial(object $actor, array $input): int
    {
        $this->role($actor, ['director', 'docente']);
        $material = DB::table('educational_materials')->where('id', $input['id'])->whereNull('deleted_at')->first() ?? throw new AcademicError('not_found', 'Material no encontrado.', 404);
        $assignment = $this->assignment($material->teaching_assignment_id);
        $this->openPeriod($assignment);
        if ($actor->role === 'docente') {
            $this->owner($actor, $assignment);
        }
        DB::table('educational_materials')->where('id', $material->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        $path = $material->file_path;
        if ($path) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        }
        $this->event($actor, 'material_deleted', $material->id);
        self::notify($assignment->teacher_id, 'Material retirado: '.$material->title, 'materials');

        return $material->id;
    }

    private function submit(object $actor, array $input): int
    {
        $this->role($actor, ['estudiante']);
        $activity = DB::table('activity_references')->where('id', $input['activity_id'])->first() ?? throw new AcademicError('not_found', 'Actividad no encontrada.', 404);
        $assignment = $this->assignment($activity->teaching_assignment_id);
        if (DB::table('activity_settings')->where('activity_id', $activity->id)->where('state', 'closed')->exists()) {
            throw new AcademicError('activity_closed', 'La actividad está cerrada y no recibe nuevas entregas.', 409);
        }
        $this->openPeriod($assignment);
        $enrollment = DB::table('student_enrollments')->where('student_id', $actor->id)->where('academic_period_id', $assignment->academic_period_id)->where('grade_id', $assignment->grade_id)->where('section_id', $assignment->section_id)->where('state', 'active')->first();
        if (! $enrollment) {
            throw new AcademicError('scope_mismatch', 'La actividad no corresponde a tu matrícula activa.', 403);
        }
        $existing = DB::table('submission_references')->where('student_id', $actor->id)->where('activity_id', $activity->id)->first();
        if ($existing) {
            if ($existing->accepted_under_enrollment_id !== $enrollment->id) {
                throw new AcademicError('historical_read_only', 'La entrega pertenece a una matrícula anterior.', 409);
            }
            if (DB::table('submission_assessments')->where('submission_id', $existing->id)->exists()) {
                throw new AcademicError('already_assessed', 'La entrega ya fue calificada y no puede editarse.', 409);
            }
            $id = $existing->id;
            DB::table('submission_references')->where('id', $id)->update(['answer' => $input['answer'] ?? '']);
        } else {
            $id = app(AcademicService::class)->execute($actor->id, 'accept_submission', ['activity_id' => $activity->id, 'answer' => $input['answer'] ?? '']);
        }
        if (isset($input['file_path'])) {
            $prior = DB::table('submission_files')->where('submission_id', $id)->first();
            DB::table('submission_files')->updateOrInsert(['submission_id' => $id], ['file_path' => $input['file_path'], 'file_name' => $input['file_name'], 'file_mime' => $input['file_mime']]);
            if ($prior) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($prior->file_path));
            }
        }
        $this->event($actor, 'submission_saved', $id);
        self::notify($assignment->teacher_id, $actor->name.' entregó: '.$activity->title, 'submissions');

        return $id;
    }

    private function assess(object $actor, array $input): int
    {
        $submission = DB::table('submission_references')->where('id', $input['submission_id'])->first() ?? throw new AcademicError('not_found', 'Entrega no encontrada.', 404);
        $this->owner($actor, $this->assignment($submission->teaching_assignment_id), false);
        $prior = DB::table('submission_assessments')->where('submission_id', $submission->id)->first();
        DB::table('submission_assessments')->updateOrInsert(['submission_id' => $submission->id], ['teacher_id' => $actor->id, 'grade' => $input['grade'], 'feedback' => $input['feedback'], 'created_at' => $prior?->created_at ?? now(), 'updated_at' => now()]);
        $this->event($actor, 'submission_assessed', $submission->id, ['previous_grade' => $prior?->grade, 'grade' => $input['grade'], 'feedback' => $input['feedback']]);
        self::notify($submission->student_id, 'Nota '.$input['grade'].' registrada para tu entrega.', 'grades');

        return $submission->id;
    }

    private function createUser(object $actor, array $input): int
    {
        $this->role($actor, ['director']);
        $id = DB::table('users')->insertGetId(['name' => $input['name'], 'email' => $input['email'], 'password' => Hash::make($input['password']), 'role' => $input['role'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        if ($input['role'] === 'estudiante') {
            app(AcademicService::class)->execute($actor->id, 'enroll', ['student_id' => $id, 'academic_period_id' => $input['academic_period_id'], 'grade_id' => $input['grade_id'], 'section_id' => $input['section_id']]);
        }
        $this->event($actor, 'user_created', $id);
        self::notify($id, 'Tu cuenta está lista. Bienvenida a EVAL.', 'profile');

        return $id;
    }

    private function updateUser(object $actor, array $input): int
    {
        $this->role($actor, ['director']);
        $user = DB::table('users')->where('id', $input['id'])->first() ?? throw new AcademicError('not_found', 'Cuenta no encontrada.', 404);
        if (! $input['is_active'] && $user->id === $actor->id) {
            throw new AcademicError('self_deactivation', 'No puedes desactivar tu propia cuenta.');
        }
        $values = ['name' => $input['name'], 'email' => $input['email'], 'is_active' => $input['is_active'], 'updated_at' => now()];
        if (! empty($input['password'])) {
            $values['password'] = Hash::make($input['password']);
        }DB::table('users')->where('id', $user->id)->update($values);
        if (! $input['is_active'] || ! empty($input['password'])) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }$this->event($actor, 'user_updated', $user->id);

        return $user->id;
    }

    private function password(object $actor, array $input): int
    {
        if (! Hash::check($input['current_password'], $actor->password)) {
            throw new AcademicError('invalid_password', 'La contraseña actual no es correcta.');
        }DB::table('users')->where('id', $actor->id)->update(['password' => Hash::make($input['password']), 'updated_at' => now()]);
        DB::table('sessions')->where('user_id', $actor->id)->where('id', '<>', $input['session_id'])->delete();
        $this->event($actor, 'password_changed', $actor->id);

        return $actor->id;
    }

    private function readNotifications(object $actor): int
    {
        DB::table('education_notifications')->where('user_id', $actor->id)->update(['is_read' => true]);

        return $actor->id;
    }
}
