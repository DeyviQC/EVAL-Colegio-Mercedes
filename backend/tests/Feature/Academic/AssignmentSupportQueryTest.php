<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\AssignmentTestCase;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\Queries\TeachingAssignmentSupportQuery;
final class AssignmentSupportQueryTest extends AssignmentTestCase
{
    protected const DATABASE='eval_u9_test';
    public function testVicePrincipalReceivesMinimumProposedAssignmentSupport():void
    {
        $query=new TeachingAssignmentSupportQuery($this->db,new AcademicAuthorization($this->db));
        $scope=$this->assignmentInput();unset($scope['effective_from']);
        $view=$query->forAssignmentOperation($this->actor('vice_principal'),'create',$scope);
        $this->assertSame($this->period,$view['period']['id']);$this->assertSame($this->grade,$view['grade']['id']);
        $this->assertArrayHasKey('enrollment_scope',$view);
    }
    public function testClosedHistoricalScopeResolvesInactiveReferencesForExactTarget():void
    {
        $id=$this->assignment();$this->periods->close($this->actor,$this->period);
        $this->migration->table('instructional_entries')->where('id',$this->entry)->update(['is_active'=>false]);
        $query=new TeachingAssignmentSupportQuery($this->db,new AcademicAuthorization($this->db));
        $view=$query->forAssignmentOperation($this->actor('vice_principal'),'history',['assignment_id'=>$id]);
        $this->assertSame('closed',$view['period']['state']);$this->assertFalse($view['entry']['is_active']);
    }
    public function testProjectionContainsOnlyNecessaryFactsAndDoesNotWrite():void
    {
        $id=$this->assignment();$this->enrollment();$before=$this->ordinal();$count=$this->db->table('academic_lifecycle_events')->count();
        $query=new TeachingAssignmentSupportQuery($this->db,new AcademicAuthorization($this->db));
        $view=$query->forAssignmentOperation($this->actor('vice_principal'),'history',['assignment_id'=>$id]);
        $this->assertSame(['period','teacher','entry','grade','section','enrollment_scope'],array_keys($view));
        $this->assertSame(['id'],array_keys($view['teacher']));
        $this->assertSame(['academic_period_id','grade_id','section_id','active_count'],array_keys($view['enrollment_scope']));
        $this->assertSame(1,$view['enrollment_scope']['active_count']);$this->assertSame($before,$this->ordinal());
        $this->assertSame($count,$this->db->table('academic_lifecycle_events')->count());
        $this->assertStringNotContainsString('name_key',json_encode($view));
        $this->assertStringNotContainsString('student_id',json_encode($view));
    }
    public function testInactiveReferencesSupportCleanupButCannotSupportNewAssignment():void
    {
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);$this->periods->close($this->actor,$this->period);
        $this->migration->table('grades')->where('id',$this->grade)->update(['is_active'=>false]);
        $vice=$this->actor('vice_principal');$query=new TeachingAssignmentSupportQuery($this->db,new AcademicAuthorization($this->db));
        $view=$query->forAssignmentOperation($vice,'close',['assignment_id'=>$id]);$this->assertFalse($view['grade']['is_active']);
        $scope=$this->assignmentInput();unset($scope['effective_from']);
        $this->assignmentDenied('forbidden',fn()=>$query->forAssignmentOperation($vice,'create',$scope));
        $this->assignmentDenied('forbidden',fn()=>$query->forAssignmentOperation($vice,'replace',['assignment_id'=>$id]));
    }
    public function testPlannedParentProposedSupportAndInactiveNewScopeRules():void
    {
        $period=$this->periods->create($this->actor,['name'=>'Future '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
        $scope=$this->assignmentInput(['academic_period_id'=>$period]);unset($scope['effective_from']);
        $query=new TeachingAssignmentSupportQuery($this->db,new AcademicAuthorization($this->db));$vice=$this->actor('vice_principal');
        $this->assertSame('planned',$query->forAssignmentOperation($vice,'create',$scope)['period']['state']);
        $this->migration->table('instructional_entries')->where('id',$this->entry)->update(['is_active'=>false]);
        $this->assignmentDenied('inactive_catalog_reference',fn()=>$query->forAssignmentOperation($vice,'create',$scope));
    }
    public function testUnscopedBrowsingTamperingAndUnrelatedRolesAreDenied():void
    {
        $id=$this->assignment();$query=new TeachingAssignmentSupportQuery($this->db,new AcademicAuthorization($this->db));$vice=$this->actor('vice_principal');
        $this->assignmentDenied('invalid_input',fn()=>$query->forAssignmentOperation($vice,'history',[]));
        $this->assignmentDenied('invalid_input',fn()=>$query->forAssignmentOperation($vice,'history',['assignment_id'=>[]]));
        $this->assignmentDenied('invalid_input',fn()=>$query->forAssignmentOperation($vice,'history',['assignment_id'=>$id,'period_id'=>$this->period]));
        foreach(['browse','enrollment.close','material.manage','submission.read'] as $duty){$this->assignmentDenied('forbidden',fn()=>$query->forAssignmentOperation($vice,$duty,['assignment_id'=>$id]));}
        foreach([$this->actor('teacher'),$this->actor('student'),null] as $actor){$this->assignmentDenied('forbidden',fn()=>$query->forAssignmentOperation($actor,'history',['assignment_id'=>$id]));}
    }
    public function testGeneralEnrollmentAndPeriodReadsStayUnavailableToVicePrincipal():void
    {
        $enrollment=$this->enrollment();$vice=$this->actor('vice_principal');$query=new \App\Application\Academic\Queries\AcademicScopeQuery($this->db,new AcademicAuthorization($this->db));
        $this->assignmentDenied('not_found',fn()=>$query->record($vice,'period',$this->period));
        $this->assignmentDenied('not_found',fn()=>$query->record($vice,'enrollment',$enrollment));
    }
}
