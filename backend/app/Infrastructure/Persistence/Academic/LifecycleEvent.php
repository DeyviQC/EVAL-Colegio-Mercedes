<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use App\Domain\Academic\DeclaredDateRange;
use InvalidArgumentException;

final readonly class LifecycleEvent
{
    public const TARGETS=['period'=>'academic_periods','entry'=>'instructional_entries','grade'=>'grades',
        'section'=>'sections','enrollment'=>'student_enrollments','assignment'=>'teaching_assignments'];
    public string $targetId;
    public function __construct(public string $entityType,int|string $targetId,public string $eventType,
        public ?string $previousState,public ?string $newState,public array $metadata=[],public ?string $effectiveOn=null)
    {
        if (!isset(self::TARGETS[$entityType]) || !preg_match('/^[a-z][a-z_]{0,39}$/',$eventType)) {
            throw new InvalidArgumentException('Invalid typed lifecycle event.');
        }
        $this->targetId=AcademicLockSet::id($targetId);
        if ($effectiveOn!==null) { new DeclaredDateRange($effectiveOn); }
    }
}
