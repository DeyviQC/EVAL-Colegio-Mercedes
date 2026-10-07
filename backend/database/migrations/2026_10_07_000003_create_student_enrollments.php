<?php

declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        DB::schema()->create('student_enrollments', function (Blueprint $table) {
            $table->id();
            foreach (['student_id'=>'retained_identities','academic_period_id'=>'academic_periods','grade_id'=>'grades'] as $column=>$parent) {
                $table->foreignId($column)->constrained($parent)->restrictOnDelete()->restrictOnUpdate();
            }
            $table->unsignedBigInteger('section_id');
            $table->foreign(['section_id','grade_id'])->references(['id','grade_id'])->on('sections')->restrictOnDelete()->restrictOnUpdate();
            $table->string('state',32)->default('active');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('operational_start_key');
            $table->unsignedBigInteger('operational_end_key')->nullable();
            $table->timestamps();
        });
        DB::connection()->statement(<<<'SQL'
            ALTER TABLE student_enrollments
            ADD CONSTRAINT enrollment_state CHECK (state IN ('active','transferred','closed')),
            ADD CONSTRAINT enrollment_dates CHECK
                (effective_until IS NULL OR effective_from<=effective_until),
            ADD CONSTRAINT enrollment_keys CHECK (
                operational_start_key BETWEEN 1 AND 9223372036854775807 AND
                (operational_end_key IS NULL OR
                    (operational_end_key>operational_start_key AND
                     operational_end_key<=9223372036854775807))),
            ADD CONSTRAINT enrollment_lifecycle CHECK (
                (state='active' AND operational_end_key IS NULL) OR
                (state IN ('closed','transferred') AND effective_until IS NOT NULL AND operational_end_key IS NOT NULL)),
            ADD active_student BIGINT UNSIGNED GENERATED ALWAYS AS
                (CASE WHEN state='active' THEN student_id ELSE NULL END) STORED,
            ADD UNIQUE INDEX uq_enrollment_active(academic_period_id,active_student)
            SQL);
        // No enrollment-period date containment or automatic expiry rule.
    }
    public function down(): void
    {
        throw new RuntimeException('Preserve enrollment history; use a reviewed forward repair.');
    }
};
