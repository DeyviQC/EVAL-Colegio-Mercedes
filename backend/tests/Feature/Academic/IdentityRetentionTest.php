<?php

declare(strict_types=1);
namespace Tests\Feature\Academic;

use App\Domain\Academic\AcademicWriteGuard;
use App\Domain\Academic\TransferTiming;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IdentityRetentionTest extends TestCase
{
    private AcademicWriteGuard $guard;

    protected function setUp(): void
    {
        $this->assertSame('eval_u1_test', DB::connection()->getDatabaseName());
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::connection()->beginTransaction();
        $this->guard = new AcademicWriteGuard(DB::connection());
    }

    protected function tearDown(): void
    {
        while (DB::connection()->transactionLevel() > 0) { DB::connection()->rollBack(); }
    }

    private function identity(): int
    {
        return DB::table('retained_identities')->insertGetId(['credential_status' => 'active']);
    }

    private function denied(callable $operation, array $codes): void
    {
        try { $operation(); $this->fail('Operation unexpectedly permitted.'); }
        catch (QueryException $error) { $this->assertContains((int) $error->errorInfo[1], $codes); }
    }

    public function testSharedIdentityUsesUnsignedBigint(): void
    {
        $column = DB::connection()->selectOne("SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='retained_identities' AND COLUMN_NAME='id'");
        $this->assertSame('bigint unsigned', $column->type);
        $id = $this->identity();
        $this->assertGreaterThan(0, $id);
        // Actor, teacher and student references resolve one retained identity, not exclusive role IDs.
        foreach (['actor', 'student', 'teacher'] as $context) {
            DB::table('academic_identity_references')->insert(['retained_identity_id' => $id, 'reference_context' => $context]);
        }
        $this->assertSame(3, DB::table('academic_identity_references')->where('retained_identity_id', $id)->count());
    }

    public function testOnlyU1TablesAndMigrationAreInstalled(): void
    {
        $tables = DB::connection()->select('SHOW TABLES');
        $names = array_map(fn ($row) => array_values((array) $row)[0], $tables);
        sort($names);
        $this->assertSame(['academic_identity_references', 'academic_write_guard', 'migrations', 'retained_identities'], $names);
        $this->assertSame(['2026_10_07_000001_create_academic_write_guard'], DB::table('migrations')->pluck('migration')->all());
    }

    public function testPersistentGuardRejectsOtherIdsAndDuplicates(): void
    {
        $this->guard->ensureGuardExists();
        $this->assertSame(1, DB::table('academic_write_guard')->count());
        $this->denied(fn () => DB::connection('migration')->table('academic_write_guard')->insert(['id' => 2]), [3819]);
        $this->denied(fn () => DB::connection('migration')->table('academic_write_guard')->insert(['id' => 1]), [1062]);
    }

    public function testUnreferencedIdentityIsAlsoPermanentAndAllocatedIdsIncrease(): void
    {
        $first = $this->identity();
        $this->denied(fn () => DB::table('retained_identities')->where('id', $first)->delete(), [1142]);
        $second = $this->identity();
        $this->assertGreaterThan($first, $second);
        $this->assertTrue(DB::table('retained_identities')->where('id', $first)->exists());
    }

    public function testIdentityReferenceCannotBeReboundByRuntime(): void
    {
        $id = $this->identity();
        DB::table('academic_identity_references')->insert(['retained_identity_id' => $id, 'reference_context' => 'actor']);
        $this->denied(fn () => DB::table('academic_identity_references')->where('retained_identity_id', $id)->update(['retained_identity_id' => PHP_INT_MAX]), [1142]);
        $this->assertSame($id, (int) DB::table('academic_identity_references')->where('retained_identity_id', $id)->value('retained_identity_id'));
    }

    public function testGuardCommitAndRollback(): void
    {
        $before = $this->guard->getCurrentOrdinal();
        $boundary = $this->guard->allocateNextBoundary();
        $this->assertSame($before + 1, $boundary->ordinal);
        $this->assertSame('UTC', $boundary->timestamp->getTimezone()->getName());
        DB::connection()->commit();
        $this->assertSame($before + 1, $this->guard->getCurrentOrdinal());
        DB::connection()->beginTransaction();
        $this->guard->allocateNextBoundary();
        DB::connection()->rollBack();
        $this->assertSame($before + 1, $this->guard->getCurrentOrdinal());
    }

    public function testOrdinalExhaustionDoesNotMutateGuard(): void
    {
        DB::table('academic_write_guard')->where('id', 1)->update(['last_ordinal' => PHP_INT_MAX]);
        try { $this->guard->allocateNextBoundary(); $this->fail('Exhausted ordinal allocated.'); }
        catch (RuntimeException $error) { $this->assertSame('Operational ordinal range exhausted.', $error->getMessage()); }
        $this->assertSame(PHP_INT_MAX, $this->guard->getCurrentOrdinal());
    }

    public function testGuardAdmitsLastOrdinalAndRejectsOutOfRangeDatabaseValues(): void
    {
        DB::table('academic_write_guard')->where('id', 1)->update(['last_ordinal' => PHP_INT_MAX - 1]);
        $this->assertSame((string) PHP_INT_MAX, $this->guard->allocateNextBoundary()->toServerKey());
        $this->denied(fn () => DB::table('academic_write_guard')->where('id', 1)->update(['last_ordinal' => '9223372036854775808']), [3819]);
        $this->assertSame(PHP_INT_MAX, $this->guard->getCurrentOrdinal());
    }

    public function testGuardCannotRegressWithinTransaction(): void
    {
        $boundary = $this->guard->allocateNextBoundary();
        $this->denied(fn () => DB::table('academic_write_guard')->where('id', 1)->update(['last_ordinal' => $boundary->ordinal - 1]), [1644]);
        $this->assertSame($boundary->ordinal, $this->guard->getCurrentOrdinal());
    }

    public function testMigrationPrivilegesCannotDeleteOrRewritePermanentIdentityEither(): void
    {
        $id = $this->identity();
        // A committed retained ID must survive even a privileged accidental SQL write.
        DB::connection()->commit();
        $this->denied(fn () => DB::connection('migration')->table('retained_identities')->where('id', $id)->delete(), [1644]);
        $this->denied(fn () => DB::connection('migration')->table('retained_identities')->where('id', $id)->update(['id' => $id + 1]), [1644]);
        $this->denied(fn () => DB::connection('migration')->table('retained_identities')->insert(['id' => $id, 'credential_status' => 'active']), [1644]);
        $this->assertSame(1, DB::table('retained_identities')->where('id', $id)->count());
    }

    public function testCredentialStatusConstraintRejectsUnknownStatus(): void
    {
        $this->denied(fn () => DB::table('retained_identities')->insert(['credential_status' => 'invalid']), [3819]);
    }

    public function testUnsupportedTimingDoesNotChangePersistedContext(): void
    {
        $id = $this->identity();
        $before = $this->guard->getCurrentOrdinal();
        foreach ([['scheduled', null], ['retroactive', null], ['corrective', null], ['immediate', '2026-03-01']] as [$mode, $date]) {
            try { new TransferTiming($mode, $date); $this->fail('Unsupported timing accepted.'); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
            $this->assertSame($before, $this->guard->getCurrentOrdinal());
            $this->assertSame('active', DB::table('retained_identities')->where('id', $id)->value('credential_status'));
        }
    }

    public function testCredentialChangesKeepPermanentIdentityAndPreventReuse(): void
    {
        $id = $this->identity();
        foreach (['deactivated', 'removed'] as $status) {
            DB::table('retained_identities')->where('id', $id)->update(['credential_status' => $status]);
            $this->assertSame($id, (int) DB::table('retained_identities')->where('id', $id)->value('id'));
            $this->assertSame($status, DB::table('retained_identities')->where('id', $id)->value('credential_status'));
        }
        $this->denied(fn () => DB::table('retained_identities')->where('id', $id)->delete(), [1142, 1644]);
        $this->denied(fn () => DB::table('retained_identities')->where('id', $id)->update(['id' => $id + 1]), [1143, 1644]);
        $this->denied(fn () => DB::table('retained_identities')->insert(['id' => $id, 'credential_status' => 'active']), [1143, 1644]);
        $this->assertSame(1, DB::table('retained_identities')->where('id', $id)->count());
    }

    public function testRestrictiveIdentityForeignKeyRejectsMissingParent(): void
    {
        $this->denied(fn () => DB::table('academic_identity_references')->insert(['retained_identity_id' => PHP_INT_MAX, 'reference_context' => 'actor']), [1452]);
        $constraints = DB::connection()->select("SELECT DELETE_RULE AS rule FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='academic_identity_references'");
        $this->assertCount(1, $constraints);
        $this->assertSame('RESTRICT', $constraints[0]->rule);
    }

    public function testRuntimeHasNoDdlDeleteOrIdMutationPrivileges(): void
    {
        $grants = implode('\n', array_map(fn ($row) => implode('', (array) $row), DB::connection()->select('SHOW GRANTS')));
        $this->assertStringNotContainsString('ALL PRIVILEGES', $grants);
        $this->assertDoesNotMatchRegularExpression('/GRANT .*\b(DELETE|CREATE|DROP|ALTER|TRIGGER|EXECUTE)\b/', $grants);
        $this->denied(fn () => DB::connection()->statement('CREATE TABLE eval_u1_permission_probe (id INT)'), [1142]);
        $this->denied(fn () => DB::table('academic_write_guard')->where('id', 999)->delete(), [1142]);
    }

    public function testIdentityInsertAndReferenceRollBackTogether(): void
    {
        $id = $this->identity();
        DB::table('academic_identity_references')->insert(['retained_identity_id' => $id, 'reference_context' => 'actor']);
        DB::connection()->rollBack();
        $this->assertFalse(DB::table('retained_identities')->where('id', $id)->exists());
        $this->assertFalse(DB::table('academic_identity_references')->where('retained_identity_id', $id)->exists());
    }
}
