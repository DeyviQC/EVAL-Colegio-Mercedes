<?php
declare(strict_types=1);
require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='runtime')exit(2);require dirname(__DIR__).'/bootstrap.php';
try{
 $db=Illuminate\Database\Capsule\Manager::connection();$id=App\Infrastructure\Persistence\Academic\AcademicLockSet::id($argv[1]);$r=$db->table('local_credentials')->where('id',$id)->first();$actor=new App\Infrastructure\Authentication\AuthenticatedActor($id,$db->table('local_role_grants')->where('identity_id',$id)->pluck('role')->all(),App\Infrastructure\Authentication\CredentialRevision::current($db,$id,$r->password));
 (new App\Application\Academic\Commands\MaterialMaintenance($db))->execute($actor,$argv[2],'edited',['expected_revision_id'=>$argv[3],'title'=>'Race winner '.$argv[4],'description'=>null]);
 echo 'COMMITTED';
}catch(App\Application\Academic\AcademicCommandFailure $e){if(!in_array($e->category,['stale_material_revision'],true))exit(2);echo $e->category;}catch(Throwable){fwrite(STDERR,'Material race worker failed');exit(2);}
