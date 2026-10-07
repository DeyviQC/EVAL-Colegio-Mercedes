<?php
require dirname(__DIR__).'/app/auth.php';
$user=isset($_SESSION['user_id'])?query('SELECT * FROM eval_users WHERE id=? AND active=1',[$_SESSION['user_id']])->fetch():false;
if(!$user){http_response_code(401);exit('Inicia sesión.');}
if($user['role']==='subdirector'){http_response_code(403);exit('Esta operación no pertenece al soporte de asignaciones docentes.');}
$file=query('SELECT f.*,c.teacher_id,c.group_id FROM eval_material_files f JOIN eval_materials m ON m.id=f.material_id JOIN eval_courses c ON c.id=m.course_id WHERE m.id=?',[(int)($_GET['id']??0)])->fetch();
if(!$file||($user['role']==='estudiante'&&(int)$user['group_id']!==(int)$file['group_id'])||($user['role']==='docente'&&(int)$user['id']!==(int)$file['teacher_id'])){http_response_code(403);exit('Archivo no disponible para tu cuenta.');}
$path=PROJECT_ROOT.'/storage/uploads/'.basename($file['path']);if(!is_file($path)){http_response_code(404);exit('Archivo no encontrado.');}
header('Content-Type: '.$file['mime']);header('X-Content-Type-Options: nosniff');header("Content-Disposition: attachment; filename=material; filename*=UTF-8''".rawurlencode($file['original_name']));header('Content-Length: '.filesize($path));readfile($path);

