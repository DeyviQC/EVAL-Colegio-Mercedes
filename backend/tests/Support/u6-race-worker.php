<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\AcademicPeriodCommands;
use App\Application\Academic\Commands\TransferStudent;
use App\Infrastructure\Authentication\LocalUserProvider;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Hashing\BcryptHasher;

[$script,$mode,$actorId,$payload,$hold,$signals]=$argv;
$db=DB::connection();if($db->getDatabaseName()!=='eval_u6_test'){throw new RuntimeException('U6 fixture database required.');}
$input=json_decode($payload,true,flags:JSON_THROW_ON_ERROR);
$provider=new LocalUserProvider($db,new BcryptHasher(),'local_credentials');$actor=$provider->actor($provider->retrieveById($actorId));
$held=false;
$db->listen(function($query)use($mode,$hold,$signals,&$held){
    $needle=$mode==='period_close'?'update `academic_periods`':'update `student_enrollments`';
    if($hold==='hold' && !$held && str_contains(strtolower($query->sql),$needle)){
        $held=true;echo "HELD\n";fflush(STDOUT);$deadline=microtime(true)+15;
        while(!is_file($signals.'/go')){if(microtime(true)>$deadline){throw new RuntimeException('Barrier aborted.');}usleep(10000);}
    }
});
echo "STARTED\n";fflush(STDOUT);
try{
    if($mode==='transfer'){(new TransferStudent($db))->execute($actor,$input['prior_id'],$input['destination']);}
    elseif($mode==='period_close'){(new AcademicPeriodCommands($db))->close($actor,$input['period_id']);}
    else{throw new RuntimeException('Unknown U6 fixture mode');}
    echo "COMMITTED\n";
}catch(AcademicCommandFailure $error){echo $error->category."\n";}
fflush(STDOUT);
