<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\ReplacementTestCase;
use App\Application\Academic\Commands\ReplaceTeacher;

final class TeacherReplacementTest extends ReplacementTestCase
{
    protected const DATABASE='eval_u8_test';
    private ReplaceTeacher $replacement;
    private string $prior;
    private string $nextTeacher;
    protected function setUp():void
    {
        parent::setUp();$this->replacement=new ReplaceTeacher($this->db);
        $this->nextTeacher=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $this->prior=$this->assignment();$this->assignments->activate($this->actor,$this->prior);
    }
    private function replace(array $input=[]):array
    {return $this->replacement->execute($this->actor,$this->prior,array_replace(['teacher_id'=>$this->nextTeacher],$input));}
    public function testReplacementClosesPriorAndCreatesDistinctSuccessorAtOneK():void
    {
        $before=$this->ordinal();$result=$this->replace();$this->assertArrayHasKey('successor_id',$result);
        $old=$this->db->table('teaching_assignments')->where('id',$this->prior)->first();
        $new=$this->db->table('teaching_assignments')->where('id',$result['successor_id'])->first();
        $this->assertNotSame($this->prior,$result['successor_id']);$this->assertSame('closed',$old->state);$this->assertSame('active',$new->state);
        $this->assertSame($this->teacher,(string)$old->teacher_id);$this->assertSame($this->nextTeacher,(string)$new->teacher_id);
        $this->assertSame($this->prior,(string)$new->replaces_assignment_id);$this->assertSame($this->today(),$old->effective_until);
        $this->assertSame($this->today(),$new->effective_from);$this->assertSame($before+1,(int)$old->operational_end_key);
        $this->assertSame((string)$old->operational_end_key,(string)$new->operational_start_key);
    }
    public function testClosedParentDenialDoesNotClosePrior():void
    {$this->periods->close($this->actor,$this->period);$this->assignmentDenied('period_not_active',fn()=>$this->replace());}
    public function testPastAndFutureDatesAreDeniedWithoutMutation():void
    {
        foreach(['2026-03-01','2026-12-20'] as $date){$this->assignmentDenied('invalid_interval',fn()=>$this->replace(['effective_on'=>$date]));}
    }
    public function testPlannedDestinationConflictDoesNotClosePrior():void
    {$this->assignment(['teacher_id'=>$this->nextTeacher]);$this->assignmentDenied('overlapping_assignment',fn()=>$this->replace());}
    public function testExplicitTodayAndAllScopeFieldsArePreserved():void
    {
        $before=(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first();$result=$this->replace(['effective_on'=>$this->today()]);
        $old=(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first();
        $new=(array)$this->db->table('teaching_assignments')->where('id',$result['successor_id'])->first();
        foreach(['id','teacher_id','academic_period_id','instructional_entry_id','grade_id','section_id','effective_from','operational_start_key','replaces_assignment_id'] as $field){$this->assertSame($before[$field],$old[$field]);}
        foreach(['academic_period_id','instructional_entry_id','grade_id','section_id'] as $field){$this->assertSame($before[$field],$new[$field]);}
        $this->assertNull($new['effective_until']);$this->assertSame($this->today(),$result['effective_on']);
        $events=$this->db->table('academic_lifecycle_events')->where('operation_key',$result['operation_key'])->get();
        $this->assertCount(2,$events);$this->assertSame($events[0]->correlation_id,$events[1]->correlation_id);
        $this->assertSame('replaced',$events[0]->event_type);$this->assertSame($this->today(),$events[0]->effective_on);
    }
    public function testReversedTeacherIdsLockOneCanonicalUnionAndRetainClosedDestinationHistory():void
    {
        $this->assignments->close($this->actor,$this->prior,$this->today());
        $this->prior=$this->assignment(['teacher_id'=>$this->nextTeacher]);$this->assignments->activate($this->actor,$this->prior);
        $this->nextTeacher=$this->teacher;$bindings=[];
        $this->db->listen(function($event)use(&$bindings){if(str_contains($event->sql,'`retained_identities`') && str_contains($event->sql,'for update')){$bindings[]=$event->bindings;}});
        $result=$this->replace();$this->assertCount(1,$bindings);
        $expected=[$this->actor->identityId,$this->teacher,(string)$this->db->table('teaching_assignments')->where('id',$this->prior)->value('teacher_id')];
        sort($expected,SORT_NUMERIC);$this->assertSame($expected,array_map('strval',$bindings[0]));
        $this->assertSame($this->teacher,(string)$this->db->table('teaching_assignments')->where('id',$result['successor_id'])->value('teacher_id'));
    }
    public function testSameDayReplacementChainRetainsDistinctLineageAndNonemptyIntervals():void
    {
        $first=$this->replace();$second=$this->replacement->execute($this->actor,$first['successor_id'],['teacher_id'=>$this->teacher]);
        $this->assertCount(3,array_unique([$this->prior,$first['successor_id'],$second['successor_id']]));
        $middle=$this->db->table('teaching_assignments')->where('id',$first['successor_id'])->first();
        $this->assertSame($this->today(),$middle->effective_from);$this->assertSame($this->today(),$middle->effective_until);
        $this->assertGreaterThan((int)$middle->operational_start_key,(int)$middle->operational_end_key);
        $this->assertSame($first['successor_id'],(string)$this->db->table('teaching_assignments')->where('id',$second['successor_id'])->value('replaces_assignment_id'));
    }
    public function testActiveDestinationBlocksEvenWhenDeclaredPlanningDatesArePast():void
    {
        $id=$this->assignment(['teacher_id'=>$this->nextTeacher,'effective_until'=>'2026-04-01']);$this->assignments->activate($this->actor,$id);
        $this->assignmentDenied('overlapping_assignment',fn()=>$this->replace());
        $this->assignments->close($this->actor,$id,'2026-04-01');$result=$this->replace();$this->assertArrayHasKey('successor_id',$result);
    }
    public function testFuturePlannedReservationBlocksOpenSuccessorWithoutChangingPrior():void
    {
        $this->assignment(['teacher_id'=>$this->nextTeacher,'effective_from'=>'2026-11-01']);
        $before=(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first();
        $this->assignmentDenied('overlapping_assignment',fn()=>$this->replace());
        $this->assertSame($before,(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first());
    }
    public function testDifferentEntryOrGradeScopeDoesNotBlockDestinationTeacher():void
    {
        $entry=$this->catalog->create($this->actor,'entry',['name'=>'Other '.$this->suffix,'kind'=>'subject']);
        $id=$this->assignment(['teacher_id'=>$this->nextTeacher,'instructional_entry_id'=>$entry]);$this->assignments->activate($this->actor,$id);
        $this->assertArrayHasKey('successor_id',$this->replace());
    }
    public function testMalformedScopeDateAndIdentityInputsAreRejected():void
    {
        foreach([['teacher_id'=>'0'],['teacher_id'=>null],['grade_id'=>$this->grade],['state'=>'active'],['replaces_assignment_id'=>$this->prior],['operational_start_key'=>1]] as $input){
            $this->assignmentDenied('invalid_input',fn()=>$this->replace($input));
        }
        foreach([null,'2026-02-30',20261007] as $date){$this->assignmentDenied('invalid_interval',fn()=>$this->replace(['effective_on'=>$date]));}
        $this->assignmentDenied('missing_lock_target',fn()=>$this->replacement->execute($this->actor,'9223372036854775807',['teacher_id'=>$this->nextTeacher]));
        $this->assignmentDenied('missing_lock_target',fn()=>$this->replace(['teacher_id'=>'9223372036854775807']));
    }
    public function testUnchangedTeacherAndNonActivePriorCannotBeReplaced():void
    {
        $this->assignmentDenied('teacher_unchanged',fn()=>$this->replace(['teacher_id'=>$this->teacher]));
        $planned=$this->assignment(['teacher_id'=>$this->nextTeacher]);
        $this->assignmentDenied('invalid_transition',fn()=>$this->replacement->execute($this->actor,$planned,['teacher_id'=>$this->teacher]));
        $this->assignments->close($this->actor,$this->prior,$this->today());
        $this->assignmentDenied('invalid_transition',fn()=>$this->replace());
    }
    public function testPlannedParentCannotGainSuccessorAuthority():void
    {
        $this->migration->table('academic_periods')->where('id',$this->period)->update(['state'=>'planned']);
        $this->assignmentDenied('period_not_active',fn()=>$this->replace());
    }
    public function testCalendarPastActivePeriodCannotBeReplacedButCanBeCleanedUp():void
    {
        $this->periods->close($this->actor,$this->period);
        $period=$this->periods->create($this->actor,['name'=>'Past '.$this->suffix,'start_on'=>'2020-01-01','end_on'=>'2020-12-31']);
        $this->periods->activate($this->actor,$period);$this->prior=$this->assignment(['academic_period_id'=>$period,'effective_from'=>'2020-01-01']);
        $this->assignments->activate($this->actor,$this->prior);$this->assignmentDenied('invalid_interval',fn()=>$this->replace());
        $this->assignments->close($this->actor,$this->prior,'2020-12-31');
        $this->assertSame('closed',$this->db->table('teaching_assignments')->where('id',$this->prior)->value('state'));
    }
    public function testCalendarFutureActiveFixtureDoesNotPermitBackdatedReplacement():void
    {
        // Maintenance-only edge fixture: never expose a writer that fabricates active future authority.
        $this->migration->table('academic_periods')->where('id',$this->period)->update(['start_on'=>'2027-01-01','end_on'=>'2027-12-31']);
        $this->migration->table('teaching_assignments')->where('id',$this->prior)->update(['effective_from'=>'2027-01-01']);
        $this->assignmentDenied('invalid_interval',fn()=>$this->replace());
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$this->prior)->value('state'));
    }
    public function testDeclaredEndDoesNotAutomaticallyExpirePriorAuthority():void
    {
        $this->assignments->close($this->actor,$this->prior,$this->today());
        $this->prior=$this->assignment(['effective_until'=>'2026-04-01']);$this->assignments->activate($this->actor,$this->prior);
        $result=$this->replace();$this->assertArrayHasKey('successor_id',$result);
        $event=$this->db->table('academic_lifecycle_events')->where('assignment_id',$this->prior)->where('event_type','replaced')->first();
        $metadata=json_decode($event->metadata,true,flags:JSON_THROW_ON_ERROR);
        $this->assertSame('2026-04-01',$metadata['previous_declared_end']);
        $this->assertSame($this->today(),$this->db->table('teaching_assignments')->where('id',$this->prior)->value('effective_until'));
    }
    public function testInactiveCatalogBlocksReplacementWithoutClosingPrior():void
    {
        foreach(['instructional_entries'=>$this->entry,'grades'=>$this->grade,'sections'=>$this->section] as $table=>$id){
            $this->migration->table($table)->where('id',$id)->update(['is_active'=>false]);
            $this->assignmentDenied('inactive_catalog_reference',fn()=>$this->replace());
            $this->migration->table($table)->where('id',$id)->update(['is_active'=>true]);
        }
    }
    public function testVicePrincipalMayReplaceButForgedTeacherAndStudentRolesCannot():void
    {
        foreach(['teacher','student'] as $role){$actor=$this->actor($role);
            $forged=new \App\Infrastructure\Authentication\AuthenticatedActor($actor->identityId,['director_admin'],$actor->credentialRevision());
            $this->assignmentDenied('forbidden',fn()=>$this->replacement->execute($forged,$this->prior,['teacher_id'=>$this->nextTeacher]));
        }
        $result=$this->replacement->execute($this->actor('vice_principal'),$this->prior,['teacher_id'=>$this->nextTeacher]);
        $this->assertArrayHasKey('successor_id',$result);
    }
    public function testActorRoleAndCredentialRevocationAreRefreshed():void
    {
        $this->migration->table('local_role_grants')->where('identity_id',$this->actor->identityId)->delete();
        $this->assignmentDenied('forbidden',fn()=>$this->replace());
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor->identityId,'role'=>'director_admin']);
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>null]);
        $this->assignmentDenied('forbidden',fn()=>$this->replace());
    }
    public function testPermanentTeachersDoNotRequireLiveLoginCredentials():void
    {
        $this->migration->table('retained_identities')->whereIn('id',[$this->teacher,$this->nextTeacher])->update(['credential_status'=>'deactivated']);
        $this->assertArrayHasKey('successor_id',$this->replace());
    }
    public function testSuccessorInsertFailureRollsBackPriorAndOrdinal():void
    {
        $before=(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first();$trigger='u8_insert_fault_'.(int)$this->actor->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON teaching_assignments FOR EACH ROW BEGIN IF NEW.replaces_assignment_id IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U8 successor failure'; END IF; END");
        try{$this->assignmentDenied('integrity_conflict',fn()=>$this->replace());}finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame($before,(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first());
    }
    public function testSecondEventFailureRollsBackFirstEventBothSourcesAndOrdinal():void
    {
        $before=(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first();$trigger='u8_event_fault_'.(int)$this->actor->identityId;
        $prior=(int)$this->prior;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON academic_lifecycle_events FOR EACH ROW BEGIN IF NEW.assignment_id <> $prior AND NEW.event_type='created' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U8 second event failure'; END IF; END");
        try{$this->assignmentDenied('integrity_conflict',fn()=>$this->replace());}finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame($before,(array)$this->db->table('teaching_assignments')->where('id',$this->prior)->first());
    }
    public function testRuntimeCannotRewriteTeacherLineageIdentityOrDeleteHistory():void
    {
        $next=$this->replace()['successor_id'];
        foreach([
            [fn()=>$this->db->table('teaching_assignments')->where('id',$next)->update(['replaces_assignment_id'=>null]),1143],
            [fn()=>$this->db->table('teaching_assignments')->where('id',$this->prior)->update(['teacher_id'=>$this->nextTeacher]),1143],
            [fn()=>$this->db->table('teaching_assignments')->where('id',$next)->delete(),1142],
            [fn()=>$this->migration->table('teaching_assignments')->where('id',$this->prior)->delete(),1451],
        ] as [$operation,$code]){try{$operation();$this->fail('Forbidden mutation succeeded.');}catch(\Illuminate\Database\QueryException $error){$this->assertSame($code,(int)$error->errorInfo[1]);}}
        $source=$this->db->table('teaching_assignments')->where('id',$next)->first();
        try{$this->migration->table('teaching_assignments')->insert(['teacher_id'=>$source->teacher_id,'academic_period_id'=>$source->academic_period_id,
            'instructional_entry_id'=>$source->instructional_entry_id,'grade_id'=>$source->grade_id,'section_id'=>$source->section_id,
            'state'=>'planned','effective_from'=>$this->today(),'replaces_assignment_id'=>$this->prior]);$this->fail('Duplicate lineage succeeded.');}
        catch(\Illuminate\Database\QueryException $error){$this->assertSame(1062,(int)$error->errorInfo[1]);}
    }
    public function testRealSimultaneousReplacementOfOnePriorCreatesOneSuccessor():void
    {
        $other=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);$before=$this->ordinal();
        $this->replacementRace('replace','replace','invalid_transition',
            ['id'=>$this->prior,'teacher_id'=>$this->nextTeacher],['id'=>$this->prior,'teacher_id'=>$other]);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->db->table('teaching_assignments')->where('replaces_assignment_id',$this->prior)->count());
    }
    public function testRealTwoPriorsCompetingForDestinationCannotDuplicateAuthority():void
    {
        $other=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $prior=$this->assignment(['teacher_id'=>$other]);$this->assignments->activate($this->actor,$prior);$before=$this->ordinal();
        $this->replacementRace('replace','replace','overlapping_assignment',
            ['id'=>$this->prior,'teacher_id'=>$this->nextTeacher],['id'=>$prior,'teacher_id'=>$this->nextTeacher]);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$prior)->value('state'));
        $this->assertNull($this->db->table('teaching_assignments')->where('id',$prior)->value('operational_end_key'));
    }
    public function testRealParentClosureFirstRejectsReplacementWithoutPriorClosure():void
    {
        $before=$this->ordinal();$this->replacementRace('period_close','replace','period_not_active',
            ['academic_period_id'=>$this->period],['id'=>$this->prior,'teacher_id'=>$this->nextTeacher]);
        $this->assertSame($before+1,$this->ordinal());
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$this->prior)->value('state'));
        $this->assertSame(0,$this->db->table('teaching_assignments')->where('replaces_assignment_id',$this->prior)->count());
    }
    public function testRealReplacementBeforeParentClosureRetainsSuccessor():void
    {
        $before=$this->ordinal();$this->replacementRace('replace','period_close','COMMITTED',
            ['id'=>$this->prior,'teacher_id'=>$this->nextTeacher],['academic_period_id'=>$this->period]);
        $this->assertSame($before+2,$this->ordinal());
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('replaces_assignment_id',$this->prior)->value('state'));
        $this->assertSame('closed',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
    }
}
