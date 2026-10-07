<?php

declare(strict_types=1);

namespace App\Domain\Academic;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * AcademicWriteGuard manages the singleton persistent mutex row in MySQL.
 *
 * Enforces:
 * - Exactly one technical row exists in academic_write_guard with id = 1.
 * - Sequential allocation of monotonically increasing operational ordinals.
 * - Rollback-safe within transactions.
 */
final class AcademicWriteGuard
{
    public const int GUARD_ID = 1;

    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Verifies that the singleton guard row exists.
     */
    public function ensureGuardExists(): void
    {
        $count = $this->connection->table('academic_write_guard')
            ->where('id', self::GUARD_ID)
            ->count();

        if ($count !== 1) {
            throw new RuntimeException('Academic write guard singleton row missing or corrupt.');
        }
    }

    /**
     * Retrieves the current ordinal without locking.
     */
    public function getCurrentOrdinal(): int
    {
        $row = $this->connection->table('academic_write_guard')
            ->where('id', self::GUARD_ID)
            ->first();

        if ($row === null) {
            throw new RuntimeException('Academic write guard row does not exist.');
        }

        return (int) $row->last_ordinal;
    }

    /**
     * Allocates the next operational boundary ordinal under an exclusive row lock (FOR UPDATE).
     *
     * Must be called within a database transaction.
     */
    public function allocateNextBoundary(): OperationalBoundary
    {
        if ($this->connection->transactionLevel() < 1) {
            throw new RuntimeException('Boundary allocation requires an active transaction.');
        }

        $row = $this->connection->table('academic_write_guard')
            ->where('id', self::GUARD_ID)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new RuntimeException('Academic write guard row does not exist.');
        }

        if ((int) $row->last_ordinal >= PHP_INT_MAX) {
            throw new RuntimeException('Operational ordinal range exhausted.');
        }
        $nextOrdinal = ((int) $row->last_ordinal) + 1;

        $this->connection->table('academic_write_guard')
            ->where('id', self::GUARD_ID)
            ->update([
                'last_ordinal' => $nextOrdinal,
            ]);

        $utcTimestamp = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new OperationalBoundary($nextOrdinal, $utcTimestamp);
    }
}
