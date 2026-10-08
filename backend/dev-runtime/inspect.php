<?php
declare(strict_types=1);
$stage='binding';
try {
    require __DIR__.'/config.php';
    if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='runtime')throw new RuntimeException();
    $stage='connection';require dirname(__DIR__).'/bootstrap.php';$db=Illuminate\Database\Capsule\Manager::connection();
    $identity=$db->selectOne('SELECT CURRENT_USER() AS identity')->identity;
    if(!in_array($identity,['eval_dev_runtime@127.0.0.1','eval_dev_runtime@localhost'],true))throw new RuntimeException();
    $stage='tls';$tls=$db->select("SHOW SESSION STATUS LIKE 'Ssl_cipher'");if(empty($tls[0]->Value))throw new RuntimeException();
    $tables=$db->select('SHOW TABLES');$migrations=$db->table('migrations')->count();
    $stage='schema';if(!(($migrations===12&&count($tables)===23)||($migrations===13&&count($tables)===24)||($migrations===14&&count($tables)===25)))throw new RuntimeException();
    $stage='privileges';foreach($db->select('SHOW GRANTS FOR CURRENT_USER()') as $grant){
        $text=array_values((array)$grant)[0];
        if(preg_match('/^GRANT .*\b(ALL PRIVILEGES|CREATE|DROP|ALTER|DELETE|GRANT OPTION)\b/',$text))throw new RuntimeException();
    }
    echo json_encode(['database'=>'eval_dev','tls'=>true,'runtime_restricted'=>true,'tables'=>count($tables),'migrations'=>$migrations,'accounts'=>$db->table('local_credentials')->count(),'periods'=>$db->table('academic_periods')->count(),'assignments'=>$db->table('teaching_assignments')->count(),'enrollments'=>$db->table('student_enrollments')->count(),'materials'=>$db->table('course_materials')->count(),'ordinal'=>(string)$db->table('academic_write_guard')->value('last_ordinal')],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){$detail=$error instanceof Error?$error->getMessage():get_class($error);fwrite(STDERR,"Development runtime/schema verification failed at $stage (".$detail.").\n");exit(2);}
