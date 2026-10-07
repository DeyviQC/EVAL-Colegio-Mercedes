<?php

declare(strict_types=1);

namespace App\Domain\Academic;

use InvalidArgumentException;

/**
 * EffectiveInterval represents a half-open operational interval [start, end).
 *
 * - start is mandatory.
 * - end is nullable (null represents open-ended / infinity without automatic expiry).
 * - Invariant: if end is present, end.ordinal > start.ordinal.
 */
final readonly class EffectiveInterval
{
    public OperationalBoundary $start;
    public ?OperationalBoundary $end;

    public function __construct(OperationalBoundary $start, ?OperationalBoundary $end = null)
    {
        if ($end !== null && $end->compareTo($start) <= 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Effective interval end ordinal (%d) must be strictly greater than start ordinal (%d).',
                    $end->ordinal,
                    $start->ordinal
                )
            );
        }

        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Returns true if the interval is open-ended (no end boundary defined).
     */
    public function isOpenEnded(): bool
    {
        return $this->end === null;
    }

    /** Projection only; declared dates do not establish operational keys. */
    public function historicalView(DeclaredDateRange $dates): array
    {
        return [
            'effectiveFrom' => $dates->from,
            'effectiveUntil' => $dates->until,
            'operationalStartKey' => (string) $this->start->ordinal,
            'operationalEndKeyExclusive' => $this->end === null ? null : (string) $this->end->ordinal,
        ];
    }

    /**
     * Determines whether the given point falls within [start, end).
     */
    public function contains(OperationalBoundary $point): bool
    {
        if ($point->compareTo($this->start) < 0) {
            return false;
        }

        if ($this->end !== null && $point->compareTo($this->end) >= 0) {
            return false;
        }

        return true;
    }
}
