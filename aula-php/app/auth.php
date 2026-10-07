<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); session_start(); }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$accounts = [
 'estudiante'=>['name'=>'Lucía · Estudiante de ejemplo','label'=>'Estudiante','email'=>'estudiante@eval.test'],
 'docente'=>['name'=>'María · Docente de ejemplo','label'=>'Docente','email'=>'docente@eval.test'],
 'director'=>['name'=>'Carmen · Directora de ejemplo','label'=>'Dirección','email'=>'director@eval.test'],
 'subdirector'=>['name'=>'Elena · Subdirectora de ejemplo','label'=>'Subdirección','email'=>'subdirector@eval.test'],
];
function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function validToken(): bool { return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }


