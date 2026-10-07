<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Domain\Academic\AcademicNameKey;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Task 2.4 Feature Test: Foundation schema, relationship constraints,
 * approved equality, single-active period guard, and replacement lineage.
 */
final class FoundationSchemaTest extends TestCase
{
    private string $periodId1 = '01923456-1000-7000-8000-000000000001';
    private string $periodId2 = '01923456-1000-7000-8000-000000000002';
    private string $periodId3 = '01923456-1000-7000-8000-000000000003';
    private string $gradeId1 = '01923456-2000-7000-8000-000000000001';
    private string $gradeId2 = '01923456-2000-7000-8000-000000000002';
    private string $sectionId1 = '01923456-3000-7000-8000-000000000001';
    private string $sectionId2 = '01923456-3000-7000-8000-000000000002';
    private string $entryIdSubject = '01923456-4000-7000-8000-000000000001';
    private string $entryIdArea = '01923456-4000-7000-8000-000000000002';
    private string $studentId = '01923456-5000-7000-8000-000000000001';
    private string $teacherId = '01923456-6000-7000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        DB::table('teaching_assignments')->delete();
        DB::table('student_enrollments')->delete();
        DB::table('sections')->delete();
        DB::table('grades')->delete();
        DB::table('instructional_entries')->delete();
        DB::table('academic_periods')->delete();
        DB::table('retained_identities')->whereIn('id', [$this->studentId, $this->teacherId])->delete();
    }

    private function seedIdentities(): void
    {
        DB::table('retained_identities')->insert([
            ['id' => $this->studentId, 'identity_type' => 'student', 'credential_status' => 'active'],
            ['id' => $this->teacherId, 'identity_type' => 'teacher', 'credential_status' => 'active'],
        ]);
    }

    public function testAcademicPeriodStateCheckConstraint(): void
    {
        $this->expectException(QueryException::class);

        DB::table('academic_periods')->insert([
            'id' => $this->periodId1,
            'name' => 'Periodo Inválido',
            'name_key' => AcademicNameKey::generate('Periodo Inválido'),
            'start_on' => '2026-03-01',
            'end_on' => '2026-12-20',
            'state' => 'invalid_state',
        ]);
    }

    public function testAcademicPeriodDateOrderCheckConstraint(): void
    {
        $this->expectException(QueryException::class);

        // start_on > end_on must be rejected
        DB::table('academic_periods')->insert([
            'id' => $this->periodId1,
            'name' => 'Año Escolar 2026',
            'name_key' => AcademicNameKey::generate('Año Escolar 2026'),
            'start_on' => '2026-12-20',
            'end_on' => '2026-03-01',
            'state' => 'planned',
        ]);
    }

    public function testAcademicPeriodSingleActiveGuardConstraint(): void
    {
        // 1. First active period succeeds
        DB::table('academic_periods')->insert([
            'id' => $this->periodId1,
            'name' => 'Año Escolar 2026',
            'name_key' => AcademicNameKey::generate('Año Escolar 2026'),
            'start_on' => '2026-03-01',
            'end_on' => '2026-12-20',
            'state' => 'active',
        ]);

        // 2. Additional planned period is allowed
        DB::table('academic_periods')->insert([
            'id' => $this->periodId2,
            'name' => 'Año Escolar 2027',
            'name_key' => AcademicNameKey::generate('Año Escolar 2027'),
            'start_on' => '2027-03-01',
            'end_on' => '2027-12-20',
            'state' => 'planned',
        ]);

        // 3. Second active period must fail the single-active guard
        $this->expectException(QueryException::class);
        DB::table('academic_periods')->insert([
            'id' => $this->periodId3,
            'name' => 'Año Escolar Extra Activo',
            'name_key' => AcademicNameKey::generate('Año Escolar Extra Activo'),
            'start_on' => '2026-06-01',
            'end_on' => '2026-12-31',
            'state' => 'active',
        ]);
    }

    public function testAcademicPeriodAllStateNameKeyUniqueness(): void
    {
        DB::table('academic_periods')->insert([
            'id' => $this->periodId1,
            'name' => 'Año 2026',
            'name_key' => AcademicNameKey::generate('Año 2026'),
            'start_on' => '2026-03-01',
            'end_on' => '2026-12-20',
            'state' => 'closed',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to create another period with equivalent name (even in planned state) must fail
        DB::table('academic_periods')->insert([
            'id' => $this->periodId2,
            'name' => ' aÑo 2026 ',
            'name_key' => AcademicNameKey::generate(' aÑo 2026 '),
            'start_on' => '2026-03-01',
            'end_on' => '2026-12-20',
            'state' => 'planned',
        ]);
    }

    public function testApprovedEqualityCollisionsAndAccentDistinction(): void
    {
        // 1. Create active subject 'Álgebra'
        $label1 = 'Álgebra';
        DB::table('instructional_entries')->insert([
            'id' => $this->entryIdSubject,
            'kind' => 'subject',
            'name' => $label1,
            'name_key' => AcademicNameKey::generate($label1),
            'is_active' => 1,
        ]);

        // 2. Decomposed outer-spaced " áLGEBRA " collides with 'Álgebra' within subject
        $decomposedLabel = " a\u{0301}LGEBRA ";
        try {
            DB::table('instructional_entries')->insert([
                'id' => '01923456-4000-7000-8000-000000000099',
                'kind' => 'subject',
                'name' => $decomposedLabel,
                'name_key' => AcademicNameKey::generate($decomposedLabel),
                'is_active' => 1,
            ]);
            $this->fail('Expected unique collision for decomposed case-variant subject');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'Collision correctly detected for equivalent subject name');
        }

        // 3. 'Álgebra' as area is a separate scope and must NOT collide with subject
        DB::table('instructional_entries')->insert([
            'id' => $this->entryIdArea,
            'kind' => 'area',
            'name' => 'Álgebra',
            'name_key' => AcademicNameKey::generate('Álgebra'),
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('instructional_entries', ['id' => $this->entryIdArea]);

        // 4. 'Algebra' (without accent) is distinct from 'Álgebra' (accents distinguished)
        $unaccentedId = '01923456-4000-7000-8000-000000000003';
        DB::table('instructional_entries')->insert([
            'id' => $unaccentedId,
            'kind' => 'subject',
            'name' => 'Algebra',
            'name_key' => AcademicNameKey::generate('Algebra'),
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('instructional_entries', ['id' => $unaccentedId]);
    }

    public function testGradeActiveUniqueness(): void
    {
        DB::table('grades')->insert([
            'id' => $this->gradeId1,
            'name' => '1° Secundaria',
            'name_key' => AcademicNameKey::generate('1° Secundaria'),
            'is_active' => 1,
        ]);

        $this->expectException(QueryException::class);

        // Active duplicate in grades must fail
        DB::table('grades')->insert([
            'id' => $this->gradeId2,
            'name' => ' 1° secundaria ',
            'name_key' => AcademicNameKey::generate(' 1° secundaria '),
            'is_active' => 1,
        ]);
    }

    public function testSectionScopedByGradeUniquenessAndInterGradePermission(): void
    {
        // Seed grades
        DB::table('grades')->insert([
            ['id' => $this->gradeId1, 'name' => '1° Secundaria', 'name_key' => '1° secundaria', 'is_active' => 1],
            ['id' => $this->gradeId2, 'name' => '2° Secundaria', 'name_key' => '2° secundaria', 'is_active' => 1],
        ]);

        // 1. Create section 'A' in grade 1
        DB::table('sections')->insert([
            'id' => $this->sectionId1,
            'grade_id' => $this->gradeId1,
            'name' => 'A',
            'name_key' => AcademicNameKey::generate('A'),
            'is_active' => 1,
        ]);

        // 2. Duplicate active section 'A' in the SAME grade must fail
        try {
            DB::table('sections')->insert([
                'id' => '01923456-3000-7000-8000-000000000099',
                'grade_id' => $this->gradeId1,
                'name' => ' a ',
                'name_key' => AcademicNameKey::generate(' a '),
                'is_active' => 1,
            ]);
            $this->fail('Expected collision for duplicate section in same grade');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // 3. Section 'A' in DIFFERENT grade 2 is PERMITTED
        DB::table('sections')->insert([
            'id' => $this->sectionId2,
            'grade_id' => $this->gradeId2,
            'name' => 'A',
            'name_key' => AcademicNameKey::generate('A'),
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('sections', ['id' => $this->sectionId2]);
    }

    public function testStudentEnrollmentCompositeGradeSectionForeignKeyEnforcement(): void
    {
        $this->seedIdentities();

        DB::table('academic_periods')->insert([
            'id' => $this->periodId1,
            'name' => '2026',
            'name_key' => '2026',
            'start_on' => '2026-03-01',
            'end_on' => '2026-12-20',
            'state' => 'active',
        ]);

        DB::table('grades')->insert([
            ['id' => $this->gradeId1, 'name' => '1°', 'name_key' => '1°', 'is_active' => 1],
            ['id' => $this->gradeId2, 'name' => '2°', 'name_key' => '2°', 'is_active' => 1],
        ]);

        // Section 1 belongs to Grade 1
        DB::table('sections')->insert([
            'id' => $this->sectionId1,
            'grade_id' => $this->gradeId1,
            'name' => 'A',
            'name_key' => 'a',
            'is_active' => 1,
        ]);

        // 1. Valid enrollment (Section 1 with Grade 1) succeeds
        $enrollmentId1 = '01923456-7000-7000-8000-000000000001';
        DB::table('student_enrollments')->insert([
            'id' => $enrollmentId1,
            'student_id' => $this->studentId,
            'academic_period_id' => $this->periodId1,
            'grade_id' => $this->gradeId1,
            'section_id' => $this->sectionId1,
            'state' => 'active',
            'effective_from' => '2026-03-01',
            'effective_until' => null,
            'start_ordinal' => 1,
            'end_ordinal' => null,
        ]);
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollmentId1]);

        // 2. Mismatched pair (Section 1 with Grade 2) must fail composite foreign key constraint
        $enrollmentId2 = '01923456-7000-7000-8000-000000000002';
        $this->expectException(QueryException::class);
        DB::table('student_enrollments')->insert([
            'id' => $enrollmentId2,
            'student_id' => $this->studentId,
            'academic_period_id' => $this->periodId1,
            'grade_id' => $this->gradeId2, // Mismatch! Section 1 belongs to Grade 1
            'section_id' => $this->sectionId1,
            'state' => 'active',
            'effective_from' => '2026-03-01',
            'effective_until' => null,
            'start_ordinal' => 2,
            'end_ordinal' => null,
        ]);
    }

    public function testStudentEnrollmentNoContainmentConstraint(): void
    {
        $this->seedIdentities();

        // Period from 2026-03-01 to 2026-12-20
        DB::table('academic_periods')->insert([
            'id' => $this->periodId1,
            'name' => '2026',
            'name_key' => '2026',
            'start_on' => '2026-03-01',
            'end_on' => '2026-12-20',
            'state' => 'active',
        ]);

        DB::table('grades')->insert([
            'id' => $this->gradeId1, 'name' => '1°', 'name_key' => '1°', 'is_active' => 1,
        ]);

        DB::table('sections')->insert([
            'id' => $this->sectionId1, 'grade_id' => $this->gradeId1, 'name' => 'A', 'name_key' => 'a', 'is_active' => 1,
        ]);

        // Enrollment declared dates outside the period (e.g. earlier enrollment date)
        // must succeed at database level (proving no containment check constraint on enrollment)
        $enrollmentId = '01923456-7000-7000-8000-000000000005';
        DB::table('student_enrollments')->insert([
            'id' => $enrollmentId,
            'student_id' => $this->studentId,
            'academic_period_id' => $this->periodId1,
            'grade_id' => $this->gradeId1,
            'section_id' => $this->sectionId1,
            'state' => 'active',
            'effective_from' => '2026-01-15', // Outside 2026-03-01..2026-12-20
            'effective_until' => null,
            'start_ordinal' => 10,
            'end_ordinal' => null,
        ]);

        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollmentId]);
    }

    public function testStudentEnrollmentDateAndOrdinalOrderConstraints(): void
    {
        $this->seedIdentities();

        DB::table('academic_periods')->insert([
            'id' => $this->periodId1, 'name' => '2026', 'name_key' => '2026', 'start_on' => '2026-03-01', 'end_on' => '2026-12-20', 'state' => 'active',
        ]);
        DB::table('grades')->insert([
            'id' => $this->gradeId1, 'name' => '1°', 'name_key' => '1°', 'is_active' => 1,
        ]);
        DB::table('sections')->insert([
            'id' => $this->sectionId1, 'grade_id' => $this->gradeId1, 'name' => 'A', 'name_key' => 'a', 'is_active' => 1,
        ]);

        // 1. Inverted dates (effective_from > effective_until) must fail
        try {
            DB::table('student_enrollments')->insert([
                'id' => '01923456-7000-7000-8000-000000000010',
                'student_id' => $this->studentId,
                'academic_period_id' => $this->periodId1,
                'grade_id' => $this->gradeId1,
                'section_id' => $this->sectionId1,
                'state' => 'closed',
                'effective_from' => '2026-08-01',
                'effective_until' => '2026-05-01',
                'start_ordinal' => 1,
                'end_ordinal' => 2,
            ]);
            $this->fail('Expected failure for inverted declared dates');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // 2. Inverted ordinals (start_ordinal >= end_ordinal) must fail
        try {
            DB::table('student_enrollments')->insert([
                'id' => '01923456-7000-7000-8000-000000000011',
                'student_id' => $this->studentId,
                'academic_period_id' => $this->periodId1,
                'grade_id' => $this->gradeId1,
                'section_id' => $this->sectionId1,
                'state' => 'closed',
                'effective_from' => '2026-03-01',
                'effective_until' => '2026-08-01',
                'start_ordinal' => 50,
                'end_ordinal' => 40,
            ]);
            $this->fail('Expected failure for inverted ordinals');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function testTeachingAssignmentLineageAndSuccessorConstraint(): void
    {
        $this->seedIdentities();

        DB::table('academic_periods')->insert([
            'id' => $this->periodId1, 'name' => '2026', 'name_key' => '2026', 'start_on' => '2026-03-01', 'end_on' => '2026-12-20', 'state' => 'active',
        ]);
        DB::table('grades')->insert([
            'id' => $this->gradeId1, 'name' => '1°', 'name_key' => '1°', 'is_active' => 1,
        ]);
        DB::table('sections')->insert([
            'id' => $this->sectionId1, 'grade_id' => $this->gradeId1, 'name' => 'A', 'name_key' => 'a', 'is_active' => 1,
        ]);
        DB::table('instructional_entries')->insert([
            'id' => $this->entryIdSubject, 'kind' => 'subject', 'name' => 'Matemática', 'name_key' => 'matemática', 'is_active' => 1,
        ]);

        $assignmentId1 = '01923456-8000-7000-8000-000000000001';
        $assignmentId2 = '01923456-8000-7000-8000-000000000002';
        $assignmentId3 = '01923456-8000-7000-8000-000000000003';

        // 1. Initial assignment
        DB::table('teaching_assignments')->insert([
            'id' => $assignmentId1,
            'academic_period_id' => $this->periodId1,
            'teacher_id' => $this->teacherId,
            'instructional_entry_id' => $this->entryIdSubject,
            'grade_id' => $this->gradeId1,
            'section_id' => $this->sectionId1,
            'state' => 'replaced',
            'effective_from' => '2026-03-01',
            'effective_until' => '2026-06-01',
            'start_ordinal' => 1,
            'end_ordinal' => 10,
            'replaces_assignment_id' => null,
        ]);

        // 2. Successor 1 replacing assignment 1
        DB::table('teaching_assignments')->insert([
            'id' => $assignmentId2,
            'academic_period_id' => $this->periodId1,
            'teacher_id' => $this->teacherId,
            'instructional_entry_id' => $this->entryIdSubject,
            'grade_id' => $this->gradeId1,
            'section_id' => $this->sectionId1,
            'state' => 'active',
            'effective_from' => '2026-06-01',
            'effective_until' => null,
            'start_ordinal' => 10,
            'end_ordinal' => null,
            'replaces_assignment_id' => $assignmentId1,
        ]);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignmentId2]);

        // 3. Second successor trying to replace the same assignment 1 must fail UNIQUE(replaces_assignment_id)
        $this->expectException(QueryException::class);
        DB::table('teaching_assignments')->insert([
            'id' => $assignmentId3,
            'academic_period_id' => $this->periodId1,
            'teacher_id' => $this->teacherId,
            'instructional_entry_id' => $this->entryIdSubject,
            'grade_id' => $this->gradeId1,
            'section_id' => $this->sectionId1,
            'state' => 'active',
            'effective_from' => '2026-06-01',
            'effective_until' => null,
            'start_ordinal' => 11,
            'end_ordinal' => null,
            'replaces_assignment_id' => $assignmentId1, // Collision! assignment 1 already replaced
        ]);
    }

    public function testRestrictiveDeletesPreventOrphaningAcademicRecords(): void
    {
        $this->seedIdentities();

        DB::table('academic_periods')->insert([
            'id' => $this->periodId1, 'name' => '2026', 'name_key' => '2026', 'start_on' => '2026-03-01', 'end_on' => '2026-12-20', 'state' => 'active',
        ]);
        DB::table('grades')->insert([
            'id' => $this->gradeId1, 'name' => '1°', 'name_key' => '1°', 'is_active' => 1,
        ]);
        DB::table('sections')->insert([
            'id' => $this->sectionId1, 'grade_id' => $this->gradeId1, 'name' => 'A', 'name_key' => 'a', 'is_active' => 1,
        ]);

        DB::table('student_enrollments')->insert([
            'id' => '01923456-7000-7000-8000-000000000020',
            'student_id' => $this->studentId,
            'academic_period_id' => $this->periodId1,
            'grade_id' => $this->gradeId1,
            'section_id' => $this->sectionId1,
            'state' => 'active',
            'effective_from' => '2026-03-01',
            'effective_until' => null,
            'start_ordinal' => 1,
            'end_ordinal' => null,
        ]);

        // Attempting to delete the grade while an enrollment references it must fail (RESTRICT)
        try {
            DB::table('grades')->where('id', $this->gradeId1)->delete();
            $this->fail('Expected foreign key restrict failure when deleting grade');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key constraint fails', $e->getMessage());
        }

        // Attempting to delete the academic period while an enrollment references it must fail (RESTRICT)
        try {
            DB::table('academic_periods')->where('id', $this->periodId1)->delete();
            $this->fail('Expected foreign key restrict failure when deleting period');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key constraint fails', $e->getMessage());
        }
    }

    private function assertDatabaseHas(string $table, array $data): void
    {
        $query = DB::table($table);
        foreach ($data as $key => $value) {
            $query->where($key, $value);
        }
        $this->assertTrue($query->exists(), "Failed asserting that table [{$table}] contains matching row.");
    }
}
