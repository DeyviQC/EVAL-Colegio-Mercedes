<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Domain\Academic\AcademicNameKey;
use App\Domain\Academic\AcademicWriteGuard;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

abstract class U3TestCase extends TestCase
{
    protected Connection $db;
    protected Connection $migration;
    protected int $actorId;
    protected int $lowIdentity;

    protected function setUp():void
    {
        $this->db=DB::connection();
        $this->migration=DB::connection('migration');
        $this->assertSame('eval_u3_test',$this->db->getDatabaseName());
        $this->assertSame('mysql',$this->db->getDriverName());
        $this->lowIdentity=$this->identity();
        $this->actorId=$this->identity();
    }
    protected function tearDown():void
    {
        foreach ([$this->db,$this->migration] as $connection) {
            if ($connection->transactionLevel()>0) { $connection->rollBack(0); }
        }
        // Retain committed synthetic history; only transaction fixtures are rolled back.
        $this->migration->table('academic_periods')->where('state','active')->update(['state'=>'closed']);
    }
    protected function identity():int
    {
        return $this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
    }
    protected function grade():int
    {
        $name='Synthetic grade '.bin2hex(random_bytes(6));
        return $this->migration->table('grades')->insertGetId(['name'=>$name,'name_key'=>AcademicNameKey::generate($name),'is_active'=>true]);
    }
    protected function period():int
    {
        $name='Synthetic period '.bin2hex(random_bytes(6));
        return $this->migration->table('academic_periods')->insertGetId(['name'=>$name,'name_key'=>AcademicNameKey::generate($name),
            'start_on'=>'2026-03-01','end_on'=>'2026-12-20','state'=>'planned']);
    }
    protected function ordinal():int { return (new AcademicWriteGuard($this->db))->getCurrentOrdinal(); }
    protected function events():int { return $this->db->table('academic_lifecycle_events')->where('actor_id',$this->actorId)->count(); }
    protected function denied(callable $operation,array $codes):void
    {
        try { $operation(); $this->fail('Invalid SQL unexpectedly accepted.'); }
        catch(QueryException $error) { $this->assertContains((int)$error->errorInfo[1],$codes); }
    }
}
