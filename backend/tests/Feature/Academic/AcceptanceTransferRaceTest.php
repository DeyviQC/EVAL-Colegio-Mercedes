<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
final class AcceptanceTransferRaceTest extends SubmissionReferenceTestCase
{
    private function payload():array
    {$section=$this->catalog->create($this->actor,'section',['name'=>'Destination','grade_id'=>$this->grade]);
        return ['student_id'=>$this->studentActor->identityId,'activity_id'=>$this->activityId,'assignment_id'=>$this->assignmentId,
            'enrollment_id'=>$this->enrollmentId,'grade_id'=>$this->grade,'section_id'=>$section,'academic_period_id'=>$this->period];}
    public function testRealAcceptanceFirstPreservesOldEnrollmentAfterTransfer():void
    {$input=$this->payload();$before=$this->ordinal();$this->acceptanceRace('accept','transfer','COMMITTED',$input,$input);
        $this->assertSame($before+2,$this->ordinal());$this->assertSame($this->enrollmentId,(string)$this->db->table('submission_references')->where('activity_id',$this->activityId)->value('accepted_under_enrollment_id'));}
    public function testRealTransferFirstDeniesOldActivityScope():void
    {$input=$this->payload();$before=$this->ordinal();$this->acceptanceRace('transfer','accept','scope_mismatch',$input,$input);
        $this->assertSame($before+1,$this->ordinal());$this->assertSame(0,$this->db->table('submission_references')->where('activity_id',$this->activityId)->count());}
    public function testRealParentClosureFirstDeniesAcceptance():void
    {$input=$this->payload();$before=$this->ordinal();$this->acceptanceRace('period_close','accept','period_not_active',$input,$input);
        $this->assertSame($before+1,$this->ordinal());$this->assertSame(0,$this->db->table('submission_references')->where('activity_id',$this->activityId)->count());}
    public function testRealAcceptanceFirstSurvivesParentClosure():void
    {$input=$this->payload();$before=$this->ordinal();$this->acceptanceRace('accept','period_close','COMMITTED',$input,$input);
        $this->assertSame($before+2,$this->ordinal());$this->assertSame(1,$this->db->table('submission_references')->where('activity_id',$this->activityId)->count());}
    public function testRealAssignmentClosureFirstStillPermitsOriginalRouteAcceptance():void
    {$input=$this->payload();$before=$this->ordinal();$this->acceptanceRace('assignment_close','accept','COMMITTED',$input,$input);
        $this->assertSame($before+2,$this->ordinal());$this->assertSame($this->assignmentId,(string)$this->db->table('submission_references')->where('activity_id',$this->activityId)->value('teaching_assignment_id'));}
}
