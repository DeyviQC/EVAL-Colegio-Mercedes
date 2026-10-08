<?php
declare(strict_types=1);
namespace App\Application\Academic\Authorization;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Connection;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
final class AcademicAuthorization
{
    public function __construct(private Connection $db,private AcademicReferenceReader $references=new UnavailableAcademicReferences()){}
    public function allows(?AuthenticatedActor $actor,string $operation,array $locators=[]):bool
    {
        if(!$actor){return false;}
        try{FoundationCommandChecks::credentials($this->db,$actor);}
        catch(AcademicCommandFailure){return false;}
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'){return false;}
        $roles=$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();
        $director=in_array('director_admin',$roles,true);$manager=$director || in_array('vice_principal',$roles,true);
        $teacher=in_array('teacher',$roles,true);$student=in_array('student',$roles,true);
        $field=match($operation){
            'catalog.manage','period.create'=>null,
            'period.read','period.activate','period.close','enrollment.create','assignment.create'=>'period_id',
            'enrollment.read','enrollment.close','enrollment.transfer'=>'enrollment_id',
            'assignment.read','assignment.activate','assignment.close','assignment.replace','activity.create'=>'assignment_id',
            'activity.read','submission.accept','submission.accept_late'=>'activity_id',
            'submission.read'=>'submission_id',default=>false,
        };
        if($field===false || array_keys($locators)!==($field===null?[]:[$field])){return false;}
        if($field===null){return $director;}
        try{$id=AcademicLockSet::id($locators[$field]);}catch(\InvalidArgumentException){return false;}
        $row=match($field){
            'period_id'=>$this->record('academic_periods',$id),'enrollment_id'=>$this->record('student_enrollments',$id),
            'assignment_id'=>$this->record('teaching_assignments',$id),'activity_id'=>$this->references->activity($id),
            'submission_id'=>$this->references->submission($id),
        };
        if(!$row){return false;}
        if($field==='period_id'){
            return match($operation){
                'period.read'=>$director,'period.activate'=>$director && $row['state']==='planned',
                'period.close'=>$director && $row['state']==='active',
                'enrollment.create'=>$director && $row['state']==='active',
                'assignment.create'=>$manager && in_array($row['state'],['planned','active'],true),default=>false,
            };
        }
        if($field==='enrollment_id'){
            if($operation==='enrollment.read'){return $director || ($student && (string)$row['student_id']===$actor->identityId);}
            return $director && $row['state']==='active' && ($operation==='enrollment.close' || $this->activeParent($row));
        }
        if($field==='assignment_id'){
            $owned=$teacher && (string)$row['teacher_id']===$actor->identityId;
            return match($operation){
                'assignment.read'=>$manager || $owned,
                'assignment.close'=>$manager && $row['state']==='active',
                'assignment.activate'=>$manager && $row['state']==='planned' && $this->activeParent($row),
                'assignment.replace'=>$manager && $row['state']==='active' && $this->activeParent($row),
                'activity.create'=>$owned && $row['state']==='active' && $this->activeParent($row),default=>false,
            };
        }
        $assignment=$this->record('teaching_assignments',(string)$row['teaching_assignment_id']);
        if(!$assignment){return false;}
        if($field==='submission_id'){
            return $director || ($teacher && (string)$assignment['teacher_id']===$actor->identityId)
                || ($student && (string)$row['student_id']===$actor->identityId);
        }
        if($operation==='activity.read' && ($director || ($teacher && (string)$assignment['teacher_id']===$actor->identityId))){return true;}
        if(!$student || !$this->activeParent($assignment)){return false;}
        // Existing activity retains its route even when the original assignment is closed.
        return $this->db->table('student_enrollments')->where('student_id',$actor->identityId)->where('state','active')
            ->where('academic_period_id',$assignment['academic_period_id'])->where('grade_id',$assignment['grade_id'])
            ->where('section_id',$assignment['section_id'])->exists();
    }
    private function record(string $table,string $id):?array
    {$row=$this->db->table($table)->where('id',$id)->first();return $row?(array)$row:null;}
    private function activeParent(array $row):bool
    {return $this->db->table('academic_periods')->where('id',$row['academic_period_id'])->value('state')==='active';}
}
