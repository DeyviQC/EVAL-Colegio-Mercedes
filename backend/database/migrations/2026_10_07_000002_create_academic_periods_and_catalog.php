<?php

declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        DB::schema()->create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name',255);
            $table->binary('name_key',2048);
            $table->date('start_on');
            $table->date('end_on');
            $table->string('state',32)->default('planned');
            $table->timestamps();
            $table->unique('name_key','uq_period_name');
        });
        DB::connection()->statement(<<<'SQL'
            ALTER TABLE academic_periods
            ADD active_guard TINYINT GENERATED ALWAYS AS
                (CASE WHEN state='active' THEN 1 ELSE NULL END) STORED,
            ADD UNIQUE INDEX uq_period_active(active_guard),
            ADD CONSTRAINT period_state CHECK (state IN ('planned','active','closed')),
            ADD CONSTRAINT period_dates CHECK (start_on<=end_on)
            SQL);
        foreach (['instructional_entries','grades','sections'] as $entity) {
            DB::schema()->create($entity, function (Blueprint $table) use ($entity) {
                $table->id();
                $table->string('name',255);
                $table->binary('name_key',2048);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                if ($entity==='instructional_entries') { $table->string('kind',32); }
                if ($entity==='sections') {
                    $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete()->restrictOnUpdate();
                    $table->unique(['id','grade_id'],'uq_sections_id_grade');
                }
            });
            DB::connection()->statement(<<<SQL
                ALTER TABLE $entity
                ADD active_name_key VARBINARY(2048) GENERATED ALWAYS AS
                    (CASE WHEN is_active=1 THEN name_key ELSE NULL END) STORED,
                ADD CONSTRAINT {$entity}_active CHECK (is_active IN (0,1))
                SQL);
            $columns=match($entity) { 'instructional_entries'=>'kind,active_name_key', 'sections'=>'grade_id,active_name_key', default=>'active_name_key' };
            DB::connection()->statement("ALTER TABLE $entity ADD UNIQUE INDEX uq_{$entity}_active_name ($columns)");
        }
        DB::connection()->statement("ALTER TABLE instructional_entries ADD CONSTRAINT entry_kind CHECK (kind IN ('subject','area'))");
    }
    public function down(): void
    {
        throw new RuntimeException('Preserve academic history; use a reviewed forward repair.');
    }
};
