<?php

declare(strict_types=1);

namespace App\Domain\Academic;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Declared calendar dates only: never allocate or infer operational authority. */
final readonly class DeclaredDateRange
{
    public function __construct(public string $from, public ?string $until = null)
    {
        self::date($from);
        if ($until !== null) {
            self::date($until);
            if ($until < $from) {
                throw new InvalidArgumentException('Declared end cannot precede start.');
            }
        }
    }

    public function assertAssignmentContainedIn(self $period): void
    {
        if ($period->until === null) {
            throw new InvalidArgumentException('Academic period requires an end date.');
        }
        if ($this->from < $period->from || $this->from > $period->until
            || ($this->until !== null && $this->until > $period->until)) {
            throw new InvalidArgumentException('Assignment dates must be contained in the period.');
        }
    }

    public function planningEndExclusive(self $period): string
    {
        $this->assertAssignmentContainedIn($period);
        return self::date($this->until ?? $period->until)->modify('+1 day')->format('Y-m-d');
    }

    private static function date(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Declared date must be a valid YYYY-MM-DD calendar date.');
        }
        return $parsed;
    }
}
