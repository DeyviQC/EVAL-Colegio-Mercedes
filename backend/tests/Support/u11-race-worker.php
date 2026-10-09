<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\AcademicPeriodCommands;
use App\Application\Academic\Commands\TeachingAssignmentCommands;
use App\Application\Academic\Commands\AcceptSubmissionReference;
use App\Application\Academic\Commands\TransferStudent;
use App\Infrastructure\Authentication\LocalUserProvider;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Hashing\BcryptHasher;

[$script,$mode,$actorId,$payload,$hold,$signals]=$argv;
$db=DB::connection();
if($db->getDatabaseName()!=='eval_u11_test'){throw new RuntimeException('U11 fixture database required.');}
$input=json_decode($payload,true,flags:JSON_THROW_ON_ERROR);
$provider=new LocalUserProvider($db,new BcryptHasher(),'local_credentials');
$actor=$provider->actor($provider->retrieveById($mode==='publish'?$input['publisher_id']:($mode==='accept'?$input['student_id']:$actorId)));
$held=false;
$db->listen(function($query)use($mode,$hold,$signals,&$held){
    $needle=$mode==='publish'?'insert into `course_materials`':($mode==='replace'?'update `teaching_assignments`':($mode==='period_close'?'update `academic_periods`':($mode==='assignment_close'?'update `teaching_assignments`':($mode==='transfer'?'update `student_enrollments`':'insert into `submission_references`'))));
    if($hold==='hold' && !$held && str_contains(strtolower($query->sql),$needle)){
        $held=true;echo "HELD\n";fflush(STDOUT);$deadline=microtime(true)+15;
        while(!is_file($signals.'/go')){if(microtime(true)>$deadline){throw new RuntimeException('Barrier aborted.');}usleep(10000);}
    }
});
echo "STARTED\n";fflush(STDOUT);
try{
    if($mode==='publish'){(new \App\Application\Academic\Commands\CourseMaterials($db,new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($input['storage'])))->publish($actor,$input['assignment_id'],['title'=>'Race material'],$input['source'],'race.pdf');}
    elseif($mode==='replace'){(new \App\Application\Academic\Commands\ReplaceTeacher($db))->execute($actor,$input['assignment_id'],['teacher_id'=>$input['teacher_id']]);}
    elseif($mode==='accept'){(new AcceptSubmissionReference($db))->execute($actor,['activity_id'=>$input['activity_id']]);}
    elseif($mode==='transfer'){(new TransferStudent($db))->execute($actor,$input['enrollment_id'],['grade_id'=>$input['grade_id'],'section_id'=>$input['section_id']]);}
    elseif($mode==='assignment_close'){(new TeachingAssignmentCommands($db))->close($actor,$input['assignment_id'],(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d'));}
    elseif($mode==='period_close'){(new AcademicPeriodCommands($db))->close($actor,$input['academic_period_id']);}
    else{throw new RuntimeException('Unknown U11 fixture mode');}
    echo "COMMITTED\n";
}catch(AcademicCommandFailure $error){echo $error->category."\n";}
fflush(STDOUT);
