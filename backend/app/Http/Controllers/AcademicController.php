<?php

namespace App\Http\Controllers;

use App\Academic\AcademicError;
use App\Academic\AcademicService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AcademicController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials + ['is_active' => true])) {
            return response()->json(['category' => 'invalid_credentials', 'message' => 'Correo o contraseña incorrectos.'], 401);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $this->user($request)]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function user(Request $request): array
    {
        $user = $request->user();

        return ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role];
    }

    public function state(Request $request)
    {
        $user = $request->user();
        if (! $user->is_active) {
            abort(403);
        }
        if (array_diff(array_keys($request->query()), ['section_id'])) {
            return response()->json(['category' => 'unexpected_fields', 'message' => 'No se permite cambiar el alcance académico desde la consulta.'], 422);
        }
        $period = DB::table('academic_periods')->where('state', 'active')->first();
        $targetSection = $request->integer('section_id');
        if ($targetSection && ! DB::table('sections')->where('id', $targetSection)->exists()) {
            abort(404);
        }
        if ($targetSection && $user->role === 'estudiante' && ! DB::table('student_enrollments')->where('student_id', $user->id)->where('academic_period_id', $period?->id ?? 0)->where('state', 'active')->where('section_id', $targetSection)->exists()) {
            abort(403);
        }
        if ($targetSection && $user->role === 'docente' && ! DB::table('teaching_assignments')->where('teacher_id', $user->id)->where('section_id', $targetSection)->exists()) {
            abort(403);
        }
        $assignments = DB::table('teaching_assignments as a')->join('users as u', 'u.id', '=', 'a.teacher_id')->join('instructional_entries as i', 'i.id', '=', 'a.instructional_entry_id')->join('grades as g', 'g.id', '=', 'a.grade_id')->join('sections as s', 's.id', '=', 'a.section_id')->select('a.*', 'u.name as teacher', 'i.name as course', 'g.name as grade', 's.name as section');
        $enrollments = DB::table('student_enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->join('grades as g', 'g.id', '=', 'e.grade_id')->join('sections as s', 's.id', '=', 'e.section_id')->select('e.*', 'u.name as student', 'g.name as grade', 's.name as section');
        if ($user->role === 'docente') {
            $assignments->where('a.teacher_id', $user->id);
        }
        if ($user->role === 'director') {
            $assignments->where('a.section_id', $targetSection ?: 0);
            $enrollments->where('e.section_id', $targetSection ?: 0);
        }
        if ($user->role === 'estudiante') {
            $enrollments->where('e.student_id', $user->id);
            $current = DB::table('student_enrollments')->where('student_id', $user->id)->where('academic_period_id', $period?->id ?? 0)->where('state', 'active')->first();
            $assignments->where('a.academic_period_id', $period?->id ?? 0)->where('a.grade_id', $current?->grade_id ?? 0)->where('a.section_id', $current?->section_id ?? 0);
        }
        $rows = $assignments->get();
        $activities = DB::table('activity_references as r')->leftJoin('activity_settings as z', 'z.activity_id', '=', 'r.id')->select('r.*', 'z.due_date', 'z.state as availability')->whereIn('teaching_assignment_id', $rows->pluck('id'));
        $submissions = DB::table('submission_references');
        if ($user->role === 'estudiante') {
            $submissions->where('student_id', $user->id);
        } elseif ($user->role === 'docente') {
            $submissions->whereIn('teaching_assignment_id', $rows->pluck('id'));
        } elseif ($user->role === 'subdirector') {
            $submissions->whereRaw('1=0');
        } elseif ($user->role === 'director') {
            $submissions->whereIn('teaching_assignment_id', $rows->pluck('id'));
        }
        $submissionRows = $submissions->get();
        if ($user->role === 'estudiante') {
            $currentEnrollmentIds = DB::table('student_enrollments')->where('student_id', $user->id)->where('state', 'active')->where('academic_period_id', $period?->id ?? 0)->pluck('id')->all();
            foreach ($submissionRows as $submission) {
                if (! in_array($submission->accepted_under_enrollment_id, $currentEnrollmentIds, true)) {
                    unset($submission->answer);
                }
            }
        }
        if ($user->role === 'estudiante') {
            $activities = DB::table('activity_references as r')->leftJoin('activity_settings as z', 'z.activity_id', '=', 'r.id')->select('r.*', 'z.due_date', 'z.state as availability')->where(function ($query) use ($rows, $submissionRows) {
                $query->whereIn('teaching_assignment_id', $rows->pluck('id'))->orWhereIn('r.id', $submissionRows->pluck('activity_id'));
            });
            $historicalAssignments = DB::table('teaching_assignments as a')->join('users as u', 'u.id', '=', 'a.teacher_id')->join('instructional_entries as i', 'i.id', '=', 'a.instructional_entry_id')->join('grades as g', 'g.id', '=', 'a.grade_id')->join('sections as s', 's.id', '=', 'a.section_id')->whereIn('a.id', $submissionRows->pluck('teaching_assignment_id'))->select('a.*', 'u.name as teacher', 'i.name as course', 'g.name as grade', 's.name as section')->get();
            $rows = $rows->concat($historicalAssignments)->unique('id')->values();
        }
        $director = $user->role === 'director';
        $supportGroup = $request->integer('section_id');
        if ($user->role === 'subdirector') {
            // Assignment-scoped projections: never return student identities or histories.
            if ($supportGroup && ! DB::table('sections')->where('id', $supportGroup)->exists()) {
                abort(404);
            }
            $enrollmentRows = [];
            $activityRows = [];
            $periods = $supportGroup ? DB::table('academic_periods')->where('state', 'active')->get() : [];
            $grades = $supportGroup ? DB::table('grades')->whereIn('id', DB::table('sections')->where('id', $supportGroup)->pluck('grade_id'))->get() : [];
            $sections = $supportGroup ? DB::table('sections')->where('id', $supportGroup)->get() : [];
            $entries = $supportGroup ? DB::table('instructional_entries')->where('is_active', true)->get() : [];
        } else {
            $enrollmentRows = ($director || $user->role === 'estudiante') ? $enrollments->get() : [];
            $activityRows = $activities->get();
            $periods = $director ? DB::table('academic_periods')->get() : ($period ? [$period] : []);
            $grades = $director ? DB::table('grades')->get() : [];
            $sections = $director ? DB::table('sections')->get() : [];
            $entries = $director ? DB::table('instructional_entries')->get() : [];
        }
        $people = $director ? DB::table('users')->select('id', 'name', 'email', 'role', 'is_active')->where(function ($query) use ($targetSection, $period) {
            $query->where('role', '<>', 'estudiante')->orWhereIn('id', DB::table('student_enrollments')->where('section_id', $targetSection ?: 0)->pluck('student_id'))->orWhereNotIn('id', DB::table('student_enrollments')->where('state', 'active')->where('academic_period_id', $period?->id ?? 0)->pluck('student_id'));
        })->get() : ($user->role === 'subdirector' && $supportGroup ? DB::table('users')->where('role', 'docente')->where('is_active', true)->select('id', 'name', 'role')->get() : []);

        return response()->json($this->stringIds(['user' => $this->user($request), 'current_period' => $user->role === 'subdirector' && ! $supportGroup ? null : $period, 'periods' => $periods, 'grades' => $grades, 'sections' => $sections, 'entries' => $entries, 'people' => $people, 'assignments' => $rows, 'enrollments' => $enrollmentRows, 'activities' => $activityRows, 'submissions' => $submissionRows]));
    }

    private function stringIds(mixed $value, ?string $key = null): mixed
    {
        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }
        if (is_array($value)) {
            return $this->mapObject($value);
        }
        if ($value !== null && $key !== null && ($key === 'id' || str_ends_with($key, '_id') || str_ends_with($key, '_key'))) {
            return (string) $value;
        }

        return $value;
    }

    private function mapObject(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = $this->stringIds($item, (string) $key);
        }

        return $value;
    }

    public function command(Request $request, string $command, AcademicService $service)
    {
        $schemas = [
            'create_period' => ['name' => 'required|string|max:120', 'start_on' => 'required|date_format:Y-m-d', 'end_on' => 'required|date_format:Y-m-d'],
            'activate_period' => ['id' => 'required|integer|min:1'], 'close_period' => ['id' => 'required|integer|min:1'],
            'create_catalog' => ['type' => 'required|in:entry,grade,section', 'name' => 'required|string|max:120', 'classification' => 'required_if:type,entry|in:subject,area', 'grade_id' => 'required_if:type,section|integer|min:1'],
            'update_catalog' => ['type' => 'required|in:entry,grade,section', 'id' => 'required|integer|min:1', 'name' => 'required|string|max:120', 'is_active' => 'required|boolean'],
            'enroll' => ['student_id' => 'required|integer|min:1', 'academic_period_id' => 'required|integer|min:1', 'grade_id' => 'required|integer|min:1', 'section_id' => 'required|integer|min:1'],
            'transfer' => ['id' => 'required|integer|min:1', 'grade_id' => 'required|integer|min:1', 'section_id' => 'required|integer|min:1'],
            'close_enrollment' => ['id' => 'required|integer|min:1'],
            'create_assignment' => ['academic_period_id' => 'required|integer|min:1', 'teacher_id' => 'required|integer|min:1', 'instructional_entry_id' => 'required|integer|min:1', 'grade_id' => 'required|integer|min:1', 'section_id' => 'required|integer|min:1'],
            'activate_assignment' => ['id' => 'required|integer|min:1'], 'close_assignment' => ['id' => 'required|integer|min:1'],
            'replace_teacher' => ['id' => 'required|integer|min:1', 'teacher_id' => 'required|integer|min:1'],
            'create_activity' => ['assignment_id' => 'required|integer|min:1', 'title' => 'required|string|max:200', 'description' => 'required|string|max:10000', 'due_date' => 'nullable|date_format:Y-m-d'],
            'accept_submission' => ['activity_id' => 'required|integer|min:1', 'answer' => 'required|string|max:10000'],
        ];
        if (! isset($schemas[$command])) {
            abort(404);
        }
        $unknown = array_diff(array_keys($request->all()), array_keys($schemas[$command]));
        if ($unknown) {
            return response()->json(['category' => 'unexpected_fields', 'message' => 'No se permiten selectores de alcance o destinatarios adicionales.'], 422);
        }
        $input = Validator::make($request->all(), $schemas[$command])->validate();
        try {
            return response()->json(['id' => (string) $service->execute((int) $request->user()->id, $command, $input)], 201);
        } catch (AcademicError $error) {
            return response()->json(['category' => $error->category, 'message' => $error->getMessage()], $error->status);
        } catch (QueryException $error) {
            if (in_array((string) ($error->errorInfo[0] ?? ''), ['23000', '45000'], true)) {
                return response()->json(['category' => 'conflict', 'message' => 'La operación contradice una restricción académica; no se guardaron cambios.'], 409);
            }
            throw $error;
        }
    }
}
