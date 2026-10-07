<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Task 2.4 Migration 1: Academic periods and catalog tables.
 *
 * Implements:
 * - academic_periods with all-state name uniqueness, date ordering check,
 *   state check, and generated virtual active_guard column ensuring at most 1 active period.
 * - instructional_entries with kind check, approved equality name key, and active scoped uniqueness.
 * - grades with active name key uniqueness.
 * - sections with grade foreign key, active scoped uniqueness by grade, and indexed (id, grade_id) pair.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. academic_periods
        DB::schema()->create('academic_periods', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->date('start_on');
            $table->date('end_on');
            $table->string('state', 32)->default('planned');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');
            $table->unique('name_key', 'uq_academic_periods_name_key');
        });

        // Virtual generated column for single-active guard: only 'active' produces a non-null value (1)
        DB::connection()->statement('
            ALTER TABLE `academic_periods`
            ADD COLUMN `active_guard` TINYINT GENERATED ALWAYS AS (CASE WHEN `state` = \'active\' THEN 1 ELSE NULL END) VIRTUAL,
            ADD UNIQUE INDEX `uq_academic_periods_single_active` (`active_guard`),
            ADD CONSTRAINT `chk_period_state` CHECK (`state` IN (\'planned\', \'active\', \'closed\')),
            ADD CONSTRAINT `chk_period_dates` CHECK (`start_on` <= `end_on`)
        ');

        // 2. instructional_entries (subjects and areas)
        DB::schema()->create('instructional_entries', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('kind', 32);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');
        });

        DB::connection()->statement('
            ALTER TABLE `instructional_entries`
            ADD COLUMN `active_scoped_key` VARCHAR(300) GENERATED ALWAYS AS (CASE WHEN `is_active` = 1 THEN CONCAT(`kind`, \':\', `name_key`) ELSE NULL END) VIRTUAL,
            ADD UNIQUE INDEX `uq_instructional_entries_active_name` (`active_scoped_key`),
            ADD CONSTRAINT `chk_instructional_entry_kind` CHECK (`kind` IN (\'subject\', \'area\'))
        ');

        // 3. grades
        DB::schema()->create('grades', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');
        });

        DB::connection()->statement('
            ALTER TABLE `grades`
            ADD COLUMN `active_name_key` VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN `is_active` = 1 THEN `name_key` ELSE NULL END) VIRTUAL,
            ADD UNIQUE INDEX `uq_grades_active_name` (`active_name_key`)
        ');

        // 4. sections
        DB::schema()->create('sections', function (Blueprint $table) {
            $table->char('id', 36);
            $table->char('grade_id', 36);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');
            $table->foreign('grade_id')->references('id')->on('grades')->onDelete('restrict');
            $table->unique(['id', 'grade_id'], 'uq_sections_id_grade_id');
        });

        DB::connection()->statement('
            ALTER TABLE `sections`
            ADD COLUMN `active_scoped_key` VARCHAR(300) GENERATED ALWAYS AS (CASE WHEN `is_active` = 1 THEN CONCAT(`grade_id`, \':\', `name_key`) ELSE NULL END) VIRTUAL,
            ADD UNIQUE INDEX `uq_sections_active_name_in_grade` (`active_scoped_key`)
        ');
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('sections');
        DB::schema()->dropIfExists('grades');
        DB::schema()->dropIfExists('instructional_entries');
        DB::schema()->dropIfExists('academic_periods');
    }
};
