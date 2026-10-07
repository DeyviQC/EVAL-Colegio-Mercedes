<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;

final readonly class LockedAcademicContext
{
    public function __construct(public string $actorId, private array $records) {}
    public function rows(string $table): array { return $this->records[$table] ?? []; }
    public function row(string $table,int|string $id): array
    {
        return $this->records[$table][AcademicLockSet::id($id)] ?? throw new AcademicTransactionFailure('missing_locked_context');
    }
}
