<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
final class TeachingAssignmentSupportQuery
{
    public function __construct(private Connection $db,private AcademicAuthorization $authorization){}
    public function forAssignmentOperation(?AuthenticatedActor $actor,string $duty,array $target):array
    {
        if(!$actor || !in_array($duty,['create','activate','close','replace','history'],true)) {throw new AcademicCommandFailure('forbidden');}
        $manager=$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->whereIn('role',['director_admin','vice_principal'])->exists();
        if(!$manager){throw new AcademicCommandFailure('forbidden');}
        if($duty==='create'){
            $fields=\App\Infrastructure\Persistence\Academic\AssignmentOverlapQuery::SCOPE;
            FoundationCommandChecks::fields($target,$fields,$fields);
            try{foreach($target as $key=>$id){$target[$key]=AcademicLockSet::id($id);}}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
            if(!$this->authorization->allows($actor,'assignment.create',['period_id'=>$target['academic_period_id']])){throw new AcademicCommandFailure('forbidden');}
            $scope=$target;
        }else{
            FoundationCommandChecks::fields($target,['assignment_id'],['assignment_id']);
            try{$assignmentId=AcademicLockSet::id($target['assignment_id']);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
            $scope=(new AcademicScopeQuery($this->db,$this->authorization))->record($actor,'assignment',$assignmentId);
            if($duty!=='history' && !$this->authorization->allows($actor,'assignment.'.$duty,['assignment_id'=>$scope['id']])){throw new AcademicCommandFailure('forbidden');}
        }
        $ref=function(string $table,string $id,array $fields):array{
            $row=$this->db->table($table)->where('id',$id)->first($fields);
            if(!$row){throw new AcademicCommandFailure('not_found');}
            $result=(array)$row;foreach($result as $key=>$value){if($key==='id' || str_ends_with($key,'_id')){$result[$key]=(string)$value;}
                elseif($key==='is_active'){$result[$key]=(bool)$value;}}return $result;
        };
        $period=$ref('academic_periods',$scope['academic_period_id'],['id','name','start_on','end_on','state']);
        $teacher=$ref('retained_identities',$scope['teacher_id'],['id']);
        $entry=$ref('instructional_entries',$scope['instructional_entry_id'],['id','name','kind','is_active']);
        $grade=$ref('grades',$scope['grade_id'],['id','name','is_active']);
        $section=$ref('sections',$scope['section_id'],['id','name','grade_id','is_active']);
        if($section['grade_id']!==$grade['id']){throw new AcademicCommandFailure('scope_mismatch');}
        if(in_array($duty,['create','activate','replace'],true) && (!$entry['is_active'] || !$grade['is_active'] || !$section['is_active'])){
            throw new AcademicCommandFailure('inactive_catalog_reference');
        }
        $enrollmentScope=['academic_period_id'=>$period['id'],'grade_id'=>$grade['id'],'section_id'=>$section['id']];
        $enrollments=$this->db->table('student_enrollments')->where($enrollmentScope)->where('state','active')->count();
        return ['period'=>$period,'teacher'=>$teacher,'entry'=>$entry,'grade'=>$grade,'section'=>$section,
            'enrollment_scope'=>$enrollmentScope+['active_count'=>$enrollments]];
    }
}
