<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\CreateActivityReference;
use App\Application\Academic\Commands\AcceptSubmissionReference;
abstract class SubmissionReferenceTestCase extends AssignmentTestCase
{
    protected const DATABASE='eval_u11_test';
    protected AuthenticatedActor $studentActor;
    protected AuthenticatedActor $teacherActor;
    protected string $assignmentId;
    protected string $activityId;
    protected string $enrollmentId;
    protected function setUp():void
    {
        parent::setUp();$this->studentActor=$this->actor('student');$this->teacherActor=$this->actor('teacher');
        $this->assignmentId=$this->assignment(['teacher_id'=>$this->teacherActor->identityId]);$this->assignments->activate($this->actor,$this->assignmentId);
        $this->activityId=(new CreateActivityReference($this->db))->execute($this->teacherActor,['assignment_id'=>$this->assignmentId]);
        $this->enrollmentId=$this->enrollment(['student_id'=>$this->studentActor->identityId]);
    }
    protected function accept(array $input=[]):string
    {return (new AcceptSubmissionReference($this->db))->execute($this->studentActor,array_replace(['activity_id'=>$this->activityId],$input));}
    protected function submissionDenied(string $category,callable $operation):void
    {$count=$this->db->table('submission_references')->count();$this->assignmentDenied($category,$operation);$this->assertSame($count,$this->db->table('submission_references')->count());}
    protected function acceptanceRace(string $firstMode,string $secondMode,string $secondOutcome,array $firstInput,array $secondInput):void
    {
        $worker=function($mode,$hold,$input){
            $signals=sys_get_temp_dir().'/eval-u11-'.bin2hex(random_bytes(8));mkdir($signals);
            $process=proc_open([PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
                __DIR__.'/u11-race-worker.php',$mode,$this->actor->identityId,json_encode($input),$hold?'hold':'free',$signals],
                [0=>['file','NUL','r'],1=>['file',$signals.'/out','w'],2=>['file',$signals.'/err','w']],$pipes);
            $this->assertIsResource($process);return [$process,$signals];
        };
        $output=function($signals,$needle){
            $deadline=microtime(true)+10;
            do{$text=is_file($signals.'/out')?(string)file_get_contents($signals.'/out'):'';
                if(str_contains($text,$needle)){return $text;}usleep(10000);
            }while(microtime(true)<$deadline);
            $this->fail('Worker barrier timed out: '.$text.(string)file_get_contents($signals.'/err'));
        };
        $workers=[];
        try{
            $workers[]=$a=$worker($firstMode,true,$firstInput);$output($a[1],'HELD');
            $workers[]=$b=$worker($secondMode,false,$secondInput);$output($b[1],'STARTED');
            $waiting=false;$deadline=microtime(true)+5;
            do{
                foreach($this->db->select('SHOW PROCESSLIST') as $row){
                    if(str_contains(strtolower((string)$row->Info),'academic_write_guard') && str_contains(strtolower((string)$row->Info),'for update')){$waiting=true;break;}
                }
                if(!$waiting){usleep(10000);}
            }while(!$waiting && microtime(true)<$deadline);
            $this->assertTrue($waiting,'Second real command must wait on the guard.');
            file_put_contents($a[1].'/go','go');
            $this->assertStringContainsString('COMMITTED',$output($a[1],'COMMITTED'));
            $this->assertStringContainsString($secondOutcome,$output($b[1],$secondOutcome));
        }finally{
            foreach($workers as [$process,$signals]){
                if(proc_get_status($process)['running']){proc_terminate($process);}proc_close($process);
                foreach(glob($signals.'/*') as $file){unlink($file);}rmdir($signals);
            }
        }
    }
}
