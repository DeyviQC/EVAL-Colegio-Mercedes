<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use App\Domain\Academic\AcademicNameKey;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
use Tests\Support\U3TestCase;

final class AcademicWriteTransactionTest extends U3TestCase
{
    private function rename(int $grade,string $label):AcademicMutationResult
    {
        $before=$this->db->table('grades')->where('id',$grade)->value('name');
        $this->db->table('grades')->where('id',$grade)->update(['name'=>$label,'name_key'=>AcademicNameKey::generate($label)]);
        return new AcademicMutationResult('saved',[new LifecycleEvent('grade',$grade,'renamed','active','active',['previous_name'=>$before,'new_name'=>$label])]);
    }
    public function testGuardPrecedesDiscoveryAndAllocationFollowsValidation():void
    {
        $grade=$this->grade(); $before=$this->ordinal(); $validated=false;
        $transaction=new AcademicWriteTransaction($this->db);
        $result=$transaction->execute($this->actorId,
            fn ()=>new AcademicLockSet(['grades'=>[$grade],'teachers'=>[$this->actorId,$this->lowIdentity]]),
            function($context,$key)use($grade,$before,&$validated) {
                $this->assertSame($before,$this->ordinal());
                $this->assertSame($before+1,$key);
                $this->assertSame($grade,(int)$context->row('grades',$grade)['id']);
                $validated=true; return true;
            },fn ()=>$this->rename($grade,'Saved '.bin2hex(random_bytes(4))));
        $this->assertSame('saved',$result);
        $this->assertTrue($validated);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->events());
        $trace=$transaction->trace();
        $this->assertSame('guard',$trace[0]['phase']);
        $identityLocks=array_values(array_filter($trace,fn($step)=>($step['table']??null)==='retained_identities'));
        $this->assertCount(1,$identityLocks);
        $this->assertSame([(string)$this->lowIdentity,(string)$this->actorId],$identityLocks[0]['ids']);
    }
    public function testValidationDenialDoesNotAllocateOrMutate():void
    {
        $grade=$this->grade(); $before=$this->ordinal(); $name=$this->db->table('grades')->where('id',$grade)->value('name');
        try {
            (new AcademicWriteTransaction($this->db))->execute($this->actorId,fn()=>new AcademicLockSet(['grades'=>[$grade]]),fn()=>false,fn()=>$this->rename($grade,'Denied'));
            $this->fail('Denied validation committed.');
        } catch(AcademicTransactionFailure $error) { $this->assertSame('validation_denied',$error->category); }
        $this->assertSame($before,$this->ordinal());
        $this->assertSame($name,$this->db->table('grades')->where('id',$grade)->value('name'));
        $this->assertSame(0,$this->events());
    }
    public function testEventFailureRollsBackStateOrdinalAndEarlierEvent():void
    {
        $grade=$this->grade(); $before=$this->ordinal(); $name=$this->db->table('grades')->where('id',$grade)->value('name');
        try {
            (new AcademicWriteTransaction($this->db))->execute($this->actorId,fn()=>new AcademicLockSet(['grades'=>[$grade]]),fn()=>true,
                function()use($grade) {
                    $first=$this->rename($grade,'Uncommitted');
                    return new AcademicMutationResult('invalid',[$first->events[0],new LifecycleEvent('grade',PHP_INT_MAX,'renamed','active','active')]);
                });
            $this->fail('Invalid event committed.');
        } catch(AcademicTransactionFailure $error) { $this->assertSame('integrity_conflict',$error->category); }
        $this->assertSame($before,$this->ordinal());
        $this->assertSame($name,$this->db->table('grades')->where('id',$grade)->value('name'));
        $this->assertSame(0,$this->events());
    }
    public function testNestedTransactionsAreRejectedWithoutTakingOwnership():void
    {
        $this->db->beginTransaction();
        $this->expectException(AcademicTransactionFailure::class);
        (new AcademicWriteTransaction($this->db))->execute($this->actorId,fn()=>new AcademicLockSet(),fn()=>true,fn()=>new AcademicMutationResult('bad'));
    }
    public function testRediscoveryRestartsWithTheCompleteUnion():void
    {
        $low=$this->grade(); $high=$this->grade(); $calls=0; $validated=0; $before=$this->ordinal();
        $transaction=new AcademicWriteTransaction($this->db);
        $transaction->execute($this->actorId,function()use($low,$high,&$calls) {
            return new AcademicLockSet(['grades'=>++$calls===1?[$high]:[$high,$low]]);
        },function()use(&$validated){$validated++;return true;},fn()=>$this->rename($high,'Rediscovered '.bin2hex(random_bytes(4))));
        $this->assertSame(4,$calls);
        $this->assertSame(1,$validated);
        $this->assertSame($before+1,$this->ordinal());
        $locks=array_values(array_filter($transaction->trace(),fn($s)=>($s['table']??null)==='grades'));
        $this->assertSame([(string)$high],$locks[0]['ids']);
        $this->assertSame([(string)$low,(string)$high],$locks[1]['ids']);
        $this->assertSame(2,$locks[1]['attempt']);
    }
    private function deadlock():\Illuminate\Database\QueryException
    {
        $cause=new \PDOException('Injected deadlock after mutation');
        $cause->errorInfo=['40001',1213,'Injected deadlock after mutation'];
        return new \Illuminate\Database\QueryException('default','test fault',[],$cause);
    }
    public function testFullRetryRollsBackMutationAndRevalidatesBeforeReallocation():void
    {
        $grade=$this->grade(); $before=$this->ordinal(); $attempts=0; $keys=[];
        $transaction=new AcademicWriteTransaction($this->db,function()use($before){
            $this->assertSame(0,$this->db->transactionLevel());
            $this->assertFalse($this->db->getPdo()->inTransaction());
            $this->assertSame($before,$this->ordinal());
            $this->assertSame(0,$this->events());
        });
        $transaction->execute($this->actorId,fn()=>new AcademicLockSet(['grades'=>[$grade]]),
            function($c,$key)use(&$keys){$keys[]=$key;return true;},function()use($grade,&$attempts){
                $result=$this->rename($grade,'Retried '.bin2hex(random_bytes(4)));
                if(++$attempts===1){throw $this->deadlock();} return $result;
            });
        $this->assertSame(2,$attempts);
        $this->assertSame([$before+1,$before+1],$keys);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->events());
    }
    public function testRetryExhaustionIsBoundedAndLeavesNoChanges():void
    {
        $before=$this->ordinal(); $attempts=0;
        try {
            (new AcademicWriteTransaction($this->db))->execute($this->actorId,fn()=>new AcademicLockSet(),fn()=>true,
                function()use(&$attempts){$attempts++;throw $this->deadlock();});
            $this->fail('Retry limit ignored.');
        } catch(AcademicTransactionFailure $error){$this->assertSame('retry_exhausted',$error->category);}
        $this->assertSame(3,$attempts);
        $this->assertSame($before,$this->ordinal());
        $this->assertSame(0,$this->events());
    }
    public function testRealLockTimeoutRetriesWithFreshLockedState():void
    {
        $grade=$this->grade(); $before=$this->ordinal(); $validations=0; $retries=0;
        $this->migration->beginTransaction();
        $this->migration->table('grades')->where('id',$grade)->update(['is_active'=>false]);
        $this->db->statement('SET SESSION innodb_lock_wait_timeout=1');
        $transaction=new AcademicWriteTransaction($this->db,function($attempt,$error)use(&$retries){
            $retries++; $this->assertSame(1205,(int)$error->errorInfo[1]);
            $this->assertFalse($this->db->getPdo()->inTransaction());
            $this->migration->commit();
        });
        try {
            $transaction->execute($this->actorId,fn()=>new AcademicLockSet(['grades'=>[$grade]]),
                function($context)use($grade,&$validations){$validations++;return (bool)$context->row('grades',$grade)['is_active'];},
                fn()=>$this->rename($grade,'Forbidden'));
            $this->fail('Fresh inactive state ignored.');
        } catch(AcademicTransactionFailure $error){$this->assertSame('validation_denied',$error->category);}
        finally{$this->db->statement('SET SESSION innodb_lock_wait_timeout=50');}
        $this->assertSame(1,$retries); $this->assertSame(1,$validations);
        $this->assertSame($before,$this->ordinal()); $this->assertSame(0,$this->events());
    }
    public function testInactiveActorIsDeniedBeforeAllocation():void
    {
        $before=$this->ordinal();
        $this->migration->table('retained_identities')->where('id',$this->actorId)->update(['credential_status'=>'deactivated']);
        try {
            (new AcademicWriteTransaction($this->db))->execute($this->actorId,fn()=>new AcademicLockSet(),fn()=>true,fn()=>new AcademicMutationResult('bad'));
            $this->fail('Inactive actor accepted.');
        } catch(AcademicTransactionFailure $error){$this->assertSame('actor_inactive',$error->category);}
        $this->assertSame($before,$this->ordinal()); $this->assertSame(0,$this->events());
    }
    public function testLostCommitAcknowledgementNeverReplaysCommittedMutation():void
    {
        $fault=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public function commit(){parent::commit();throw new \RuntimeException('Injected acknowledgement loss');}
        };
        $grade=$this->grade(); $before=$this->ordinal(); $mutations=0;
        try {
            (new AcademicWriteTransaction($fault))->execute($this->actorId,fn()=>new AcademicLockSet(['grades'=>[$grade]]),fn()=>true,
                function()use($grade,&$mutations){$mutations++;return $this->rename($grade,'Committed once '.bin2hex(random_bytes(4)));});
            $this->fail('Uncertain commit acknowledged.');
        } catch(AcademicTransactionFailure $error){
            $this->assertSame('commit_outcome_unknown',$error->category);
            $this->assertSame($error->correlationId,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actorId)->value('correlation_id'));
        }
        $this->assertSame(1,$mutations); $this->assertSame($before+1,$this->ordinal()); $this->assertSame(1,$this->events());
    }
    public function testRollbackFailureStopsWithoutRetry():void
    {
        $fault=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public bool $fail=true;
            public function rollBack($toLevel=null){if($this->fail){throw new \RuntimeException('Injected rollback failure');}parent::rollBack($toLevel);}
        };
        $attempts=0; $before=$this->ordinal();
        try {
            (new AcademicWriteTransaction($fault))->execute($this->actorId,fn()=>new AcademicLockSet(),fn()=>true,
                function()use(&$attempts){$attempts++;throw $this->deadlock();});
            $this->fail('Failed rollback retried.');
        } catch(AcademicTransactionFailure $error){$this->assertSame('rollback_outcome_unknown',$error->category);}
        finally{$fault->fail=false;$fault->rollBack(0);}
        $this->assertSame(1,$attempts); $this->assertSame($before,$this->ordinal()); $this->assertSame(0,$this->events());
    }
    private function worker(string $mode,int|string $target,bool $hold):array
    {
        $signals=sys_get_temp_dir().'/eval-u3-'.bin2hex(random_bytes(8)); mkdir($signals);
        $command=[PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
            dirname(__DIR__,2).'/Support/u3-race-worker.php',$mode,(string)$this->actorId,(string)$target,$hold?'hold':'free',$signals];
        $process=proc_open($command,[0=>['file','NUL','r'],1=>['file',$signals.'/out','w'],2=>['file',$signals.'/err','w']],$pipes);
        $this->assertIsResource($process);
        return [$process,$signals];
    }
    private function awaitOutput(string $signals,string $needle):string
    {
        $output=''; $deadline=microtime(true)+10;
        do{$output=is_file($signals.'/out')?(string)file_get_contents($signals.'/out'):'';if(str_contains($output,$needle)){return $output;}usleep(10000);}while(microtime(true)<$deadline);
        $this->fail('Worker barrier timed out: '.$output.(string)file_get_contents($signals.'/err'));
    }
    private function competingWrites(string $mode,int|string $first,int|string $second):void
    {
        $before=$this->ordinal(); $workers=[];
        try {
            $workers[]=$a=$this->worker($mode,$first,true);
            $this->awaitOutput($a[1],'HELD');
            $workers[]=$b=$this->worker($mode,$second,false);
            $this->awaitOutput($b[1],'STARTED');
            $waiting=false; $deadline=microtime(true)+5;
            do {
                foreach($this->db->select('SHOW PROCESSLIST') as $row){
                    if(str_contains(strtolower((string)$row->Info),'academic_write_guard') && str_contains(strtolower((string)$row->Info),'for update')){$waiting=true;break;}
                }
                if(!$waiting){usleep(10000);}
            }while(!$waiting && microtime(true)<$deadline);
            $this->assertTrue($waiting,'Second real connection must wait on the exclusive guard.');
            file_put_contents($a[1].'/go','go');
            $this->assertStringContainsString('COMMITTED',$this->awaitOutput($a[1],'COMMITTED'));
            $this->assertStringContainsString('validation_denied',$this->awaitOutput($b[1],'validation_denied'));
            $this->assertSame($before+1,$this->ordinal()); $this->assertSame(1,$this->events());
        } finally {
            foreach($workers as [$process,$signals]){
                if(proc_get_status($process)['running']){proc_terminate($process);}
                proc_close($process);
                foreach(glob($signals.'/*') as $file){unlink($file);} rmdir($signals);
            }
        }
    }
    public function testRealEmptyActiveSetPeriodRaceCommitsOnlyOne():void
    {
        $this->assertSame(0,$this->db->table('academic_periods')->where('state','active')->count());
        $this->competingWrites('period',$this->period(),$this->period());
        $this->assertSame(1,$this->db->table('academic_periods')->where('state','active')->count());
    }
    public function testRealCompetingCatalogCreationUsesFreshConflictHistory():void
    {
        $name='Race grade '.bin2hex(random_bytes(6));
        $this->competingWrites('grade',$name,$name);
        $this->assertSame(1,$this->db->table('grades')->where('name_key',AcademicNameKey::generate($name))->where('is_active',true)->count());
    }
}
