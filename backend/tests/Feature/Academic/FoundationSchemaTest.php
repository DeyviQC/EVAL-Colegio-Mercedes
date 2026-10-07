<?php

declare(strict_types=1);
namespace Tests\Feature\Academic;

use App\Domain\Academic\AcademicNameKey;
use App\Domain\Academic\DeclaredDateRange;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

final class FoundationSchemaTest extends TestCase
{
    private ConnectionInterface $db;
    protected function setUp(): void
    {
        $this->db = DB::connection('migration');
        $this->assertSame('eval_u2_test', $this->db->getDatabaseName());
        $this->assertSame('mysql', $this->db->getDriverName());
        $this->db->beginTransaction();
    }
    protected function tearDown(): void
    {
        while ($this->db->transactionLevel() > 0) { $this->db->rollBack(); }
    }
    private function denied(callable $operation, array $codes = [3819]): void
    {
        try { $operation(); $this->fail('Invalid SQL unexpectedly accepted.'); }
        catch (QueryException $error) { $this->assertContains((int) $error->errorInfo[1], $codes); }
    }
    private function period(string $name = 'Sample 2026', string $state = 'planned'): int
    {
        return $this->db->table('academic_periods')->insertGetId([
            'name' => $name, 'name_key' => AcademicNameKey::generate($name), 'state' => $state,
            'start_on' => '2026-03-01', 'end_on' => '2026-12-20',
        ]);
    }
    private function catalog(string $table, string $name, array $scope = [], bool $active = true): int
    {
        return $this->db->table($table)->insertGetId($scope + [
            'name' => $name, 'name_key' => AcademicNameKey::generate($name), 'is_active' => $active,
        ]);
    }
    private function scope(): array
    {
        $period = $this->period();
        $grade = $this->catalog('grades', 'Grade 2');
        $section = $this->catalog('sections', 'A', ['grade_id' => $grade]);
        return ['academic_period_id' => $period, 'grade_id' => $grade, 'section_id' => $section];
    }
    private function identity(): int
    {
        return $this->db->table('retained_identities')->insertGetId(['credential_status' => 'active']);
    }
    private function enrollment(): array
    {
        $scope = $this->scope();
        $this->db->table('academic_periods')->where('id', $scope['academic_period_id'])->update(['state' => 'active']);
        return $scope + ['student_id' => $this->identity(), 'state' => 'active',
            'effective_from' => '2026-01-01', 'effective_until' => null,
            'operational_start_key' => 1, 'operational_end_key' => null];
    }
    private function assignment(): array
    {
        return $this->scope() + ['teacher_id' => $this->identity(),
            'instructional_entry_id' => $this->catalog('instructional_entries', 'Mathematics', ['kind' => 'subject']),
            'state' => 'planned', 'effective_from' => '2026-03-01', 'effective_until' => null,
            'operational_start_key' => null, 'operational_end_key' => null];
    }

