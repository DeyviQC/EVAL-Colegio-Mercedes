<?php

declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

// U1 identity/guard prerequisite only. No academic aggregates or lifecycle ledger.
return new class extends Migration {
    public function up(): void
    {
        if (PHP_INT_SIZE !== 8) { throw new RuntimeException('U1 requires 64-bit PHP.'); }
        DB::schema()->create('academic_write_guard', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_ordinal')->default(0);
        });
        DB::connection()->statement('ALTER TABLE academic_write_guard ADD CONSTRAINT singleton_guard CHECK (id=1), ADD CONSTRAINT ordinal_range CHECK (last_ordinal<=9223372036854775807)');
        DB::table('academic_write_guard')->insert(['id' => 1, 'last_ordinal' => 0]);
        DB::connection()->unprepared("CREATE TRIGGER ordinal_monotonic BEFORE UPDATE ON academic_write_guard FOR EACH ROW BEGIN IF NEW.last_ordinal<OLD.last_ordinal THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operational ordinal cannot regress'; END IF; END");

        // One permanent local identity can be referenced as actor, student and teacher.
        // This table does not authenticate accounts or grant role authority.
        DB::schema()->create('retained_identities', function (Blueprint $table) {
            $table->id();
            $table->string('credential_status', 32)->default('active');
            $table->timestamp('deactivated_at')->nullable();
        });
        DB::connection()->statement("ALTER TABLE retained_identities ADD CONSTRAINT retained_status CHECK (credential_status IN ('active','deactivated','removed'))");
        DB::connection()->unprepared("CREATE TRIGGER retained_identity_delete BEFORE DELETE ON retained_identities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retained identity is permanent'");
        DB::connection()->unprepared("CREATE TRIGGER retained_identity_update BEFORE UPDATE ON retained_identities FOR EACH ROW BEGIN IF NEW.id<>OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retained identity ID is immutable'; END IF; END");
        DB::connection()->unprepared("CREATE TRIGGER retained_identity_insert BEFORE INSERT ON retained_identities FOR EACH ROW BEGIN IF NEW.id<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retained IDs are allocated locally'; END IF; END");
        DB::schema()->create('academic_identity_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retained_identity_id')->constrained('retained_identities')->restrictOnDelete()->restrictOnUpdate();
            $table->string('reference_context', 32);
        });
        DB::connection()->statement("ALTER TABLE academic_identity_references ADD CONSTRAINT retained_context CHECK (reference_context IN ('actor','student','teacher'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Permanent identities cannot be torn down; explicitly rebuild only the disposable U1 database.');
    }
};
