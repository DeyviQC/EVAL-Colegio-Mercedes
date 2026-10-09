<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Domain\Academic\AcademicNameKey;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
use Illuminate\Database\Capsule\Manager as DB;

[$script,$mode,$actor,$target,$barrier,$signals]=$argv;
$db=DB::connection();
if($db->getDatabaseName()!=='eval_u3_test'){throw new RuntimeException('U3 test database required.');}
echo "STARTED\n"; fflush(STDOUT);
try {
    (new AcademicWriteTransaction($db))->execute($actor,
        fn()=>new AcademicLockSet($mode==='period'?['academic_periods'=>[$target]]:[]),
        function()use($db,$mode,$target,$barrier,$signals){
            $eligible=$mode==='period'?!$db->table('academic_periods')->where('state','active')->exists():
                !$db->table('grades')->where('name_key',AcademicNameKey::generate($target))->where('is_active',true)->exists();
            if($barrier==='hold'){
                echo "HELD\n";fflush(STDOUT);$deadline=microtime(true)+15;
                while(!is_file($signals.'/go')){if(microtime(true)>$deadline){throw new RuntimeException('Barrier aborted.');}usleep(10000);}
            }
            return $eligible;
        },function()use($db,$mode,$target){
            if($mode==='period'){
                $db->table('academic_periods')->where('id',$target)->update(['state'=>'active']);
                return new AcademicMutationResult('ok',[new LifecycleEvent('period',$target,'activated','planned','active')]);
            }
            $id=$db->table('grades')->insertGetId(['name'=>$target,'name_key'=>AcademicNameKey::generate($target),'is_active'=>true]);
            return new AcademicMutationResult('ok',[new LifecycleEvent('grade',$id,'created',null,'active')]);
        });
    echo "COMMITTED\n";
} catch(AcademicTransactionFailure $error){echo $error->category."\n";}
fflush(STDOUT);
