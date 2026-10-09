<?php
declare(strict_types=1);

require __DIR__.'/config.php';

// No framework bootstrap, default database, writes or credential-bearing diagnostics.
try {
    $role = getenv('EVAL_DEV_CAPABILITY_ROLE');
    if (!in_array($role, ['migration', 'runtime'], true)
        || getenv('EVAL_DB_HOST') !== '127.0.0.1' || getenv('EVAL_DB_PORT') !== '3307'
        || getenv('EVAL_DB_USER') !== 'eval_u1_'.$role || !getenv('EVAL_DB_PASSWORD')
        || !is_file(getenv('EVAL_DB_CA') ?: '')) {
        throw new RuntimeException('Invalid capability binding');
    }
    $db = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', getenv('EVAL_DB_USER'), getenv('EVAL_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => getenv('EVAL_DB_CA'),
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ]);
    $identity = $db->query('SELECT CURRENT_USER()')->fetchColumn();
    if (!in_array($identity, ['eval_u1_'.$role.'@localhost', 'eval_u1_'.$role.'@127.0.0.1'], true)) {
        throw new RuntimeException('Unexpected authenticated account');
    }
    $tls = $db->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
    if (!is_array($tls) || empty($tls[1])) {
        throw new RuntimeException('Verified TLS required');
    }
    $grants = $db->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['account_binding' => true, 'tls' => true] + evalDevCapabilities($grants), JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Owned capability check unavailable; no database mutation performed.\n");
    exit(2);
}
