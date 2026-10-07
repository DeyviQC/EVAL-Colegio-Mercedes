<?php
require dirname(__DIR__).'/app/auth.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !validToken()) { http_response_code(403); exit('Solicitud no válida.'); }
$_SESSION=[];
session_destroy();
header('Location: login.php');

