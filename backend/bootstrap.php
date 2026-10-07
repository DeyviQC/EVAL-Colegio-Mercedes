<?php

declare(strict_types=1);

$vendor = getenv('EVAL_VENDOR_DIR');
if (!$vendor || !is_file($vendor.'/autoload.php')) {
    throw new RuntimeException('Invoke the isolated local wrapper; external dependencies are missing.');
}
require_once $vendor.'/autoload.php';

if (getenv('EVAL_DB_HOST')) {
    require_once __DIR__.'/scripts/database.php';
}
