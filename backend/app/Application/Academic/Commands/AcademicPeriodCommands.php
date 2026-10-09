<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Connection;
use App\Application\Academic\AcademicCommandFailure;
use App\Domain\Academic\DeclaredDateRange;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
final class AcademicPeriodCommands
{
    public function __construct(private Connection $db){}
    public function create(AuthenticatedActor $actor,array $input):string
    {
        FoundationCommandChecks::fields($input,['name','start_on','end_on'],['name','start_on','end_on']);
        $name=FoundationCommandChecks::name($input['name']);
        try{
            if(!is_string($input['start_on']) || !is_string($input['end_on'])){throw new \InvalidArgumentException();}
            new DeclaredDateRange($input['start_on'],$input['end_on']);
        }catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_interval');}
        $conflicts=fn()=>$this->db->table('academic_periods')->where('name_key',$name['name_key']);
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            fn()=>new AcademicLockSet(['academic_periods'=>$conflicts()->pluck('id')->all()]),
            function($context)use($conflicts,$actor){
                FoundationCommandChecks::director($this->db,$context,$actor);
                if($conflicts()->exists()){throw new AcademicCommandFailure('duplicate_name');}return true;
            },function()use($input,$name){
                $this->db->table('academic_periods')->insert($name+['start_on'=>$input['start_on'],'end_on'=>$input['end_on'],'state'=>'planned']);
                $id=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult($id,[new LifecycleEvent('period',$id,'created',null,'planned',
                    ['start_on'=>$input['start_on'],'end_on'=>$input['end_on']])]);
            });
    }
    public function activate(AuthenticatedActor $actor,int|string $id):string {return $this->transition($actor,$id,'planned','active');}
    public function close(AuthenticatedActor $actor,int|string $id):string {return $this->transition($actor,$id,'active','closed');}
    private function transition(AuthenticatedActor $actor,int|string $id,string $from,string $to):string
    {
        $id=AcademicLockSet::id($id);
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function()use($id,$to){
                $ids=[$id];if($to==='active'){$ids=array_merge($ids,$this->db->table('academic_periods')->where('state','active')->pluck('id')->all());}
                return new AcademicLockSet(['academic_periods'=>$ids]);
            },function($context)use($id,$from,$to,$actor){
                FoundationCommandChecks::director($this->db,$context,$actor);
                $row=$context->row('academic_periods',$id);
                if($row['state']!==$from){throw new AcademicCommandFailure('invalid_transition');}
                new DeclaredDateRange($row['start_on'],$row['end_on']);
                if($to==='active' && $this->db->table('academic_periods')->where('state','active')->where('id','<>',$id)->exists()){
                    throw new AcademicCommandFailure('active_period_conflict');
                }return true;
            },function()use($id,$from,$to){
                $this->db->table('academic_periods')->where('id',$id)->update(['state'=>$to]);
                return new AcademicMutationResult($id,[new LifecycleEvent('period',$id,$to==='active'?'activated':'closed',$from,$to)]);
            });
    }
    public function current(AuthenticatedActor $actor):?array
    {
        $this->authorizeRead($actor);
        $row=$this->db->table('academic_periods')->where('state','active')->first();
        return $row?$this->view($row):null;
    }
    public function historical(AuthenticatedActor $actor,int|string $id):?array
    {
        $this->authorizeRead($actor);
        $row=$this->db->table('academic_periods')->where('id',AcademicLockSet::id($id))->first();
        return $row?$this->view($row):null;
    }
    private function authorizeRead(AuthenticatedActor $actor):void
    {
        FoundationCommandChecks::credentials($this->db,$actor);
        if(!$this->db->table('retained_identities as i')->join('local_role_grants as r','r.identity_id','=','i.id')
            ->where('i.id',$actor->identityId)->where('i.credential_status','active')->where('r.role','director_admin')->exists()){
            throw new AcademicCommandFailure('forbidden');
        }
    }
    private function view(object $row):array
    {return ['id'=>(string)$row->id,'name'=>$row->name,'start_on'=>$row->start_on,'end_on'=>$row->end_on,'state'=>$row->state];}
}
