<?php

namespace Tests\Feature;

use App\Academic\AcademicService;
use App\Education\EducationService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EducationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $director;

    private User $vice;

    private User $teacher;

    private User $other;

    private User $student;

    private User $outside;

    private array $scope;

    private int $roomB;

    private int $assignment;

    private int $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_testing', DB::getDatabaseName());
        Storage::fake('local');
        $this->director = User::factory()->create(['role' => 'director']);
        $this->vice = User::factory()->create(['role' => 'subdirector']);
        $this->teacher = User::factory()->create(['role' => 'docente']);
        $this->other = User::factory()->create(['role' => 'docente']);
        $this->student = User::factory()->create(['role' => 'estudiante']);
        $this->outside = User::factory()->create(['role' => 'estudiante']);
        $service = app(AcademicService::class);
        $actor = $this->director->id;
        $period = $service->execute($actor, 'create_period', ['name' => 'Education '.Str::uuid(), 'start_on' => now()->startOfYear()->toDateString(), 'end_on' => now()->endOfYear()->toDateString()]);
        $service->execute($actor, 'activate_period', ['id' => $period]);
        $grade = $service->execute($actor, 'create_catalog', ['type' => 'grade', 'name' => 'Education grade '.Str::uuid()]);
        $room = $service->execute($actor, 'create_catalog', ['type' => 'section', 'name' => 'A', 'grade_id' => $grade]);
        $this->roomB = $service->execute($actor, 'create_catalog', ['type' => 'section', 'name' => 'B', 'grade_id' => $grade]);
        $entry = $service->execute($actor, 'create_catalog', ['type' => 'entry', 'name' => 'Education area '.Str::uuid(), 'classification' => 'area']);
        $this->scope = ['academic_period_id' => $period, 'grade_id' => $grade, 'section_id' => $room];
        $service->execute($actor, 'enroll', $this->scope + ['student_id' => $this->student->id]);
        $service->execute($actor, 'enroll', ['section_id' => $this->roomB] + $this->scope + ['student_id' => $this->outside->id]);
        $this->assignment = $service->execute($actor, 'create_assignment', $this->scope + ['teacher_id' => $this->teacher->id, 'instructional_entry_id' => $entry]);
        $service->execute($actor, 'activate_assignment', ['id' => $this->assignment]);
        $this->activity = $service->execute($this->teacher->id, 'create_activity', ['assignment_id' => $this->assignment, 'title' => 'Connected task', 'description' => 'Answer this task']);
    }

    private function submit(): int
    {
        return app(EducationService::class)->execute($this->student->id, 'submit', ['activity_id' => $this->activity, 'answer' => 'My answer']);
    }

    public function test_material_is_immediately_visible_only_to_its_class_and_director_can_remove_it(): void
    {
        $r = $this->actingAs($this->teacher)->post('/api/education/publish_material', ['assignment_id' => $this->assignment, 'title' => 'Class guide', 'body' => 'Instructions', 'attachment' => UploadedFile::fake()->createWithContent('guide.txt', 'Learning material')], ['Accept' => 'application/json'])->assertCreated();
        $id = $r->json('id');
        $this->actingAs($this->student)->getJson('/api/education')->assertOk()->assertJsonFragment(['title' => 'Class guide']);
        $this->get('/api/education/files/material/'.$id)->assertDownload('guide.txt');
        $this->actingAs($this->outside)->getJson('/api/education')->assertOk()->assertJsonCount(0, 'materials');
        $this->getJson('/api/education/files/material/'.$id)->assertForbidden();
        $this->actingAs($this->vice)->postJson('/api/education/delete_material', ['id' => $id])->assertForbidden();
        $this->actingAs($this->director)->postJson('/api/education/delete_material', ['id' => $id])->assertCreated();
        $this->actingAs($this->student)->getJson('/api/education/files/material/'.$id)->assertNotFound();
    }

    public function test_delivery_grade_and_feedback_are_shared_with_original_student(): void
    {
        $id = $this->submit();
        $this->actingAs($this->other)->postJson('/api/education/assess', ['submission_id' => $id, 'grade' => 'AD', 'feedback' => 'Not owner'])->assertForbidden();
        $this->actingAs($this->teacher)->postJson('/api/education/assess', ['submission_id' => $id, 'grade' => 'AD', 'feedback' => 'Excellent reasoning'])->assertCreated();
        $this->actingAs($this->student)->getJson('/api/education')->assertOk()->assertJsonFragment(['grade' => 'AD', 'feedback' => 'Excellent reasoning']);
        $this->postJson('/api/education/submit', ['activity_id' => $this->activity, 'answer' => 'Changed after grading'])->assertStatus(409)->assertJsonPath('category', 'already_assessed');
        $this->actingAs($this->outside)->getJson('/api/education')->assertJsonCount(0, 'deliveries');
    }

    public function test_ungraded_revision_preserves_acceptance_identity_and_route(): void
    {
        $id = $this->submit();
        $prior = DB::table('submission_references')->find($id);
        $this->actingAs($this->student)->postJson('/api/education/submit', ['activity_id' => $this->activity, 'answer' => 'Revised answer'])->assertCreated()->assertJsonPath('id', (string) $id);
        $after = DB::table('submission_references')->find($id);
        $this->assertSame('Revised answer', $after->answer);
        $this->assertSame($prior->acceptance_operation_key, $after->acceptance_operation_key);
        $this->assertSame($prior->accepted_under_enrollment_id, $after->accepted_under_enrollment_id);
        $this->assertSame($prior->teaching_assignment_id, $after->teaching_assignment_id);
    }

    public function test_original_teacher_can_grade_existing_work_after_replacement_and_history_remains_owned(): void
    {
        $id = $this->submit();
        app(AcademicService::class)->execute($this->director->id, 'replace_teacher', ['id' => $this->assignment, 'teacher_id' => $this->other->id]);
        $this->actingAs($this->other)->postJson('/api/education/assess', ['submission_id' => $id, 'grade' => 'A', 'feedback' => 'Replacement cannot inherit'])->assertForbidden();
        $this->actingAs($this->teacher)->postJson('/api/education/assess', ['submission_id' => $id, 'grade' => 'A', 'feedback' => 'Original route'])->assertCreated();
        $enrollment = DB::table('student_enrollments')->where('student_id', $this->student->id)->where('state', 'active')->first();
        app(AcademicService::class)->execute($this->director->id, 'transfer', ['id' => $enrollment->id, 'grade_id' => $this->scope['grade_id'], 'section_id' => $this->roomB]);
        $this->actingAs($this->student)->getJson('/api/education')->assertJsonFragment(['grade' => 'A', 'feedback' => 'Original route']);
    }

    public function test_new_accounts_authenticate_and_disabled_accounts_do_not(): void
    {
        $email = 'created-'.Str::uuid().'@eval.test';
        $r = $this->actingAs($this->director)->postJson('/api/education/create_user', ['name' => 'New student', 'email' => $email, 'password' => 'Password123!', 'role' => 'estudiante'] + $this->scope)->assertCreated();
        $id = (int) $r->json('id');
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $id, 'section_id' => $this->scope['section_id'], 'state' => 'active']);
        $this->postJson('/api/login', ['email' => $email, 'password' => 'Password123!'])->assertOk();
        $this->actingAs($this->director)->postJson('/api/education/update_user', ['id' => $id, 'name' => 'New student', 'email' => $email, 'is_active' => false])->assertCreated();
        $this->postJson('/api/login', ['email' => $email, 'password' => 'Password123!'])->assertUnauthorized();
    }

    public function test_file_only_delivery_download_is_private(): void
    {
        $r = $this->actingAs($this->student)->post('/api/education/submit', ['activity_id' => $this->activity, 'attachment' => UploadedFile::fake()->createWithContent('answer.txt', 'Written answer')], ['Accept' => 'application/json'])->assertCreated();
        $id = $r->json('id');
        $this->actingAs($this->teacher)->get('/api/education/files/submission/'.$id)->assertDownload('answer.txt');
        $this->actingAs($this->outside)->getJson('/api/education/files/submission/'.$id)->assertForbidden();
    }

    public function test_notifications_are_private_and_can_be_acknowledged(): void
    {
        $id = $this->submit();
        $this->actingAs($this->teacher)->getJson('/api/education')->assertJsonFragment(['message' => $this->student->name.' entregó: Connected task']);
        $this->actingAs($this->outside)->getJson('/api/education')->assertJsonCount(0, 'notifications');
        $this->actingAs($this->teacher)->postJson('/api/education/read_notifications', [])->assertCreated();
        $this->getJson('/api/education')->assertJsonPath('unread', 0);
    }

    public function test_password_changes_require_current_secret_and_closed_period_is_read_only(): void
    {
        $this->actingAs($this->student)->postJson('/api/education/change_password', ['current_password' => 'wrong', 'password' => 'NewPassword123!'])->assertUnprocessable();
        $password = DB::table('users')->find($this->student->id)->password;
        DB::table('users')->where('id', $this->student->id)->update(['password' => Hash::make('OldPassword123!')]);
        $this->postJson('/api/education/change_password', ['current_password' => 'OldPassword123!', 'password' => 'NewPassword123!'])->assertCreated();
        $this->assertTrue(Hash::check('NewPassword123!', DB::table('users')->find($this->student->id)->password));
        $id = $this->submit();
        app(AcademicService::class)->execute($this->director->id, 'close_period', ['id' => $this->scope['academic_period_id']]);
        $this->actingAs($this->teacher)->postJson('/api/education/assess', ['submission_id' => $id, 'grade' => 'A', 'feedback' => 'Closed'])->assertStatus(409)->assertJsonPath('category', 'read_only_period');
    }

    public function test_explicit_activity_closure_blocks_all_submission_entry_points(): void
    {
        $this->actingAs($this->teacher)->postJson('/api/education/close_activity', ['id' => $this->activity])->assertCreated();
        $this->actingAs($this->student)->postJson('/api/education/submit', ['activity_id' => $this->activity, 'answer' => 'Too late'])->assertStatus(409)->assertJsonPath('category', 'activity_closed');
        $this->postJson('/api/academic/accept_submission', ['activity_id' => $this->activity, 'answer' => 'Alternate endpoint'])->assertStatus(409)->assertJsonPath('category', 'activity_closed');
    }
}
