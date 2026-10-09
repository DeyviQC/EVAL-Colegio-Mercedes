<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use App\Domain\Academic\AcademicWriteGuard;
use Throwable;

final class AcademicWriteTransaction
{
    public const MAX_ATTEMPTS=3;
    private array $trace=[];
    /** afterRollback is a deterministic test seam, invoked only outside confirmed transactions. */
    public function __construct(private Connection $connection,private ?Closure $afterRollback=null) {}

    /** Internal server callbacks only: readonly validation, no external effects, no client callbacks. */
    public function execute(int|string $actorId,Closure $discover,Closure $validate,Closure $mutate): mixed
    {
        if ($this->connection->getDriverName()!=='mysql') { throw new AcademicTransactionFailure('mysql_required'); }
        if ($this->connection->transactionLevel()!==0 || $this->connection->getPdo()->inTransaction()) {
            throw new AcademicTransactionFailure('top_level_transaction_required');
        }
        $actorId=AcademicLockSet::id($actorId);
        $this->trace=[];
        for ($attempt=1;$attempt<=self::MAX_ATTEMPTS;$attempt++) {
            $correlation=bin2hex(random_bytes(16));
            $committing=false;
            $this->connection->beginTransaction();
            try {
                $guard=$this->connection->table('academic_write_guard')->where('id',1)->lockForUpdate()->first();
                $this->step($attempt,'guard');
                if (!$guard) { throw new AcademicTransactionFailure('guard_missing'); }
                if ((int)$guard->last_ordinal>=PHP_INT_MAX) { throw new AcademicTransactionFailure('ordinal_exhausted'); }
                $prospective=(int)$guard->last_ordinal+1;
                $this->step($attempt,'discover');
                $set=$discover($this->connection,$actorId,null);
                if (!$set instanceof AcademicLockSet) { throw new AcademicTransactionFailure('invalid_discovery'); }
                $set=$set->withActor($actorId);
                $records=$set->acquire($this->connection);
                foreach ($set->ordered() as $table=>$ids) { $this->step($attempt,'lock',['table'=>$table,'ids'=>$ids]); }
                $context=new LockedAcademicContext($actorId,$records);
                $fresh=$discover($this->connection,$actorId,$context);
                if (!$fresh instanceof AcademicLockSet) { throw new AcademicTransactionFailure('invalid_discovery'); }
                if ($fresh->withActor($actorId)->ordered()!==$set->ordered()) {
                    throw new AcademicTransactionFailure('rediscovery_required');
                }
                if ($context->row('retained_identities',$actorId)['credential_status']!=='active') {
                    throw new AcademicTransactionFailure('actor_inactive');
                }
                $this->step($attempt,'validate',['key'=>(string)$prospective]);
                if ($validate($context,$prospective)!==true) { throw new AcademicTransactionFailure('validation_denied'); }
                $this->assertOwnership($correlation);
                $boundary=(new AcademicWriteGuard($this->connection))->allocateNextBoundary();
                if ($boundary->ordinal!==$prospective) { throw new AcademicTransactionFailure('allocation_changed'); }
                $this->step($attempt,'allocate',['key'=>$boundary->toServerKey()]);
                $result=$mutate($context,$boundary);
                if (!$result instanceof AcademicMutationResult) { throw new AcademicTransactionFailure('invalid_mutation_result'); }
                $this->assertOwnership($correlation);
                $this->step($attempt,'ledger');
                $writer=new LifecycleEventWriter($this->connection);
                foreach ($result->events as $event) { $writer->append($event,$actorId,$boundary,$correlation); }
                $this->step($attempt,'commit');
                $committing=true;
                $this->connection->commit();
                return $result->value;
            } catch (Throwable $error) {
                try {
                    $this->connection->rollBack(0);
                    if ($this->connection->transactionLevel()!==0 || $this->connection->getPdo()->inTransaction()) {
                        throw new \RuntimeException('Rollback not confirmed.');
                    }
                    $this->step($attempt,'rollback_confirmed');
                } catch (Throwable $rollbackFailure) {
                    throw new AcademicTransactionFailure($committing?'commit_outcome_unknown':'rollback_outcome_unknown',$correlation,$rollbackFailure);
                }
                if ($committing) { throw new AcademicTransactionFailure('commit_outcome_unknown',$correlation,$error); }
                if ($this->retryable($error)) {
                    if ($attempt===self::MAX_ATTEMPTS) { throw new AcademicTransactionFailure('retry_exhausted',$correlation,$error); }
                    $this->step($attempt,'retry');
                    if ($this->afterRollback) { ($this->afterRollback)($attempt,$error); }
                    continue;
                }
                if ($error instanceof QueryException && (in_array($error->errorInfo[0]??null,['23000','45000'],true)
                    || in_array((int)($error->errorInfo[1]??0),[3819,1644],true))) {
                    throw new AcademicTransactionFailure('integrity_conflict',$correlation,$error);
                }
                throw $error;
            }
        }
        throw new AcademicTransactionFailure('retry_exhausted');
    }
    private function assertOwnership(string $correlation):void
    {
        if ($this->connection->transactionLevel()!==1 || !$this->connection->getPdo()->inTransaction()) {
            throw new AcademicTransactionFailure('commit_outcome_unknown',$correlation);
        }
    }
    private function retryable(Throwable $error):bool
    {
        if ($error instanceof AcademicTransactionFailure) { return $error->category==='rediscovery_required'; }
        return $error instanceof QueryException &&
            (($error->errorInfo[0]??null)==='40001' || in_array((int)($error->errorInfo[1]??0),[1205,1213],true));
    }
    private function step(int $attempt,string $phase,array $data=[]):void { $this->trace[]=['attempt'=>$attempt,'phase'=>$phase]+$data; }
    public function trace():array { return $this->trace; }
}
