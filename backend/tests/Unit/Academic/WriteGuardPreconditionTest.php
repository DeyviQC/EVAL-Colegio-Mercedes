<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicWriteGuard;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WriteGuardPreconditionTest extends TestCase
{
    public function testAllocationOutsideTransactionFailsBeforeDatabaseAccess(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('transactionLevel')->willReturn(0);
        $connection->expects($this->never())->method('table');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active transaction');
        (new AcademicWriteGuard($connection))->allocateNextBoundary();
    }
}
