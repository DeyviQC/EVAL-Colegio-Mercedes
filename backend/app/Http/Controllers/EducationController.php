<?php

namespace App\Http\Controllers;

use App\Academic\AcademicError;
use App\Education\EducationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EducationController extends Controller
{
    private function fresh(Request $request): object
    {
        $actor = DB::table('users')->where('id', $request->user()->id)->first();
        abort_unless($actor && $actor->is_active, 403);

        return $actor;
    }

    private function canRead(object $actor, object $assignment): bool
    {
        if ($actor->role === 'director') {
            return true;
        }if ($actor->role === 'docente') {
            return $assignment->teacher_id === $actor->id;
        }

        return $actor->role === 'estudiante' && DB::table('student_enrollments')->where('student_id', $actor->id)->where('academic_period_id', $assignment->academic_period_id)->where('grade_id', $assignment->grade_id)->where('section_id', $assignment->section_id)->where('state', 'active')->whereIn('academic_period_id', DB::table('academic_periods')->where('state', 'active')->pluck('id'))->exists();
    }

    public function state(Request $request): JsonResponse
    {
        $actor = $this->fresh($request);
        $room = $request->integer('section_id');
        abort_if(array_diff(array_keys($request->query()), ['section_id']), 422);
        if ($room && $actor->role === 'docente') {
            abort_unless(DB::table('teaching_assignments')->where('teacher_id', $actor->id)->where('section_id', $room)->exists(), 403);
        }
        if ($room && $actor->role === 'estudiante') {
            abort_unless(DB::table('student_enrollments')->where('student_id', $actor->id)->where('section_id', $room)->where('state', 'active')->whereIn('academic_period_id', DB::table('academic_periods')->where('state', 'active')->pluck('id'))->exists(), 403);
        }
        $scope = DB::table('teaching_assignments');
        if ($actor->role === 'docente') {
            $scope->where('teacher_id', $actor->id);
        } elseif ($actor->role === 'estudiante') {
            $enro = DB::table('student_enrollments')->where('student_id', $actor->id)->where('state', 'active')->whereIn('academic_period_id', DB::table('academic_periods')->where('state', 'active')->pluck('id'))->first();
            $scope->where('academic_period_id', $enro?->academic_period_id ?? 0)->where('grade_id', $enro?->grade_id ?? 0)->where('section_id', $enro?->section_id ?? 0);
        } elseif ($actor->role === 'subdirector') {
            $scope->whereRaw('1=0');
        } elseif ($actor->role === 'director') {
            $scope->where('section_id', $room ?: 0);
        }
        if ($room) {
            $scope->where('section_id', $room);
        }$assignmentIds = $scope->pluck('id');
        $materials = DB::table('educational_materials as m')->join('teaching_assignments as a', 'a.id', '=', 'm.teaching_assignment_id')->join('instructional_entries as i', 'i.id', '=', 'a.instructional_entry_id')->join('users as u', 'u.id', '=', 'a.teacher_id')->whereIn('a.id', $assignmentIds)->whereNull('m.deleted_at')->select('m.id', 'm.teaching_assignment_id', 'm.title', 'm.body', 'm.file_name', 'm.created_at', 'm.updated_at', 'i.name as course', 'u.name as teacher')->orderByDesc('m.id')->get();
        $deliveries = DB::table('submission_references as s')->join('activity_references as t', 't.id', '=', 's.activity_id')->join('teaching_assignments as a', 'a.id', '=', 's.teaching_assignment_id')->join('users as pupil', 'pupil.id', '=', 's.student_id')->join('instructional_entries as i', 'i.id', '=', 'a.instructional_entry_id')->leftJoin('submission_assessments as g', 'g.submission_id', '=', 's.id')->leftJoin('submission_files as f', 'f.submission_id', '=', 's.id')->select('s.*', 't.title', 'i.name as course', 'pupil.name as student', 'g.grade', 'g.feedback', 'g.updated_at as graded_at', 'f.file_name');
        if ($actor->role === 'estudiante') {
            $deliveries->where('s.student_id', $actor->id);
        } else {
            $deliveries->whereIn('s.teaching_assignment_id', $assignmentIds);
        }
        $rows = $deliveries->orderByDesc('s.id')->get();
        $roster = [];
        if ($room && in_array($actor->role, ['director', 'docente'], true) && (clone $scope)->where('state', 'active')->whereIn('academic_period_id', DB::table('academic_periods')->where('state', 'active')->pluck('id'))->exists()) {
            $roster = DB::table('student_enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->where('e.section_id', $room)->where('e.state', 'active')->whereIn('e.academic_period_id', DB::table('academic_periods')->where('state', 'active')->pluck('id'))->select('u.id', 'u.name', 'u.email')->get();
        }
        $notifications = DB::table('education_notifications')->where('user_id', $actor->id)->orderByDesc('id')->limit(100)->get();
        $unread = DB::table('education_notifications')->where('user_id', $actor->id)->where('is_read', false)->count();

        return response()->json($this->ids(['materials' => $materials, 'deliveries' => $rows, 'roster' => $roster, 'notifications' => $notifications, 'unread' => $unread]));
    }

    private function ids(mixed $data, ?string $key = null): mixed
    {
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->ids($v, (string) $k);
            }

            return $data;
        }

        return $data !== null && $key !== null && ($key === 'id' || str_ends_with($key, '_id') || str_ends_with($key, '_key')) ? (string) $data : $data;
    }

    public function command(Request $request, string $action, EducationService $service): JsonResponse
    {
        $actor = $this->fresh($request);
        $schemas = [
            'publish_material' => ['assignment_id' => 'required|integer|min:1', 'title' => 'required|string|max:200', 'body' => 'nullable|string|max:10000|required_without:attachment', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,txt,docx|max:10240'],
            'edit_material' => ['id' => 'required|integer|min:1', 'title' => 'required|string|max:200', 'body' => 'present|string|max:10000'],
            'delete_material' => ['id' => 'required|integer|min:1'],
            'submit' => ['activity_id' => 'required|integer|min:1', 'answer' => 'nullable|string|max:10000|required_without:attachment', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,txt,docx|max:10240'],
            'assess' => ['submission_id' => 'required|integer|min:1', 'grade' => 'required|in:AD,A,B,C', 'feedback' => 'required|string|max:5000'],
            'create_user' => ['name' => 'required|string|max:120', 'email' => 'required|email|max:200|unique:users,email', 'password' => 'required|string|min:10|max:200', 'role' => 'required|in:director,subdirector,docente,estudiante', 'academic_period_id' => 'required_if:role,estudiante|nullable|integer|min:1', 'grade_id' => 'required_if:role,estudiante|nullable|integer|min:1', 'section_id' => 'required_if:role,estudiante|nullable|integer|min:1'],
            'update_user' => ['id' => 'required|integer|min:1', 'name' => 'required|string|max:120', 'email' => 'required|email|max:200', 'is_active' => 'required|boolean', 'password' => 'nullable|string|min:10|max:200'],
            'change_password' => ['current_password' => 'required|string', 'password' => 'required|string|min:10|max:200'],
            'read_notifications' => [],
            'close_activity' => ['id' => 'required|integer|min:1'],
        ];
        abort_unless(isset($schemas[$action]), 404);
        if (array_diff(array_keys($request->all()), array_keys($schemas[$action]))) {
            return response()->json(['category' => 'unexpected_fields', 'message' => 'El formulario contiene campos no permitidos.'], 422);
        }
        $data = $request->validate($schemas[$action]);
        $path = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('education', 'local');
            $data['file_path'] = $path;
            $data['file_name'] = mb_substr(basename($file->getClientOriginalName()), 0, 200);
            $data['file_mime'] = $file->getMimeType();
            unset($data['attachment']);
        }
        if ($action === 'change_password') {
            $data['session_id'] = $request->session()->getId();
        }
        try {
            $id = $service->execute($actor->id, $action, $data);
            if ($action === 'change_password') {
                $request->session()->regenerate();
            }

            return response()->json(['id' => (string) $id], 201);
        } catch (AcademicError $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json(['category' => $error->category, 'message' => $error->getMessage()], $error->status);
        } catch (QueryException $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }if (in_array($error->errorInfo[0] ?? '', ['23000', '45000'])) {
                return response()->json(['category' => 'conflict', 'message' => 'No se pudo guardar; revisa los datos o los registros duplicados.'], 409);
            }throw $error;
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $error;
        }
    }

    public function download(Request $request, string $kind, int $id): StreamedResponse
    {
        $actor = $this->fresh($request);
        if ($kind === 'material') {
            $record = DB::table('educational_materials')->where('id', $id)->whereNull('deleted_at')->first();
            abort_unless($record, 404);
            $assignment = DB::table('teaching_assignments')->find($record->teaching_assignment_id);
            abort_unless($assignment && $this->canRead($actor, $assignment), 403);
        } else {
            $submission = DB::table('submission_references')->find($id);
            abort_unless($submission, 404);
            $assignment = DB::table('teaching_assignments')->find($submission->teaching_assignment_id);
            abort_unless($actor->role === 'director' || ($actor->role === 'estudiante' && $submission->student_id === $actor->id) || ($actor->role === 'docente' && $assignment->teacher_id === $actor->id), 403);
            $record = DB::table('submission_files')->where('submission_id', $id)->first();
        }
        abort_unless($record && $record->file_path && Storage::disk('local')->exists($record->file_path), 404);

        return Storage::disk('local')->download($record->file_path, $record->file_name, ['Content-Type' => $record->file_mime, 'X-Content-Type-Options' => 'nosniff']);
    }
}
