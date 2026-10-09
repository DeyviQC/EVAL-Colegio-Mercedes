<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\ActivityReferenceTestCase;
use App\Application\Academic\Commands\CreateActivityReference;
final class ActivityReferenceContractTest extends ActivityReferenceTestCase
{
    protected const DATABASE='eval_u10_test';
    public function testOwnedActiveAssignmentCreatesOriginalReference():void
    {
        $teacher=$this->actor('teacher');$assignment=$this->assignment(['teacher_id'=>$teacher->identityId]);$this->assignments->activate($this->actor,$assignment);
        $id=(new CreateActivityReference($this->db))->execute($teacher,['assignment_id'=>$assignment]);
        $this->assertSame($assignment,(string)$this->db->table('activity_references')->where('id',$id)->value('teaching_assignment_id'));
    }
    public function testClosedParentDeniesActivityWithoutAllocation():void
    {
        $teacher=$this->actor('teacher');$assignment=$this->assignment(['teacher_id'=>$teacher->identityId]);$this->assignments->activate($this->actor,$assignment);
        $this->periods->close($this->actor,$this->period);
        $this->assignmentDenied('period_not_active',fn()=>(new CreateActivityReference($this->db))->execute($teacher,['assignment_id'=>$assignment]));
    }
    private function fixture():array
    {$teacher=$this->actor('teacher');$id=$this->assignment(['teacher_id'=>$teacher->identityId]);$this->assignments->activate($this->actor,$id);return [$teacher,$id,new CreateActivityReference($this->db)];}
    public function testOneOriginalReferenceDerivesScopeAndAllocatesEvidence():void
    {
        [$teacher,$id,$command]=$this->fixture();$before=$this->ordinal();$activity=$command->execute($teacher,['assignment_id'=>$id]);
        $row=(array)$this->db->table('activity_references')->where('id',$activity)->first();$this->assertSame(['id','teaching_assignment_id'],array_keys($row));
        $this->assertSame($before+1,$this->ordinal());$event=$this->db->table('academic_lifecycle_events')->where('operation_key',$before+1)->first();
        $this->assertSame('activity_reference_created',$event->event_type);$this->assertSame($id,(string)$event->assignment_id);
        $this->assertSame($activity,json_decode($event->metadata,true)['activity_reference_id']);
    }
    public function testOtherOwnerManagementRolesAndForgedRolesCannotCreate():void
    {
        [$teacher,$id,$command]=$this->fixture();foreach([$this->actor,$this->actor('vice_principal'),$this->actor('student'),$this->actor('teacher')] as $actor){
            $this->activityDenied('forbidden',fn()=>$command->execute($actor,['assignment_id'=>$id]));}
        $forged=new \App\Infrastructure\Authentication\AuthenticatedActor($this->actor->identityId,['teacher'],$this->actor->credentialRevision());
        $this->activityDenied('forbidden',fn()=>$command->execute($forged,['assignment_id'=>$id]));
    }
    public function testClosedAndPlannedAssignmentsCannotCreateReferences():void
    {
        [$teacher,$id,$command]=$this->fixture();$this->assignments->close($this->actor,$id,$this->today());
        $this->activityDenied('invalid_transition',fn()=>$command->execute($teacher,['assignment_id'=>$id]));
        $planned=$this->assignment(['teacher_id'=>$teacher->identityId]);$this->activityDenied('invalid_transition',fn()=>$command->execute($teacher,['assignment_id'=>$planned]));
        $this->migration->table('academic_periods')->where('id',$this->period)->update(['state'=>'planned']);
        $this->activityDenied('period_not_active',fn()=>$command->execute($teacher,['assignment_id'=>$planned]));
    }
    public function testInvalidMissingAndClientSelectedScopeOrRecipientAreDenied():void
    {
        [$teacher,$id,$command]=$this->fixture();foreach([[],['assignment_id'=>'0'],['assignment_id'=>[]],['assignment_id'=>$id,'teacher_id'=>$teacher->identityId],
            ['assignment_id'=>$id,'period_id'=>$this->period],['assignment_id'=>$id,'recipient_id'=>$teacher->identityId],['assignment_id'=>$id,'content'=>'Deferred']] as $input){
            $this->activityDenied('invalid_input',fn()=>$command->execute($teacher,$input));}
        $this->activityDenied('missing_lock_target',fn()=>$command->execute($teacher,['assignment_id'=>'9223372036854775807']));
    }
    public function testRevokedRoleCredentialAndActorStatusAreRechecked():void
    {
        [$teacher,$id,$command]=$this->fixture();$this->migration->table('local_role_grants')->where('identity_id',$teacher->identityId)->delete();
        $this->activityDenied('forbidden',fn()=>$command->execute($teacher,['assignment_id'=>$id]));
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$teacher->identityId,'role'=>'teacher']);
        $this->migration->table('local_credentials')->where('id',$teacher->identityId)->update(['password'=>null]);
        $this->activityDenied('forbidden',fn()=>$command->execute($teacher,['assignment_id'=>$id]));
        $this->migration->table('retained_identities')->where('id',$teacher->identityId)->update(['credential_status'=>'deactivated']);
        $this->activityDenied('actor_inactive',fn()=>$command->execute($teacher,['assignment_id'=>$id]));
    }
    public function testOriginalReferenceSurvivesReplacementClosureAndInactiveCatalog():void
    {
        [$teacher,$id,$command]=$this->fixture();$activity=$command->execute($teacher,['assignment_id'=>$id]);
        $next=(new \App\Application\Academic\Commands\ReplaceTeacher($this->db))->execute($this->actor,$id,['teacher_id'=>$this->teacher]);
        $this->periods->close($this->actor,$this->period);$this->migration->table('instructional_entries')->where('id',$this->entry)->update(['is_active'=>false]);
        $reader=new \App\Infrastructure\Persistence\Academic\PersistedActivityReferences($this->db);
        $this->assertSame($id,$reader->activity($activity)['teaching_assignment_id']);$this->assertNotSame($next['successor_id'],$reader->activity($activity)['teaching_assignment_id']);
        $policy=new \App\Application\Academic\Authorization\AcademicAuthorization($this->db,$reader);
        $this->assertTrue($policy->allows($teacher,'activity.read',['activity_id'=>$activity]));
        $this->assertFalse($policy->allows($teacher,'activity.create',['assignment_id'=>$id]));$this->assertNull($reader->submission('1'));
    }
    public function testRuntimeAndMaintenanceCannotRewriteOrDeleteOriginalReference():void
    {
        [$teacher,$id,$command]=$this->fixture();$activity=$command->execute($teacher,['assignment_id'=>$id]);
        foreach([
            [fn()=>$this->db->table('activity_references')->where('id',$activity)->update(['teaching_assignment_id'=>$id]),1142],
            [fn()=>$this->db->table('activity_references')->where('id',$activity)->delete(),1142],
            [fn()=>$this->migration->table('activity_references')->where('id',$activity)->update(['teaching_assignment_id'=>$id]),1644],
            [fn()=>$this->migration->table('activity_references')->where('id',$activity)->delete(),1644],
            [fn()=>$this->migration->table('teaching_assignments')->where('id',$id)->delete(),1451],
        ] as [$operation,$code]){try{$operation();$this->fail('Destructive reference mutation succeeded.');}catch(\Illuminate\Database\QueryException $error){$this->assertSame($code,(int)$error->errorInfo[1]);}}
    }
    public function testLedgerFailureRollsBackReferenceAndOrdinal():void
    {
        [$teacher,$id,$command]=$this->fixture();$trigger='u10_event_fault_'.(int)$teacher->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U10 ledger failure'");
        try{$this->activityDenied('integrity_conflict',fn()=>$command->execute($teacher,['assignment_id'=>$id]));}finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
    }
    public function testMultipleActivitiesRetainIndependentIdsOnSameAssignment():void
    {[$teacher,$id,$command]=$this->fixture();$first=$command->execute($teacher,['assignment_id'=>$id]);$second=$command->execute($teacher,['assignment_id'=>$id]);
        $this->assertNotSame($first,$second);$this->assertSame(2,$this->db->table('activity_references')->where('teaching_assignment_id',$id)->count());}
    public function testRealPeriodClosureFirstDeniesActivityWithoutReference():void
    {
        [$teacher,$id]=$this->fixture();$before=$this->ordinal();$input=['assignment_id'=>$id,'teacher_id'=>$teacher->identityId,'academic_period_id'=>$this->period];
        $this->activityRace('period_close','create','period_not_active',$input,$input);
        $this->assertSame($before+1,$this->ordinal());$this->assertSame(0,$this->db->table('activity_references')->where('teaching_assignment_id',$id)->count());
    }
    public function testRealActivityFirstSurvivesParentClosure():void
    {
        [$teacher,$id]=$this->fixture();$before=$this->ordinal();$input=['assignment_id'=>$id,'teacher_id'=>$teacher->identityId,'academic_period_id'=>$this->period];
        $this->activityRace('create','period_close','COMMITTED',$input,$input);
        $this->assertSame($before+2,$this->ordinal());$this->assertSame(1,$this->db->table('activity_references')->where('teaching_assignment_id',$id)->count());
    }
    public function testRealAssignmentClosureFirstDeniesNewActivity():void
    {
        [$teacher,$id]=$this->fixture();$before=$this->ordinal();$input=['assignment_id'=>$id,'teacher_id'=>$teacher->identityId,'academic_period_id'=>$this->period];
        $this->activityRace('assignment_close','create','invalid_transition',$input,$input);
        $this->assertSame($before+1,$this->ordinal());$this->assertSame(0,$this->db->table('activity_references')->where('teaching_assignment_id',$id)->count());
    }
    public function testRealActivityFirstSurvivesAssignmentClosure():void
    {
        [$teacher,$id]=$this->fixture();$before=$this->ordinal();$input=['assignment_id'=>$id,'teacher_id'=>$teacher->identityId,'academic_period_id'=>$this->period];
        $this->activityRace('create','assignment_close','COMMITTED',$input,$input);
        $this->assertSame($before+2,$this->ordinal());$this->assertSame(1,$this->db->table('activity_references')->where('teaching_assignment_id',$id)->count());
    }
    public function testReferenceInsertFailureRollsBackAllocatedKey():void
    {
        [$teacher,$id,$command]=$this->fixture();$trigger='u10_source_fault_'.(int)$teacher->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON activity_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U10 insert failure'");
        try{$this->activityDenied('integrity_conflict',fn()=>$command->execute($teacher,['assignment_id'=>$id]));}finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
    }
    public function testMissingAssignmentFkAndNullLinkCannotBeInserted():void
    {
        foreach([['teaching_assignment_id'=>'9223372036854775807'],['teaching_assignment_id'=>null]] as $input){
            try{$this->migration->table('activity_references')->insert($input);$this->fail('Invalid original reference inserted.');}
            catch(\Illuminate\Database\QueryException $error){$this->assertContains((int)$error->errorInfo[1],[1452,1048]);}
        }
    }
    public function testLostCommitAcknowledgementDoesNotReplayActivityCreation():void
    {
        [$teacher,$id]=$this->fixture();$before=$this->ordinal();
        $connection=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public int $commits=0;public function commit(){$this->commits++;parent::commit();throw new \RuntimeException('Lost U10 acknowledgement');}
        };
        try{(new CreateActivityReference($connection))->execute($teacher,['assignment_id'=>$id]);$this->fail('Uncertain creation acknowledged.');}
        catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){
            $this->assertSame('commit_outcome_unknown',$error->category);
            $this->assertSame($error->correlationId,$this->db->table('academic_lifecycle_events')->where('operation_key',$before+1)->value('correlation_id'));
        }
        $this->assertSame(1,$connection->commits);$this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->db->table('activity_references')->where('teaching_assignment_id',$id)->count());
    }
}
