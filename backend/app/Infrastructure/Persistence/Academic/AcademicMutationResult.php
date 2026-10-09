<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use InvalidArgumentException;

final readonly class AcademicMutationResult
{
    /** @param list<LifecycleEvent> $events */
    public function __construct(public mixed $value,public array $events=[])
    {
        foreach ($events as $event) {
            if (!$event instanceof LifecycleEvent) { throw new InvalidArgumentException('Typed lifecycle events required.'); }
        }
    }
}
