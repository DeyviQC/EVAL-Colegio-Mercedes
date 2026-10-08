<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use App\Application\Academic\Commands\TransferStudent;
use Tests\Support\EnrollmentTestCase;

final class StudentTransferTest extends EnrollmentTestCase
{
    protected const DATABASE='eval_u6_test';
    private TransferStudent $transfer;
    private string $destination;
    protected function setUp():void
    {
        parent::setUp();$this->transfer=new TransferStudent($this->db);
        $this->destination=$this->catalog->create($this->actor,'section',['name'=>'B','grade_id'=>$this->grade]);
    }
    private function destination():array {return ['grade_id'=>$this->grade,'section_id'=>$this->destination];}
    public function testTransferCreatesDistinctSuccessorAndSharedActualBoundary():void
    {
        $prior=$this->enrollment();$before=$this->ordinal();
        $result=$this->transfer->execute($this->actor,$prior,$this->destination());
        $this->assertArrayHasKey('successor_id',$result);
        $old=$this->db->table('student_enrollments')->where('id',$prior)->first();
        $new=$this->db->table('student_enrollments')->where('id',$result['successor_id'])->first();
        $this->assertNotNull($new);$this->assertNotSame($prior,$result['successor_id']);
        $this->assertSame('transferred',$old->state);$this->assertSame('active',$new->state);
        $this->assertSame($before+1,(int)$old->operational_end_key);$this->assertSame($before+1,(int)$new->operational_start_key);
        $this->assertSame($this->section,(string)$old->section_id);$this->assertSame($this->destination,(string)$new->section_id);
    }
    public function testSameScopeCannotPretendToTransfer():void
    {
        $prior=$this->enrollment();$this->denied('scope_unchanged',fn()=>$this->transfer->execute($this->actor,$prior,
            ['grade_id'=>$this->grade,'section_id'=>$this->section]));
    }
    public function testScheduledRetroactiveAndCorrectionFieldsAreNotAccepted():void
    {
        $prior=$this->enrollment();
        foreach([['mode'=>'scheduled'],['effective_on'=>'2020-01-01'],['correction'=>true]] as $extra){
            $this->denied('invalid_input',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()+$extra));
        }
    }
    public function testTwoSameDayTransfersRetainNonemptyTouchingHistories():void
    {
        $prior=$this->enrollment(['effective_from'=>$this->today()]);$k1=$this->ordinal();
        $first=$this->transfer->execute($this->actor,$prior,$this->destination());$this->assertArrayHasKey('successor_id',$first);
        $second=$this->transfer->execute($this->actor,$first['successor_id'],['grade_id'=>$this->grade,'section_id'=>$this->section]);
        $this->assertArrayHasKey('successor_id',$second);
        $rows=$this->db->table('student_enrollments')->where('student_id',$this->student)->orderBy('id')->get();
        $this->assertCount(3,$rows);$this->assertSame($k1,(int)$rows[0]->operational_start_key);
        $this->assertSame($k1+1,(int)$rows[0]->operational_end_key);$this->assertSame($k1+1,(int)$rows[1]->operational_start_key);
        $this->assertSame($k1+2,(int)$rows[1]->operational_end_key);$this->assertSame($k1+2,(int)$rows[2]->operational_start_key);
        $this->assertSame($this->today(),$rows[0]->effective_until);$this->assertSame($this->today(),$rows[1]->effective_until);
    }
    public function testConfirmationAndBothEventsUseServerDateKeyCorrelationAndStablePriorScope():void
    {
        $prior=$this->enrollment(['effective_until'=>'2027-01-01']);$original=(array)$this->db->table('student_enrollments')->where('id',$prior)->first();
        $result=$this->transfer->execute($this->actor,$prior,$this->destination());
        $this->assertSame($prior,$result['prior_id']);$this->assertSame((string)$this->ordinal(),$result['operation_key']);
        $this->assertSame($this->today(),$result['effective_on']);
        $after=(array)$this->db->table('student_enrollments')->where('id',$prior)->first();
        foreach(['id','student_id','academic_period_id','grade_id','section_id','effective_from','operational_start_key'] as $column){$this->assertSame($original[$column],$after[$column]);}
        $oldEvent=$this->db->table('academic_lifecycle_events')->where('enrollment_id',$prior)->where('event_type','transferred')->first();
        $newEvent=$this->db->table('academic_lifecycle_events')->where('enrollment_id',$result['successor_id'])->first();
        $this->assertSame($oldEvent->correlation_id,$newEvent->correlation_id);
        $this->assertSame((string)$oldEvent->operation_key,(string)$newEvent->operation_key);
        $this->assertSame($this->today(),$oldEvent->effective_on);$this->assertSame($this->today(),$newEvent->effective_on);
        $this->assertSame($result['successor_id'],json_decode($oldEvent->metadata,true)['successor_id']);
        $this->assertSame($prior,json_decode($newEvent->metadata,true)['predecessor_id']);
        $this->assertSame('2027-01-01',json_decode($oldEvent->metadata,true)['previous_declared_end']);
        $active=$this->db->table('student_enrollments')->where('student_id',$this->student)->where('academic_period_id',$this->period)->where('state','active')->first();
        $this->assertSame($result['successor_id'],(string)$active->id);$this->assertSame($this->destination,(string)$active->section_id);
    }
    public function testClosedAndPlannedParentsCannotCreateSuccessor():void
    {
        $prior=$this->enrollment();$original=(array)$this->db->table('student_enrollments')->where('id',$prior)->first();
        $this->periods->close($this->actor,$this->period);
        $this->denied('period_not_active',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));
        // Legacy/inconsistent parent-state fixture, not an authorized reversed period transition.
        $this->migration->table('academic_periods')->where('id',$this->period)->update(['state'=>'planned']);
        $this->denied('period_not_active',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));
        $this->assertSame($original,(array)$this->db->table('student_enrollments')->where('id',$prior)->first());
    }
    public function testInactiveMismatchedAndMissingDestinationCannotMutatePrior():void
    {
        $prior=$this->enrollment();$grade=$this->catalog->create($this->actor,'grade',['name'=>'Other '.$this->suffix]);
        $this->denied('scope_mismatch',fn()=>$this->transfer->execute($this->actor,$prior,['grade_id'=>$grade,'section_id'=>$this->destination]));
        $this->catalog->update($this->actor,'section',$this->destination,['is_active'=>false]);
        $this->denied('inactive_catalog_reference',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));
        $this->denied('missing_lock_target',fn()=>$this->transfer->execute($this->actor,$prior,['grade_id'=>$grade,'section_id'=>PHP_INT_MAX]));
    }
    public function testStudentPeriodRecipientAndClockSelectorsCannotOverrideOriginalContext():void
    {
        $prior=$this->enrollment();
        foreach(['student_id'=>PHP_INT_MAX,'academic_period_id'=>PHP_INT_MAX,'recipient_id'=>1,
            'teacher_id'=>1,'operational_start_key'=>1,'scheduled_for'=>'2099-01-01','effective_until'=>'2026-01-01'] as $field=>$value){
            $this->denied('invalid_input',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()+[$field=>$value]));
        }
    }
    public function testTerminalPriorCannotTransferAgain():void
    {
        $prior=$this->enrollment();$result=$this->transfer->execute($this->actor,$prior,$this->destination());
        $this->denied('invalid_transition',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));
        $this->commands->close($this->actor,$result['successor_id'],$this->today());
        $this->denied('invalid_transition',fn()=>$this->transfer->execute($this->actor,$result['successor_id'],['grade_id'=>$this->grade,'section_id'=>$this->section]));
    }
    public function testImmediateTransferCannotEndBeforeDeclaredStart():void
    {
        $future=(new \DateTimeImmutable($this->today()))->modify('+1 day')->format('Y-m-d');
        $prior=$this->enrollment(['effective_from'=>$future]);
        $this->denied('invalid_interval',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));
    }
    public function testOtherRolesAndRevokedCredentialsCannotTransfer():void
    {
        $prior=$this->enrollment();
        foreach(['vice_principal','teacher','student'] as $role){
            $actor=$this->actor($role);$this->denied('forbidden',fn()=>$this->transfer->execute($actor,$prior,$this->destination()));
        }
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>null]);
        $this->denied('forbidden',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));
    }
    public function testSuccessorInsertFailureRestoresPriorAndOrdinalBeforeConfirmation():void
    {
        $prior=$this->enrollment();$original=(array)$this->db->table('student_enrollments')->where('id',$prior)->first();
        $trigger='u6_successor_fault_'.(int)$this->actor->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON student_enrollments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected successor failure'");
        try{$this->denied('integrity_conflict',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));}
        finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame($original,(array)$this->db->table('student_enrollments')->where('id',$prior)->first());
    }
    public function testSecondEventFailureRollsBackBothRowsAndEarlierEvent():void
    {
        $prior=$this->enrollment();$original=(array)$this->db->table('student_enrollments')->where('id',$prior)->first();
        $trigger='u6_event_fault_'.(int)$this->actor->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON academic_lifecycle_events FOR EACH ROW BEGIN IF NEW.event_type='created' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected second transfer event failure'; END IF; END");
        try{$this->denied('integrity_conflict',fn()=>$this->transfer->execute($this->actor,$prior,$this->destination()));}
        finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame($original,(array)$this->db->table('student_enrollments')->where('id',$prior)->first());
    }
    public function testReversedCatalogIdsStillAcquireCompleteAscendingLockUnion():void
    {
        $higherGrade=$this->catalog->create($this->actor,'grade',['name'=>'Higher '.$this->suffix]);
        $higherSection=$this->catalog->create($this->actor,'section',['name'=>'Z','grade_id'=>$higherGrade]);
        $prior=$this->enrollment(['grade_id'=>$higherGrade,'section_id'=>$higherSection]);$locks=[];
        $this->db->listen(function($query)use(&$locks){
            if(str_contains(strtolower($query->sql),'for update') && preg_match('/from `([a-z_]+)`/',$query->sql,$match)){
                $locks[$match[1]][]=array_map('strval',$query->bindings);
            }
        });
        $result=$this->transfer->execute($this->actor,$prior,['grade_id'=>$this->grade,'section_id'=>$this->section]);
        $this->assertSame([$this->grade,$higherGrade],$locks['grades'][0]);
        $this->assertSame([$this->section,$higherSection],$locks['sections'][0]);
        $this->assertCount(1,$locks['retained_identities']);
        $this->assertSame($this->grade,(string)$this->db->table('student_enrollments')->where('id',$result['successor_id'])->value('grade_id'));
    }
    private function retryConnection(?\Closure $afterRollback=null):\Illuminate\Database\MySqlConnection
    {
        return new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig(),$afterRollback) extends \Illuminate\Database\MySqlConnection {
            public int $begins=0;
            private bool $injected=false;
            public function __construct($pdo,$database,$prefix,$config,private ?\Closure $afterRollback){parent::__construct($pdo,$database,$prefix,$config);}
            public function beginTransaction(){$this->begins++;parent::beginTransaction();}
            public function insert($query,$bindings=[],$sequence=null){
                if(!$this->injected && str_contains($query,'insert into `student_enrollments`')){
                    $this->injected=true;$cause=new \PDOException('Injected retry after prior mutation');$cause->errorInfo=['40001',1213,'Injected retry'];
                    throw new \Illuminate\Database\QueryException('default','test successor fault',[],$cause);
                }return parent::insert($query,$bindings,$sequence);
            }
            public function rollBack($toLevel=null){parent::rollBack($toLevel);if($this->afterRollback){($this->afterRollback)();$this->afterRollback=null;}}
        };
    }
    public function testWholeTransferRetryAfterConfirmedRollbackCommitsOnlyOnce():void
    {
        $prior=$this->enrollment();$before=$this->ordinal();
        $connection=$this->retryConnection(function()use($prior,$before){
            $this->assertFalse($this->db->getPdo()->inTransaction());$this->assertSame($before,$this->ordinal());
            $this->assertSame('active',$this->db->table('student_enrollments')->where('id',$prior)->value('state'));
        });
        $result=(new TransferStudent($connection))->execute($this->actor,$prior,$this->destination());
        $this->assertSame(2,$connection->begins);$this->assertSame($before+1,$this->ordinal());
        $this->assertSame(2,$this->db->table('student_enrollments')->where('student_id',$this->student)->count());
        $this->assertSame($result['successor_id'],(string)$this->db->table('student_enrollments')->where('student_id',$this->student)->where('state','active')->value('id'));
    }
    public function testRetryRevalidatesAuthorityRevokedAfterFirstRollback():void
    {
        $prior=$this->enrollment();$connection=$this->retryConnection(function(){
            $this->migration->table('local_role_grants')->where('identity_id',$this->actor->identityId)->delete();
        });
        $this->denied('forbidden',fn()=>(new TransferStudent($connection))->execute($this->actor,$prior,$this->destination()));
        $this->assertSame(2,$connection->begins);
        $this->assertSame('active',$this->db->table('student_enrollments')->where('id',$prior)->value('state'));
    }
    public function testLostCommitAcknowledgementDoesNotConfirmOrReplayTransfer():void
    {
        $prior=$this->enrollment();$before=$this->ordinal();
        $connection=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public int $commits=0;
            public function commit(){$this->commits++;parent::commit();throw new \RuntimeException('Injected lost acknowledgement');}
        };
        try{(new TransferStudent($connection))->execute($this->actor,$prior,$this->destination());$this->fail('Uncertain transfer confirmed.');}
        catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){
            $this->assertSame('commit_outcome_unknown',$error->category);
            $this->assertSame($error->correlationId,$this->db->table('academic_lifecycle_events')->where('enrollment_id',$prior)->where('event_type','transferred')->value('correlation_id'));
        }
        $this->assertSame(1,$connection->commits);$this->assertSame($before+1,$this->ordinal());
        $this->assertSame(2,$this->db->table('student_enrollments')->where('student_id',$this->student)->count());
    }
    private function transferRace(string $prior,string $firstMode,string $secondMode,array $secondDestination,string $secondOutcome):void
    {
        $worker=function($mode,$destination,$hold)use($prior){
            $signals=sys_get_temp_dir().'/eval-u6-'.bin2hex(random_bytes(8));mkdir($signals);
            $payload=['prior_id'=>$prior,'period_id'=>$this->period,'destination'=>$destination];
            $process=proc_open([PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
                dirname(__DIR__,2).'/Support/u6-race-worker.php',$mode,$this->actor->identityId,json_encode($payload),$hold?'hold':'free',$signals],
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
            $workers[]=$a=$worker($firstMode,$this->destination(),true);$output($a[1],'HELD');
            $workers[]=$b=$worker($secondMode,$secondDestination,false);$output($b[1],'STARTED');
            $waiting=false;$deadline=microtime(true)+5;
            do{
                foreach($this->db->select('SHOW PROCESSLIST') as $row){
                    if(str_contains(strtolower((string)$row->Info),'academic_write_guard') && str_contains(strtolower((string)$row->Info),'for update')){$waiting=true;break;}
                }
                if(!$waiting){usleep(10000);}
            }while(!$waiting && microtime(true)<$deadline);
            $this->assertTrue($waiting,'Second real command must wait on the common guard.');
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
    public function testRealCompetingTransfersCannotCreateConflictingSuccessors():void
    {
        $prior=$this->enrollment();$other=$this->catalog->create($this->actor,'section',['name'=>'C','grade_id'=>$this->grade]);$before=$this->ordinal();
        $this->transferRace($prior,'transfer','transfer',['grade_id'=>$this->grade,'section_id'=>$other],'invalid_transition');
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(2,$this->db->table('student_enrollments')->where('student_id',$this->student)->count());
        $this->assertSame($this->destination,(string)$this->db->table('student_enrollments')->where('student_id',$this->student)->where('state','active')->value('section_id'));
    }
    public function testRealPeriodClosureFirstDeniesWaitingTransferWithoutEndingPrior():void
    {
        $prior=$this->enrollment();$original=(array)$this->db->table('student_enrollments')->where('id',$prior)->first();$before=$this->ordinal();
        $this->transferRace($prior,'period_close','transfer',$this->destination(),'period_not_active');
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame($original,(array)$this->db->table('student_enrollments')->where('id',$prior)->first());
        $this->assertSame(1,$this->db->table('student_enrollments')->where('student_id',$this->student)->count());
    }
    public function testRealTransferFirstThenPeriodClosurePreservesBothIntervals():void
    {
        $prior=$this->enrollment();$before=$this->ordinal();
        $this->transferRace($prior,'transfer','period_close',$this->destination(),'COMMITTED');
        $this->assertSame($before+2,$this->ordinal());
        $this->assertSame('transferred',$this->db->table('student_enrollments')->where('id',$prior)->value('state'));
        $current=$this->db->table('student_enrollments')->where('student_id',$this->student)->where('state','active')->first();
        $this->assertSame($before+1,(int)$current->operational_start_key);$this->assertNull($current->operational_end_key);
        $this->assertSame('closed',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
    }
    public function testTransferredHistoryRemainsOccupiedAtEarlierKeysWithoutOverlappingSuccessor():void
    {
        $prior=$this->enrollment();$start=$this->ordinal();$result=$this->transfer->execute($this->actor,$prior,$this->destination());
        $query=new \App\Infrastructure\Persistence\Academic\EnrollmentOverlapQuery($this->db);$key=(int)$result['operation_key'];
        $this->assertSame([$prior],$query->conflictingIds($this->period,$this->student,$start,$key));
        $this->assertSame([$result['successor_id']],$query->conflictingIds($this->period,$this->student,$key));
    }
    public function testTransferOutOfInactiveOldCatalogToActiveDestinationPreservesHistory():void
    {
        $prior=$this->enrollment();$grade=$this->catalog->create($this->actor,'grade',['name'=>'Next '.$this->suffix]);
        $section=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$grade]);
        $this->catalog->update($this->actor,'grade',$this->grade,['is_active'=>false]);
        $result=$this->transfer->execute($this->actor,$prior,['grade_id'=>$grade,'section_id'=>$section]);
        $this->assertSame($this->grade,(string)$this->db->table('student_enrollments')->where('id',$prior)->value('grade_id'));
        $this->assertSame($grade,(string)$this->db->table('student_enrollments')->where('id',$result['successor_id'])->value('grade_id'));
    }
}
