<?php
declare(strict_types=1);
require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='runtime')exit(2);require dirname(__DIR__).'/bootstrap.php';
try{
 $db=Illuminate\Database\Capsule\Manager::connection();$id=App\Infrastructure\Persistence\Academic\AcademicLockSet::id($argv[1]);$r=$db->table('local_credentials')->where('id',$id)->first();$actor=new App\Infrastructure\Authentication\AuthenticatedActor($id,$db->table('local_role_grants')->where('identity_id',$id)->pluck('role')->all(),App\Infrastructure\Authentication\CredentialRevision::current($db,$id,$r->password));
 (new App\Application\Academic\Commands\MaterialObservations($db,new App\Infrastructure\Persistence\Academic\LocalMaterialStorage(dirname(__DIR__,2).'/.local/eval-dev/materials')))->execute($actor,$argv[3],$argv[2],$argv[2]==='reply'?['expected_event_id'=>$argv[4]==='none'?null:$argv[4],'expected_revision_id'=>$argv[5]==='none'?null:$argv[5],'body'=>'Concurrent private teacher reply']:['expected_event_id'=>$argv[4]]);
 echo 'COMMITTED';
}catch(App\Application\Academic\AcademicCommandFailure $e){if(!in_array($e->category,['stale_observation'],true))exit(2);echo $e->category;}catch(Throwable){fwrite(STDERR,'Observation race worker failed');exit(2);}
