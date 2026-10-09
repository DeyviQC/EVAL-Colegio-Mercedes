<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;

return new class extends Migration {
    public function up(): void
    {
        DB::schema()->create('academic_lifecycle_events',function(Blueprint $table) {
            $table->id();
            $table->string('entity_type',16);
            foreach (LifecycleEvent::TARGETS as $kind=>$parent) {
                $table->foreignId($kind.'_id')->nullable()->constrained($parent)->restrictOnDelete()->restrictOnUpdate();
                $table->index([$kind.'_id','operation_key']);
            }
            $table->foreignId('actor_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->string('event_type',40);
            $table->string('previous_state',32)->nullable();
            $table->string('new_state',32)->nullable();
            $table->date('effective_on');
            $table->unsignedBigInteger('operation_key');
            $table->char('correlation_id',32);
            $table->dateTime('recorded_at',6);
            $table->json('metadata');
            $table->index(['actor_id','operation_key']);
            $table->index('correlation_id');
        });
        DB::connection()->statement(<<<'SQL'
            ALTER TABLE academic_lifecycle_events
            ADD CONSTRAINT event_one_target CHECK (
                (period_id IS NOT NULL)+(entry_id IS NOT NULL)+(grade_id IS NOT NULL)+
                (section_id IS NOT NULL)+(enrollment_id IS NOT NULL)+(assignment_id IS NOT NULL)=1),
            ADD CONSTRAINT event_matching_type CHECK (
                (entity_type='period' AND period_id IS NOT NULL) OR
                (entity_type='entry' AND entry_id IS NOT NULL) OR
                (entity_type='grade' AND grade_id IS NOT NULL) OR
                (entity_type='section' AND section_id IS NOT NULL) OR
                (entity_type='enrollment' AND enrollment_id IS NOT NULL) OR
                (entity_type='assignment' AND assignment_id IS NOT NULL)),
            ADD CONSTRAINT event_key_range CHECK (operation_key BETWEEN 1 AND 9223372036854775807)
            SQL);
        DB::connection()->unprepared("CREATE TRIGGER ledger_no_update BEFORE UPDATE ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lifecycle evidence is append-only'");
        DB::connection()->unprepared("CREATE TRIGGER ledger_no_delete BEFORE DELETE ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lifecycle evidence is append-only'");
    }
    public function down():void { throw new RuntimeException('Preserve lifecycle history; use a reviewed forward repair.'); }
};
