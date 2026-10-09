<?php

declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        DB::schema()->create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            foreach (['teacher_id'=>'retained_identities','academic_period_id'=>'academic_periods','instructional_entry_id'=>'instructional_entries','grade_id'=>'grades'] as $column=>$parent) {
                $table->foreignId($column)->constrained($parent)->restrictOnDelete()->restrictOnUpdate();
            }
            $table->unsignedBigInteger('section_id');
            $table->foreign(['section_id','grade_id'])->references(['id','grade_id'])->on('sections')->restrictOnDelete()->restrictOnUpdate();
            $table->string('state',32)->default('planned');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('operational_start_key')->nullable();
            $table->unsignedBigInteger('operational_end_key')->nullable();
            $table->foreignId('replaces_assignment_id')->nullable()->unique()->constrained('teaching_assignments')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps();
        });
        DB::connection()->statement(<<<'SQL'
            ALTER TABLE teaching_assignments
            ADD CONSTRAINT assignment_state CHECK (state IN ('planned','active','closed')),
            ADD CONSTRAINT assignment_dates CHECK
                (effective_until IS NULL OR effective_from<=effective_until),
            ADD CONSTRAINT assignment_keys CHECK (
                (operational_start_key IS NULL OR operational_start_key BETWEEN 1 AND 9223372036854775807) AND
                (operational_end_key IS NULL OR
                    (operational_start_key IS NOT NULL AND operational_end_key>operational_start_key AND
                     operational_end_key<=9223372036854775807))),
            ADD CONSTRAINT assignment_lifecycle CHECK (
                (state='planned' AND operational_start_key IS NULL AND operational_end_key IS NULL) OR
                (state='active' AND operational_start_key IS NOT NULL AND operational_end_key IS NULL) OR
                (state='closed' AND operational_start_key IS NOT NULL AND operational_end_key IS NOT NULL AND effective_until IS NOT NULL))
            SQL);
        // Cross-row overlap/containment/parent-state authority belongs to later locked commands.
        // No active-parent-only predicate or temporal mapping is selected here.
    }
    public function down(): void
    {
        throw new RuntimeException('Preserve assignment history; use a reviewed forward repair.');
    }
};
