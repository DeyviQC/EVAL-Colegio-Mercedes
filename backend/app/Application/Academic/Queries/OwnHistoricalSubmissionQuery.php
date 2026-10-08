<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\Authorization\AcademicReferenceReader;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
final class OwnHistoricalSubmissionQuery
{
    public function __construct(private Connection $db,private AcademicAuthorization $authorization,
        private AcademicReferenceReader $references,private AcademicIdentityLabels $identityLabels){}
    public function get(?AuthenticatedActor $actor,string $id):array
    {
        try{$id=\App\Infrastructure\Persistence\Academic\AcademicLockSet::id($id);}
        catch(\InvalidArgumentException){throw new AcademicCommandFailure('not_found');}
        $submission=$this->references->submission($id);
        // Owner gate precedes historical relationship/label resolution, even for administrators.
        if(!$actor || !$submission || (string)$submission['student_id']!==$actor->identityId
            || !$this->authorization->allows($actor,'submission.read',['submission_id'=>$id])){throw new AcademicCommandFailure('not_found');}
        $activity=$this->references->activity((string)$submission['activity_id']);
        if(!$activity || (string)$activity['teaching_assignment_id']!==(string)$submission['teaching_assignment_id']){throw new AcademicCommandFailure('not_found');}
        $assignment=$this->load('teaching_assignments',(string)$submission['teaching_assignment_id']);
        $enrollment=$this->load('student_enrollments',(string)$submission['accepted_under_enrollment_id']);
        if((string)$enrollment['student_id']!==$actor->identityId){throw new AcademicCommandFailure('not_found');}
        foreach(['academic_period_id','grade_id','section_id'] as $field){
            if((string)$assignment[$field]!==(string)$enrollment[$field]){throw new AcademicCommandFailure('not_found');}
        }
        $label=$this->identityLabels->displayName((string)$assignment['teacher_id']);
        if($label===null){throw new AcademicCommandFailure('reference_unavailable');}
        return ['submission'=>['id'=>$id,'acceptedAt'=>$submission['accepted_at']],
            'activityReference'=>['id'=>(string)$activity['id']],
            'acceptedUnderEnrollment'=>['id'=>(string)$enrollment['id'],
                'academicPeriod'=>$this->reference('academic_periods',(string)$enrollment['academic_period_id']),
                'grade'=>$this->reference('grades',(string)$enrollment['grade_id']),'section'=>$this->reference('sections',(string)$enrollment['section_id'])]+$this->interval($enrollment),
            'originalTeachingAssignment'=>['id'=>(string)$assignment['id'],
                'academicPeriod'=>$this->reference('academic_periods',(string)$assignment['academic_period_id']),
                'teacher'=>['id'=>(string)$assignment['teacher_id'],'displayName'=>$label],
                'instructionalEntry'=>$this->reference('instructional_entries',(string)$assignment['instructional_entry_id'],true),
                'grade'=>$this->reference('grades',(string)$assignment['grade_id']),'section'=>$this->reference('sections',(string)$assignment['section_id'])]+$this->interval($assignment),
        ];
    }
    private function load(string $table,string $id):array
    {$row=$this->db->table($table)->where('id',$id)->first();if(!$row){throw new AcademicCommandFailure('not_found');}return (array)$row;}
    private function reference(string $table,string $id,bool $kind=false):array
    {$row=$this->load($table,$id);return ['id'=>(string)$row['id'],'displayName'=>$row['name']]+($kind?['kind'=>$row['kind']]:[]);}
    private function interval(array $row):array
    {return ['effectiveFrom'=>$row['effective_from'],'effectiveUntil'=>$row['effective_until'],
        'operationalStartKey'=>$row['operational_start_key']===null?null:(string)$row['operational_start_key'],
        'operationalEndKeyExclusive'=>$row['operational_end_key']===null?null:(string)$row['operational_end_key']];}
}
