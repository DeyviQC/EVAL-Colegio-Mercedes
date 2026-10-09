<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\AcademicPeriodCommands;
use App\Application\Academic\Commands\AcademicCatalogCommands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Capsule\Manager as DB;

[$script,$mode,$actorId,$id,$label,$hold,$signals]=$argv;
$db=DB::connection();
if($db->getDatabaseName()!=='eval_u4_test'){throw new RuntimeException('U4 test database required.');}
$held=false;
$db->listen(function($query)use($mode,$hold,$signals,&$held){
    $needle=$mode==='activate'?'update `academic_periods`':($mode==='create'?'insert into `grades`':'update `grades`');
    if($hold==='hold' && !$held && str_contains(strtolower($query->sql),$needle)){
        $held=true;echo "HELD\n";fflush(STDOUT);$deadline=microtime(true)+15;
        while(!is_file($signals.'/go')){if(microtime(true)>$deadline){throw new RuntimeException('Barrier aborted.');}usleep(10000);}
    }
});
// Test-only server actor locator. Commands ignore its role snapshot and re-read authoritative grants under U3.
$provider=new \App\Infrastructure\Authentication\LocalUserProvider($db,new \Illuminate\Hashing\BcryptHasher(),'local_credentials');
$actor=$provider->actor($provider->retrieveById($actorId));
echo "STARTED\n";fflush(STDOUT);
try {
    if($mode==='activate'){(new AcademicPeriodCommands($db))->activate($actor,$id);}
    else{
        $commands=new AcademicCatalogCommands($db);
        match($mode){
            'create'=>$commands->create($actor,'grade',['name'=>$label]),
            'rename'=>$commands->update($actor,'grade',$id,['name'=>$label]),
            'reactivate'=>$commands->update($actor,'grade',$id,['is_active'=>true]),
            default=>throw new RuntimeException('Unknown test command'),
        };
    }
    echo "COMMITTED\n";
}catch(AcademicCommandFailure $error){echo $error->category."\n";}
fflush(STDOUT);