    public function testAggregatePrimaryKeysAreUnsignedBigint(): void
    {
        foreach (['academic_periods','instructional_entries','grades','sections','student_enrollments','teaching_assignments'] as $table) {
            $column = $this->db->selectOne("SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id'", [$table]);
            $this->assertSame('bigint unsigned', $column?->type, $table);
        }
    }
    public function testComparisonKeysUseBinaryBytesAndU3TablesAreAbsent(): void
    {
        foreach (['academic_periods','instructional_entries','grades','sections'] as $table) {
            $column = $this->db->selectOne("SELECT DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='name_key'", [$table]);
            $this->assertSame('varbinary', $column?->type);
        }
        $this->assertSame(0, (int) $this->db->selectOne("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('academic_lifecycle_events','activity_references','submission_references')")->n);
    }
    public function testPeriodRangeStateAndSingleActiveConstraints(): void
    {
        $id = $this->period();
        $this->assertSame('planned', $this->db->table('academic_periods')->where('id', $id)->value('state'));
        $this->denied(fn () => $this->db->table('academic_periods')->where('id', $id)->update(['end_on' => '2026-02-28']));
        $this->denied(fn () => $this->db->table('academic_periods')->where('id', $id)->update(['state' => 'invalid']));
        $this->db->table('academic_periods')->where('id', $id)->update(['state' => 'active']);
        $this->denied(fn () => $this->period('Other year', 'active'), [1062]);
        $this->assertSame('active', $this->db->table('academic_periods')->where('id', $id)->value('state'));
    }
    public function testPeriodNamesAreUniqueAcrossAllStatesAndLabelsArePreserved(): void
    {
        $label = " a\u{0301}LGEBRA ";
        $id = $this->period($label, 'closed');
        $this->denied(fn () => $this->period("\u{00A0}Álgebra\u{2003}"), [1062]);
        $this->assertSame($label, $this->db->table('academic_periods')->where('id', $id)->value('name'));
        $this->assertGreaterThan(0, $this->period('Algebra'));
    }
    public function testCatalogUnicodeEqualitySeparatesKindsAndAccents(): void
    {
        $label = " a\u{0301}LGEBRA ";
        $id = $this->catalog('instructional_entries', $label, ['kind' => 'subject']);
        $this->denied(fn () => $this->catalog('instructional_entries', "\u{00A0}ÁLGEBRA\u{2003}", ['kind' => 'subject']), [1062]);
        $this->assertGreaterThan(0, $this->catalog('instructional_entries', 'Álgebra', ['kind' => 'area']));
        $this->assertGreaterThan(0, $this->catalog('instructional_entries', 'Algebra', ['kind' => 'subject']));
        $this->assertSame($label, $this->db->table('instructional_entries')->where('id', $id)->value('name'));
        $this->assertSame('álgebra', $this->db->table('instructional_entries')->where('id', $id)->value('name_key'));
    }
    public function testClassificationAndActivationChecksRejectAmbiguousValues(): void
    {
        $id = $this->catalog('instructional_entries', 'Sample', ['kind' => 'subject']);
        $this->denied(fn () => $this->db->table('instructional_entries')->where('id', $id)->update(['kind' => 'both']));
        $this->denied(fn () => $this->db->table('instructional_entries')->where('id', $id)->update(['is_active' => 2]));
    }
    public function testActiveRenameReactivationAndInactiveDuplicatesForAllCatalogScopes(): void
    {
        $grade = $this->catalog('grades', 'Parent grade');
        foreach (['instructional_entries' => ['kind' => 'subject'], 'grades' => [], 'sections' => ['grade_id' => $grade]] as $table => $scope) {
            $first = $this->catalog($table, 'Unique A', $scope);
            $second = $this->catalog($table, 'Unique B', $scope);
            $this->denied(fn () => $this->db->table($table)->where('id', $second)->update(['name' => ' unique a ', 'name_key' => AcademicNameKey::generate(' unique a ')]), [1062]);
            $this->assertSame('Unique B', $this->db->table($table)->where('id', $second)->value('name'));
            $inactive = $this->catalog($table, ' UNIQUE A ', $scope, false);
            $this->assertGreaterThan(0, $this->catalog($table, 'Unique A', $scope, false));
            $this->denied(fn () => $this->db->table($table)->where('id', $inactive)->update(['is_active' => true]), [1062]);
            $this->assertSame(0, (int) $this->db->table($table)->where('id', $inactive)->value('is_active'));
            $this->assertSame(1, (int) $this->db->table($table)->where('id', $first)->value('is_active'));
        }
    }
    public function testSameSectionNameIsAllowedInDifferentGrades(): void
    {
        $first = $this->catalog('grades', 'Grade 1');
        $second = $this->catalog('grades', 'Grade 2');
        $this->catalog('sections', 'A', ['grade_id' => $first]);
        $this->denied(fn () => $this->catalog('sections', ' a ', ['grade_id' => $first]), [1062]);
        $this->assertGreaterThan(0, $this->catalog('sections', ' a ', ['grade_id' => $second]));
    }
    public function testEnrollmentScopeIsMandatoryAndGradeSectionPairIsEnforced(): void
    {
        $values = $this->enrollment();
        foreach (['student_id','academic_period_id','grade_id','section_id'] as $field) {
            $invalid = $values; unset($invalid[$field]);
            $this->denied(fn () => $this->db->table('student_enrollments')->insert($invalid), [1364]);
        }
        $otherGrade = $this->catalog('grades', 'Other grade');
        $this->denied(fn () => $this->db->table('student_enrollments')->insert(array_replace($values, ['grade_id' => $otherGrade])), [1452]);
        $this->assertGreaterThan(0, $this->db->table('student_enrollments')->insertGetId($values));
    }
    public function testEnrollmentHasNoAdditionalPeriodContainmentOrAutomaticExpiry(): void
    {
        $values = $this->enrollment();
        $id = $this->db->table('student_enrollments')->insertGetId($values);
        $this->assertSame('2026-01-01', $this->db->table('student_enrollments')->where('id', $id)->value('effective_from'));
        $this->db->table('academic_periods')->where('id', $values['academic_period_id'])->update(['state' => 'closed']);
        $row = $this->db->table('student_enrollments')->where('id', $id)->first();
        $this->assertSame('active', $row->state);
        $this->assertNull($row->effective_until);
    }

