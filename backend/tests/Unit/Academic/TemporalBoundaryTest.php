<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\EffectiveInterval;
use App\Domain\Academic\DeclaredDateRange;
use App\Domain\Academic\OperationalBoundary;
use App\Domain\Academic\TransferTiming;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Task 2.1 / 2.2: Temporal boundary and interval tests.
 *
 * Verifies:
 * - OperationalBoundary ordinal ordering vs UTC timestamp evidence.
 * - EffectiveInterval half-open [start, end) semantics.
 * - Touching non-overlap at boundary points.
 * - Null end as open-ended / infinity without automatic expiry.
 * - Ordinal sequence K1 < K2 < K3 for same-day history.
 * - Rejection of invalid ordinals (<= 0) and inverted/equal interval ends.
 * - Clock regression immunity: ordering is strictly determined by ordinal.
 * - Declared dates remain separate and are never conflated with ordinals.
 */
final class TemporalBoundaryTest extends TestCase
{
    private DateTimeZone $utc;

    public function testServerKeysAreDecimalStrings(): void
    {
        $this->assertSame('9007199254740993', (new OperationalBoundary(9007199254740993,
            new DateTimeImmutable('2026-03-01T00:00:00Z')))->toServerKey());
    }

    public function testImmediateBoundaryDateUsesInstitutionalTimezone(): void
    {
        $boundary = new OperationalBoundary(1, new DateTimeImmutable('2026-03-02T02:00:00Z'));
        $this->assertSame('2026-03-01', $boundary->schoolLocalDate());
        $this->assertSame('UTC', $boundary->timestamp->getTimezone()->getName());
    }

    public function testHistoricalOpenIntervalRetainsNullEnd(): void
    {
        $interval = new EffectiveInterval(new OperationalBoundary(1, new DateTimeImmutable('now')));
        $this->assertNull($interval->historicalView(new DeclaredDateRange('2026-01-01'))['operationalEndKeyExclusive']);
    }

    public function testImmediateTimingRejectsUnsupportedModesBeforeAnyPersistence(): void
    {
        foreach (['scheduled', 'retroactive', 'corrective'] as $mode) {
            try {
                new TransferTiming($mode);
                $this->fail('Unsupported timing accepted.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Only server-confirmed immediate transfer timing is supported.', $error->getMessage());
            }
        }
        $this->assertSame('immediate', (new TransferTiming())->mode);
    }

