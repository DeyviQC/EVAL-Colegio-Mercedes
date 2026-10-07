<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/bootstrap.php';

use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;

if (getenv('EVAL_DB_HOST') !== '127.0.0.1' || getenv('EVAL_DB_NAME') !== 'eval_u1_test') {
    throw new RuntimeException('Only the disposable U1 database is permitted.');
}
$database = new Manager;
$config = [
    'driver' => 'mysql',
    'host' => getenv('EVAL_DB_HOST'),
    'port' => getenv('EVAL_DB_PORT'),
    'database' => getenv('EVAL_DB_NAME'),
    'username' => getenv('EVAL_DB_USER'),
    'password' => getenv('EVAL_DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_0900_as_cs',
    'prefix' => '',
    'strict' => true,
    'options' => [
        PDO::MYSQL_ATTR_SSL_CA => getenv('EVAL_DB_CA'),
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ],
];
$database->addConnection($config);
if (getenv('EVAL_U1_MIGRATION_PASSWORD')) {
    $database->addConnection(array_replace($config, ['username' => 'eval_u1_migration', 'password' => getenv('EVAL_U1_MIGRATION_PASSWORD')]), 'migration');
}
$database->setEventDispatcher(new Dispatcher($database->getContainer()));
$database->setAsGlobal();
$database->bootEloquent();

return $database;