    public function testDeclaredEnrollmentEndDoesNotInventAnOperationalClosure(): void
    {
        $values=array_replace($this->enrollment(),['effective_until'=>'2026-11-01']);
        try { $id=$this->db->table('student_enrollments')->insertGetId($values); }
        catch (QueryException) { $this->fail('Declared dates must not be converted into an operational closure by the schema.'); }
        $row=$this->db->table('student_enrollments')->where('id',$id)->first();
        $this->assertSame('active',$row->state);
        $this->assertSame('2026-11-01',$row->effective_until);
        $this->assertNull($row->operational_end_key);
    }
    public function testEnrollmentStatesDatesAndOperationalIntervals(): void
    {
        $values = $this->enrollment();
        foreach ([['state'=>'planned'], ['state'=>'closed'], ['state'=>'transferred'], ['operational_start_key'=>null], ['operational_start_key'=>0], ['operational_start_key'=>'9223372036854775808'], ['operational_end_key'=>2], ['effective_until'=>'2025-12-31']] as $invalid) {
            $this->denied(fn () => $this->db->table('student_enrollments')->insert(array_replace($values, $invalid)), [3819,1048]);
        }
        foreach (['closed','transferred'] as $state) {
            $this->assertGreaterThan(0, $this->db->table('student_enrollments')->insertGetId(array_replace($values, ['state'=>$state, 'effective_until'=>'2026-01-01', 'operational_end_key'=>2])));
        }
    }

    public function testClosedEnrollmentDateAndKeyOrderAreIndependentChecks(): void
    {
        $values=array_replace($this->enrollment(),['state'=>'closed','effective_until'=>'2026-01-01','operational_end_key'=>2]);
        $this->denied(fn () => $this->db->table('student_enrollments')->insert(array_replace($values,['effective_until'=>'2025-12-31'])));
        $this->denied(fn () => $this->db->table('student_enrollments')->insert(array_replace($values,['operational_end_key'=>1])));
        $this->assertGreaterThan(0,$this->db->table('student_enrollments')->insertGetId($values));
    }

