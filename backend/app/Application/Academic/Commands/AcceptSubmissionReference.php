<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
final class AcceptSubmissionReference
{
    public function __construct(private Connection $db){}
    public function execute(AuthenticatedActor $actor,array $input):string
    {
        FoundationCommandChecks::fields($input,['activity_id'],['activity_id']);
        try{$activityId=AcademicLockSet::id($input['activity_id']);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
        $enrollmentId=null;
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function($db,$actorId,$context)use($activityId,$actor){
                $activity=$context?$context->row('activity_references',$activityId):(array)$this->db->table('activity_references')->where('id',$activityId)->first();
                if(!$activity){return new AcademicLockSet(['activity_references'=>[$activityId]]);}
                $assignment=$context?$context->row('teaching_assignments',$activity['teaching_assignment_id']):(array)$this->db->table('teaching_assignments')->where('id',$activity['teaching_assignment_id'])->first();
                return new AcademicLockSet(['academic_periods'=>[$assignment['academic_period_id']],'students'=>[$actor->identityId],
                    'teachers'=>[$assignment['teacher_id']],'teaching_assignments'=>[$assignment['id']],'activity_references'=>[$activityId],
                    'student_enrollments'=>$this->db->table('student_enrollments')->where('student_id',$actor->identityId)->where('academic_period_id',$assignment['academic_period_id'])->pluck('id')->all()]);
            },function($context,$key)use($actor,$activityId,&$enrollmentId){
                FoundationCommandChecks::credentials($this->db,$actor);
                if(!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','student')->exists()){throw new AcademicCommandFailure('forbidden');}
                $activity=$context->row('activity_references',$activityId);$assignment=$context->row('teaching_assignments',$activity['teaching_assignment_id']);
                if($context->row('academic_periods',$assignment['academic_period_id'])['state']!=='active'){throw new AcademicCommandFailure('period_not_active');}
                $active=array_filter($context->rows('student_enrollments'),fn($row)=>$row['state']==='active' && $row['operational_start_key']!==null
                    && (int)$row['operational_start_key']<=$key && ($row['operational_end_key']===null || (int)$row['operational_end_key']>$key));
                if(count($active)!==1){throw new AcademicCommandFailure('no_active_enrollment');}
                $enrollment=array_values($active)[0];foreach(['academic_period_id','grade_id','section_id'] as $field){
                    if((string)$assignment[$field]!==(string)$enrollment[$field]){throw new AcademicCommandFailure('scope_mismatch');}}
                $enrollmentId=(string)$enrollment['id'];return true;
            },function($context,$boundary)use($actor,$activityId,&$enrollmentId){
                $activity=$context->row('activity_references',$activityId);
                $this->db->table('submission_references')->insert(['student_id'=>$actor->identityId,'activity_id'=>$activityId,
                    'teaching_assignment_id'=>$activity['teaching_assignment_id'],'accepted_under_enrollment_id'=>$enrollmentId,
                    'accepted_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u'),'acceptance_operation_key'=>$boundary->toServerKey()]);
                $id=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult($id,[new LifecycleEvent('assignment',$activity['teaching_assignment_id'],'submission_reference_accepted',null,null,
                    ['submission_reference_id'=>$id,'activity_reference_id'=>$activityId,'accepted_under_enrollment_id'=>$enrollmentId])]);
            });
    }
}
