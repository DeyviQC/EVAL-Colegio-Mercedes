<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\AssignmentTestCase;

final class TeachingAssignmentCommandsTest extends AssignmentTestCase
{
    public function testPlannedCreationHasNoOperationalAuthorityAndManualActivationAllocatesK():void
    {
        $id=$this->assignment();$row=$this->db->table('teaching_assignments')->where('id',$id)->first();
        $this->assertNotNull($row);$this->assertSame('planned',$row->state);$this->assertNull($row->operational_start_key);
        $before=$this->ordinal();$this->assignments->activate($this->actor,$id);
        $this->assertSame($before+1,(int)$this->db->table('teaching_assignments')->where('id',$id)->value('operational_start_key'));
    }
    public function testOverlappingPlannedReservationIsRejected():void
    {$this->assignment();$this->assignmentDenied('overlapping_assignment',fn()=>$this->assignment());}
    public function testDeclaredDatesOutsidePeriodAreRejected():void
    {$this->assignmentDenied('invalid_interval',fn()=>$this->assignment(['effective_from'=>'2026-01-01']));}
    public function testAdministrativeClosurePreservesDeclaredEndAndUsesActualK():void
    {
        $id=$this->assignment();$this->assertNotSame('0',$id);$this->assignments->activate($this->actor,$id);$before=$this->ordinal();
        $this->assignments->close($this->actor,$id,'2026-04-01');$row=$this->db->table('teaching_assignments')->where('id',$id)->first();
        $this->assertSame('closed',$row->state);$this->assertSame('2026-04-01',$row->effective_until);$this->assertSame($before+1,(int)$row->operational_end_key);
    }
    public function testPlannedParentAllowsPlanningButNotActivation():void
    {
        $period=$this->periods->create($this->actor,['name'=>'Planned '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
        $id=$this->assignment(['academic_period_id'=>$period]);
        $this->assignmentDenied('period_not_active',fn()=>$this->assignments->activate($this->actor,$id));
        $this->assertNull($this->db->table('teaching_assignments')->where('id',$id)->value('operational_start_key'));
    }
    public function testClosedParentRejectsCreationAndActivationButAllowsResidualClosure():void
    {
        $id=$this->assignment(['effective_until'=>'2026-04-30']);$this->assignments->activate($this->actor,$id);
        $planned=$this->assignment(['effective_from'=>'2026-11-01']);
        $this->periods->close($this->actor,$this->period);
        $this->assignmentDenied('period_closed',fn()=>$this->assignment());
        $this->assignmentDenied('period_not_active',fn()=>$this->assignments->activate($this->actor,$planned));
        $this->assignments->close($this->actor,$id,$this->today());
        $this->assertSame('closed',$this->db->table('teaching_assignments')->where('id',$id)->value('state'));
    }
    public function testDisjointPlansDoNotPermitTwoOpenOperationalAssignments():void
    {
        $first=$this->assignment(['effective_until'=>'2026-04-30']);
        $second=$this->assignment(['effective_from'=>'2026-05-01','effective_until'=>'2026-06-30']);
        $this->assignments->activate($this->actor,$first);
        $this->assignmentDenied('overlapping_assignment',fn()=>$this->assignments->activate($this->actor,$second));
        $this->assignments->close($this->actor,$first,'2026-04-30');$this->assignments->activate($this->actor,$second);
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$second)->value('state'));
    }
    public function testInclusiveReservationEndpointsAndNullEndHorizon():void
    {
        $this->assignment(['effective_until'=>'2026-04-30']);
        $this->assignmentDenied('overlapping_assignment',fn()=>$this->assignment(['effective_from'=>'2026-04-30']));
        $this->assignment(['effective_from'=>'2026-05-01']);
        $this->assignmentDenied('overlapping_assignment',fn()=>$this->assignment(['effective_from'=>'2026-12-20']));
    }
    public function testFutureStartDoesNotActivateAutomaticallyOrManuallyEarly():void
    {
        $tomorrow=(new \DateTimeImmutable($this->today()))->modify('+1 day')->format('Y-m-d');
        $id=$this->assignment(['effective_from'=>$tomorrow]);
        $this->assignmentDenied('invalid_interval',fn()=>$this->assignments->activate($this->actor,$id));
        $this->assertSame('planned',$this->db->table('teaching_assignments')->where('id',$id)->value('state'));
    }
    public function testSameDayHistoryIsRetainedWithDistinctIdsAndActualKeys():void
    {
        $first=$this->assignment(['effective_from'=>$this->today()]);$this->assignments->activate($this->actor,$first);
        $start=$this->ordinal();$this->assignments->close($this->actor,$first,$this->today());$end=$this->ordinal();
        $second=$this->assignment(['effective_from'=>$this->today()]);$this->assignments->activate($this->actor,$second);
        $this->assertNotSame($first,$second);$this->assertGreaterThan($start,$end);
        $this->assertGreaterThan($end,(int)$this->db->table('teaching_assignments')->where('id',$second)->value('operational_start_key'));
        $this->assertSame($this->teacher,(string)$this->db->table('teaching_assignments')->where('id',$first)->value('teacher_id'));
    }
    public function testVicePrincipalCanManageAssignmentsButForgedRolesCannot():void
    {
        $vice=$this->actor('vice_principal');$id=$this->assignments->create($vice,$this->assignmentInput());
        $this->assignments->activate($vice,$id);$this->assignments->close($vice,$id,$this->today());
        foreach(['teacher','student'] as $role){
            $actor=$this->actor($role);$forged=new \App\Infrastructure\Authentication\AuthenticatedActor($actor->identityId,['director_admin'],$actor->credentialRevision());
            $this->assignmentDenied('forbidden',fn()=>$this->assignments->create($forged,$this->assignmentInput()));
            $this->assignmentDenied('forbidden',fn()=>$this->assignments->activate($forged,$id));
            $this->assignmentDenied('forbidden',fn()=>$this->assignments->close($forged,$id,$this->today()));
        }
    }
    public function testRoleAndCredentialRevocationAreRecheckedAtWriteTime():void
    {
        $id=$this->assignment();$this->migration->table('local_role_grants')->where('identity_id',$this->actor->identityId)->delete();
        $this->assignmentDenied('forbidden',fn()=>$this->assignments->activate($this->actor,$id));
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor->identityId,'role'=>'director_admin']);
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>null]);
        $this->assignmentDenied('forbidden',fn()=>$this->assignments->activate($this->actor,$id));
    }
    public function testImmutableFieldsAndInvalidDatesAreRejectedWithoutWrites():void
    {
        foreach(['id'=>'1','state'=>'active','operational_start_key'=>1,'predecessor_id'=>'1'] as $field=>$value){
            $this->assignmentDenied('invalid_input',fn()=>$this->assignment([$field=>$value]));
        }
        foreach([['effective_from'=>'2026-02-30'],['effective_until'=>'2026-12-21'],['effective_until'=>'2026-02-28'],['teacher_id'=>'0']] as $input){
            $this->assignmentDenied(isset($input['teacher_id'])?'invalid_input':'invalid_interval',fn()=>$this->assignment($input));
        }
    }
    public function testNoReopenNoSkippedTransitionsAndInvalidClosureDates():void
    {
        $id=$this->assignment();$this->assignmentDenied('invalid_transition',fn()=>$this->assignments->close($this->actor,$id,$this->today()));
        $this->assignments->activate($this->actor,$id);
        $this->assignmentDenied('invalid_transition',fn()=>$this->assignments->activate($this->actor,$id));
        foreach(['2026-02-28','2026-12-21','2026-02-30'] as $end){$this->assignmentDenied('invalid_interval',fn()=>$this->assignments->close($this->actor,$id,$end));}
        $this->assignments->close($this->actor,$id,$this->today());
        $this->assignmentDenied('invalid_transition',fn()=>$this->assignments->activate($this->actor,$id));
        $this->assignmentDenied('invalid_transition',fn()=>$this->assignments->close($this->actor,$id,$this->today()));
    }
    public function testLifecycleAppendFailureRollsBackCreationActivationAndClosure():void
    {
        $id=$this->assignment();$trigger='u7_event_fault_'.(int)$this->actor->identityId;
        $fault=function()use($trigger){$this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U7 failure'");};
        $drop=function()use($trigger){$this->migration->unprepared("DROP TRIGGER $trigger");};
        $other=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $before=(array)$this->db->table('teaching_assignments')->where('id',$id)->first();$fault();
        try{
            $this->assignmentDenied('integrity_conflict',fn()=>$this->assignment(['teacher_id'=>$other]));
            $this->assignmentDenied('integrity_conflict',fn()=>$this->assignments->activate($this->actor,$id));
        }finally{$drop();}
        $this->assertSame($before,(array)$this->db->table('teaching_assignments')->where('id',$id)->first());
        $this->assignments->activate($this->actor,$id);$before=(array)$this->db->table('teaching_assignments')->where('id',$id)->first();$fault();
        try{$this->assignmentDenied('integrity_conflict',fn()=>$this->assignments->close($this->actor,$id,$this->today()));}finally{$drop();}
        $this->assertSame($before,(array)$this->db->table('teaching_assignments')->where('id',$id)->first());
    }
    public function testLedgerRetentionAndRuntimePrivilegesProtectIdentityAndScope():void
    {
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);$this->assignments->close($this->actor,$id,$this->today());
        foreach([
            [fn()=>$this->migration->table('teaching_assignments')->where('id',$id)->delete(),1451],
            [fn()=>$this->db->table('teaching_assignments')->where('id',$id)->delete(),1142],
            [fn()=>$this->db->table('teaching_assignments')->where('id',$id)->update(['teacher_id'=>$this->teacher]),1143],
            [fn()=>$this->db->table('teaching_assignments')->where('id',$id)->update(['id'=>$id]),1143],
        ] as [$operation,$code]){try{$operation();$this->fail('Forbidden mutation succeeded.');}
            catch(\Illuminate\Database\QueryException $error){$this->assertSame($code,(int)$error->errorInfo[1]);}}
    }
    public function testDistinctTeachersAndDistinctScopesMayCoexist():void
    {
        $first=$this->assignment();$this->assignments->activate($this->actor,$first);
        $other=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $second=$this->assignment(['teacher_id'=>$other]);$this->assignments->activate($this->actor,$second);
        $entry=$this->catalog->create($this->actor,'entry',['name'=>'Other '.$this->suffix,'kind'=>'area']);
        $third=$this->assignment(['instructional_entry_id'=>$entry]);$this->assignments->activate($this->actor,$third);
        $this->assertCount(3,array_unique([$first,$second,$third]));
    }
    public function testInactiveCatalogDeniesNewWorkButNotExactTargetCleanup():void
    {
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);
        $this->migration->table('instructional_entries')->where('id',$this->entry)->update(['is_active'=>false]);
        $this->assignmentDenied('inactive_catalog_reference',fn()=>$this->assignment());
        $this->assignments->close($this->actor,$id,$this->today());
        $this->assertSame('closed',$this->db->table('teaching_assignments')->where('id',$id)->value('state'));
    }
    public function testInactiveReferenceAndGradeSectionMismatchBlockActivationAndPlanning():void
    {
        $id=$this->assignment();$this->migration->table('grades')->where('id',$this->grade)->update(['is_active'=>false]);
        $this->assignmentDenied('inactive_catalog_reference',fn()=>$this->assignments->activate($this->actor,$id));
        $this->migration->table('grades')->where('id',$this->grade)->update(['is_active'=>true]);
        $grade=$this->catalog->create($this->actor,'grade',['name'=>'Other grade '.$this->suffix]);
        $this->assignmentDenied('scope_mismatch',fn()=>$this->assignment(['grade_id'=>$grade]));
    }
    public function testPastPeriodClosureKeepsContainedDeclaredDateAndActualAuthority():void
    {
        $this->periods->close($this->actor,$this->period);
        $period=$this->periods->create($this->actor,['name'=>'Past '.$this->suffix,'start_on'=>'2020-01-01','end_on'=>'2020-12-31']);
        $this->periods->activate($this->actor,$period);$id=$this->assignment(['academic_period_id'=>$period,'effective_from'=>'2020-01-01']);
        $this->assignments->activate($this->actor,$id);$start=$this->ordinal();$this->periods->close($this->actor,$period);
        $this->assignments->close($this->actor,$id,'2020-12-31');$row=$this->db->table('teaching_assignments')->where('id',$id)->first();
        $this->assertSame('2020-12-31',$row->effective_until);$this->assertGreaterThan($start,(int)$row->operational_end_key);
    }
    public function testRealConcurrentDuplicatePlanningCommitsOneReservation():void
    {
        $before=$this->ordinal();$this->assignmentRace('create','create','overlapping_assignment',$this->assignmentInput(),$this->assignmentInput());
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->db->table('teaching_assignments')->where('teacher_id',$this->teacher)->count());
    }
    public function testRealConcurrentDisjointPlanActivationCommitsOneAuthority():void
    {
        $first=$this->assignment(['effective_until'=>'2026-04-30']);$second=$this->assignment(['effective_from'=>'2026-05-01']);$before=$this->ordinal();
        $this->assignmentRace('activate','activate','overlapping_assignment',['id'=>$first],['id'=>$second]);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$first)->value('state'));
        $this->assertSame('planned',$this->db->table('teaching_assignments')->where('id',$second)->value('state'));
    }
    public function testRealParentClosureWinsBeforeActivationWithoutChildMutation():void
    {
        $id=$this->assignment();$before=$this->ordinal();
        $this->assignmentRace('period_close','activate','period_not_active',['academic_period_id'=>$this->period],['id'=>$id]);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame('planned',$this->db->table('teaching_assignments')->where('id',$id)->value('state'));
    }
    public function testRealActivationBeforeParentClosureRetainsActiveChild():void
    {
        $id=$this->assignment();$before=$this->ordinal();
        $this->assignmentRace('activate','period_close','COMMITTED',['id'=>$id],['academic_period_id'=>$this->period]);
        $this->assertSame($before+2,$this->ordinal());
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$id)->value('state'));
        $this->assertSame('closed',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
    }
    public function testRetainedOperationalConflictCannotBeHiddenByClosedState():void
    {
        $query=new \App\Infrastructure\Persistence\Academic\AssignmentOverlapQuery($this->db);
        $range=new \App\Domain\Academic\DeclaredDateRange('2026-03-01');
        $period=new \App\Domain\Academic\DeclaredDateRange('2026-03-01','2026-12-20');
        $row=['id'=>'1','state'=>'closed','effective_from'=>'2026-03-01','effective_until'=>'2026-04-01','operational_start_key'=>'5','operational_end_key'=>'10'];
        $this->assertTrue($query->conflicts([$row],$range,$period,9,true));
        $this->assertFalse($query->conflicts([$row],$range,$period,10,true));
        $this->assertFalse($query->conflicts([$row],$range,$period,11,false));
    }
    public function testCanonicalLocksPrecedeAllocationAndEventsHaveTypedAssignmentTargets():void
    {
        $queries=[];$this->db->listen(function($event)use(&$queries){$queries[]=strtolower($event->sql);});
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);
        $locks=array_values(array_filter($queries,fn($sql)=>str_contains($sql,'for update')));
        $this->assertStringContainsString('academic_write_guard',$locks[0]);
        $first=function($needle)use($locks){foreach($locks as $index=>$sql){if(str_contains($sql,$needle)){return $index;}}$this->fail('Missing lock '.$needle);};
        $this->assertLessThan($first('instructional_entries'),$first('academic_periods'));
        $this->assertLessThan($first('retained_identities'),$first('sections'));
        $this->assertLessThan($first('teaching_assignments'),$first('retained_identities'));
        $events=$this->db->table('academic_lifecycle_events')->where('assignment_id',$id)->orderBy('id')->get();
        $this->assertCount(2,$events);$this->assertSame('created',$events[0]->event_type);$this->assertSame('activated',$events[1]->event_type);
        $this->assertSame('assignment',$events[0]->entity_type);
    }
}
