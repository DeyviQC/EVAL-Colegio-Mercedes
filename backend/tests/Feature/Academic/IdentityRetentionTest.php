<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Domain\Academic\AcademicWriteGuard;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Task 2.3 Feature Test: Identity retention and persistent singleton write guard.
 *
 * Verifies:
 * - Exactly one pre-created academic write guard row exists with id = 1.
 * - CHECK constraint (id = 1) prevents creating guard rows with other IDs.
 * - Primary key prevents inserting duplicate guard rows.
 * - Monotonic ordinal allocation under transaction and rollback safety.
 * - Retained actor identity after account deactivation and credential removal.
 * - Durable non-reusable IDs (primary key rejects ID collision).
 * - Referential integrity constraints (RESTRICT delete behavior).
 */
final class IdentityRetentionTest extends TestCase
{
    private AcademicWriteGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new AcademicWriteGuard(DB::connection());
    }

    public function testExactlyOnePreCreatedGuardRowExists(): void
    {
        $this->guard->ensureGuardExists();

        $rows = DB::table('academic_write_guard')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
    }

    public function testGuardRejectsRowsWithIdOtherThanOneViaCheckConstraint(): void
    {
        $this->expectException(QueryException::class);

        // Attempting to insert a second guard row with id = 2 must fail CHECK (id = 1)
        DB::table('academic_write_guard')->insert([
            'id' => 2,
            'last_ordinal' => 10,
        ]);
    }

    public function testGuardRejectsDuplicateIdOneViaPrimaryKey(): void
    {
        $this->expectException(QueryException::class);

        // Attempting to insert duplicate id = 1 must fail Primary Key
        DB::table('academic_write_guard')->insert([
            'id' => 1,
            'last_ordinal' => 50,
        ]);
    }

    public function testMonotonicOrdinalAllocationAndRollbackSafety(): void
    {
        $initialOrdinal = $this->guard->getCurrentOrdinal();

        // Transaction 1: allocate boundary
        DB::connection()->transaction(function () use (&$boundary1) {
            $boundary1 = $this->guard->allocateNextBoundary();
        });

        $this->assertSame($initialOrdinal + 1, $boundary1->ordinal);
        $this->assertSame('UTC', $boundary1->timestamp->getTimezone()->getName());
        $this->assertSame($boundary1->ordinal, $this->guard->getCurrentOrdinal());

        // Transaction 2: allocate another boundary
        DB::connection()->transaction(function () use (&$boundary2) {
            $boundary2 = $this->guard->allocateNextBoundary();
        });

        $this->assertSame($boundary1->ordinal + 1, $boundary2->ordinal);
        $this->assertTrue($boundary2->compareTo($boundary1) > 0);

        // Transaction 3: failed transaction rolls back ordinal allocation
        $ordinalBeforeFailure = $this->guard->getCurrentOrdinal();

        try {
            DB::connection()->transaction(function () {
                $this->guard->allocateNextBoundary();
                throw new \RuntimeException('Simulated failure during write');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame(
            $ordinalBeforeFailure,
            $this->guard->getCurrentOrdinal(),
            'Ordinal allocation must roll back when transaction fails.'
        );
    }

    public function testRetainedActorIdentityPersistsAfterDeactivationAndCredentialRemoval(): void
    {
        $actorId = '01923456-789a-7b1c-8d2e-3f4a5b6c7d8e'; // UUID v7 format

        // Clean up test actor if exists
        DB::table('retained_identities')->where('id', $actorId)->delete();

        // 1. Create active actor identity
        DB::table('retained_identities')->insert([
            'id' => $actorId,
            'identity_type' => 'actor',
            'credential_status' => 'active',
            'deactivated_at' => null,
        ]);

        $actor = DB::table('retained_identities')->where('id', $actorId)->first();
        $this->assertNotNull($actor);
        $this->assertSame('active', $actor->credential_status);
        $this->assertNull($actor->deactivated_at);

        // 2. Deactivate account
        $now = date('Y-m-d H:i:s');
        DB::table('retained_identities')->where('id', $actorId)->update([
            'credential_status' => 'deactivated',
            'deactivated_at' => $now,
        ]);

        $deactivated = DB::table('retained_identities')->where('id', $actorId)->first();
        $this->assertNotNull($deactivated, 'Identity record must persist after account deactivation');
        $this->assertSame('deactivated', $deactivated->credential_status);
        $this->assertNotNull($deactivated->deactivated_at);

        // 3. Remove credentials completely
        DB::table('retained_identities')->where('id', $actorId)->update([
            'credential_status' => 'removed',
        ]);

        $removed = DB::table('retained_identities')->where('id', $actorId)->first();
        $this->assertNotNull($removed, 'Identity record must persist even after credential removal');
        $this->assertSame('removed', $removed->credential_status);
        $this->assertSame($actorId, $removed->id, 'Identity ID must remain identical and durable');
    }

    public function testIdentifierReuseIsRejected(): void
    {
        $existingId = '01923456-0000-7000-8000-000000000001';

        // Ensure record exists
        DB::table('retained_identities')->where('id', $existingId)->delete();
        DB::table('retained_identities')->insert([
            'id' => $existingId,
            'identity_type' => 'student',
            'credential_status' => 'deactivated',
        ]);

        // Attempting to reuse the same ID for a new teacher/student must fail
        $this->expectException(QueryException::class);
        DB::table('retained_identities')->insert([
            'id' => $existingId,
            'identity_type' => 'teacher',
            'credential_status' => 'active',
        ]);
    }

    public function testIdentityTypeCheckConstraint(): void
    {
        $this->expectException(QueryException::class);

        // An invalid identity_type must be rejected by MySQL CHECK constraint
        DB::table('retained_identities')->insert([
            'id' => '01923456-9999-7000-8000-000000000002',
            'identity_type' => 'invalid_type',
            'credential_status' => 'active',
        ]);
    }

    public function testRestrictDeleteOnReferencedRetainedIdentity(): void
    {
        $actorId = '01923456-1111-7000-8000-000000000003';

        // Clean up references and actor
        DB::table('academic_identity_references')->where('retained_identity_id', $actorId)->delete();
        DB::table('retained_identities')->where('id', $actorId)->delete();

        // 1. Create retained identity
        DB::table('retained_identities')->insert([
            'id' => $actorId,
            'identity_type' => 'actor',
            'credential_status' => 'active',
        ]);

        // 2. Insert a referencing child row
        DB::table('academic_identity_references')->insert([
            'retained_identity_id' => $actorId,
            'reference_context' => 'test_audit_trail',
        ]);

        // 3. Attempting to delete the referenced retained identity must fail with FK violation (RESTRICT)
        try {
            DB::table('retained_identities')->where('id', $actorId)->delete();
            $this->fail('Expected QueryException for foreign key restrict constraint');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key constraint fails', $e->getMessage());
        } finally {
            DB::table('academic_identity_references')->where('retained_identity_id', $actorId)->delete();
            DB::table('retained_identities')->where('id', $actorId)->delete();
        }
    }
}
