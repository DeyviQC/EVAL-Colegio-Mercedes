<?php
require __DIR__.'/../app/database.php';
$hash=password_hash('Mercedes2026!',PASSWORD_DEFAULT);
$subjects=['matematica'=>['Matemática','María Huamán','docente@eval.test'],'comunicacion'=>['Comunicación','José Medina','comunicacion@eval.test'],'ciencia'=>['Ciencia y Tecnología','Rosa Flores','ciencia@eval.test'],'sociales'=>['Ciencias Sociales','Ana Torres','sociales@eval.test'],'ingles'=>['Inglés','Elena Castro','ingles@eval.test'],'arte'=>['Arte y Cultura','Patricia Ramos','arte@eval.test']];
db()->beginTransaction();try{
foreach($subjects as $key=>$data){$u=query('SELECT id FROM eval_users WHERE email=?',[$data[2]])->fetchColumn();if(!$u){query("INSERT INTO eval_users(name,email,password_hash,role) VALUES (?,?,?,'docente')",[$data[1],$data[2],$hash]);$u=db()->lastInsertId();}$teachers[$key]=(int)$u;}
for($grade=1;$grade<=5;$grade++)foreach(['A','B','C','D','E'] as $section){
 $name=$grade.'° '.$section;$group=query('SELECT id FROM eval_groups WHERE name=?',[$name])->fetchColumn();if(!$group){query('INSERT INTO eval_groups(name,grade_level) VALUES (?,?)',[$name,$grade]);$group=db()->lastInsertId();}else query('UPDATE eval_groups SET grade_level=? WHERE id=?',[$grade,$group]);
 $existing=(int)query("SELECT COUNT(*) FROM eval_users WHERE group_id=? AND role='estudiante'",[$group])->fetchColumn();
 for($i=$existing+1;$i<=10;$i++){$email='alumna.'.$grade.strtolower($section).'.'.str_pad((string)$i,2,'0',STR_PAD_LEFT).'@eval.test';if(query('SELECT id FROM eval_users WHERE email=?',[$email])->fetchColumn())continue;query("INSERT INTO eval_users(name,email,password_hash,role,group_id) VALUES (?,?,?,'estudiante',?)",['Estudiante '.$name.' · '.str_pad((string)$i,2,'0',STR_PAD_LEFT),$email,$hash,$group]);}
 foreach($subjects as $key=>$data){$course=query('SELECT id FROM eval_courses WHERE name=? AND group_id=?',[$data[0],$group])->fetchColumn();if(!$course){query('INSERT INTO eval_courses(name,teacher_id,group_id) VALUES (?,?,?)',[$data[0],$teachers[$key],$group]);$course=db()->lastInsertId();}else query('UPDATE eval_courses SET teacher_id=? WHERE id=?',[$teachers[$key],$course]);
 if(!(int)query('SELECT COUNT(*) FROM eval_activities WHERE course_id=?',[$course])->fetchColumn())query('INSERT INTO eval_activities(course_id,title,description,due_date,created_at) VALUES (?,?,?,?,?)',[$course,'Actividad de '.$data[0].' · '.$name,'Comparte lo que has aprendido en este curso.',date('Y-m-d',strtotime('+7 days')),date('c')]);
 }
}
db()->commit();echo "Escenario creado: 25 salones, 6 cursos por salón y al menos 10 alumnas por salón.\n";
}catch(Throwable $e){db()->rollBack();throw $e;}