    public function testEveryAggregateParentReferenceRejectsMissingIdentityOrScope(): void
    {
        $enrollment=$this->enrollment();
        foreach (['student_id','academic_period_id','grade_id','section_id'] as $field) {
            $this->denied(fn () => $this->db->table('student_enrollments')->insert(array_replace($enrollment,[$field=>PHP_INT_MAX])),[1452]);
        }
        $assignment=array_intersect_key($enrollment,array_flip(['academic_period_id','grade_id','section_id'])) + [
            'teacher_id'=>$this->identity(),
            'instructional_entry_id'=>$this->catalog('instructional_entries','Mathematics',['kind'=>'subject']),
            'state'=>'planned','effective_from'=>'2026-03-01','effective_until'=>null,
            'operational_start_key'=>null,'operational_end_key'=>null,
        ];
        foreach (['teacher_id','academic_period_id','instructional_entry_id','grade_id','section_id'] as $field) {
            $this->denied(fn () => $this->db->table('teaching_assignments')->insert(array_replace($assignment,[$field=>PHP_INT_MAX])),[1452]);
        }
    }
    public function testOneActiveEnrollmentPerStudentPeriodAllowsDifferentPeriods(): void
    {
        $values = $this->enrollment();
        $this->db->table('student_enrollments')->insert($values);
        $this->denied(fn () => $this->db->table('student_enrollments')->insert(array_replace($values, ['operational_start_key'=>2])), [1062]);
        $other = $this->period('Other period');
        $this->db->table('academic_periods')->where('id', $values['academic_period_id'])->update(['state' => 'closed']);
        $this->db->table('academic_periods')->where('id', $other)->update(['state' => 'active']);
        $this->assertGreaterThan(0, $this->db->table('student_enrollments')->insertGetId(array_replace($values, ['academic_period_id'=>$other])));
    }
    public function testPlannedAssignmentHasNoOperationalStartInPlannedParent(): void
    {
        $values = $this->assignment();
        $id = $this->db->table('teaching_assignments')->insertGetId($values);
        $this->assertNull($this->db->table('teaching_assignments')->where('id', $id)->value('operational_start_key'));
        $this->assertSame('planned', $this->db->table('academic_periods')->where('id', $values['academic_period_id'])->value('state'));
        $this->denied(fn () => $this->db->table('teaching_assignments')->insert(array_replace($values, ['operational_start_key'=>1])));
        $this->denied(fn () => $this->db->table('teaching_assignments')->insert(array_replace($values, ['operational_end_key'=>1])));
    }
    public function testAssignmentScopeStatesAndKeysAreValidatedWithoutInventingEligibility(): void
    {
        $values = $this->assignment();
        foreach (['teacher_id','academic_period_id','instructional_entry_id','grade_id','section_id'] as $field) {
            $invalid=$values; unset($invalid[$field]);
            $this->denied(fn () => $this->db->table('teaching_assignments')->insert($invalid), [1364]);
        }
        $otherGrade = $this->catalog('grades', 'Other grade');
        $this->denied(fn () => $this->db->table('teaching_assignments')->insert(array_replace($values, ['grade_id'=>$otherGrade])), [1452]);
        foreach ([['state'=>'replaced'], ['state'=>'active'], ['state'=>'closed'], ['effective_until'=>'2026-02-28']] as $invalid) {
            $this->denied(fn () => $this->db->table('teaching_assignments')->insert(array_replace($values, $invalid)));
        }
        // Operational fixtures use an active parent; planned-parent eligibility is undecided.
        $this->db->table('academic_periods')->where('id', $values['academic_period_id'])->update(['state' => 'active']);
        $active = array_replace($values, ['state'=>'active','operational_start_key'=>1]);
        $id=$this->db->table('teaching_assignments')->insertGetId($active);
        $this->denied(fn () => $this->db->table('teaching_assignments')->where('id',$id)->update(['operational_end_key'=>1]));
        $this->db->table('teaching_assignments')->where('id',$id)->update(['state'=>'closed','effective_until'=>'2026-03-01','operational_end_key'=>2]);
        $this->assertSame('closed', $this->db->table('teaching_assignments')->where('id',$id)->value('state'));
    }
    public function testAssignmentInclusiveContainmentAndOpenPlanningHorizonUseApprovedPrimitive(): void
    {
        $period=new DeclaredDateRange('2026-03-01','2026-12-20');
        $range=new DeclaredDateRange('2026-03-01','2026-12-20');
        $range->assertAssignmentContainedIn($period);
        $this->assertSame('2026-12-21',$range->planningEndExclusive($period));
        $open=new DeclaredDateRange('2026-03-01');
        $this->assertSame('2026-12-21',$open->planningEndExclusive($period));
        $this->assertNull($open->until);
        $this->expectException(\InvalidArgumentException::class);
        (new DeclaredDateRange('2026-02-28'))->assertAssignmentContainedIn($period);
    }
    public function testDistinctTeachersCanShareScopeAndReplacementLineageIsUnique(): void
    {
        $values=$this->assignment();
        $this->db->table('academic_periods')->where('id', $values['academic_period_id'])->update(['state' => 'active']);
        $prior=$this->db->table('teaching_assignments')->insertGetId(array_replace($values,['state'=>'closed','effective_until'=>'2026-03-01','operational_start_key'=>1,'operational_end_key'=>2]));
        $successor=array_replace($values,['teacher_id'=>$this->identity(),'state'=>'active','operational_start_key'=>2,'replaces_assignment_id'=>$prior]);
        $next=$this->db->table('teaching_assignments')->insertGetId($successor);
        $this->assertNotSame($prior,$next);
        $this->denied(fn () => $this->db->table('teaching_assignments')->insert($successor),[1062]);
        $this->assertGreaterThan(0,$this->db->table('teaching_assignments')->insertGetId(array_replace($successor,['teacher_id'=>$this->identity(),'replaces_assignment_id'=>null])));
        $this->denied(fn () => $this->db->table('teaching_assignments')->where('id',$prior)->delete(),[1451]);
    }
    public function testReferencedScopeAndIdentityDeletesAreRestrictive(): void
    {
        $values=$this->enrollment();
        $this->db->table('student_enrollments')->insert($values);
        foreach (['academic_periods'=>'academic_period_id','grades'=>'grade_id','sections'=>'section_id'] as $table=>$field) {
            $this->denied(fn () => $this->db->table($table)->where('id',$values[$field])->delete(),[1451]);
            $this->assertTrue($this->db->table($table)->where('id',$values[$field])->exists());
        }
        $this->denied(fn () => $this->db->table('retained_identities')->where('id',$values['student_id'])->delete(),[1644]);
    }

