<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\AssignmentTestCase;
use Tests\Support\FixtureAcademicReferences;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\Queries\OwnHistoricalSubmissionQuery;
use App\Application\Academic\Queries\AcademicIdentityLabels;
final class HistoricalAcademicReadTest extends AssignmentTestCase
{
    protected const DATABASE='eval_u9_test';
    public function testOwnClosedPeriodEnvelopeRetainsOriginalRouteAndEnrollmentContext():void
    {
        $student=$this->actor('student');$id=$this->assignment();$this->assignments->activate($this->actor,$id);
        $enrollment=$this->enrollment(['student_id'=>$student->identityId]);$this->commands->close($this->actor,$enrollment,$this->today());
        $section=$this->catalog->create($this->actor,'section',['name'=>'New','grade_id'=>$this->grade]);
        $this->enrollment(['student_id'=>$student->identityId,'section_id'=>$section]);$this->assignments->close($this->actor,$id,$this->today());$this->periods->close($this->actor,$this->period);
        $refs=new FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>$id]],['1'=>['id'=>'1','student_id'=>$student->identityId,
            'activity_id'=>'1','teaching_assignment_id'=>$id,'accepted_under_enrollment_id'=>$enrollment,'accepted_at'=>'2026-10-07 12:00:00.000000']]);
        $labels=new class implements AcademicIdentityLabels {public function displayName(string $identityId):?string{return 'Synthetic teacher';}};
        $query=new OwnHistoricalSubmissionQuery($this->db,new AcademicAuthorization($this->db,$refs),$refs,$labels);
        $view=$query->get($student,'1');$this->assertSame($id,$view['originalTeachingAssignment']['id']);
        $this->assertSame($enrollment,$view['acceptedUnderEnrollment']['id']);$this->assertSame($this->section,$view['acceptedUnderEnrollment']['section']['id']);
        $this->assertSame('Synthetic teacher',$view['originalTeachingAssignment']['teacher']['displayName']);
    }
    private function fixture():array
    {
        $student=$this->actor('student');$id=$this->assignment();$this->assignments->activate($this->actor,$id);
        $enrollment=$this->enrollment(['student_id'=>$student->identityId]);
        $refs=new FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>$id]],['1'=>['id'=>'1','student_id'=>$student->identityId,
            'activity_id'=>'1','teaching_assignment_id'=>$id,'accepted_under_enrollment_id'=>$enrollment,'accepted_at'=>'2026-10-07 12:00:00.000000',
            'content'=>'Deferred payload must not appear','grade'=>'AD','recipient_id'=>'999']]);
        $labels=new class implements AcademicIdentityLabels {public ?string $label='Original teacher';public int $calls=0;
            public function displayName(string $identityId):?string{$this->calls++;return $this->label;}};
        $query=new OwnHistoricalSubmissionQuery($this->db,new AcademicAuthorization($this->db,$refs),$refs,$labels);
        return [$student,$id,$enrollment,$refs,$labels,$query];
    }
    public function testOwnerFirstDenialNeverResolvesAnotherStudentsLabels():void
    {
        [$student,$id,$enrollment,$refs,$labels,$query]=$this->fixture();
        foreach([$this->actor('student'),$this->actor('vice_principal'),$this->actor,null] as $actor){
            $this->assignmentDenied('not_found',fn()=>$query->get($actor,'1'));
        }
        $this->assertSame(0,$labels->calls);
        $this->assertSame('1',$query->get($student,'1')['submission']['id']);$this->assertSame(1,$labels->calls);
    }
    public function testCorrectedLabelsResolveNowAndDeferredPayloadNeverLeaks():void
    {
        [$student,$id,$enrollment,$refs,$labels,$query]=$this->fixture();
        $original=$query->get($student,'1');$labels->label='Corrected teacher';
        $this->catalog->update($this->actor,'entry',$this->entry,['name'=>'Corrected subject '.$this->suffix]);
        $view=$query->get($student,'1');$this->assertSame('Corrected teacher',$view['originalTeachingAssignment']['teacher']['displayName']);
        $this->assertSame('Corrected subject '.$this->suffix,$view['originalTeachingAssignment']['instructionalEntry']['displayName']);
        $this->assertSame($original['originalTeachingAssignment']['id'],$view['originalTeachingAssignment']['id']);
        $this->assertSame(['submission','activityReference','acceptedUnderEnrollment','originalTeachingAssignment'],array_keys($view));
        $this->assertSame(['id','acceptedAt'],array_keys($view['submission']));
        foreach(['Deferred payload','recipient_id','name_key','"grade":"AD"'] as $needle){$this->assertStringNotContainsString($needle,json_encode($view));}
    }
    public function testActualTransferAndReplacementDoNotRerouteSyntheticHistory():void
    {
        [$student,$id,$enrollment,$refs,$labels,$query]=$this->fixture();
        $section=$this->catalog->create($this->actor,'section',['name'=>'Other','grade_id'=>$this->grade]);
        (new \App\Application\Academic\Commands\TransferStudent($this->db))->execute($this->actor,$enrollment,['grade_id'=>$this->grade,'section_id'=>$section]);
        $nextTeacher=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $replacement=(new \App\Application\Academic\Commands\ReplaceTeacher($this->db))->execute($this->actor,$id,['teacher_id'=>$nextTeacher]);
        $this->periods->close($this->actor,$this->period);$view=$query->get($student,'1');
        $this->assertSame($id,$view['originalTeachingAssignment']['id']);$this->assertNotSame($replacement['successor_id'],$view['originalTeachingAssignment']['id']);
        $this->assertSame($this->teacher,$view['originalTeachingAssignment']['teacher']['id']);
        $this->assertSame($enrollment,$view['acceptedUnderEnrollment']['id']);$this->assertSame($this->section,$view['acceptedUnderEnrollment']['section']['id']);
        $this->assertNotNull($view['acceptedUnderEnrollment']['operationalEndKeyExclusive']);
    }
    public function testUnavailableIdentityNameAndBrokenReferenceRouteFailClosed():void
    {
        [$student,$id,$enrollment,$refs,$labels,$query]=$this->fixture();$labels->label=null;
        $this->assignmentDenied('reference_unavailable',fn()=>$query->get($student,'1'));
        $this->assignmentDenied('not_found',fn()=>$query->get($student,'999'));
        $this->assignmentDenied('not_found',fn()=>$query->get($student,'0'));
        $bad=new FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>'999']],['1'=>['id'=>'1','student_id'=>$student->identityId,
            'activity_id'=>'1','teaching_assignment_id'=>$id,'accepted_under_enrollment_id'=>$enrollment,'accepted_at'=>'2026-10-07 12:00:00.000000']]);
        $query=new OwnHistoricalSubmissionQuery($this->db,new AcademicAuthorization($this->db,$bad),$bad,$labels);
        $this->assignmentDenied('not_found',fn()=>$query->get($student,'1'));
    }
}
