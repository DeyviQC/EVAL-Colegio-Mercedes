<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use App\Domain\Academic\OperationalBoundary;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
use App\Infrastructure\Persistence\Academic\LifecycleEventWriter;
use Tests\Support\U3TestCase;

final class LifecycleLedgerTest extends U3TestCase
{
    private function raw(int $gradeId):array
    {
        return ['entity_type'=>'grade','grade_id'=>$gradeId,'actor_id'=>$this->actorId,'event_type'=>'created',
            'effective_on'=>'2026-03-01','operation_key'=>1,'correlation_id'=>str_repeat('a',32),
            'recorded_at'=>'2026-03-01 12:00:00.123456','metadata'=>'{}'];
    }
    public function testExactlyOneTargetMustMatchItsDiscriminator():void
    {
        $grade=$this->grade(); $period=$this->period(); $values=$this->raw($grade);
        $this->db->beginTransaction();
        foreach ([['grade_id'=>null],['period_id'=>$period],['entity_type'=>'period'],['entity_type'=>'material']] as $invalid) {
            $this->denied(fn ()=>$this->db->table('academic_lifecycle_events')->insert(array_replace($values,$invalid)),[3819]);
        }
    }
    public function testTypedTargetAndActorForeignKeysRejectMissingRows():void
    {
        $values=$this->raw($this->grade());
        $this->db->beginTransaction();
        $this->denied(fn ()=>$this->db->table('academic_lifecycle_events')->insert(array_replace($values,['grade_id'=>PHP_INT_MAX])),[1452]);
        $this->denied(fn ()=>$this->db->table('academic_lifecycle_events')->insert(array_replace($values,['actor_id'=>PHP_INT_MAX])),[1452]);
        foreach(array_keys(LifecycleEvent::TARGETS) as $kind){
            $this->denied(fn()=>$this->db->table('academic_lifecycle_events')->insert(array_replace($values,
                ['entity_type'=>$kind,'grade_id'=>null,$kind.'_id'=>PHP_INT_MAX])),[1452]);
        }
    }
    public function testWriterRetainsDatesKeysActorCorrelationAndDisplayCorrection():void
    {
        $grade=$this->grade();
        $boundary=new OperationalBoundary(42,new \DateTimeImmutable('2026-03-02T02:00:00.123456Z'));
        $this->db->beginTransaction();
        $id=(new LifecycleEventWriter($this->db))->append(new LifecycleEvent('grade',$grade,'renamed','active','active',
            ['previous_name'=>'Old label','new_name'=>'New label']),(string)$this->actorId,$boundary,str_repeat('b',32));
        $row=$this->db->table('academic_lifecycle_events')->where('id',$id)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->actorId,(int)$row->actor_id);
        $this->assertSame('2026-03-01',$row->effective_on);
        $this->assertSame('2026-03-02 02:00:00.123456',$row->recorded_at);
        $this->assertSame(42,(int)$row->operation_key);
        $this->assertSame(str_repeat('b',32),$row->correlation_id);
        $metadata=json_decode($row->metadata,true);
        $this->assertSame('Old label',$metadata['previous_name']);
        $this->assertSame('New label',$metadata['new_name']);
    }
    public function testLedgerOnlyReferenceProtectsTargetAndEvidenceIsAppendOnly():void
    {
        $grade=$this->grade();
        $id=$this->db->table('academic_lifecycle_events')->insertGetId($this->raw($grade));
        $this->denied(fn ()=>$this->migration->table('grades')->where('id',$grade)->delete(),[1451]);
        $this->denied(fn ()=>$this->migration->table('academic_lifecycle_events')->where('id',$id)->update(['metadata'=>'{}']),[1644]);
        $this->denied(fn ()=>$this->migration->table('academic_lifecycle_events')->where('id',$id)->delete(),[1644]);
    }
    public function testRuntimeCanReadAndInsertButCannotUpdateOrDeleteLedger():void
    {
        $this->db->beginTransaction();
        $id=$this->db->table('academic_lifecycle_events')->insertGetId($this->raw($this->grade()));
        $this->assertTrue($this->db->table('academic_lifecycle_events')->where('id',$id)->exists());
        $this->denied(fn ()=>$this->db->table('academic_lifecycle_events')->where('id',$id)->update(['metadata'=>'{}']),[1142]);
        $this->denied(fn ()=>$this->db->table('academic_lifecycle_events')->where('id',$id)->delete(),[1142]);
    }
    public function testWriterRequiresTransactionAndKeysStayInApprovedRange():void
    {
        $grade=$this->grade();
        try {
            (new LifecycleEventWriter($this->db))->append(new LifecycleEvent('grade',$grade,'created',null,'active'),
                (string)$this->actorId,new OperationalBoundary(1,new \DateTimeImmutable('now')),str_repeat('a',32));
            $this->fail('Unscoped ledger write accepted.');
        } catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){
            $this->assertSame('ledger_requires_transaction',$error->category);
        }
        $this->db->beginTransaction();
        foreach([0,'9223372036854775808'] as $key){
            $this->denied(fn()=>$this->db->table('academic_lifecycle_events')->insert(array_replace($this->raw($grade),['operation_key'=>$key])),[3819]);
        }
        $this->denied(fn()=>$this->db->table('academic_lifecycle_events')->insert(array_replace($this->raw($grade),['metadata'=>'invalid json'])),[3140]);
    }
    public function testActorDeactivationRetainsEvidenceAndRuntimeCannotAlterSchema():void
    {
        $grade=$this->grade();$id=$this->db->table('academic_lifecycle_events')->insertGetId($this->raw($grade));
        $this->migration->table('retained_identities')->where('id',$this->actorId)->update(['credential_status'=>'removed']);
        $this->assertSame($this->actorId,(int)$this->db->table('academic_lifecycle_events')->where('id',$id)->value('actor_id'));
        $this->denied(fn()=>$this->migration->table('retained_identities')->where('id',$this->actorId)->delete(),[1644,1451]);
        $this->denied(fn()=>$this->db->statement('ALTER TABLE academic_lifecycle_events ADD COLUMN forbidden INT NULL'),[1142]);
        $this->assertFalse(\Illuminate\Database\Capsule\Manager::schema()->hasColumn('academic_lifecycle_events','forbidden'));
    }
}
