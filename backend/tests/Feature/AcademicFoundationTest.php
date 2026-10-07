<?php

namespace Tests\Feature;

use App\Academic\AcademicError;
use App\Academic\AcademicService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private AcademicService $service;

    private User $director;

    private User $vice;

    private User $teacher;

    private User $replacement;

    private User $student;

    private array $scope;

    private int $otherSection;

    private int $entry;

    private int $enrollment;

    private int $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_testing', DB::getDatabaseName());
        $this->service = app(AcademicService::class);
        $this->director = User::factory()->create(['role' => 'director']);
        $this->vice = User::factory()->create(['role' => 'subdirector']);
        $this->teacher = User::factory()->create(['role' => 'docente']);
        $this->replacement = User::factory()->create(['role' => 'docente']);
        $this->student = User::factory()->create(['role' => 'estudiante']);
        $period = $this->write('create_period', ['name' => 'School year', 'start_on' => now()->startOfYear()->toDateString(), 'end_on' => now()->endOfYear()->toDateString()]);
        $this->write('activate_period', ['id' => $period]);
        $grade = $this->write('create_catalog', ['type' => 'grade', 'name' => 'Grade 3']);
        $section = $this->write('create_catalog', ['type' => 'section', 'name' => 'A', 'grade_id' => $grade]);
        $this->otherSection = $this->write('create_catalog', ['type' => 'section', 'name' => 'B', 'grade_id' => $grade]);
        $this->entry = $this->write('create_catalog', ['type' => 'entry', 'name' => 'Mathematics', 'classification' => 'area']);
        $this->scope = ['academic_period_id' => $period, 'grade_id' => $grade, 'section_id' => $section];
        $this->enrollment = $this->write('enroll', $this->scope + ['student_id' => $this->student->id]);
        $this->assignment = $this->write('create_assignment', $this->scope + ['teacher_id' => $this->teacher->id, 'instructional_entry_id' => $this->entry]);
        $this->write('activate_assignment', ['id' => $this->assignment]);
    }

    private function write(string $command, array $input): int
    {
        return $this->service->execute($this->director->id, $command, $input);
    }

    private function activity(): int
    {
        return $this->service->execute($this->teacher->id, 'create_activity', ['assignment_id' => $this->assignment, 'title' => 'Activity', 'description' => 'Instructions']);
    }

    public function test_one_current_period_and_failed_operation_rolls_back_ordinal_and_event(): void
    {
        $next = $this->write('create_period', ['name' => 'Next year', 'start_on' => '2027-01-01', 'end_on' => '2027-12-31']);
        $ordinal = DB::table('academic_write_guard')->value('next_ordinal');
        $events = DB::table('academic_lifecycle_events')->count();
        try {
            $this->write('activate_period', ['id' => $next]);
            $this->fail('Second period activated');
        } catch (AcademicError $error) {
            $this->assertSame('conflict', $error->category);
        }
        $this->assertSame($ordinal, DB::table('academic_write_guard')->value('next_ordinal'));
        $this->assertSame($events, DB::table('academic_lifecycle_events')->count());
    }

    public function test_same_day_transfer_retains_history_and_does_not_allow_old_scope(): void
    {
        $activity = $this->activity();
        $submission = $this->service->execute($this->student->id, 'accept_submission', ['activity_id' => $activity, 'answer' => 'Before transfer']);
        $successor = $this->write('transfer', ['id' => $this->enrollment, 'grade_id' => $this->scope['grade_id'], 'section_id' => $this->otherSection]);
        $prior = DB::table('student_enrollments')->find($this->enrollment);
        $next = DB::table('student_enrollments')->find($successor);
        $this->assertSame('transferred', $prior->state);
        $this->assertSame($prior->operational_end_key, $next->operational_start_key);
        $this->assertLessThan($prior->operational_end_key, $prior->operational_start_key);
        $this->assertSame($this->enrollment, DB::table('submission_references')->find($submission)->accepted_under_enrollment_id);
        $this->actingAs($this->student)->getJson('/api/academic')->assertOk()->assertJsonFragment(['accepted_under_enrollment_id' => (string) $this->enrollment]);
        $this->expectException(AcademicError::class);
        $this->service->execute($this->student->id, 'accept_submission', ['activity_id' => $this->activity(), 'answer' => 'Wrong room']);
    }

    public function test_replacement_preserves_original_route_and_distinct_assignment(): void
    {
        $activity = $this->activity();
        $successor = $this->write('replace_teacher', ['id' => $this->assignment, 'teacher_id' => $this->replacement->id]);
        $this->assertNotSame($successor, $this->assignment);
        $this->assertSame('closed', DB::table('teaching_assignments')->find($this->assignment)->state);
        $this->assertSame($this->teacher->id, DB::table('teaching_assignments')->find($this->assignment)->teacher_id);
        $submission = $this->service->execute($this->student->id, 'accept_submission', ['activity_id' => $activity, 'answer' => 'Routed after replacement']);
        $this->assertSame($this->assignment, DB::table('submission_references')->find($submission)->teaching_assignment_id);
        $this->actingAs($this->replacement)->getJson('/api/academic')->assertOk()->assertJsonCount(0, 'submissions');
        $this->actingAs($this->teacher)->getJson('/api/academic')->assertOk()->assertJsonFragment(['id' => (string) $submission, 'activity_id' => (string) $activity]);
    }

    public function test_replacement_conflict_rolls_back_prior_closure(): void
    {
        $other = $this->write('create_assignment', $this->scope + ['teacher_id' => $this->replacement->id, 'instructional_entry_id' => $this->entry]);
        $this->write('activate_assignment', ['id' => $other]);
        $events = DB::table('academic_lifecycle_events')->count();
        try {
            $this->write('replace_teacher', ['id' => $this->assignment, 'teacher_id' => $this->replacement->id]);
            $this->fail('Conflict accepted');
        } catch (AcademicError $error) {
            $this->assertSame('conflict', $error->category);
        }
        $this->assertSame('active', DB::table('teaching_assignments')->find($this->assignment)->state);
        $this->assertSame($events, DB::table('academic_lifecycle_events')->count());
    }

    public function test_vice_principal_can_manage_assignments_but_not_enrollments_or_deferred_work(): void
    {
        $this->service->execute($this->vice->id, 'close_assignment', ['id' => $this->assignment]);
        $this->actingAs($this->vice)->postJson('/api/academic/enroll', $this->scope + ['student_id' => $this->student->id])->assertForbidden();
        $this->actingAs($this->vice)->getJson('/api/academic')->assertOk()->assertJsonCount(0, 'enrollments')->assertJsonCount(0, 'activities')->assertJsonCount(0, 'submissions')->assertJsonCount(0, 'people')->assertJsonCount(0, 'periods');
        $this->actingAs($this->vice)->getJson('/api/academic?section_id='.$this->scope['section_id'])->assertOk()->assertJsonCount(1, 'sections')->assertJsonCount(0, 'enrollments');
    }

    public function test_closed_assignment_cannot_create_new_work_and_recipient_injection_is_rejected(): void
    {
        $activity = $this->activity();
        $this->write('close_assignment', ['id' => $this->assignment]);
        $this->actingAs($this->teacher)->postJson('/api/academic/create_activity', ['assignment_id' => $this->assignment, 'title' => 'Denied', 'description' => 'Closed'])->assertUnprocessable();
        $this->actingAs($this->student)->postJson('/api/academic/accept_submission', ['activity_id' => $activity, 'answer' => 'Work', 'recipient_id' => $this->replacement->id])->assertUnprocessable()->assertJsonPath('category', 'unexpected_fields');
    }

    public function test_database_rejects_route_mutation(): void
    {
        $activity = $this->activity();
        $other = $this->write('create_assignment', $this->scope + ['teacher_id' => $this->replacement->id, 'instructional_entry_id' => $this->entry]);
        $this->expectException(QueryException::class);
        DB::table('activity_references')->where('id', $activity)->update(['teaching_assignment_id' => $other]);
    }

    public function test_inactive_catalog_and_mismatched_section_are_denied(): void
    {
        $otherGrade = $this->write('create_catalog', ['type' => 'grade', 'name' => 'Grade 4']);
        $this->actingAs($this->director)->postJson('/api/academic/create_assignment', ['grade_id' => $otherGrade] + $this->scope + ['teacher_id' => $this->teacher->id, 'instructional_entry_id' => $this->entry])->assertUnprocessable()->assertJsonPath('category', 'scope_mismatch');
        $this->write('update_catalog', ['type' => 'entry', 'id' => $this->entry, 'name' => 'Mathematics', 'is_active' => false]);
        $this->actingAs($this->director)->postJson('/api/academic/create_assignment', $this->scope + ['teacher_id' => $this->replacement->id, 'instructional_entry_id' => $this->entry])->assertUnprocessable();
    }

    public function test_closing_period_does_not_cascade_children(): void
    {
        $this->write('close_period', ['id' => $this->scope['academic_period_id']]);
        $this->assertSame('active', DB::table('student_enrollments')->find($this->enrollment)->state);
        $this->assertSame('active', DB::table('teaching_assignments')->find($this->assignment)->state);
        $this->actingAs($this->teacher)->postJson('/api/academic/create_activity', ['assignment_id' => $this->assignment, 'title' => 'Pending decision', 'description' => 'Closed period'])->assertStatus(409)->assertJsonPath('category', 'decision_required');
    }

    public function test_large_operational_keys_are_transported_as_strings(): void
    {
        $key = '9007199254740993';
        DB::table('academic_write_guard')->where('id', 1)->update(['next_ordinal' => $key]);
        $successor = $this->write('replace_teacher', ['id' => $this->assignment, 'teacher_id' => $this->replacement->id]);
        $this->actingAs($this->replacement)->getJson('/api/academic')->assertOk()->assertJsonFragment(['id' => (string) $successor, 'operational_start_key' => $key]);
    }

    public function test_closed_assignment_cannot_be_reopened_through_direct_sql(): void
    {
        $this->write('close_assignment', ['id' => $this->assignment]);
        $this->expectException(QueryException::class);
        DB::table('teaching_assignments')->where('id', $this->assignment)->update(['state' => 'active', 'effective_until' => null, 'operational_end_key' => null]);
    }

    public function test_server_denies_tampered_read_scope(): void
    {
        $this->actingAs($this->student)->getJson('/api/academic?section_id='.$this->otherSection)->assertForbidden();
        $this->actingAs($this->student)->getJson('/api/academic?grade_id=999')->assertUnprocessable();
    }
}
