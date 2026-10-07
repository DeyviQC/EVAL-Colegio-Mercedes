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
if (!is_dir($path) || !glob($path.'/*.php')) {
    fwrite(STDERR, 'No authorized migrations exist; no schema was created.'.PHP_EOL);
    exit(1);
}
$repository = new DatabaseMigrationRepository($database->getDatabaseManager(), 'migrations');
if (!$repository->repositoryExists()) {
    $repository->createRepository();
}
$migrator = new Migrator($repository, $database->getDatabaseManager(), new Filesystem, $database->getEventDispatcher());
$migrator->run([$path]);
echo 'Authorized migrations executed.'.PHP_EOL;
