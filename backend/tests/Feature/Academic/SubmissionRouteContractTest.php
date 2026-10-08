<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
final class SubmissionRouteContractTest extends SubmissionReferenceTestCase
{
    public function testAcceptanceCopiesOriginalRouteAndCurrentEnrollment():void
    {$id=$this->accept();$row=$this->db->table('submission_references')->where('id',$id)->first();
        $this->assertSame($this->assignmentId,(string)$row->teaching_assignment_id);$this->assertSame($this->enrollmentId,(string)$row->accepted_under_enrollment_id);}
    public function testClosedParentDeniesAcceptanceWithoutAllocation():void
    {$this->periods->close($this->actor,$this->period);$this->assignmentDenied('period_not_active',fn()=>$this->accept());}
    public function testClosedAssignmentAloneAllowsOriginalRouteAcceptance():void
    {$this->assignments->close($this->actor,$this->assignmentId,$this->today());$id=$this->accept();
        $this->assertSame($this->assignmentId,(string)$this->db->table('submission_references')->where('id',$id)->value('teaching_assignment_id'));}
    public function testAcceptanceTimeAndKeyComeFromActualGuardBoundary():void
    {
        $before=$this->ordinal();$id=$this->accept();$row=$this->db->table('submission_references')->where('id',$id)->first();
        $this->assertSame($before+1,(int)$row->acceptance_operation_key);$this->assertSame($this->studentActor->identityId,(string)$row->student_id);
        $event=$this->db->table('academic_lifecycle_events')->where('operation_key',$before+1)->first();
        $this->assertSame($event->recorded_at,$row->accepted_at);$this->assertSame('submission_reference_accepted',$event->event_type);
    }
    public function testMissingHistoricalAndMismatchedEnrollmentDenyAcceptance():void
    {
        $this->commands->close($this->actor,$this->enrollmentId,$this->today());$this->submissionDenied('no_active_enrollment',fn()=>$this->accept());
        $section=$this->catalog->create($this->actor,'section',['name'=>'Other','grade_id'=>$this->grade]);
        $this->enrollment(['student_id'=>$this->studentActor->identityId,'section_id'=>$section]);
        $this->submissionDenied('scope_mismatch',fn()=>$this->accept());
    }
    public function testClientCannotSelectScopeRecipientStudentOrAcceptedContext():void
    {
        foreach(['recipient_id'=>$this->teacherActor->identityId,'teacher_id'=>$this->teacherActor->identityId,'assignment_id'=>$this->assignmentId,
            'enrollment_id'=>$this->enrollmentId,'student_id'=>$this->studentActor->identityId,'grade_id'=>$this->grade,'accepted_at'=>'2026-01-01','content'=>'Deferred','late'=>true] as $field=>$value){
            $this->submissionDenied('invalid_input',fn()=>$this->accept([$field=>$value]));}
        $this->submissionDenied('invalid_input',fn()=>$this->accept(['activity_id'=>'0']));
        $this->submissionDenied('missing_lock_target',fn()=>$this->accept(['activity_id'=>'9223372036854775807']));
    }
    public function testRolesCredentialAndRetainedActorAreRechecked():void
    {
        $command=new \App\Application\Academic\Commands\AcceptSubmissionReference($this->db);
        foreach([$this->actor,$this->teacherActor,$this->actor('vice_principal')] as $actor){$this->submissionDenied('forbidden',fn()=>$command->execute($actor,['activity_id'=>$this->activityId]));}
        $this->migration->table('local_role_grants')->where('identity_id',$this->studentActor->identityId)->delete();
        $this->submissionDenied('forbidden',fn()=>$this->accept());
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->studentActor->identityId,'role'=>'student']);
        $this->migration->table('local_credentials')->where('id',$this->studentActor->identityId)->update(['password'=>null]);
        $this->submissionDenied('forbidden',fn()=>$this->accept());
        $this->migration->table('retained_identities')->where('id',$this->studentActor->identityId)->update(['credential_status'=>'removed']);
        $this->submissionDenied('actor_inactive',fn()=>$this->accept());
    }
    public function testSourceAndLedgerFailuresRollBackReferenceAllocation():void
    {
        foreach(['submission_references','academic_lifecycle_events'] as $table){$trigger='u11_fault_'.(int)$this->studentActor->identityId;
            $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U11 failure'");
            try{$this->submissionDenied('integrity_conflict',fn()=>$this->accept());}finally{$this->migration->unprepared("DROP TRIGGER $trigger");}}
    }
    public function testAcceptedContextAndLockingPrivilegeCannotRewriteHistory():void
    {
        $id=$this->accept();foreach([
            [fn()=>$this->db->table('submission_references')->where('id',$id)->update(['teaching_assignment_id'=>$this->assignmentId]),1142],
            [fn()=>$this->db->table('submission_references')->where('id',$id)->delete(),1142],
            [fn()=>$this->migration->table('submission_references')->where('id',$id)->update(['accepted_under_enrollment_id'=>$this->enrollmentId]),1644],
            [fn()=>$this->migration->table('submission_references')->where('id',$id)->delete(),1644],
            [fn()=>$this->db->table('activity_references')->where('id',$this->activityId)->update(['teaching_assignment_id'=>$this->assignmentId]),1644],
            [fn()=>$this->migration->table('student_enrollments')->where('id',$this->enrollmentId)->delete(),1451],
        ] as [$operation,$code]){try{$operation();$this->fail('Immutable context mutation succeeded.');}catch(\Illuminate\Database\QueryException $error){$this->assertSame($code,(int)$error->errorInfo[1]);}}
    }
    public function testPersistedHistoryRetainsOriginalContextAfterActualTransferAndReplacement():void
    {
        $id=$this->accept();$section=$this->catalog->create($this->actor,'section',['name'=>'Destination','grade_id'=>$this->grade]);
        (new \App\Application\Academic\Commands\TransferStudent($this->db))->execute($this->actor,$this->enrollmentId,['grade_id'=>$this->grade,'section_id'=>$section]);
        $replacement=(new \App\Application\Academic\Commands\ReplaceTeacher($this->db))->execute($this->actor,$this->assignmentId,['teacher_id'=>$this->teacher]);
        $this->periods->close($this->actor,$this->period);$reader=new \App\Infrastructure\Persistence\Academic\PersistedAcademicReferences($this->db);
        $labels=new class implements \App\Application\Academic\Queries\AcademicIdentityLabels {public function displayName(string $id):?string{return 'Synthetic corrected teacher';}};
        $policy=new \App\Application\Academic\Authorization\AcademicAuthorization($this->db,$reader);
        $query=new \App\Application\Academic\Queries\OwnHistoricalSubmissionQuery($this->db,$policy,$reader,$labels);$view=$query->get($this->studentActor,$id);
        $this->assertSame($this->enrollmentId,$view['acceptedUnderEnrollment']['id']);$this->assertSame($this->section,$view['acceptedUnderEnrollment']['section']['id']);
        $this->assertSame($this->assignmentId,$view['originalTeachingAssignment']['id']);$this->assertNotSame($replacement['successor_id'],$view['originalTeachingAssignment']['id']);
        $this->assertTrue($policy->allows($this->teacherActor,'submission.read',['submission_id'=>$id]));
        $this->assertFalse($policy->allows($this->actor('student'),'submission.read',['submission_id'=>$id]));
        $this->assertFalse($policy->allows($this->actor('vice_principal'),'submission.read',['submission_id'=>$id]));
    }
    public function testLostCommitAcknowledgementNeverReplaysAcceptance():void
    {
        $before=$this->ordinal();$connection=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public int $commits=0;public function commit(){$this->commits++;parent::commit();throw new \RuntimeException('Lost U11 acknowledgement');}
        };
        try{(new \App\Application\Academic\Commands\AcceptSubmissionReference($connection))->execute($this->studentActor,['activity_id'=>$this->activityId]);$this->fail('Uncertain acceptance confirmed.');}
        catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){$this->assertSame('commit_outcome_unknown',$error->category);
            $this->assertSame($error->correlationId,$this->db->table('academic_lifecycle_events')->where('operation_key',$before+1)->value('correlation_id'));}
        $this->assertSame(1,$connection->commits);$this->assertSame($before+1,$this->ordinal());$this->assertSame(1,$this->db->table('submission_references')->where('activity_id',$this->activityId)->count());
    }
    public function testConfirmedRollbackRetryRevalidatesAcceptanceAndCommitsOnce():void
    {
        $before=$this->ordinal();$connection=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public int $begins=0;public bool $failed=false;
            public function beginTransaction(){$this->begins++;parent::beginTransaction();}
            public function insert($query,$bindings=[],$sequence=null){
                if(!$this->failed && str_contains($query,'submission_references')){$this->failed=true;
                    $previous=new \PDOException('Injected deadlock');$previous->errorInfo=['40001',1213,'Injected deadlock'];
                    throw new \Illuminate\Database\QueryException($this->getName()??'default',$query,$bindings,$previous);}
                return parent::insert($query,$bindings,$sequence);
            }
        };
        $id=(new \App\Application\Academic\Commands\AcceptSubmissionReference($connection))->execute($this->studentActor,['activity_id'=>$this->activityId]);
        $this->assertSame(2,$connection->begins);$this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->db->table('submission_references')->where('activity_id',$this->activityId)->count());$this->assertNotSame('0',$id);
    }
}
