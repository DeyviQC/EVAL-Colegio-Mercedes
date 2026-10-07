<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicConcurrencyTest extends TestCase
{
    public function test_two_mysql_writers_serialize_and_duplicate_creation_commits_only_once(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_testing', DB::getDatabaseName());
        $actor = User::factory()->create(['role' => 'director']);
        $name = 'Concurrent period '.Str::uuid();
        $ordinal = DB::table('academic_write_guard')->value('next_ordinal');
        $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => DB::getDatabaseName(), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
        $processes = [];
        DB::beginTransaction();
        DB::table('academic_write_guard')->where('id', 1)->lockForUpdate()->first();
        try {
            for ($index = 0; $index < 2; $index++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('artisan'), 'academic:test-worker', (string) $actor->id, $name], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $processes[] = ['process' => $process, 'pipes' => $pipes];
            }
            $blocked = false;
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline) {
                $waiting = array_filter(DB::select('SHOW PROCESSLIST'), fn ($row) => str_contains(strtolower($row->Info ?? ''), 'academic_write_guard') && str_contains(strtolower($row->Info ?? ''), 'for update'));
                if (count($waiting) === 2) {
                    $blocked = true;
                    break;
                }
                usleep(50000);
            }
            $this->assertTrue($blocked, 'Both independent MySQL connections reached the exclusive guard barrier.');
            DB::commit();
            $output = '';
            foreach ($processes as $entry) {
                stream_set_blocking($entry['pipes'][1], true);
                $output .= stream_get_contents($entry['pipes'][1]);
                $errors = stream_get_contents($entry['pipes'][2]);
                fclose($entry['pipes'][1]);
                fclose($entry['pipes'][2]);
                $this->assertSame(0, proc_close($entry['process']), $errors);
            }
            $processes = [];
            $this->assertSame(1, substr_count($output, 'committed'));
            $this->assertSame(1, substr_count($output, 'conflict'));
            $this->assertSame(1, DB::table('academic_periods')->where('name', $name)->count());
            $this->assertSame((int) $ordinal + 1, (int) DB::table('academic_write_guard')->value('next_ordinal'));
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $entry) {
                proc_terminate($entry['process']);
                foreach ($entry['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }proc_close($entry['process']);
            }
        }
    }
}
