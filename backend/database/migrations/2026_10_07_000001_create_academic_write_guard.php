<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Task 2.3 Migration: Pre-created singleton academic_write_guard and retained_identities.
 *
 * Implements:
 * - Singleton persistent mutex row with MySQL CHECK (id = 1) constraint.
 * - Pre-created technical row with id = 1.
 * - Retained local identity table ensuring durable identity independent of credentials,
 *   rejecting identifier reuse and providing target for restrictive foreign keys.
 * - Restrictive references to retained identities (academic_identity_references) proving
 *   that deletion of referenced identities is prohibited (ON DELETE RESTRICT).
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. academic_write_guard
        DB::schema()->create('academic_write_guard', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->default(1);
            $table->unsignedBigInteger('last_ordinal')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');
        });

        // MySQL CHECK constraint enforcing singleton row id = 1
        DB::connection()->statement('ALTER TABLE `academic_write_guard` ADD CONSTRAINT `chk_academic_write_guard_single_row` CHECK (`id` = 1)');

        // Pre-create the single technical guard row
        DB::table('academic_write_guard')->insert([
            'id' => 1,
            'last_ordinal' => 0,
        ]);

        // 2. retained_identities (actor, student, teacher)
        DB::schema()->create('retained_identities', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('identity_type', 32);
            $table->string('credential_status', 32)->default('active');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary('id');
        });

        DB::connection()->statement("ALTER TABLE `retained_identities` ADD CONSTRAINT `chk_retained_identities_type` CHECK (`identity_type` IN ('actor', 'student', 'teacher'))");
        DB::connection()->statement("ALTER TABLE `retained_identities` ADD CONSTRAINT `chk_retained_identities_status` CHECK (`credential_status` IN ('active', 'deactivated', 'removed'))");

        // 3. academic_identity_references proving restrictive foreign keys
        DB::schema()->create('academic_identity_references', function (Blueprint $table) {
            $table->id();
            $table->char('retained_identity_id', 36);
            $table->string('reference_context', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('retained_identity_id')
                ->references('id')
                ->on('retained_identities')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('academic_identity_references');
        DB::schema()->dropIfExists('retained_identities');
        DB::schema()->dropIfExists('academic_write_guard');
    }
};
