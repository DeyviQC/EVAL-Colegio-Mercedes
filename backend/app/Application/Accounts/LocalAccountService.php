<?php
declare(strict_types=1);
namespace App\Application\Accounts;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\FoundationCommandChecks as Checks;
use App\Infrastructure\Persistence\Academic\{AcademicWriteTransaction,AcademicLockSet,AcademicMutationResult};

final class LocalAccountService {
    public function __construct(private Connection $db){}
    private function authority(AuthenticatedActor $actor,bool $director):void {
        Checks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active')throw new AcademicCommandFailure('actor_inactive');
        if($director&&!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','director_admin')->exists())throw new AcademicCommandFailure('forbidden');
    }
    public function own(AuthenticatedActor $actor):array {
        $this->authority($actor,false);$profile=$this->db->table('retained_identity_profiles')->where('identity_id',$actor->identityId)->value('display_name');
        return ['id'=>$actor->identityId,'name'=>$profile??'Cuenta institucional','username'=>$this->db->table('local_credentials')->where('id',$actor->identityId)->value('login'),
            'can_manage'=>$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','director_admin')->exists()];
    }
    public function directory(AuthenticatedActor $actor,array $query):array {
        $this->authority($actor,true);Checks::fields($query,['after','username']);$after=$query['after']??null;
        if($after!==null){try{$after=AcademicLockSet::id($after);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}}
        if(isset($query['username'])&&(!is_string($query['username'])||strlen($query['username'])>64))throw new AcademicCommandFailure('invalid_input');
        $rows=$this->db->table('retained_identities as i')->join('local_credentials as c','c.id','=','i.id')->join('retained_identity_profiles as p','p.identity_id','=','i.id')
            ->join('local_role_grants as r','r.identity_id','=','i.id')->whereIn('r.role',['teacher','student'])
            ->whereNotExists(function($q){$q->selectRaw('1')->from('local_role_grants as extra')->whereColumn('extra.identity_id','i.id')->whereNotIn('extra.role',['teacher','student']);});
        if($after!==null)$rows->where('i.id','>',$after);if(isset($query['username'])&&$query['username']!=='')$rows->where('c.login',strtolower(trim($query['username'])));
        $rows=$rows->orderBy('i.id')->limit(51)->get(['i.id','p.display_name','c.login','r.role','i.credential_status']);
        $more=$rows->count()>50;$rows=$rows->take(50);
        $items=$rows->map(fn($row)=>['id'=>(string)$row->id,'name'=>$row->display_name,'username'=>$row->login,'role'=>$row->role,'active'=>$row->credential_status==='active'])->values()->all();
        return ['items'=>$items,'next_after'=>$more?end($items)['id']:null];
    }
    public function execute(AuthenticatedActor $actor,string $operation,?string $target,array $input):array {
        $self=$operation==='password_changed';$create=$operation==='created';$secret=null;$hash=null;$name=null;$username=null;$role=null;
        if($create){
            Checks::fields($input,['name','username','role'],['name','username','role']);
            if(!is_string($input['name'])||!mb_check_encoding($input['name'],'UTF-8')||trim($input['name'])===''||mb_strlen($input['name'])>255||!is_string($input['username'])||!in_array($input['role'],['teacher','student'],true))throw new AcademicCommandFailure('invalid_input');
            $name=trim($input['name']);$username=strtolower(trim($input['username']));$role=$input['role'];if(!preg_match('/^[a-z][a-z0-9._-]{2,63}$/D',$username))throw new AcademicCommandFailure('invalid_input');
        }elseif($self){
            Checks::fields($input,['current_password','password'],['current_password','password']);
            if(!is_string($input['current_password'])||strlen($input['current_password'])>72||!is_string($input['password'])||!mb_check_encoding($input['password'],'UTF-8')||strlen($input['password'])<12||strlen($input['password'])>72)throw new AcademicCommandFailure('invalid_input');
            $target=$actor->identityId;$hash=password_hash($input['password'],PASSWORD_BCRYPT,['cost'=>12]);
        }else{Checks::fields($input,[]);if(!in_array($operation,['password_reset','deactivated','reactivated'],true))throw new AcademicCommandFailure('invalid_input');}
        if($create||$operation==='password_reset'){$alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#';$secret='';for($i=0;$i<20;$i++)$secret.=$alphabet[random_int(0,strlen($alphabet)-1)];$hash=password_hash($secret,PASSWORD_BCRYPT,['cost'=>12]);}
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            fn()=>new AcademicLockSet($target===null?[]:['retained_identities'=>[$target]]),
            function()use($actor,$self,$create,$target,$input,$username){
                $this->authority($actor,!$self);
                if($create&&$this->db->table('local_credentials')->where('login',$username)->exists())throw new AcademicCommandFailure('username_unavailable');
                if(!$create&&!$self){$roles=$this->db->table('local_role_grants')->where('identity_id',$target)->pluck('role')->all();if(count($roles)!==1||!in_array($roles[0],['teacher','student'],true))throw new AcademicCommandFailure('forbidden');}
                if($self&&!password_verify($input['current_password'],$this->db->table('local_credentials')->where('id',$target)->value('password')??''))throw new AcademicCommandFailure('invalid_current_password');
                if(!$create&&!$this->db->table('local_credentials')->where('id',$target)->exists())throw new AcademicCommandFailure('not_found');
                return true;
            },function($context,$boundary)use($actor,$operation,$create,$target,$name,$username,$role,$hash,$secret){
                if($create){$target=(string)$this->db->table('retained_identities')->insertGetId(['credential_status'=>'active']);$this->db->table('retained_identity_profiles')->insert(['identity_id'=>$target,'display_name'=>$name]);$this->db->table('local_credentials')->insert(['id'=>$target,'login'=>$username,'password'=>$hash]);$this->db->table('local_role_grants')->insert(['identity_id'=>$target,'role'=>$role]);}
                elseif($hash!==null)$this->db->table('local_credentials')->where('id',$target)->update(['password'=>$hash]);
                else $this->db->table('retained_identities')->where('id',$target)->update(['credential_status'=>$operation==='reactivated'?'active':'deactivated']);
                $this->db->table('local_account_events')->insert(['actor_id'=>$actor->identityId,'target_id'=>$target,'operation'=>$operation,'operation_key'=>$boundary->toServerKey(),'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);
                return new AcademicMutationResult(['id'=>$target]+($secret===null?[]:['password'=>$secret]));
            });
    }
}
