<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use App\Domain\Academic\OperationalBoundary;
use Illuminate\Database\Connection;

final readonly class LifecycleEventWriter
{
    public function __construct(private Connection $connection) {}
    public function append(LifecycleEvent $event,string $actorId,OperationalBoundary $boundary,string $correlationId):int
    {
        if ($this->connection->transactionLevel()!==1 || !$this->connection->getPdo()->inTransaction()) {
            throw new AcademicTransactionFailure('ledger_requires_transaction');
        }
        return $this->connection->table('academic_lifecycle_events')->insertGetId([
            'entity_type'=>$event->entityType, $event->entityType.'_id'=>$event->targetId,
            'actor_id'=>AcademicLockSet::id($actorId), 'event_type'=>$event->eventType,
            'previous_state'=>$event->previousState, 'new_state'=>$event->newState,
            'effective_on'=>$event->effectiveOn ?? $boundary->schoolLocalDate(),
            'operation_key'=>$boundary->toServerKey(), 'correlation_id'=>$correlationId,
            'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u'),
            'metadata'=>json_encode($event->metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
        ]);
    }
}
