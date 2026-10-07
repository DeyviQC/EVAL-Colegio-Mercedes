<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
date_default_timezone_set('America/Bogota');
function db(): PDO {
 static $pdo=null;
 if($pdo) return $pdo;
 $config=is_file(PROJECT_ROOT.'/config/local.php')?require PROJECT_ROOT.'/config/local.php':null;
 if(!$config && !is_dir(PROJECT_ROOT.'/storage')) mkdir(PROJECT_ROOT.'/storage',0700,true);
 try {
  $pdo=new PDO($config['dsn']??'sqlite:'.PROJECT_ROOT.'/storage/eval.sqlite',$config['user']??null,$config['password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') { $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA busy_timeout=5000'); }
 } catch(PDOException $e) { http_response_code(503); exit('No se pudo conectar con la base de datos. Revisa config/local.php.'); }
 return $pdo;
}
function query(string $sql,array $params=[]): PDOStatement { $q=db()->prepare($sql);$q->execute($params);return $q; }
function install(): void {
 $auto=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'INT PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
 foreach([
 "CREATE TABLE IF NOT EXISTS eval_groups(id $auto,name VARCHAR(60) NOT NULL UNIQUE)",
 "CREATE TABLE IF NOT EXISTS eval_users(id $auto,name VARCHAR(120) NOT NULL,email VARCHAR(200) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,role VARCHAR(30) NOT NULL,group_id INTEGER NULL,active INTEGER NOT NULL DEFAULT 1,FOREIGN KEY(group_id) REFERENCES eval_groups(id))",
 "CREATE TABLE IF NOT EXISTS eval_courses(id $auto,name VARCHAR(100) NOT NULL,teacher_id INTEGER NOT NULL,group_id INTEGER NOT NULL,FOREIGN KEY(teacher_id) REFERENCES eval_users(id),FOREIGN KEY(group_id) REFERENCES eval_groups(id))",
 "CREATE TABLE IF NOT EXISTS eval_activities(id $auto,course_id INTEGER NOT NULL,title VARCHAR(200) NOT NULL,description TEXT NOT NULL,due_date VARCHAR(10) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'activa',created_at VARCHAR(30) NOT NULL,FOREIGN KEY(course_id) REFERENCES eval_courses(id))",
 "CREATE TABLE IF NOT EXISTS eval_submissions(id $auto,activity_id INTEGER NOT NULL,student_id INTEGER NOT NULL,answer TEXT NOT NULL,submitted_at VARCHAR(30) NOT NULL,grade VARCHAR(2) NULL,feedback TEXT NULL,graded_at VARCHAR(30) NULL,UNIQUE(activity_id,student_id),FOREIGN KEY(activity_id) REFERENCES eval_activities(id),FOREIGN KEY(student_id) REFERENCES eval_users(id))",
 "CREATE TABLE IF NOT EXISTS eval_notifications(id $auto,user_id INTEGER NOT NULL,message VARCHAR(500) NOT NULL,created_at VARCHAR(30) NOT NULL,is_read INTEGER NOT NULL DEFAULT 0,FOREIGN KEY(user_id) REFERENCES eval_users(id))",
 "CREATE TABLE IF NOT EXISTS eval_materials(id $auto,course_id INTEGER NOT NULL,title VARCHAR(200) NOT NULL,body TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'publicado',observation TEXT NULL,FOREIGN KEY(course_id) REFERENCES eval_courses(id))"
 ] as $sql) db()->exec($sql);
}
function seed(): void {
 if((int)query('SELECT COUNT(*) FROM eval_users')->fetchColumn()>0)return;
 db()->beginTransaction();
 try {
 query('INSERT INTO eval_groups(name) VALUES (?)',['3° A']); $group=(int)db()->lastInsertId();
 query('INSERT INTO eval_groups(name) VALUES (?)',['3° B']);
 $ids=[];
 foreach(['estudiante'=>['Lucía Quispe','estudiante@eval.test'],'docente'=>['María Huamán','docente@eval.test'],'director'=>['Carmen Flores','director@eval.test'],'subdirector'=>['Elena Ramos','subdirector@eval.test']] as $role=>$person) {
 query('INSERT INTO eval_users(name,email,password_hash,role,group_id) VALUES (?,?,?,?,?)',[$person[0],$person[1],password_hash('Mercedes2026!',PASSWORD_DEFAULT),$role,$role==='estudiante'?$group:null]);$ids[$role]=(int)db()->lastInsertId();
 }
 foreach(['Matemática','Comunicación','Ciencia y Tecnología'] as $name) {
 query('INSERT INTO eval_courses(name,teacher_id,group_id) VALUES (?,?,?)',[$name,$ids['docente'],$group]);$course=(int)db()->lastInsertId();
 query('INSERT INTO eval_activities(course_id,title,description,due_date,created_at) VALUES (?,?,?,?,?)',[$course,'Exploramos '.mb_strtolower($name),'Describe lo que has aprendido y comparte un ejemplo de tu comunidad.',date('Y-m-d',strtotime('+7 days')),date('c')]);
 }
 db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
}
function notify(int $user,string $message):void {query('INSERT INTO eval_notifications(user_id,message,created_at) VALUES (?,?,?)',[$user,$message,date('c')]);}
install();seed();
require_once __DIR__.'/migrations.php';


