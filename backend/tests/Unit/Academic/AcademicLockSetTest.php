<?php
declare(strict_types=1);
namespace Tests\Unit\Academic;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AcademicLockSetTest extends TestCase
{
    public function testCanonicalRanksAndSharedIdentityUnionAreOrderedExactlyOnce(): void
    {
        $set=new AcademicLockSet(['submission_references'=>['3'], 'activity_references'=>['4'],
            'student_enrollments'=>['5'], 'sections'=>['6'], 'instructional_entries'=>['7'],
            'teaching_assignments'=>['9'], 'teachers'=>['20','2'],
            'academic_periods'=>['12','2'], 'students'=>['2','3'], 'actors'=>['20'],
            'grades'=>['9007199254740993','9'], 'retained_identities'=>['1']]);
        $this->assertSame([
            'academic_periods'=>['2','12'], 'instructional_entries'=>['7'], 'grades'=>['9','9007199254740993'],
            'sections'=>['6'], 'retained_identities'=>['1','2','3','20'], 'student_enrollments'=>['5'],
            'teaching_assignments'=>['9'], 'activity_references'=>['4'], 'submission_references'=>['3'],
        ],$set->ordered());
    }
    public function testUnknownTablesCannotBypassCanonicalLockRanks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AcademicLockSet(['academic_lifecycle_events'=>['1']]);
    }
    public function testInvalidIdentifiersAreRejected(): void
    {
        foreach (['0','-1','1.2','01','18446744073709551616'] as $id) {
            try { new AcademicLockSet(['grades'=>[$id]]); $this->fail('Invalid ID admitted.'); }
            catch (InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }
}
