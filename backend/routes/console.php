<?php

use App\Academic\AcademicError;
use App\Academic\AcademicService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('academic:test-worker {actor} {name} {operation=create_period} {input?}', function () {
    if (! app()->environment('testing') || ! str_ends_with(DB::getDatabaseName(), '_testing')) {
        throw new RuntimeException('This worker is restricted to isolated testing databases.');
    }
    $this->line('ready');
    flush();
    $input = $this->argument('input') ? json_decode(base64_decode($this->argument('input')), true, flags: JSON_THROW_ON_ERROR) : ['name' => $this->argument('name'), 'start_on' => '2026-01-01', 'end_on' => '2026-12-31'];
    try {
        app(AcademicService::class)->execute((int) $this->argument('actor'), $this->argument('operation'), $input);
        $this->line('committed');
    } catch (QueryException $error) {
        $this->line('conflict');
    } catch (AcademicError $error) {
        $this->line('domain:'.$error->category);
    }
})->purpose('Run one deterministic academic concurrency test writer');
