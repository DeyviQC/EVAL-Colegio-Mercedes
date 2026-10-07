<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Task 2.4 Migration 3: Teaching assignments table.
 *
 * Implements:
 * - Restrictive foreign keys to academic period, retained teacher identity, instructional entry,
 *   grade, and composite foreign key (section_id, grade_id) to sections(id, grade_id).
 * - Self-referencing replaces_assignment_id with UNIQUE constraint guaranteeing unique successor lineage.
 * - State check constraint (planned, active, replaced, closed).
 * - Declared date order check (effective_from <= effective_until).
 * - Operational ordinal order check (start_ordinal < end_ordinal).
 */
return new class extends Migration {
    public function up(): void
    {
        DB::schema()->create('teaching_assignments', function (Blueprint $table) {
            $table->char('id', 36);
            $table->char('academic_period_id', 36);
            $table->char('teacher_id', 36);
            $table->char('instructional_entry_id', 36);
            $table->char('grade_id', 36);
            $table->char('section_id', 36);
            $table->string('state', 32)->default('planned');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('start_ordinal');
            $table->unsignedBigInteger('end_ordinal')->nullable();
            $table->char('replaces_assignment_id', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');

            $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('restrict');
            $table->foreign('teacher_id')->references('id')->on('retained_identities')->onDelete('restrict');
            $table->foreign('instructional_entry_id')->references('id')->on('instructional_entries')->onDelete('restrict');
            $table->foreign('grade_id')->references('id')->on('grades')->onDelete('restrict');
            $table->foreign(['section_id', 'grade_id'])->references(['id', 'grade_id'])->on('sections')->onDelete('restrict');
            $table->foreign('replaces_assignment_id')->references('id')->on('teaching_assignments')->onDelete('restrict');

            $table->unique('replaces_assignment_id', 'uq_teaching_assignments_replaces_assignment_id');
        });

        DB::connection()->statement('
            ALTER TABLE `teaching_assignments`
            ADD CONSTRAINT `chk_assignment_state` CHECK (`state` IN (\'planned\', \'active\', \'replaced\', \'closed\')),
            ADD CONSTRAINT `chk_assignment_dates` CHECK (`effective_until` IS NULL OR `effective_from` <= `effective_until`),
            ADD CONSTRAINT `chk_assignment_ordinals` CHECK (`end_ordinal` IS NULL OR `start_ordinal` < `end_ordinal`)
        ');
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('teaching_assignments');
    }
};
