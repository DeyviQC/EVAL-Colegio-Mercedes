<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Connection;
final class AcademicScopeQuery
{
    public function __construct(private Connection $db,private AcademicAuthorization $authorization){}
    public function record(?AuthenticatedActor $actor,string $kind,string $id):array
    {
        $table=match($kind){'period'=>'academic_periods','enrollment'=>'student_enrollments','assignment'=>'teaching_assignments',default=>null};
        if(!$table || !$this->authorization->allows($actor,$kind.'.read',[$kind.'_id'=>$id])){throw new AcademicCommandFailure('not_found');}
        $fields=match($kind){
            'period'=>['id','name','start_on','end_on','state'],
            'enrollment'=>['id','student_id','academic_period_id','grade_id','section_id','state','effective_from','effective_until','operational_start_key','operational_end_key'],
            'assignment'=>['id','teacher_id','academic_period_id','instructional_entry_id','grade_id','section_id','state','effective_from','effective_until','operational_start_key','operational_end_key','replaces_assignment_id'],
        };
        $row=$this->db->table($table)->where('id',$id)->first($fields);
        if(!$row){throw new AcademicCommandFailure('not_found');}
        $result=(array)$row;foreach($result as $key=>$value){if($value!==null && ($key==='id' || str_ends_with($key,'_id') || str_ends_with($key,'_key'))){$result[$key]=(string)$value;}}
        return $result;
    }
}
