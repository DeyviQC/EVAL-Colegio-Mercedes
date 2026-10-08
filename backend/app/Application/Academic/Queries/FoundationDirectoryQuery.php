<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
final class FoundationDirectoryQuery
{
    public function __construct(private Connection $db){}
    public function page(AuthenticatedActor $actor,string $kind,?string $after):array
    {
        FoundationCommandChecks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'
            || !$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','director_admin')->exists()){
            throw new AcademicCommandFailure('forbidden');
        }
        $table=match($kind){'period'=>'academic_periods','entry'=>'instructional_entries','grade'=>'grades','section'=>'sections',default=>throw new AcademicCommandFailure('invalid_kind')};
        $fields=match($kind){'period'=>['id','name','start_on','end_on','state'],'entry'=>['id','name','kind','is_active'],'grade'=>['id','name','is_active'],'section'=>['id','name','grade_id','is_active']};
        $query=$this->db->table($table)->orderBy('id');
        if($after!==null){try{$after=AcademicLockSet::id($after);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}$query->where('id','>',$after);}
        $rows=$query->limit(51)->get($fields)->all();$more=count($rows)>50;$rows=array_slice($rows,0,50);
        $items=array_map(static function($row){$item=(array)$row;$item['id']=(string)$item['id'];
            if(isset($item['grade_id']))$item['grade_id']=(string)$item['grade_id'];
            if(isset($item['is_active']))$item['is_active']=(bool)$item['is_active'];return $item;},$rows);
        return ['items'=>$items,'next_after'=>$more?$items[count($items)-1]['id']:null];
    }
}
