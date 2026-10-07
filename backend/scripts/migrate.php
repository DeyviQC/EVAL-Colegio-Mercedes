<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

$database = require __DIR__.'/database.php';
if (getenv('EVAL_DB_ROLE') !== 'migration') {
    throw new RuntimeException('Migration credentials must be explicitly selected.');
}
$path = dirname(__DIR__).'/database/migrations';
$file = $path.'/2026_10_07_000001_create_academic_write_guard.php';
$files = [$file];
if (in_array(getenv('EVAL_UNIT'), ['U2','U3'],true)) {
    foreach (['000002_create_academic_periods_and_catalog', '000003_create_student_enrollments', '000004_create_teaching_assignments'] as $name) {
        $files[] = $path.'/2026_10_07_'.$name.'.php';
    }
}
if (getenv('EVAL_UNIT')==='U3') { $files[]=$path.'/2026_10_07_000005_create_academic_lifecycle_events.php'; }
$freshFlags=array_values(array_intersect($argv,['--fresh-u1','--fresh-u2','--fresh-u3']));
if (count($freshFlags)>1) { throw new RuntimeException('Select exactly one disposable unit rebuild.'); }
if ($freshFlags) {
    $freshUnit=strtoupper(substr($freshFlags[0],8));
    $expectedName='eval_'.strtolower($freshUnit).'_test';
    if ((getenv('EVAL_UNIT') ?: 'U1') !== $freshUnit || getenv('EVAL_DB_NAME') !== $expectedName || getenv('EVAL_DB_HOST') !== '127.0.0.1') {
        throw new RuntimeException('Fresh rebuild requires the matching isolated disposable unit database.');
    }
    $database->getConnection()->statement('DROP DATABASE `'.$expectedName.'`');
    $database->getConnection()->statement('CREATE DATABASE `'.$expectedName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs');
    $database->getDatabaseManager()->purge();
}
if (!is_file($file)) { throw new RuntimeException('Authorized U1 migration missing.'); }
$repository = new DatabaseMigrationRepository($database->getDatabaseManager(), 'migrations');
if (!$repository->repositoryExists()) {
    $repository->createRepository();
}
$migrator = new Migrator($repository, $database->getDatabaseManager(), new Filesystem, $database->getEventDispatcher());
$migrator->run($files);
echo 'Only '.(getenv('EVAL_UNIT') ?: 'U1').' migration allowlist executed.'.PHP_EOL;
