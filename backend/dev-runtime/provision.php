<?php
declare(strict_types=1);
// One-time, dev-only maintenance. No credentials or exception details are printed.
$stage='binding';
try {
    require __DIR__.'/config.php';
    if (!evalDevBinding(getenv()) || getenv('EVAL_DB_ROLE')!=='migration') throw new RuntimeException();
    $capsule=require dirname(__DIR__).'/scripts/database.php';
    $db=$capsule->getConnection();
    $stage='migrations';
    $repository=new Illuminate\Database\Migrations\DatabaseMigrationRepository($capsule->getDatabaseManager(),'migrations');
    if (!$repository->repositoryExists()) $repository->createRepository();
    $migrator=new Illuminate\Database\Migrations\Migrator($repository,$capsule->getDatabaseManager(),new Illuminate\Filesystem\Filesystem,$capsule->getEventDispatcher());
    $files=glob(dirname(__DIR__).'/database/migrations/2026_10_07_*.php');sort($files);
    if(count($files)!==10)throw new RuntimeException();
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000011_create_local_account_events.php';
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000012_create_activity_delivery_content.php';
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000013_create_delivery_assessments.php';
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000014_create_academic_notifications.php';
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000015_create_material_revisions.php';
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000016_create_material_observations.php';
    $files[]=dirname(__DIR__).'/database/migrations/2026_10_08_000017_create_material_file_versions.php';
    $migrator->run($files);
    $stage='runtime_grants';
    $sql=file_get_contents(dirname(__DIR__).'/scripts/u11-runtime-grants.sql');
    foreach(explode(';',$sql) as $statement) {
        $statement=trim($statement);
        if(!str_starts_with($statement,'GRANT ')||!str_contains($statement,"TO 'eval_u1_runtime'@'127.0.0.1'"))continue;
        $db->unprepared(str_replace(['eval_u11_test','eval_u1_runtime'],['eval_dev','eval_dev_runtime'],$statement));
    }
    $stage='accounts';
    foreach([
        "GRANT INSERT(credential_status) ON eval_dev.retained_identities TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(identity_id,display_name) ON eval_dev.retained_identity_profiles TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(id,login,password), UPDATE(password) ON eval_dev.local_credentials TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(identity_id,role) ON eval_dev.local_role_grants TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(actor_id,target_id,operation,operation_key,recorded_at) ON eval_dev.local_account_events TO 'eval_dev_runtime'@'127.0.0.1'"
    ] as $statement)$db->unprepared($statement);
    foreach([
        "GRANT UPDATE(accepted_at) ON eval_dev.submission_references TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(activity_id,title,instructions,due_on,state,publication_key,published_at), UPDATE(state) ON eval_dev.activity_contents TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(submission_id,student_id,activity_id), UPDATE(current_version_id,current_assessment_id) ON eval_dev.activity_deliveries TO 'eval_dev_runtime'@'127.0.0.1'",
        "GRANT INSERT(submission_id,answer,filename,storage_key,mime,bytes,sha256,operation_key,recorded_at) ON eval_dev.delivery_versions TO 'eval_dev_runtime'@'127.0.0.1'"
    ] as $statement)$db->unprepared($statement);
    $db->unprepared("GRANT INSERT(submission_id,version_id,teacher_id,grade,feedback,operation_key,recorded_at) ON eval_dev.delivery_assessments TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(recipient_id,event,activity_id,submission_id,operation_key,created_at), UPDATE(read_at) ON eval_dev.academic_notifications TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(material_id,actor_id,previous_revision_id,operation,state,title,description,operation_key,recorded_at) ON eval_dev.material_revisions TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(material_id,current_revision_id), UPDATE(current_revision_id) ON eval_dev.material_states TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(material_id,creator_id,recipient_id,observed_revision_id,body,operation_key,created_at) ON eval_dev.material_observations TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(observation_id,actor_id,material_revision_id,previous_event_id,operation,body,operation_key,created_at) ON eval_dev.material_observation_events TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(observation_id,current_event_id), UPDATE(current_event_id) ON eval_dev.material_observation_states TO 'eval_dev_runtime'@'127.0.0.1'");
    $db->unprepared("GRANT INSERT(material_id,revision_id,filename,mime,bytes,sha256,storage_key) ON eval_dev.material_file_versions TO 'eval_dev_runtime'@'127.0.0.1'");
    $passwords=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    $actors=[];$hasher=new Illuminate\Hashing\BcryptHasher(['rounds'=>12]);
    $names=['director_admin'=>'Director de desarrollo','vice_principal'=>'Subdirector de desarrollo','teacher'=>'Docente de desarrollo','student'=>'Estudiante de desarrollo'];
    $logins=['director_admin'=>'director','vice_principal'=>'subdirector','teacher'=>'docente','student'=>'estudiante'];
    $db->transaction(function()use($db,$passwords,$hasher,$names,$logins,&$actors){
        foreach($logins as $role=>$login){
            $id=$db->table('local_credentials')->where('login',$login)->value('id');
            if($id===null){
                if(!is_string($passwords[$role]??null)||strlen($passwords[$role])<16)throw new RuntimeException();
                $id=(string)$db->table('retained_identities')->insertGetId(['credential_status'=>'active']);
                $db->table('local_credentials')->insert(['id'=>$id,'login'=>$login,'password'=>$hasher->make($passwords[$role])]);
                $db->table('local_role_grants')->insert(['identity_id'=>$id,'role'=>$role]);
                $db->table('retained_identity_profiles')->insert(['identity_id'=>$id,'display_name'=>$names[$role]]);
            } elseif(!$hasher->check($passwords[$role]??'', $db->table('local_credentials')->where('id',$id)->value('password'))){throw new RuntimeException();}
            $storedHash=$db->table('local_credentials')->where('id',$id)->value('password');
            $actors[$role]=new App\Infrastructure\Authentication\AuthenticatedActor((string)$id,[$role],hash('sha256',$storedHash));
        }
    });
    $stage='academic_foundation';
    $director=$actors['director_admin'];$periods=new App\Application\Academic\Commands\AcademicPeriodCommands($db);
    $catalog=new App\Application\Academic\Commands\AcademicCatalogCommands($db);
    $period=$db->table('academic_periods')->where('name','EVAL desarrollo 2026')->value('id');
    if($period===null){$period=$periods->create($director,['name'=>'EVAL desarrollo 2026','start_on'=>'2026-01-01','end_on'=>'2026-12-31']);$periods->activate($director,$period);}
    $grade=$db->table('grades')->where('name','2.º')->value('id');
    if($grade===null)$grade=$catalog->create($director,'grade',['name'=>'2.º']);
    $section=$db->table('sections')->where('grade_id',$grade)->where('name','B')->value('id');
    if($section===null)$section=$catalog->create($director,'section',['name'=>'B','grade_id'=>(string)$grade]);
    $entry=$db->table('instructional_entries')->where('name','Matemática')->where('kind','subject')->value('id');
    if($entry===null)$entry=$catalog->create($director,'entry',['name'=>'Matemática','kind'=>'subject']);
    if(!$db->table('student_enrollments')->where('student_id',$actors['student']->identityId)->exists())(new App\Application\Academic\Commands\EnrollmentCommands($db))->create($director,['student_id'=>$actors['student']->identityId,'academic_period_id'=>(string)$period,'grade_id'=>(string)$grade,'section_id'=>(string)$section,'effective_from'=>'2026-01-01']);
    if(!$db->table('teaching_assignments')->where('teacher_id',$actors['teacher']->identityId)->exists()){
        $commands=new App\Application\Academic\Commands\TeachingAssignmentCommands($db);
        $assignment=$commands->create($director,['teacher_id'=>$actors['teacher']->identityId,'academic_period_id'=>(string)$period,'instructional_entry_id'=>(string)$entry,'grade_id'=>(string)$grade,'section_id'=>(string)$section,'effective_from'=>'2026-01-01','effective_until'=>'2026-12-31']);$commands->activate($director,$assignment);
    }
    echo json_encode(['database'=>'eval_dev','migrations'=>$db->table('migrations')->count(),'accounts'=>count($actors),'materials'=>$db->table('course_materials')->count()],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){$category=$e instanceof App\Application\Academic\AcademicCommandFailure?$e->category:get_class($e);fwrite(STDERR,"Development preparation failed at $stage ($category). Retained data was not deleted.\n");exit(2);}
