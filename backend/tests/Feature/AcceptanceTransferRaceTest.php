<?php

namespace Tests\Feature;

use App\Academic\AcademicService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcceptanceTransferRaceTest extends TestCase
{
    public function test_acceptance_first_retains_original_enrollment_after_transfer(): void
    {
        $this->race(true);
    }

    public function test_transfer_first_denies_old_scope_acceptance(): void
    {
        $this->race(false);
    }

    private function race(bool $acceptanceFirst): void
    {
        $this->assertStringEndsWith('_testing', DB::getDatabaseName());
        $service = app(AcademicService::class);
        $director = User::factory()->create(['role' => 'director']);
        $student = User::factory()->create(['role' => 'estudiante']);
        $teacher = User::factory()->create(['role' => 'docente']);
        $suffix = (string) Str::uuid();
        $period = $service->execute($director->id, 'create_period', ['name' => 'Race '.$suffix, 'start_on' => now()->startOfYear()->toDateString(), 'end_on' => now()->endOfYear()->toDateString()]);
        $service->execute($director->id, 'activate_period', ['id' => $period]);
        $grade = $service->execute($director->id, 'create_catalog', ['type' => 'grade', 'name' => 'Race grade '.$suffix]);
        $oldSection = $service->execute($director->id, 'create_catalog', ['type' => 'section', 'name' => 'A', 'grade_id' => $grade]);
        $newSection = $service->execute($director->id, 'create_catalog', ['type' => 'section', 'name' => 'B', 'grade_id' => $grade]);
        $entry = $service->execute($director->id, 'create_catalog', ['type' => 'entry', 'name' => 'Race area '.$suffix, 'classification' => 'area']);
        $scope = ['academic_period_id' => $period, 'grade_id' => $grade, 'section_id' => $oldSection];
        $enrollment = $service->execute($director->id, 'enroll', $scope + ['student_id' => $student->id]);
        $assignment = $service->execute($director->id, 'create_assignment', $scope + ['teacher_id' => $teacher->id, 'instructional_entry_id' => $entry]);
        $service->execute($director->id, 'activate_assignment', ['id' => $assignment]);
        $activity = $service->execute($teacher->id, 'create_activity', ['assignment_id' => $assignment, 'title' => 'Race activity', 'description' => 'Reference']);
        $accept = ['activity_id' => $activity, 'answer' => 'Original scope'];
        $transfer = ['id' => $enrollment, 'grade_id' => $grade, 'section_id' => $newSection];
        $process = null;
        $pipes = [];
        try {
            DB::beginTransaction();
            $service->execute($acceptanceFirst ? $student->id : $director->id, $acceptanceFirst ? 'accept_submission' : 'transfer', $acceptanceFirst ? $accept : $transfer);
            $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => DB::getDatabaseName(), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
            $process = proc_open([PHP_BINARY, base_path('artisan'), 'academic:test-worker', (string) ($acceptanceFirst ? $director->id : $student->id), 'race', $acceptanceFirst ? 'transfer' : 'accept_submission', base64_encode(json_encode($acceptanceFirst ? $transfer : $accept))], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
            fclose($pipes[0]);
            $blocked = false;
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline) {
                if (array_filter(DB::select('SHOW PROCESSLIST'), fn ($row) => str_contains(strtolower($row->Info ?? ''), 'academic_write_guard') && str_contains(strtolower($row->Info ?? ''), 'for update'))) {
                    $blocked = true;
                    break;
                }
                usleep(50000);
            }
            $this->assertTrue($blocked, 'Competing acceptance/transfer reached the guard barrier.');
            DB::commit();
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors);
            $process = null;
            $submissions = DB::table('submission_references')->where('activity_id', $activity)->get();
            if ($acceptanceFirst) {
                $this->assertStringContainsString('committed', $output);
                $this->assertCount(1, $submissions);
                $this->assertSame($enrollment, $submissions[0]->accepted_under_enrollment_id);
                $this->assertSame($assignment, $submissions[0]->teaching_assignment_id);
            } else {
                $this->assertStringContainsString('domain:scope_mismatch', $output);
                $this->assertCount(0, $submissions);
            }
            $this->assertSame('transferred', DB::table('student_enrollments')->find($enrollment)->state);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }proc_close($process);
            }
            $service->execute($director->id, 'close_period', ['id' => $period]);
        }
    }
}