    public function testImmediateTransferCannotSupplyAnEffectiveDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TransferTiming('immediate', '2026-03-01');
    }

    public function testTransferCannotBecomeEffectiveWithoutSuccessfulServerConfirmation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TransferTiming())->confirmedEffectiveBoundary(null);
    }

    public function testTransferConfirmationPreservesTheCommittedServerBoundary(): void
    {
        $boundary = new OperationalBoundary(12, new DateTimeImmutable('2026-03-01T00:00:00Z'));
        $this->assertSame($boundary, (new TransferTiming())->confirmedEffectiveBoundary($boundary));
    }

    public function testAssignmentEndOutsidePeriodAndPeriodWithoutEndAreRejected(): void
    {
        foreach ([new DeclaredDateRange('2026-01-01'), new DeclaredDateRange('2026-01-01', '2026-12-20')] as $period) {
            try {
                (new DeclaredDateRange('2026-03-01', '2026-12-21'))->assertAssignmentContainedIn($period);
                $this->fail('Invalid containment accepted.');
            } catch (InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }

    public function testDeclaredDatesPermitSameDayAndRemainSeparateFromOrdinals(): void
    {
        $dates = new DeclaredDateRange('2026-03-01', '2026-03-01');
        $this->assertSame($dates->from, $dates->until);
        $this->assertSame('2026-03-01', $dates->from);
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DeclaredDateRange('2026-02-30');
    }

    public function testInvertedDeclaredDatesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DeclaredDateRange('2026-03-02', '2026-03-01');
    }

    public function testAssignmentContainmentIsInclusiveAndOpenEndHasPlanningHorizonOnly(): void
    {
        $period = new DeclaredDateRange('2026-03-01', '2026-12-20');
        $assignment = new DeclaredDateRange('2026-03-01', '2026-12-20');
        $assignment->assertAssignmentContainedIn($period);
        $this->assertSame('2026-12-21', $assignment->planningEndExclusive($period));
        $open = new DeclaredDateRange('2026-03-01');
        $open->assertAssignmentContainedIn($period);
        $this->assertSame('2026-12-21', $open->planningEndExclusive($period));
        $this->assertNull($open->until);
    }

    public function testAssignmentOutsidePeriodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DeclaredDateRange('2026-02-28'))->assertAssignmentContainedIn(
            new DeclaredDateRange('2026-03-01', '2026-12-20')
        );
    }

    public function testEnrollmentDatesDoNotGainAnImplicitPeriodContainmentRule(): void
    {
        $enrollment = new DeclaredDateRange('2026-01-01');
        $this->assertSame('2026-01-01', $enrollment->from);
        $this->assertNull($enrollment->until);
    }

    public function testHistoricalViewPreservesDatesAndStringOperationalKeys(): void
    {
        $interval = new EffectiveInterval(
            new OperationalBoundary(10, new DateTimeImmutable('2026-03-01T00:00:00Z')),
            new OperationalBoundary(20, new DateTimeImmutable('2026-03-01T00:00:00Z'))
        );
        $this->assertSame([
            'effectiveFrom' => '2026-03-01', 'effectiveUntil' => '2026-03-01',
            'operationalStartKey' => '10', 'operationalEndKeyExclusive' => '20',
        ], $interval->historicalView(new DeclaredDateRange('2026-03-01', '2026-03-01')));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->utc = new DateTimeZone('UTC');
    }

    public function testOperationalBoundaryRequiresPositiveOrdinal(): void
    {
        $now = new DateTimeImmutable('now', $this->utc);

        $this->expectException(InvalidArgumentException::class);
        new OperationalBoundary(0, $now);
    }

    public function testOperationalBoundaryRejectsNegativeOrdinal(): void
    {
        $now = new DateTimeImmutable('now', $this->utc);

        $this->expectException(InvalidArgumentException::class);
        new OperationalBoundary(-5, $now);
    }

    public function testOperationalBoundaryNormalizesTimestampToUtc(): void
    {
        $limaTime = new DateTimeImmutable('2026-03-01 08:00:00', new DateTimeZone('America/Lima'));
        $boundary = new OperationalBoundary(1, $limaTime);

        $this->assertSame(1, $boundary->ordinal);
        $this->assertSame('UTC', $boundary->timestamp->getTimezone()->getName());
        $this->assertSame('2026-03-01 13:00:00', $boundary->timestamp->format('Y-m-d H:i:s'));
    }

    public function testOperationalBoundaryComparesExclusivelyByOrdinalIgnoringTimestamp(): void
    {
        // Boundary 1 has earlier ordinal but later timestamp (e.g. clock anomaly/skew)
        $tLater = new DateTimeImmutable('2026-03-02 12:00:00', $this->utc);
        $tEarlier = new DateTimeImmutable('2026-03-01 12:00:00', $this->utc);

        $b1 = new OperationalBoundary(10, $tLater);
        $b2 = new OperationalBoundary(20, $tEarlier);

        $this->assertLessThan(0, $b1->compareTo($b2));
        $this->assertGreaterThan(0, $b2->compareTo($b1));
        $this->assertSame(0, $b1->compareTo(new OperationalBoundary(10, $tEarlier)));
    }

    public function testOperationalBoundaryEqualityIsOrdinalBased(): void
    {
        $t1 = new DateTimeImmutable('2026-03-01 10:00:00', $this->utc);
        $t2 = new DateTimeImmutable('2026-03-01 15:00:00', $this->utc);

        $b1 = new OperationalBoundary(42, $t1);
        $b2 = new OperationalBoundary(42, $t2);
        $b3 = new OperationalBoundary(43, $t1);

        $this->assertTrue($b1->equals($b2));
        $this->assertFalse($b1->equals($b3));
    }

    public function testEffectiveIntervalRequiresEndOrdinalGreaterThanStartOrdinal(): void
    {
        $t = new DateTimeImmutable('2026-03-01 10:00:00', $this->utc);
        $start = new OperationalBoundary(10, $t);
        $equalEnd = new OperationalBoundary(10, $t);

        $this->expectException(InvalidArgumentException::class);
        new EffectiveInterval($start, $equalEnd);
    }

    public function testEffectiveIntervalRejectsEndOrdinalLessThanStartOrdinal(): void
    {
        $t = new DateTimeImmutable('2026-03-01 10:00:00', $this->utc);
        $start = new OperationalBoundary(10, $t);
        $lessEnd = new OperationalBoundary(9, $t);

        $this->expectException(InvalidArgumentException::class);
        new EffectiveInterval($start, $lessEnd);
    }

    public function testEffectiveIntervalNullEndIsOpenEnded(): void
    {
        $t = new DateTimeImmutable('2026-03-01 10:00:00', $this->utc);
        $start = new OperationalBoundary(5, $t);
        $interval = new EffectiveInterval($start, null);

        $this->assertTrue($interval->isOpenEnded());
        $this->assertNull($interval->end);

        // Does not contain point before start
        $this->assertFalse($interval->contains(new OperationalBoundary(4, $t)));

        // Contains points at or after start without automatic expiry
        $this->assertTrue($interval->contains(new OperationalBoundary(5, $t)));
        $this->assertTrue($interval->contains(new OperationalBoundary(999999, $t)));
    }

    public function testEffectiveIntervalHalfOpenSemantics(): void
    {
        $t = new DateTimeImmutable('2026-03-01 10:00:00', $this->utc);
        $k1 = new OperationalBoundary(10, $t);
        $k2 = new OperationalBoundary(20, $t);

        $interval = new EffectiveInterval($k1, $k2);

        $this->assertFalse($interval->isOpenEnded());
        $this->assertFalse($interval->contains(new OperationalBoundary(9, $t)));
        $this->assertTrue($interval->contains(new OperationalBoundary(10, $t))); // start is inclusive [
        $this->assertTrue($interval->contains(new OperationalBoundary(15, $t)));
        $this->assertFalse($interval->contains(new OperationalBoundary(20, $t))); // end is exclusive )
        $this->assertFalse($interval->contains(new OperationalBoundary(21, $t)));
    }

    public function testTouchingNonOverlapAtBoundary(): void
    {
        // Interval 1: [K1, K2), Interval 2: [K2, K3)
        $t = new DateTimeImmutable('2026-03-01 10:00:00', $this->utc);
        $k1 = new OperationalBoundary(100, $t);
        $k2 = new OperationalBoundary(200, $t);
        $k3 = new OperationalBoundary(300, $t);

        $i1 = new EffectiveInterval($k1, $k2);
        $i2 = new EffectiveInterval($k2, $k3);

        // At touching point K2:
        $pointK2 = new OperationalBoundary(200, $t);
        $this->assertFalse($i1->contains($pointK2), 'Prior interval must NOT contain exclusive end point K2');
        $this->assertTrue($i2->contains($pointK2), 'Successor interval MUST contain inclusive start point K2');
    }

    public function testSameDayTransferHistoryNonEmptySequence(): void
    {
        // Same-day transfer produces ordinals K1 < K2 < K3 with identical calendar dates
        $sameDay = new DateTimeImmutable('2026-05-15 09:00:00', $this->utc);
        $k1 = new OperationalBoundary(1, $sameDay);
        $k2 = new OperationalBoundary(2, $sameDay->modify('+1 hour'));
        $k3 = new OperationalBoundary(3, $sameDay->modify('+2 hours'));

        $initialEnrollment = new EffectiveInterval($k1, $k2);
        $transferredEnrollment = new EffectiveInterval($k2, $k3);
        $finalEnrollment = new EffectiveInterval($k3, null);

        $this->assertFalse($initialEnrollment->isOpenEnded());
        $this->assertFalse($transferredEnrollment->isOpenEnded());
        $this->assertTrue($finalEnrollment->isOpenEnded());

        // Each interval occupies distinct, touching ordinal range
        $this->assertTrue($initialEnrollment->contains($k1));
        $this->assertFalse($initialEnrollment->contains($k2));

        $this->assertTrue($transferredEnrollment->contains($k2));
        $this->assertFalse($transferredEnrollment->contains($k3));

        $this->assertTrue($finalEnrollment->contains($k3));
    }
}
