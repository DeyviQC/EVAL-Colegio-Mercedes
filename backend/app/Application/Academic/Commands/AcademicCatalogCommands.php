<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Connection;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
final class AcademicCatalogCommands
{
    public function __construct(private Connection $db){}
    private function table(string $type):string
    {return match($type){'entry'=>'instructional_entries','grade'=>'grades','section'=>'sections',default=>throw new AcademicCommandFailure('invalid_kind')};}
    private function active(mixed $value):bool
    {if(!is_bool($value)){throw new AcademicCommandFailure('invalid_input');}return $value;}
    private function conflicts(string $table,array $scope,string $key,?string $except=null)
    {
        $query=$this->db->table($table)->where('name_key',$key)->where('is_active',true);
        foreach($scope as $column=>$value){$query->where($column,$value);}
        if($except!==null){$query->where('id','<>',$except);}return $query;
    }
    public function create(AuthenticatedActor $actor,string $type,array $input):string
    {
        $table=$this->table($type);
        $scopeFields=match($type){'entry'=>['kind'],'section'=>['grade_id'],default=>[]};
        FoundationCommandChecks::fields($input,array_merge(['name','is_active'],$scopeFields),array_merge(['name'],$scopeFields));
        $name=FoundationCommandChecks::name($input['name']);$active=$this->active($input['is_active']??true);
        $scope=[];
        if($type==='entry'){
            if(!in_array($input['kind'],['subject','area'],true)){throw new AcademicCommandFailure('invalid_kind');}$scope=['kind'=>$input['kind']];
        }
        if($type==='section'){$scope=['grade_id'=>AcademicLockSet::id($input['grade_id'])];}
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function()use($table,$scope,$name){
                $set=[$table=>$this->conflicts($table,$scope,$name['name_key'])->pluck('id')->all()];
                if(isset($scope['grade_id'])){$set['grades'][]=$scope['grade_id'];}return new AcademicLockSet($set);
            },function($context)use($table,$scope,$name,$active,$actor){
                FoundationCommandChecks::director($this->db,$context,$actor);
                if($active && $this->conflicts($table,$scope,$name['name_key'])->exists()){throw new AcademicCommandFailure('duplicate_name');}return true;
            },function()use($table,$type,$scope,$name,$active){
                $this->db->table($table)->insert($name+$scope+['is_active'=>$active]);
                $id=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult($id,[new LifecycleEvent($type,$id,'created',null,$active?'active':'inactive')]);
            });
    }
    public function update(AuthenticatedActor $actor,string $type,int|string $id,array $changes):string
    {
        $table=$this->table($type);$id=AcademicLockSet::id($id);
        FoundationCommandChecks::fields($changes,['name','is_active']);
        if(!$changes){throw new AcademicCommandFailure('invalid_input');}
        $name=array_key_exists('name',$changes)?FoundationCommandChecks::name($changes['name']):null;
        $active=array_key_exists('is_active',$changes)?$this->active($changes['is_active']):null;
        $discover=function($db,$actor,$context)use($table,$id,$name){
            $row=$context?$context->row($table,$id):(array)$this->db->table($table)->where('id',$id)->first();
            $scope=array_intersect_key($row,array_flip(['kind','grade_id']));
            $set=[$table=>array_merge([$id],$row?$this->conflicts($table,$scope,$name['name_key']??$row['name_key'],$id)->pluck('id')->all():[])];
            if(isset($scope['grade_id'])){$set['grades'][]=$scope['grade_id'];}return new AcademicLockSet($set);
        };
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,$discover,
            function($context)use($table,$id,$name,$active,$actor){
                FoundationCommandChecks::director($this->db,$context,$actor);$row=$context->row($table,$id);
                $scope=array_intersect_key($row,array_flip(['kind','grade_id']));
                if(($active??(bool)$row['is_active']) && $this->conflicts($table,$scope,$name['name_key']??$row['name_key'],$id)->exists()){
                    throw new AcademicCommandFailure('duplicate_name');
                }return true;
            },function($context)use($table,$type,$id,$name,$active){
                $row=$context->row($table,$id);$update=$name??[];$events=[];
                $from=(bool)$row['is_active']?'active':'inactive';$to=($active??(bool)$row['is_active'])?'active':'inactive';
                if($name){$events[]=new LifecycleEvent($type,$id,'renamed',$from,$to,['previous_name'=>$row['name'],'new_name'=>$name['name']]);}
                if($active!==null){$update['is_active']=$active;$events[]=new LifecycleEvent($type,$id,$active?'activated':'deactivated',$from,$to);}
                $this->db->table($table)->where('id',$id)->update($update);return new AcademicMutationResult($id,$events);
            });
    }
}