    public function testPeriodAndCatalogLifecycleRetainsExistingAssignmentReferences(): void
    {
        $values = $this->assignment();
        $id = $this->db->table('teaching_assignments')->insertGetId($values);
        $this->db->table('academic_periods')->where('id', $values['academic_period_id'])->update(['state'=>'closed']);
        $this->db->table('instructional_entries')->where('id', $values['instructional_entry_id'])->update(['is_active'=>false,'name'=>'Corrected label','name_key'=>AcademicNameKey::generate('Corrected label')]);
        $retained = $this->db->table('teaching_assignments')->where('id',$id)->first();
        $this->assertSame('planned',$retained->state);
        $this->assertSame($values['instructional_entry_id'], (int) $retained->instructional_entry_id);
        $this->assertSame('Corrected label',$this->db->table('instructional_entries')->where('id',$retained->instructional_entry_id)->value('name'));
        $this->denied(fn () => $this->db->table('instructional_entries')->where('id',$retained->instructional_entry_id)->delete(),[1451]);
    }

    public function testBinaryKeysReproduceCaseFoldAndPreserveInternalSpacing(): void
    {
        $this->catalog('grades','Straße');
        $this->denied(fn () => $this->catalog('grades','STRASSE'),[1062]);
        $this->catalog('grades','Grade  1');
        $this->assertGreaterThan(0,$this->catalog('grades','Grade 1'));
        $label = str_repeat("\u{0390}",255);
        $id=$this->catalog('grades',$label);
        $this->assertSame($label,$this->db->table('grades')->where('id',$id)->value('name'));
        $this->assertSame(AcademicNameKey::generate($label),$this->db->table('grades')->where('id',$id)->value('name_key'));
    }
    public function testRuntimeHasOnlyU2ReadsAndSchemaDownIsNonDestructive(): void
    {
        $this->assertSame('eval_u2_test',DB::connection()->getDatabaseName());
        $this->denied(fn () => DB::connection()->statement("INSERT INTO grades(name,name_key,is_active) VALUES ('probe','probe',1)"),[1142]);
        foreach (['000002_create_academic_periods_and_catalog','000003_create_student_enrollments','000004_create_teaching_assignments'] as $name) {
            $migration=require dirname(__DIR__,3).'/database/migrations/2026_10_07_'.$name.'.php';
            try { $migration->down(); $this->fail('Destructive down unexpectedly permitted.'); }
            catch (\RuntimeException $error) { $this->assertStringContainsString('history',$error->getMessage()); }
        }
    }
}
