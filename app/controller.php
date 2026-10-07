<?php
require __DIR__.'/auth.php';
require __DIR__.'/uploads.php';
require __DIR__.'/scope_validation.php';
$user=isset($_SESSION['user_id'])?query('SELECT * FROM eval_users WHERE id=? AND active=1',[$_SESSION['user_id']])->fetch():false;
if(!$user){header('Location: login.php');exit;}
$labels=['estudiante'=>'Estudiante','docente'=>'Docente','director'=>'Dirección','subdirector'=>'Subdirección'];
$user['label']=$labels[$user['role']];
$student=$user['role']==='estudiante';$teacher=$user['role']==='docente';$management=in_array($user['role'],['director','subdirector'],true);
$vicePrincipal=$user['role']==='subdirector';
$pages=$student?['inicio'=>'Inicio','cursos'=>'Mis cursos','actividades'=>'Actividades','notas'=>'Mis notas','biblioteca'=>'Biblioteca','avisos'=>'Notificaciones','perfil'=>'Mi cuenta']:($teacher?['inicio'=>'Mi panel','cursos'=>'Mis aulas','actividades'=>'Actividades','entregas'=>'Entregas y notas','materiales'=>'Materiales','avisos'=>'Notificaciones','perfil'=>'Mi cuenta']:['inicio'=>'Panel institucional','usuarios'=>'Usuarios','estructura'=>'Aulas y docentes','actividades'=>'Actividades','entregas'=>'Seguimiento académico','materiales'=>'Materiales','reportes'=>'Reportes','avisos'=>'Notificaciones','perfil'=>'Mi cuenta']);
if($vicePrincipal)$pages=['inicio'=>'Mi panel','estructura'=>'Asignaciones docentes','avisos'=>'Notificaciones','perfil'=>'Mi cuenta'];
if($vicePrincipal&&isset($_GET['page'])&&!isset($pages[$_GET['page']])){http_response_code(403);exit('Tu perfil solo permite el soporte de asignaciones docentes en esta base académica.');}
$page=isset($pages[$_GET['page']??''])?$_GET['page']:'inicio';
$courses=query('SELECT c.*,u.name AS teacher,g.name AS group_name FROM eval_courses c JOIN eval_users u ON u.id=c.teacher_id JOIN eval_groups g ON g.id=c.group_id'.($student?' WHERE c.group_id=?':($teacher?' WHERE c.teacher_id=?':'')), $student?[$user['group_id']]:($teacher?[$user['id']]:[]))->fetchAll();
$courseIds=array_map('intval',array_column($courses,'id'));
if($student&&isset($_GET['grade'])&&(int)$_GET['grade']>0&&(int)$_GET['grade']!==(int)query('SELECT grade_level FROM eval_groups WHERE id=?',[$user['group_id']])->fetchColumn()){http_response_code(403);exit('El grado solicitado está fuera de tu alcance académico.');}
if($student&&((isset($_GET['group'])&&(int)$_GET['group']>0&&(int)$_GET['group']!==(int)$user['group_id'])||(isset($_GET['course'])&&(int)$_GET['course']>0&&!in_array((int)$_GET['course'],$courseIds,true)))){http_response_code(403);exit('El salón o curso solicitado está fuera de tu alcance académico.');}
function allowedCourse(int $id):bool {global $courseIds;return in_array($id,$courseIds,true);}
function textField(string $key,int $limit):string {$value=trim((string)($_POST[$key]??''));if(!$value||mb_strlen($value)>$limit)throw new RuntimeException('Completa correctamente el campo '.$key.'.');return $value;}
function requireRole(bool $allowed):void {if(!$allowed)throw new RuntimeException('Tu cuenta no tiene permiso para esta acción.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
 if(!validToken())throw new RuntimeException('Formulario vencido. Recarga e inténtalo de nuevo.');
 db()->beginTransaction();
 $action=(string)($_POST['action']??'');
 switch($action){
 case 'create_activity':
 requireRole($teacher);rejectAcademicOverrides($_POST);$course=(int)($_POST['course_id']??0);requireRole(allowedCourse($course));$title=textField('title',200);$description=textField('description',5000);$due=(string)($_POST['due_date']??'');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$due);if(!$date||$date->format('Y-m-d')!==$due||$due<date('Y-m-d'))throw new RuntimeException('Selecciona una fecha de entrega válida, desde hoy.');
 query('INSERT INTO eval_activities(course_id,title,description,due_date,created_at) VALUES (?,?,?,?,?)',[$course,$title,$description,$due,date('c')]);
 $group=query('SELECT group_id FROM eval_courses WHERE id=?',[$course])->fetchColumn();foreach(query("SELECT id FROM eval_users WHERE group_id=? AND role='estudiante' AND active=1",[$group])->fetchAll() as $recipient)notify((int)$recipient['id'],'Nueva actividad: '.$title);break;
 case 'close_activity':
 requireRole($teacher);$activity=query('SELECT * FROM eval_activities WHERE id=?',[(int)($_POST['activity_id']??0)])->fetch();requireRole($activity&&allowedCourse((int)$activity['course_id']));query("UPDATE eval_activities SET status='cerrada' WHERE id=?",[$activity['id']]);break;
 case 'submit':
 requireRole($student);rejectAcademicOverrides($_POST);if(isset($_POST['course_id']))throw new RuntimeException('La ruta se obtiene exclusivamente de la actividad.');$activity=query('SELECT a.*,c.teacher_id FROM eval_activities a JOIN eval_courses c ON c.id=a.course_id WHERE a.id=?',[(int)($_POST['activity_id']??0)])->fetch();requireRole($activity&&allowedCourse((int)$activity['course_id']));if($activity['status']!=='activa')throw new RuntimeException('Esta actividad está cerrada.');$answer=textField('answer',10000);
 $existing=query('SELECT * FROM eval_submissions WHERE activity_id=? AND student_id=?',[$activity['id'],$user['id']])->fetch();
 if($existing&&$existing['grade']!==null)throw new RuntimeException('La entrega ya fue calificada y no puede modificarse.');
 if($existing) { $updated=query('UPDATE eval_submissions SET answer=?,submitted_at=? WHERE id=? AND grade IS NULL',[$answer,date('c'),$existing['id']]);if($updated->rowCount()!==1)throw new RuntimeException('La entrega acaba de ser calificada. Recarga para ver la nota.'); } else query('INSERT INTO eval_submissions(activity_id,student_id,answer,submitted_at) VALUES (?,?,?,?)',[$activity['id'],$user['id'],$answer,date('c')]);
 notify((int)$activity['teacher_id'],$user['name'].' entregó: '.$activity['title']);break;
 case 'grade':
 requireRole($teacher);$submission=query('SELECT s.*,a.title,c.teacher_id FROM eval_submissions s JOIN eval_activities a ON a.id=s.activity_id JOIN eval_courses c ON c.id=a.course_id WHERE s.id=?',[(int)($_POST['submission_id']??0)])->fetch();requireRole($submission&&(int)$submission['teacher_id']===(int)$user['id']);$grade=(string)($_POST['grade']??'');if(!in_array($grade,['AD','A','B','C'],true))throw new RuntimeException('Selecciona una nota válida.');$feedback=textField('feedback',5000);query('UPDATE eval_submissions SET grade=?,feedback=?,graded_at=? WHERE id=?',[$grade,$feedback,date('c'),$submission['id']]);notify((int)$submission['student_id'],'Calificación '.$grade.' en: '.$submission['title']);break;
 case 'create_user':
 requireRole($user['role']==='director');$name=textField('name',120);$email=textField('email',200);$password=textField('password',200);$role=(string)($_POST['role']??'');$group=$role==='estudiante'?(int)($_POST['group_id']??0):null;if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!isset($labels[$role])||mb_strlen($password)<10)throw new RuntimeException('Revisa el correo, perfil y contraseña (mínimo 10 caracteres).');if($role==='estudiante'&&!query('SELECT id FROM eval_groups WHERE id=?',[$group])->fetch())throw new RuntimeException('Asigna un aula a la estudiante.');if(query('SELECT id FROM eval_users WHERE email=?',[$email])->fetch())throw new RuntimeException('Ese correo ya está registrado.');query('INSERT INTO eval_users(name,email,password_hash,role,group_id) VALUES (?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$group]);break;
 case 'assign_course':
 requireRole($user['role']==='director');$name=textField('name',100);$teacherId=(int)($_POST['teacher_id']??0);$group=(int)($_POST['group_id']??0);if(!query("SELECT id FROM eval_users WHERE id=? AND role='docente' AND active=1",[$teacherId])->fetch()||!query('SELECT id FROM eval_groups WHERE id=?',[$group])->fetch())throw new RuntimeException('Selecciona un docente y aula válidos.');query('INSERT INTO eval_courses(name,teacher_id,group_id) VALUES (?,?,?)',[$name,$teacherId,$group]);break;
 case 'create_group':requireRole($user['role']==='director');$grade=(int)($_POST['grade_level']??0);if($grade<1||$grade>5)throw new RuntimeException('Selecciona un grado del 1 al 5.');query('INSERT INTO eval_groups(name,grade_level) VALUES (?,?)',[textField('name',60),$grade]);break;
 case 'material':
 requireRole($teacher);$course=(int)($_POST['course_id']??0);requireRole(allowedCourse($course));$body=trim((string)($_POST['body']??''));if(mb_strlen($body)>10000)throw new RuntimeException('Contenido demasiado largo.');
 $title=textField('title',200);$file=receiveMaterialFile();if($file)$newFile=$file['path'];if(!$file&&$body==='')throw new RuntimeException('Escribe contenido o adjunta un archivo.');
 query("INSERT INTO eval_materials(course_id,title,body,status) VALUES (?,?,?,'publicado')",[$course,$title,$body]);$materialId=(int)db()->lastInsertId();if($file){$newFile=$file['path'];query('INSERT INTO eval_material_files(material_id,path,original_name,mime) VALUES (?,?,?,?)',[$materialId,$file['path'],$file['name'],$file['mime']]);}
 $group=query('SELECT group_id FROM eval_courses WHERE id=?',[$course])->fetchColumn();foreach(query("SELECT id FROM eval_users WHERE group_id=? AND role='estudiante' AND active=1",[$group])->fetchAll() as $recipient)notify((int)$recipient['id'],'Nuevo material publicado: '.(string)$_POST['title']);break;
 case 'delete_material':
 requireRole($user['role']==='director');$materialId=(int)($_POST['material_id']??0);$material=query('SELECT m.*,c.teacher_id FROM eval_materials m JOIN eval_courses c ON c.id=m.course_id WHERE m.id=?',[$materialId])->fetch();if(!$material)throw new RuntimeException('Material no encontrado.');$deletedFile=query('SELECT path FROM eval_material_files WHERE material_id=?',[$materialId])->fetchColumn();query('DELETE FROM eval_material_files WHERE material_id=?',[$materialId]);query('DELETE FROM eval_materials WHERE id=?',[$materialId]);notify((int)$material['teacher_id'],'Dirección eliminó el material: '.$material['title']);break; case 'read_notifications':query('UPDATE eval_notifications SET is_read=1 WHERE user_id=?',[$user['id']]);break;
 case 'password':if(!password_verify((string)($_POST['current_password']??''),$user['password_hash']))throw new RuntimeException('La contraseña actual no es correcta.');$password=textField('password',200);if(mb_strlen($password)<10)throw new RuntimeException('Usa al menos 10 caracteres.');query('UPDATE eval_users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);session_regenerate_id(true);break;
 default:throw new RuntimeException('Acción desconocida.');
 }
 db()->commit();if(!empty($deletedFile)&&is_file(PROJECT_ROOT.'/storage/uploads/'.basename($deletedFile)))unlink(PROJECT_ROOT.'/storage/uploads/'.basename($deletedFile));header('Location: ?page='.urlencode($page).'&saved=1&grade='.(int)($_GET['grade']??0).'&group='.(int)($_GET['group']??0));exit;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!empty($newFile)&&is_file(PROJECT_ROOT.'/storage/uploads/'.basename($newFile)))unlink(PROJECT_ROOT.'/storage/uploads/'.basename($newFile));$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'No se pudo guardar. Revisa los datos; puede existir un registro duplicado.';}
}
$scope=$student?'c.group_id=?':($teacher?'c.teacher_id=?':'1=1');$params=$student?[$user['group_id']]:($teacher?[$user['id']]:[]);
$groups=query('SELECT g.* FROM eval_groups g WHERE EXISTS (SELECT 1 FROM eval_courses c WHERE c.group_id=g.id'.($teacher?' AND c.teacher_id=?':'').') ORDER BY g.grade_level,g.name',$teacher?[$user['id']]:[])->fetchAll();
$selectedGrade=(int)($_GET['grade']??0);$selectedGroup=(int)($_GET['group']??0);
$selectedRoom=null;foreach($groups as $g)if((int)$g['id']===$selectedGroup)$selectedRoom=$g;
if(!$student&&$selectedGroup&&!$selectedRoom){http_response_code(403);exit('No tienes acceso a este salón.');}
if($selectedRoom)$selectedGrade=(int)$selectedRoom['grade_level'];
$organizedPages=['cursos','estructura','usuarios','actividades','entregas','materiales','reportes'];
$staffView=$management&&$page==='usuarios'&&($_GET['staff']??'')==='1';
$needsSelection=!$student&&in_array($page,$organizedPages,true)&&!$selectedGroup&&!$staffView;
if(!$student&&$selectedGroup){$scope.=' AND c.group_id=?';$params[]=$selectedGroup;$courses=array_values(array_filter($courses,fn($c)=>(int)$c['group_id']===$selectedGroup));}
if($needsSelection){$courses=[];$scope.=' AND 1=0';}$readScope=$vicePrincipal?$scope.' AND 1=0':$scope;
$activities=query('SELECT a.*,c.name AS course,c.group_id,c.teacher_id,g.name AS group_name,u.name AS teacher FROM eval_activities a JOIN eval_courses c ON c.id=a.course_id JOIN eval_groups g ON g.id=c.group_id JOIN eval_users u ON u.id=c.teacher_id WHERE '.$readScope.' ORDER BY a.id DESC',$params)->fetchAll();
$submissions=query('SELECT s.*,a.title,a.due_date,c.name AS course,c.id AS course_id,g.name AS group_name,u.name AS student FROM eval_submissions s JOIN eval_activities a ON a.id=s.activity_id JOIN eval_courses c ON c.id=a.course_id JOIN eval_groups g ON g.id=c.group_id JOIN eval_users u ON u.id=s.student_id WHERE '.($student?'s.student_id=?':$readScope).' ORDER BY s.submitted_at DESC',$student?[$user['id']]:$params)->fetchAll();
$mySubmissions=[];foreach($submissions as $submission)$mySubmissions[$submission['activity_id']]=$submission;
$materials=query('SELECT m.*,c.name AS course,f.original_name FROM eval_materials m JOIN eval_courses c ON c.id=m.course_id LEFT JOIN eval_material_files f ON f.material_id=m.id WHERE '.$readScope.' ORDER BY m.id DESC',$params)->fetchAll();

$unread=(int)query('SELECT COUNT(*) FROM eval_notifications WHERE user_id=? AND is_read=0',[$user['id']])->fetchColumn();
function csrf():void {echo '<input type="hidden" name="csrf" value="'.escape($_SESSION['csrf']).'">';}
function courseSelect(array $courses):void {echo '<label>Curso y aula<select name="course_id" required>';foreach($courses as $c)echo '<option value="'.(int)$c['id'].'">'.escape($c['name'].' · '.$c['group_name']).'</option>';echo '</select></label>';}
require PROJECT_ROOT.'/resources/views/dashboard.php';







