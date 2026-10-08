<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Application\Academic\Commands\TeachingAssignmentCommands;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;

abstract class AssignmentTestCase extends EnrollmentTestCase
{
    protected const DATABASE='eval_u7_test';
    protected TeachingAssignmentCommands $assignments;
    protected string $teacher;
    protected string $entry;
    protected function setUp():void
    {
        parent::setUp();$this->assignments=new TeachingAssignmentCommands($this->db);
        $this->teacher=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $this->entry=$this->catalog->create($this->actor,'entry',['name'=>'Subject '.$this->suffix,'kind'=>'subject']);
    }
    protected function assignmentInput(array $overrides=[]):array
    {
        return array_replace(['teacher_id'=>$this->teacher,'academic_period_id'=>$this->period,
            'instructional_entry_id'=>$this->entry,'grade_id'=>$this->grade,'section_id'=>$this->section,
            'effective_from'=>'2026-03-01'],$overrides);
    }
    protected function assignment(array $overrides=[]):string {return $this->assignments->create($this->actor,$this->assignmentInput($overrides));}
    protected function assignmentDenied(string $category,callable $operation):void
    {
        $before=$this->ordinal();$events=$this->db->table('academic_lifecycle_events')->count();$rows=$this->db->table('teaching_assignments')->count();
        try{$operation();$this->fail('Invalid assignment command succeeded.');}
        catch(AcademicCommandFailure|AcademicTransactionFailure $error){$this->assertSame($category,$error->category);}
        $this->assertSame($before,$this->ordinal());$this->assertSame($events,$this->db->table('academic_lifecycle_events')->count());
        $this->assertSame($rows,$this->db->table('teaching_assignments')->count());$this->assertFalse($this->db->getPdo()->inTransaction());
    }
    protected function assignmentRace(string $firstMode,string $secondMode,string $secondOutcome,array $firstInput,array $secondInput):void
    {
        $worker=function($mode,$hold,$input){
            $signals=sys_get_temp_dir().'/eval-u7-'.bin2hex(random_bytes(8));mkdir($signals);
            $process=proc_open([PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
                __DIR__.'/u7-race-worker.php',$mode,$this->actor->identityId,json_encode($input),$hold?'hold':'free',$signals],
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
