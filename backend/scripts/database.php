<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/bootstrap.php';

use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;

$unit = getenv('EVAL_UNIT') ?: 'U1';
$expectedDatabase = match ($unit) { 'U1' => 'eval_u1_test', 'U2' => 'eval_u2_test', 'U3' => 'eval_u3_test', 'U4' => 'eval_u4_test', 'U5' => 'eval_u5_test', 'U6' => 'eval_u6_test', 'U7' => 'eval_u7_test', 'U8' => 'eval_u8_test', 'U9' => 'eval_u9_test', 'U10' => 'eval_u10_test', 'Auth' => 'eval_auth_test', default => null };
if (getenv('EVAL_DB_HOST') !== '127.0.0.1' || $expectedDatabase === null || getenv('EVAL_DB_NAME') !== $expectedDatabase) {
    throw new RuntimeException('Only the selected isolated unit database is permitted.');
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
    'timezone' => '+00:00',
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
