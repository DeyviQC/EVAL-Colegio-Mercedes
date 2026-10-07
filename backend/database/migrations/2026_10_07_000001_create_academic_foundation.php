<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('The academic foundation requires MySQL; SQLite is not an integrity test substitute.');
        }
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['director', 'subdirector', 'docente', 'estudiante'])->default('estudiante');
                $table->boolean('is_active')->default(true);
            });
        }
        $this->create('academic_write_guard', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_ordinal')->default(1);
        });
        if (! DB::table('academic_write_guard')->where('id', 1)->exists()) {
            DB::table('academic_write_guard')->insert(['id' => 1, 'next_ordinal' => 1]);
        }
        $this->create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->char('name_key', 64)->unique();
            $table->date('start_on');
            $table->date('end_on');
            $table->enum('state', ['planned', 'active', 'closed'])->default('planned');
            $table->unsignedTinyInteger('active_slot')->nullable()->storedAs("CASE WHEN state='active' THEN 1 ELSE NULL END")->unique();
        });
        foreach (['instructional_entries', 'grades', 'sections'] as $entity) {
            $this->create($entity, function (Blueprint $table) use ($entity) {
                $table->id();
                $table->string('name', 120);
                $table->char('name_key', 64);
                $table->boolean('is_active')->default(true);
                $table->char('active_name_key', 64)->nullable()->storedAs('CASE WHEN is_active=1 THEN name_key ELSE NULL END');
                if ($entity === 'instructional_entries') {
                    $table->enum('classification', ['subject', 'area']);
                    $table->unique(['classification', 'active_name_key']);
                } elseif ($entity === 'sections') {
                    $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
                    $table->unique(['grade_id', 'active_name_key']);
                    $table->unique(['id', 'grade_id']);
                } else {
                    $table->unique('active_name_key');
                }
            });
        }
        foreach (['student_enrollments', 'teaching_assignments'] as $entity) {
            $this->create($entity, function (Blueprint $table) use ($entity) {
                $table->id();
                $table->foreignId('academic_period_id')->constrained('academic_periods')->restrictOnDelete();
                $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
                $table->foreignId('section_id');
                $table->foreign(['section_id', 'grade_id'])->references(['id', 'grade_id'])->on('sections')->restrictOnDelete();
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->unsignedBigInteger('operational_start_key')->nullable();
                $table->unsignedBigInteger('operational_end_key')->nullable();
                if ($entity === 'student_enrollments') {
                    $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
                    $table->enum('state', ['active', 'transferred', 'closed'])->default('active');
                    $table->foreignId('transferred_from_id')->nullable()->unique()->constrained('student_enrollments')->restrictOnDelete();
                    $table->unsignedBigInteger('active_student')->nullable()->storedAs("CASE WHEN state='active' THEN student_id ELSE NULL END");
                    $table->unique(['academic_period_id', 'active_student']);
                } else {
                    $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
                    $table->foreignId('instructional_entry_id')->constrained('instructional_entries')->restrictOnDelete();
                    $table->enum('state', ['planned', 'active', 'closed'])->default('planned');
                    $table->foreignId('replaces_assignment_id')->nullable()->unique()->constrained('teaching_assignments')->restrictOnDelete();
                    $table->index(['teacher_id', 'academic_period_id', 'instructional_entry_id', 'section_id'], 'assignment_scope_index');
                }
            });
        }
        $this->create('activity_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->unique(['id', 'teaching_assignment_id']);
            $table->string('title', 200);
            $table->text('description');
            $table->timestamps();
        });
        $this->create('submission_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('activity_id')->constrained('activity_references')->restrictOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->foreignId('accepted_under_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->dateTime('accepted_at', 6);
            $table->unsignedBigInteger('acceptance_operation_key');
            $table->text('answer');
            $table->foreign(['activity_id', 'teaching_assignment_id'])->references(['id', 'teaching_assignment_id'])->on('activity_references')->restrictOnDelete();
            $table->unique(['student_id', 'activity_id']);
        });
        $this->create('academic_lifecycle_events', function (Blueprint $table) {
            $table->id();
            foreach (['period' => 'academic_periods', 'entry' => 'instructional_entries', 'grade' => 'grades',
                'section' => 'sections', 'enrollment' => 'student_enrollments', 'assignment' => 'teaching_assignments'] as $kind => $target) {
                $table->foreignId($kind.'_id')->nullable()->constrained($target)->restrictOnDelete();
            }
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('previous_state', 30)->nullable();
            $table->string('new_state', 30)->nullable();
            $table->date('effective_on');
            $table->unsignedBigInteger('operation_key');
            $table->uuid('correlation_id');
            $table->dateTime('recorded_at', 6);
            $table->json('metadata');
        });
        $this->constraint('ALTER TABLE academic_write_guard ADD CONSTRAINT singleton_guard CHECK (id=1)');
        $this->constraint('ALTER TABLE academic_periods ADD CONSTRAINT valid_period_dates CHECK (start_on<=end_on)');
        foreach (['student_enrollments', 'teaching_assignments'] as $entity) {
            $this->constraint("ALTER TABLE $entity ADD CONSTRAINT {$entity}_dates CHECK (effective_until IS NULL OR effective_until>=effective_from)");
            $this->constraint("ALTER TABLE $entity ADD CONSTRAINT {$entity}_keys CHECK (operational_end_key IS NULL OR operational_end_key>operational_start_key)");
            $this->constraint("ALTER TABLE $entity ADD CONSTRAINT {$entity}_closed CHECK (state NOT IN ('closed','transferred') OR (effective_until IS NOT NULL AND operational_start_key IS NOT NULL AND operational_end_key IS NOT NULL))");
            $this->constraint("ALTER TABLE $entity ADD CONSTRAINT {$entity}_active CHECK (state<>'active' OR (operational_start_key IS NOT NULL AND operational_end_key IS NULL))");
        }
        $this->constraint('ALTER TABLE academic_lifecycle_events ADD CONSTRAINT exactly_one_event_target CHECK ((period_id IS NOT NULL)+(entry_id IS NOT NULL)+(grade_id IS NOT NULL)+(section_id IS NOT NULL)+(enrollment_id IS NOT NULL)+(assignment_id IS NOT NULL)=1)');
        $this->trigger("CREATE TRIGGER immutable_activity_route BEFORE UPDATE ON activity_references FOR EACH ROW BEGIN IF NEW.teaching_assignment_id<>OLD.teaching_assignment_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Original assignment is immutable'; END IF; END");
        $this->trigger("CREATE TRIGGER immutable_submission_context BEFORE UPDATE ON submission_references FOR EACH ROW BEGIN IF NEW.student_id<>OLD.student_id OR NEW.activity_id<>OLD.activity_id OR NEW.teaching_assignment_id<>OLD.teaching_assignment_id OR NEW.accepted_under_enrollment_id<>OLD.accepted_under_enrollment_id OR NEW.acceptance_operation_key<>OLD.acceptance_operation_key OR NEW.accepted_at<>OLD.accepted_at THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Acceptance context is immutable'; END IF; END");
    }

    private function create(string $name, Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }

    private function constraint(string $sql): void
    {
        preg_match('/ALTER TABLE (\w+) ADD CONSTRAINT (\w+)/', $sql, $matches);
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $matches[1])->where('CONSTRAINT_NAME', $matches[2])->exists();
        if (! $exists) {
            DB::statement($sql);
        }
    }

    private function trigger(string $sql): void
    {
        preg_match('/CREATE TRIGGER (\w+)/', $sql, $matches);
        if (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $matches[1])->exists()) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Retained academic history must not be destroyed. Disable writers and use a reviewed forward repair.');
    }
};
