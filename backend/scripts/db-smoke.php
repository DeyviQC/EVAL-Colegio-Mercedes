<?php

declare(strict_types=1);

$database = require __DIR__.'/database.php';
$connection = $database->getConnection();
$version = $connection->selectOne('SELECT VERSION() AS version, DATABASE() AS name');
$tls = $connection->selectOne("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
if (!$tls->Value) {
    throw new RuntimeException('TLS is required.');
}
$tables = $connection->selectOne('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE()');
echo json_encode(['version' => $version->version, 'database' => $version->name, 'tables' => $tables->total, 'tls' => $tls->Value], JSON_THROW_ON_ERROR).PHP_EOL;
