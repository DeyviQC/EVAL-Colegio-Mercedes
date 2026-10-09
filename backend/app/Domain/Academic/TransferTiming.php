<?php
declare(strict_types=1);
namespace App\Domain\Academic;
use InvalidArgumentException;
/** Pure U1 contract: validation only, no writer, scheduling or date/key mapping. */
final readonly class TransferTiming
{
    public function __construct(public string $mode = 'immediate', ?string $effectiveOn = null)
    {
        if ($mode !== 'immediate' || $effectiveOn !== null) {
            throw new InvalidArgumentException('Only server-confirmed immediate transfer timing is supported.');
        }
    }

    /** Caller supplies a committed server result, never a client-scheduled date. */
    public function confirmedEffectiveBoundary(?OperationalBoundary $committedBoundary): OperationalBoundary
    {
        if ($committedBoundary === null) {
            throw new InvalidArgumentException('Transfer has no effective boundary before successful server confirmation.');
        }
        return $committedBoundary;
    }
}
