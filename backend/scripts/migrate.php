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
if (in_array('--fresh-u1', $argv, true)) {
    if (getenv('EVAL_DB_NAME') !== 'eval_u1_test' || getenv('EVAL_DB_HOST') !== '127.0.0.1') {
        throw new RuntimeException('Fresh U1 requires the isolated disposable database.');
    }
    $database->getConnection()->statement('DROP DATABASE `eval_u1_test`');
    $database->getConnection()->statement('CREATE DATABASE `eval_u1_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs');
    $database->getDatabaseManager()->purge();
}
if (!is_file($file)) { throw new RuntimeException('Authorized U1 migration missing.'); }
$repository = new DatabaseMigrationRepository($database->getDatabaseManager(), 'migrations');
if (!$repository->repositoryExists()) {
    $repository->createRepository();
}
$migrator = new Migrator($repository, $database->getDatabaseManager(), new Filesystem, $database->getEventDispatcher());
$migrator->run([$file]);
echo 'Only U1 migration executed.'.PHP_EOL;
