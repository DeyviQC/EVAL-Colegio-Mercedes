<?php
// Raíz pública restringida para el servidor de desarrollo.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(in_array($path,['/','/index.php','/login.php','/logout.php','/download.php'],true)) {
 require dirname(__DIR__).'/public'.($path==='/'?'/index.php':$path);return true;
}
if(preg_match('~^/assets/[a-zA-Z0-9_-]+\.(css|png|jpg|jpeg|webp|svg)$~D',$path)&&is_file(dirname(__DIR__).'/public'.$path))return false;
http_response_code(404);echo 'Recurso no disponible.';

