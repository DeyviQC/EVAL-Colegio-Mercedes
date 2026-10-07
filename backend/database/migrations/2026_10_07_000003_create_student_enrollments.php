<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Task 2.4 Migration 2: Student enrollments table.
 *
 * Implements:
 * - Restrictive foreign keys to retained student identity, academic period, grade,
 *   and composite foreign key (section_id, grade_id) to sections(id, grade_id).
 * - State check constraint (planned, active, transferred, closed).
 * - Declared date order check (effective_from <= effective_until).
 * - Operational ordinal order check (start_ordinal < end_ordinal).
 * - Approved decision 1.3: No enrollment-containment constraint at database schema level.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::schema()->create('student_enrollments', function (Blueprint $table) {
            $table->char('id', 36);
            $table->char('student_id', 36);
            $table->char('academic_period_id', 36);
            $table->char('grade_id', 36);
            $table->char('section_id', 36);
            $table->string('state', 32)->default('active');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('start_ordinal');
            $table->unsignedBigInteger('end_ordinal')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');

            $table->foreign('student_id')->references('id')->on('retained_identities')->onDelete('restrict');
            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('restrict');
            $table->foreign('grade_id')->references('id')->on('grades')->onDelete('restrict');
            $table->foreign(['section_id', 'grade_id'])->references(['id', 'grade_id'])->on('sections')->onDelete('restrict');
        });

        DB::connection()->statement('
            ALTER TABLE `student_enrollments`
            ADD CONSTRAINT `chk_enrollment_state` CHECK (`state` IN (\'planned\', \'active\', \'transferred\', \'closed\')),
            ADD CONSTRAINT `chk_enrollment_dates` CHECK (`effective_until` IS NULL OR `effective_from` <= `effective_until`),
            ADD CONSTRAINT `chk_enrollment_ordinals` CHECK (`end_ordinal` IS NULL OR `start_ordinal` < `end_ordinal`)
        ');
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('student_enrollments');
    }
};
