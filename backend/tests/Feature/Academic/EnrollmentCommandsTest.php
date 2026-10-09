<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\EnrollmentTestCase;

final class EnrollmentCommandsTest extends EnrollmentTestCase
{
    public function testCreateRequiresCompletePeriodBoundScopeAndAllocatesActualKey():void
    {
        $before=$this->ordinal();$id=$this->enrollment();
        $row=$this->db->table('student_enrollments')->where('id',$id)->first();
        $this->assertNotNull($row);$this->assertSame('active',$row->state);
        $this->assertSame($this->student,(string)$row->student_id);$this->assertSame($this->period,(string)$row->academic_period_id);
        $this->assertSame($before+1,(int)$row->operational_start_key);$this->assertNull($row->operational_end_key);
    }
    public function testDuplicateActiveEnrollmentDoesNotAllocateOrInsert():void
    {
        $this->enrollment();$this->denied('overlapping_enrollment',fn()=>$this->enrollment());
    }
    public function testPastDeclaredClosureUsesActualSuccessfulKeyWithoutChangingScope():void
    {
        $id=$this->enrollment();$before=$this->ordinal();
        $this->commands->close($this->actor,$id,'2026-02-01');
        $row=$this->db->table('student_enrollments')->where('id',$id)->first();
        $this->assertNotNull($row);
        $this->assertSame('closed',$row->state);$this->assertSame('2026-02-01',$row->effective_until);
        $this->assertSame($before+1,(int)$row->operational_end_key);$this->assertSame($this->grade,(string)$row->grade_id);
    }
    public function testApprovedFutureClosureDateDenialPreservesSource():void
    {
        $id=$this->enrollment();$end=(new \DateTimeImmutable($this->today()))->modify('+1 day')->format('Y-m-d');
        $this->denied('invalid_interval',fn()=>$this->commands->close($this->actor,$id,$end));
    }
    public function testDeclaredDatesOutsidePeriodDoNotCreateContainmentOrScheduling():void
    {
        $id=$this->enrollment(['effective_from'=>'2025-01-01','effective_until'=>'2027-01-01']);
        $row=$this->db->table('student_enrollments')->where('id',$id)->first();
        $this->assertSame('2025-01-01',$row->effective_from);$this->assertSame('2027-01-01',$row->effective_until);
        $this->assertSame('active',$row->state);$this->assertNull($row->operational_end_key);
        $this->assertSame($this->ordinal(),(int)$row->operational_start_key);
        $this->assertSame('2025-01-01',$this->db->table('academic_lifecycle_events')->where('enrollment_id',$id)->value('effective_on'));
    }
    public function testIncompleteOrTamperedScopeAndKeysFailWithoutMutation():void
    {
        foreach(['student_id','academic_period_id','grade_id','section_id','effective_from'] as $column){
            $input=$this->input();unset($input[$column]);$this->denied('invalid_input',fn()=>$this->commands->create($this->actor,$input));
        }
        foreach(['state'=>'closed','operational_start_key'=>1,'effective_at'=>'2026-01-01','student_id'=>0] as $column=>$value){
            $this->denied('invalid_input',fn()=>$this->commands->create($this->actor,$this->input([$column=>$value])));
        }
    }
    public function testMissingRetainedIdentityOrPeriodCannotCreateEnrollment():void
    {
        foreach(['student_id','academic_period_id','grade_id','section_id'] as $column){
            $this->denied('missing_lock_target',fn()=>$this->enrollment([$column=>PHP_INT_MAX]));
        }
    }
    public function testMismatchedSectionAndInactiveReferencesAreDenied():void
    {
        $otherGrade=$this->catalog->create($this->actor,'grade',['name'=>'Other '.$this->suffix]);
        $otherSection=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$otherGrade]);
        $this->denied('scope_mismatch',fn()=>$this->enrollment(['section_id'=>$otherSection]));
        foreach(['grade'=>$this->grade,'section'=>$this->section] as $type=>$id){
            $this->catalog->update($this->actor,$type,$id,['is_active'=>false]);
            $this->denied('inactive_catalog_reference',fn()=>$this->enrollment());
            $this->catalog->update($this->actor,$type,$id,['is_active'=>true]);
        }
    }
    public function testApprovedActiveParentRuleRejectsPlannedAndClosed():void
    {
        $planned=$this->periods->create($this->actor,['name'=>'Planned '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
        $this->denied('period_not_active',fn()=>$this->enrollment(['academic_period_id'=>$planned]));
        $this->periods->close($this->actor,$this->period);
        $this->denied('period_not_active',fn()=>$this->enrollment());
    }
    public function testDifferentPeriodsRemainIndependentDespiteResidualActiveEnrollment():void
    {
        $old=$this->enrollment();$this->periods->close($this->actor,$this->period);
        $other=$this->periods->create($this->actor,['name'=>'Next '.$this->suffix,'start_on'=>'2027-03-01','end_on'=>'2027-12-20']);
        $this->periods->activate($this->actor,$other);$new=$this->enrollment(['academic_period_id'=>$other]);
        $this->assertNotSame($old,$new);
        $this->assertSame('active',$this->db->table('student_enrollments')->where('id',$old)->value('state'));
        $this->assertSame($other,(string)$this->db->table('student_enrollments')->where('id',$new)->value('academic_period_id'));
    }
    public function testValidResidualClosureAllowsClosedParentInactiveCatalogAndRemovedSubjectCredentials():void
    {
        $id=$this->enrollment();$before=(array)$this->db->table('student_enrollments')->where('id',$id)->first();
        $this->periods->close($this->actor,$this->period);
        $this->assertSame('active',$this->db->table('student_enrollments')->where('id',$id)->value('state'));
        $this->catalog->update($this->actor,'grade',$this->grade,['is_active'=>false]);
        $this->catalog->update($this->actor,'section',$this->section,['is_active'=>false]);
        $this->migration->table('retained_identities')->where('id',$this->student)->update(['credential_status'=>'removed']);
        $this->commands->close($this->actor,$id,$this->today());
        $after=(array)$this->db->table('student_enrollments')->where('id',$id)->first();
        foreach(['id','student_id','academic_period_id','grade_id','section_id','effective_from','operational_start_key'] as $column){$this->assertSame($before[$column],$after[$column]);}
        $this->assertSame('closed',$after['state']);$this->assertSame($this->ordinal(),(int)$after['operational_end_key']);
    }
    public function testResidualClosureDateAfterPeriodCalendarEndRemainsValid():void
    {
        $this->periods->close($this->actor,$this->period);
        $past=$this->periods->create($this->actor,['name'=>'Past '.$this->suffix,'start_on'=>'2020-03-01','end_on'=>'2020-12-20']);
        $this->periods->activate($this->actor,$past);$id=$this->enrollment(['academic_period_id'=>$past,'effective_from'=>'2020-01-01']);
        $this->assertSame('active',$this->db->table('student_enrollments')->where('id',$id)->value('state'));
        $this->assertNull($this->db->table('student_enrollments')->where('id',$id)->value('effective_until'));
        $this->periods->close($this->actor,$past);$this->commands->close($this->actor,$id,$this->today());
        $this->assertSame($this->today(),$this->db->table('student_enrollments')->where('id',$id)->value('effective_until'));
    }
    public function testInvalidDateOrderRepeatedClosureAndReopenInputsFail():void
    {
        $this->denied('invalid_interval',fn()=>$this->enrollment(['effective_until'=>'2025-12-31']));
        $this->denied('invalid_interval',fn()=>$this->enrollment(['effective_from'=>'2026-02-30']));
        $id=$this->enrollment();$this->denied('invalid_interval',fn()=>$this->commands->close($this->actor,$id,'2025-12-31'));
        $this->commands->close($this->actor,$id,$this->today());
        $this->denied('invalid_transition',fn()=>$this->commands->close($this->actor,$id,$this->today()));
        $this->denied('invalid_input',fn()=>$this->enrollment(['id'=>$id,'state'=>'active']));
    }
    public function testHistoricalOccupiedIntervalsAreCheckedWithHalfOpenEdges():void
    {
        $id=$this->enrollment();$start=$this->ordinal();$this->commands->close($this->actor,$id,$this->today());$end=$this->ordinal();
        $query=new \App\Infrastructure\Persistence\Academic\EnrollmentOverlapQuery($this->db);
        $this->assertSame([$id],$query->conflictingIds($this->period,$this->student,$start,$end));
        $this->assertSame([],$query->conflictingIds($this->period,$this->student,$end,$end+1));
        $this->assertSame([$id],$query->conflictingIds($this->period,$this->student,$start));
        $new=$this->enrollment();$this->assertNotSame($id,$new);
        $this->assertSame([$id,$new],array_map('strval',$query->history($this->period,$this->student)->orderBy('id')->pluck('id')->all()));
    }
    public function testVicePrincipalTeacherStudentAndForgedSnapshotCannotEnrollOrClose():void
    {
        $id=$this->enrollment();
        foreach(['vice_principal','teacher','student'] as $role){
            $actor=$this->actor($role);
            $forged=new \App\Infrastructure\Authentication\AuthenticatedActor($actor->identityId,['director_admin'],$actor->credentialRevision());
            $this->denied('forbidden',fn()=>$this->commands->create($forged,$this->input()));
            $this->denied('forbidden',fn()=>$this->commands->close($forged,$id,$this->today()));
        }
    }
    public function testCredentialRevocationBetweenAuthenticationAndCommandDeniesWrite():void
    {
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>null]);
        $this->denied('forbidden',fn()=>$this->enrollment());
    }
    public function testCreationAndClosureLedgerFailureRollBackSourceAndOrdinal():void
    {
        $id=$this->enrollment();$student=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $before=(array)$this->db->table('student_enrollments')->where('id',$id)->first();$trigger='u5_event_fault_'.(int)$this->actor->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U5 event failure'");
        try{
            $this->denied('integrity_conflict',fn()=>$this->enrollment(['student_id'=>$student]));
            $this->denied('integrity_conflict',fn()=>$this->commands->close($this->actor,$id,$this->today()));
        }finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame($before,(array)$this->db->table('student_enrollments')->where('id',$id)->first());
    }
    public function testLedgerOnlyHistoryAndRuntimePermissionsPreventDeletionAndScopeRewrite():void
    {
        $id=$this->enrollment();$this->commands->close($this->actor,$id,$this->today());
        foreach([
            [fn()=>$this->migration->table('student_enrollments')->where('id',$id)->delete(),1451],
            [fn()=>$this->db->table('student_enrollments')->where('id',$id)->delete(),1142],
            [fn()=>$this->db->table('student_enrollments')->where('id',$id)->update(['grade_id'=>$this->grade]),1143],
            [fn()=>$this->db->table('student_enrollments')->where('id',$id)->update(['operational_start_key'=>1]),1143],
        ] as [$operation,$code]){
            try{$operation();$this->fail('Forbidden history mutation succeeded.');}
            catch(\Illuminate\Database\QueryException $error){$this->assertSame($code,(int)$error->errorInfo[1]);}
        }
    }
    public function testRealConcurrentCreationCommitsOnlyOneEnrollmentAndOrdinal():void
    {
        $before=$this->ordinal();$this->race('create','create','overlapping_enrollment');
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->db->table('student_enrollments')->where('student_id',$this->student)->where('academic_period_id',$this->period)->count());
    }
    public function testRealPeriodClosureFirstDeniesWaitingEnrollmentCreation():void
    {
        $before=$this->ordinal();$this->race('period_close','create','period_not_active');
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(0,$this->db->table('student_enrollments')->where('student_id',$this->student)->count());
        $this->assertSame('closed',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
    }
    public function testRealEnrollmentFirstRetainsActiveChildAfterWaitingPeriodClosure():void
    {
        $before=$this->ordinal();$this->race('create','period_close','COMMITTED');
        $this->assertSame($before+2,$this->ordinal());
        $row=$this->db->table('student_enrollments')->where('student_id',$this->student)->first();
        $this->assertNotNull($row);$this->assertSame('active',$row->state);$this->assertNull($row->operational_end_key);
        $this->assertSame('closed',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
    }
}
