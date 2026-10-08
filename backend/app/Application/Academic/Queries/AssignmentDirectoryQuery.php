<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Application\Academic\Authorization\AcademicAuthorization;

/** Purpose-bound assignment discovery; never exposes a student roster or general account API. */
final class AssignmentDirectoryQuery
{
    public function __construct(private Connection $db){}
    public function page(AuthenticatedActor $actor,string $kind,?string $after):array
    {
        FoundationCommandChecks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'
            ||!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->whereIn('role',['director_admin','vice_principal'])->exists())throw new AcademicCommandFailure('forbidden');
        if($kind==='assignments'){
            $query=$this->db->table('teaching_assignments as a')->join('academic_periods as p','p.id','=','a.academic_period_id')
                ->join('instructional_entries as e','e.id','=','a.instructional_entry_id')->join('grades as g','g.id','=','a.grade_id')->join('sections as s','s.id','=','a.section_id')
                ->leftJoin('retained_identity_profiles as n','n.identity_id','=','a.teacher_id');
            $id='a.id';$fields=['a.id','a.teacher_id','n.display_name as teacher_name','a.academic_period_id','p.name as period_name','p.state as period_state','a.instructional_entry_id','e.name as entry_name','a.grade_id','g.name as grade_name','a.section_id','s.name as section_name','a.state','a.effective_from','a.effective_until','a.replaces_assignment_id'];
        }elseif($kind==='teachers'){
            $query=$this->db->table('retained_identities as i')->join('local_role_grants as r','r.identity_id','=','i.id')->leftJoin('retained_identity_profiles as n','n.identity_id','=','i.id')->where('r.role','teacher')->where('i.credential_status','active');
            $id='i.id';$fields=['i.id','n.display_name as name'];
        }else{
            $table=match($kind){'periods'=>'academic_periods','entries'=>'instructional_entries','grades'=>'grades','sections'=>'sections',default=>throw new AcademicCommandFailure('invalid_kind')};
            $query=$this->db->table($table);$id='id';
            $fields=match($kind){'periods'=>['id','name','start_on','end_on','state'],'entries'=>['id','name','kind','is_active'],'grades'=>['id','name','is_active'],'sections'=>['id','name','grade_id','is_active']};
            if($kind==='periods')$query->whereIn('state',['planned','active']);else $query->where('is_active',true);
        }
        if($after!==null){try{$after=AcademicLockSet::id($after);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}$query->where($id,'>',$after);}
        $rows=$query->orderBy($id)->limit(51)->get($fields)->all();$more=count($rows)>50;$authorization=new AcademicAuthorization($this->db);
        $items=[];
        foreach(array_slice($rows,0,50) as $row){
            $item=(array)$row;
            if(in_array($kind,['teachers','assignments'],true)){
                $name=$kind==='teachers'?'name':'teacher_name';if(!is_string($item[$name])||trim($item[$name])==='')throw new AcademicCommandFailure('reference_unavailable');
            }
            if($kind==='assignments'&&!$authorization->allows($actor,'assignment.read',['assignment_id'=>(string)$item['id']]))throw new AcademicCommandFailure('forbidden');
            foreach($item as $key=>$value){if($key==='id'||str_ends_with($key,'_id'))$item[$key]=$value===null?null:(string)$value;elseif($key==='is_active')$item[$key]=(bool)$value;}
            $items[]=$item;
        }
        return ['items'=>$items,'next_after'=>$more?$items[count($items)-1]['id']:null];
    }
}
