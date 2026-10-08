<?php
declare(strict_types=1);
require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='runtime')exit(2);require dirname(__DIR__).'/bootstrap.php';
try{
 $db=Illuminate\Database\Capsule\Manager::connection();$id=App\Infrastructure\Persistence\Academic\AcademicLockSet::id($argv[1]);$r=$db->table('local_credentials')->where('id',$id)->first();$actor=new App\Infrastructure\Authentication\AuthenticatedActor($id,$db->table('local_role_grants')->where('identity_id',$id)->pluck('role')->all(),App\Infrastructure\Authentication\CredentialRevision::current($db,$id,$r->password));
 if($argv[2]==='submit'){$service=new App\Application\Academic\Commands\ActivityDelivery($db,new App\Infrastructure\Persistence\Academic\LocalMaterialStorage(dirname(__DIR__,2).'/.local/eval-dev/deliveries'));$service->write($actor,'submit',$argv[3],['answer'=>'Concurrent assessment evidence']);}
 elseif($argv[2]==='assess'){(new App\Application\Academic\Commands\DeliveryAssessment($db))->assess($actor,$argv[3],['grade'=>'A','feedback'=>'Concurrent verified assessment.','expected_version_id'=>$argv[4],'expected_assessment_id'=>$argv[5]==='none'?null:$argv[5]]);}else exit(2);
 echo 'COMMITTED';
}catch(App\Application\Academic\AcademicCommandFailure $e){if(!in_array($e->category,['already_assessed','stale_delivery_version','stale_assessment'],true))exit(2);echo $e->category;}catch(Throwable){fwrite(STDERR,'Assessment race worker failed');exit(2);}
