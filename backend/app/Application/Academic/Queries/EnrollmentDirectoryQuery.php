<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;

final class EnrollmentDirectoryQuery
{
    public function __construct(private Connection $db){}
    public function page(AuthenticatedActor $actor,string $kind,?string $after):array
    {
        FoundationCommandChecks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'
            ||!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','director_admin')->exists())throw new AcademicCommandFailure('forbidden');
        if($kind==='students'){
            $query=$this->db->table('retained_identities as i')->join('local_role_grants as r','r.identity_id','=','i.id')
                ->leftJoin('retained_identity_profiles as p','p.identity_id','=','i.id')->where('r.role','student')->where('i.credential_status','active');
            $idColumn='i.id';$fields=['i.id','p.display_name as name'];
        }elseif($kind==='enrollments'){
            $query=$this->db->table('student_enrollments as e')->join('academic_periods as p','p.id','=','e.academic_period_id')
                ->join('grades as g','g.id','=','e.grade_id')->join('sections as s','s.id','=','e.section_id')
                ->leftJoin('retained_identity_profiles as n','n.identity_id','=','e.student_id');
            $idColumn='e.id';$fields=['e.id','e.student_id','n.display_name as student_name','e.academic_period_id','p.name as period_name','e.grade_id','g.name as grade_name','e.section_id','s.name as section_name','e.state','e.effective_from','e.effective_until'];
        }else{throw new AcademicCommandFailure('invalid_kind');}
        if($after!==null){try{$after=AcademicLockSet::id($after);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}$query->where($idColumn,'>',$after);}
        $rows=$query->orderBy($idColumn)->limit(51)->get($fields)->all();$more=count($rows)>50;
        $items=array_map(static function($row)use($kind){
            $item=(array)$row;$name=$kind==='students'?'name':'student_name';
            if(!is_string($item[$name])||trim($item[$name])==='')throw new AcademicCommandFailure('reference_unavailable');
            foreach(['id','student_id','academic_period_id','grade_id','section_id'] as $field)if(isset($item[$field]))$item[$field]=(string)$item[$field];
            return $item;
        },array_slice($rows,0,50));
        return ['items'=>$items,'next_after'=>$more?$items[count($items)-1]['id']:null];
    }
}
