<?php
declare(strict_types=1);
namespace Tests\Unit\Academic;
use Tests\Support\AssignmentTestCase;
use App\Application\Academic\Authorization\AcademicAuthorization;
final class AcademicAuthorizationTest extends AssignmentTestCase
{
    protected const DATABASE='eval_u9_test';
    public function testDirectorCanManageCatalogAndVicePrincipalCannot():void
    {
        $policy=new AcademicAuthorization($this->db);
        $this->assertTrue($policy->allows($this->actor,'catalog.manage'));
        $this->assertFalse($policy->allows($this->actor('vice_principal'),'catalog.manage'));
    }
    public function testPlannedParentPlanningIsPermittedWithoutActivationAuthority():void
    {
        $period=$this->periods->create($this->actor,['name'=>'Plan '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
        $id=$this->assignment(['academic_period_id'=>$period]);$policy=new AcademicAuthorization($this->db);
        $this->assertTrue($policy->allows($this->actor,'assignment.create',['period_id'=>$period]));
        $this->assertFalse($policy->allows($this->actor,'assignment.activate',['assignment_id'=>$id]));
    }
    public function testClosedParentDoesNotRemovePermittedCleanup():void
    {
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);$enrollment=$this->enrollment();
        $this->periods->close($this->actor,$this->period);$policy=new AcademicAuthorization($this->db);$vice=$this->actor('vice_principal');
        $this->assertTrue($policy->allows($vice,'assignment.close',['assignment_id'=>$id]));
        $this->assertTrue($policy->allows($this->actor,'enrollment.close',['enrollment_id'=>$enrollment]));
        $this->assertFalse($policy->allows($vice,'enrollment.close',['enrollment_id'=>$enrollment]));
    }
    public function testEveryClosedParentNewWorkCategoryIsDeniedDespiteActiveChildren():void
    {
        $teacher=$this->actor('teacher');$student=$this->actor('student');
        $id=$this->assignment(['teacher_id'=>$teacher->identityId]);$this->assignments->activate($this->actor,$id);
        $enrollment=$this->enrollment(['student_id'=>$student->identityId]);
        $refs=new \Tests\Support\FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>$id]]);
        $policy=new AcademicAuthorization($this->db,$refs);$this->periods->close($this->actor,$this->period);
        foreach([
            [$this->actor,'enrollment.create',['period_id'=>$this->period]],[$this->actor,'enrollment.transfer',['enrollment_id'=>$enrollment]],
            [$this->actor,'assignment.create',['period_id'=>$this->period]],[$this->actor,'assignment.activate',['assignment_id'=>$id]],
            [$this->actor,'assignment.replace',['assignment_id'=>$id]],[$teacher,'activity.create',['assignment_id'=>$id]],
            [$student,'submission.accept',['activity_id'=>'1']],[$student,'submission.accept_late',['activity_id'=>'1']],
            [$student,'activity.read',['activity_id'=>'1']],
        ] as [$actor,$operation,$target]){$this->assertFalse($policy->allows($actor,$operation,$target),$operation);}
        $this->assertTrue($policy->allows($this->actor,'catalog.manage'));
    }
    public function testOwnAssignmentHistoryDoesNotGrantNewWorkAfterClosure():void
    {
        $teacher=$this->actor('teacher');$other=$this->actor('teacher');$id=$this->assignment(['teacher_id'=>$teacher->identityId]);
        $this->assignments->activate($this->actor,$id);$policy=new AcademicAuthorization($this->db,new \Tests\Support\FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>$id]]));
        $this->assertTrue($policy->allows($teacher,'activity.create',['assignment_id'=>$id]));
        $this->assertFalse($policy->allows($other,'assignment.read',['assignment_id'=>$id]));
        $this->assignments->close($this->actor,$id,$this->today());$this->periods->close($this->actor,$this->period);
        $this->assertTrue($policy->allows($teacher,'assignment.read',['assignment_id'=>$id]));
        $this->assertTrue($policy->allows($this->actor('vice_principal'),'assignment.read',['assignment_id'=>$id]));
        $this->assertFalse($policy->allows($teacher,'activity.create',['assignment_id'=>$id]));
        $this->assertTrue($policy->allows($teacher,'activity.read',['activity_id'=>'1']));
        $this->assertTrue($policy->allows($this->actor,'activity.read',['activity_id'=>'1']));
        $this->assertFalse($policy->allows($other,'activity.read',['activity_id'=>'1']));
        $this->assertFalse($policy->allows($this->actor('vice_principal'),'activity.read',['activity_id'=>'1']));
    }
    public function testMatchingActiveEnrollmentPermitsOriginalClosedAssignmentRouteOnly():void
    {
        $student=$this->actor('student');$id=$this->assignment();$this->assignments->activate($this->actor,$id);
        $old=$this->enrollment(['student_id'=>$student->identityId]);$refs=new \Tests\Support\FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>$id]]);
        $policy=new AcademicAuthorization($this->db,$refs);$this->assignments->close($this->actor,$id,$this->today());
        $this->assertTrue($policy->allows($student,'activity.read',['activity_id'=>'1']));
        $this->assertTrue($policy->allows($student,'submission.accept',['activity_id'=>'1']));
        $this->assertFalse($policy->allows($student,'submission.accept',['activity_id'=>'1','recipient_id'=>$this->teacher]));
        $this->commands->close($this->actor,$old,$this->today());
        $this->assertFalse($policy->allows($student,'submission.accept',['activity_id'=>'1']));
        $section=$this->catalog->create($this->actor,'section',['name'=>'Other','grade_id'=>$this->grade]);
        $this->enrollment(['student_id'=>$student->identityId,'section_id'=>$section]);
        $this->assertFalse($policy->allows($student,'activity.read',['activity_id'=>'1']));
    }
    public function testSubmissionAndEnrollmentHistoryAreOwnerBound():void
    {
        $student=$this->actor('student');$other=$this->actor('student');$teacher=$this->actor('teacher');$id=$this->assignment(['teacher_id'=>$teacher->identityId]);
        $enrollment=$this->enrollment(['student_id'=>$student->identityId]);$this->commands->close($this->actor,$enrollment,$this->today());
        $this->periods->close($this->actor,$this->period);
        $refs=new \Tests\Support\FixtureAcademicReferences([],['1'=>['id'=>'1','student_id'=>$student->identityId,'teaching_assignment_id'=>$id]]);
        $policy=new AcademicAuthorization($this->db,$refs);
        foreach([$this->actor,$student,$teacher] as $actor){$this->assertTrue($policy->allows($actor,'submission.read',['submission_id'=>'1']));}
        foreach([$other,$this->actor('vice_principal'),$this->actor('teacher')] as $actor){$this->assertFalse($policy->allows($actor,'submission.read',['submission_id'=>'1']));}
        $this->assertTrue($policy->allows($student,'enrollment.read',['enrollment_id'=>$enrollment]));
        $this->assertFalse($policy->allows($other,'enrollment.read',['enrollment_id'=>$enrollment]));
    }
    public function testUnauthenticatedForgedRevokedAndUnknownOperationsFailClosed():void
    {
        $policy=new AcademicAuthorization($this->db);$this->assertFalse($policy->allows(null,'catalog.manage'));
        $student=$this->actor('student');$forged=new \App\Infrastructure\Authentication\AuthenticatedActor($student->identityId,['director_admin'],$student->credentialRevision());
        $this->assertFalse($policy->allows($forged,'catalog.manage'));
        foreach(['material.manage','observation.create','submission.process','library.manage','grade.write'] as $operation){$this->assertFalse($policy->allows($this->actor,$operation));}
        $this->assertFalse($policy->allows($this->actor,'period.read',['period_id'=>'0']));
        $this->assertFalse($policy->allows($this->actor,'catalog.manage',['roles'=>['director_admin']]));
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>null]);
        $this->assertFalse($policy->allows($this->actor,'catalog.manage'));
    }
    public function testRequestedClosedParentIsNeverSubstitutedByDifferentCurrentPeriod():void
    {
        $old=$this->period;$this->periods->close($this->actor,$old);
        $current=$this->periods->create($this->actor,['name'=>'Current '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);$this->periods->activate($this->actor,$current);
        $policy=new AcademicAuthorization($this->db);
        $this->assertFalse($policy->allows($this->actor,'enrollment.create',['period_id'=>$old]));
        $this->assertTrue($policy->allows($this->actor,'enrollment.create',['period_id'=>$current]));
        $this->assertTrue($policy->allows($this->actor,'period.read',['period_id'=>$old]));
    }
    public function testProductionDefaultCannotUseUnavailableReferenceFixtures():void
    {$policy=new AcademicAuthorization($this->db);$this->assertFalse($policy->allows($this->actor,'submission.read',['submission_id'=>'1']));}
    public function testStoredRoleRevocationOverridesAuthenticatedSnapshot():void
    {
        $policy=new AcademicAuthorization($this->db);$this->assertTrue($policy->allows($this->actor,'catalog.manage'));
        $this->migration->table('local_role_grants')->where('identity_id',$this->actor->identityId)->delete();
        $this->assertFalse($policy->allows($this->actor,'catalog.manage'));
        $this->migration->table('retained_identities')->where('id',$this->actor->identityId)->update(['credential_status'=>'removed']);
        $this->assertFalse($policy->allows($this->actor,'catalog.manage'));
    }
}
