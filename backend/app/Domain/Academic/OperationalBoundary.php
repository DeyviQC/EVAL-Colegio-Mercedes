<?php

declare(strict_types=1);

namespace App\Domain\Academic;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * OperationalBoundary represents a discrete point on the operational timeline.
 *
 * Ordering is exclusively determined by the positive integer ordinal.
 * The UTC timestamp serves as audit/evidence trail only and is not used for ordering.
 */
final readonly class OperationalBoundary
{
    public int $ordinal;
    public DateTimeImmutable $timestamp;

    public function __construct(int $ordinal, DateTimeImmutable $timestamp)
    {
        if ($ordinal <= 0) {
            throw new InvalidArgumentException(
                sprintf('Operational boundary ordinal must be a strictly positive integer, got %d.', $ordinal)
            );
        }

        $this->ordinal = $ordinal;
        $this->timestamp = $timestamp->getTimezone()->getName() === 'UTC'
            ? $timestamp
            : $timestamp->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Compares two boundaries exclusively by ordinal.
     */
    public function compareTo(self $other): int
    {
        return $this->ordinal <=> $other->ordinal;
    }

    /**
     * Checks equality based exclusively on ordinal.
     */
    public function equals(self $other): bool
    {
        return $this->ordinal === $other->ordinal;
    }

    public function toServerKey(): string
    {
        return sprintf('K%d', $this->ordinal);
    }
}
