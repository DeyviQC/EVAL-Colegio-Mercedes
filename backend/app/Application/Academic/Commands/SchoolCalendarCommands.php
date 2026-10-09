<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use App\Application\Academic\AcademicCommandFailure;
use App\Domain\Academic\{DeclaredDateRange,SchoolCalendarPlan};
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult,LifecycleEvent};
use Illuminate\Database\Connection;

final class SchoolCalendarCommands
{
 public function __construct(private Connection $db){}
 private function authorize(AuthenticatedActor $actor):void {
  FoundationCommandChecks::credentials($this->db,$actor);
  if(!$this->db->table('retained_identities')->where('id',$actor->identityId)->where('credential_status','active')->exists()
   ||!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','director_admin')->exists())throw new AcademicCommandFailure('forbidden');
 }
 private function row(string $table,string $id):array {
  $row=$this->db->table($table)->where('id',$id)->first();if(!$row)throw new AcademicCommandFailure('not_found');return (array)$row;
 }
 private function plan(array $period,mixed $blocks):SchoolCalendarPlan {
  if(!is_array($blocks)||count($blocks)>100)throw new AcademicCommandFailure('invalid_input');
  $weekCount=0;foreach($blocks as $block){if(!is_array($block)||!is_array($block['weeks']??null))throw new AcademicCommandFailure('invalid_input');$weekCount+=count($block['weeks']);}
  if($weekCount>366)throw new AcademicCommandFailure('invalid_input');
  try{return new SchoolCalendarPlan(new DeclaredDateRange($period['start_on'],$period['end_on']),$blocks);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_interval');}
 }
 private function pointer(string $period):?string {$id=$this->db->table('school_calendar_states')->where('period_id',$period)->value('revision_id');return $id===null?null:(string)$id;}
 private function draftView(array $row):array {return ['id'=>(string)$row['id'],'period_id'=>(string)$row['period_id'],'base_revision_id'=>$row['base_revision_id']===null?null:(string)$row['base_revision_id'],'version'=>(int)$row['version'],'state'=>$row['state'],'blocks'=>json_decode($row['blocks'],true,flags:JSON_THROW_ON_ERROR)];}
 private function revisionView(array $row):array {return ['id'=>(string)$row['id'],'period_id'=>(string)$row['period_id'],'source'=>$row['source'],'recorded_at'=>$row['recorded_at'],'blocks'=>json_decode($row['blocks'],true,flags:JSON_THROW_ON_ERROR)];}
 public function draft(AuthenticatedActor $actor,string $id):array {$this->authorize($actor);return $this->draftView($this->row('school_calendar_drafts',AcademicLockSet::id($id)));}
 public function drafts(AuthenticatedActor $actor,string $period,?string $after=null):array {
  $this->authorize($actor);$period=AcademicLockSet::id($period);$this->row('academic_periods',$period);
  $query=$this->db->table('school_calendar_drafts')->where('period_id',$period)->orderBy('id');
  if($after!==null)$query->where('id','>',AcademicLockSet::id($after));
  $rows=$query->limit(51)->get();$items=[];foreach($rows->take(50) as $row)$items[]=$this->draftView((array)$row);
  return ['items'=>$items,'next_after'=>$rows->count()>50?end($items)['id']:null];
 }
 public function revision(AuthenticatedActor $actor,string $id):array {$this->authorize($actor);return $this->revisionView($this->row('school_calendar_revisions',AcademicLockSet::id($id)));}
 public function current(AuthenticatedActor $actor,string $period):?array {
  $this->authorize($actor);$period=AcademicLockSet::id($period);$this->row('academic_periods',$period);$id=$this->pointer($period);return $id===null?null:$this->revision($actor,$id);
 }
 public function create(AuthenticatedActor $actor,string $period,array $input):array {
  FoundationCommandChecks::fields($input,['blocks'],['blocks']);$this->authorize($actor);$period=AcademicLockSet::id($period);
  return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   fn()=>new AcademicLockSet(['academic_periods'=>[$period]]),
   function($context)use($actor,$period,$input){FoundationCommandChecks::director($this->db,$context,$actor);$row=$context->row('academic_periods',$period);if($row['state']==='closed')throw new AcademicCommandFailure('calendar_period_closed');$this->plan($row,$input['blocks']);return true;},
   function()use($actor,$period,$input){$id=(string)$this->db->table('school_calendar_drafts')->insertGetId(['period_id'=>$period,'actor_id'=>$actor->identityId,'base_revision_id'=>$this->pointer($period),'blocks'=>json_encode($input['blocks'],JSON_THROW_ON_ERROR),'version'=>1,'state'=>'draft']);return new AcademicMutationResult($this->draft($actor,$id),[new LifecycleEvent('period',$period,'calendar_draft_created',null,null,['draft_id'=>$id])]);});
 }
 public function edit(AuthenticatedActor $actor,string $id,array $input):array {return $this->change($actor,$id,$input,false);}
 public function publish(AuthenticatedActor $actor,string $id,array $input):array {return $this->change($actor,$id,$input,true);}
 private function change(AuthenticatedActor $actor,string $id,array $input,bool $publish):array {
  $allowed=$publish?['expected_version','source','confirmed']:['expected_version','blocks'];FoundationCommandChecks::fields($input,$allowed,$allowed);$this->authorize($actor);$id=AcademicLockSet::id($id);
  if(!is_int($input['expected_version'])||$input['expected_version']<1||$input['expected_version']>=2147483647)throw new AcademicCommandFailure('invalid_input');
  if($publish&&($input['confirmed']!==true||!is_string($input['source'])||trim($input['source'])===''||mb_strlen($input['source'])>255))throw new AcademicCommandFailure('invalid_input');
  $period=(string)$this->row('school_calendar_drafts',$id)['period_id'];$draft=null;
  return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   fn()=>new AcademicLockSet(['academic_periods'=>[$period]]),
   function($context)use($actor,$period,$id,$input,$publish,&$draft){
    FoundationCommandChecks::director($this->db,$context,$actor);$row=$context->row('academic_periods',$period);
    if($row['state']==='closed')throw new AcademicCommandFailure('calendar_period_closed');
    $draft=(array)$this->db->table('school_calendar_drafts')->where('id',$id)->lockForUpdate()->first();
    if($draft['state']!=='draft')throw new AcademicCommandFailure('calendar_already_published');
    if((int)$draft['version']!==$input['expected_version'])throw new AcademicCommandFailure('stale_calendar_version');
    $blocks=$publish?json_decode($draft['blocks'],true,flags:JSON_THROW_ON_ERROR):$input['blocks'];$this->plan($row,$blocks);
    if($publish&&($draft['base_revision_id']===null?null:(string)$draft['base_revision_id'])!==$this->pointer($period))throw new AcademicCommandFailure('stale_calendar_publication');
    return true;
   },
   function($context,$boundary)use($actor,$period,$id,$input,$publish,&$draft){
    if(!$publish){$this->db->table('school_calendar_drafts')->where('id',$id)->update(['blocks'=>json_encode($input['blocks'],JSON_THROW_ON_ERROR),'version'=>$input['expected_version']+1]);return new AcademicMutationResult($this->draft($actor,$id),[new LifecycleEvent('period',$period,'calendar_draft_edited',null,null,['draft_id'=>$id])]);}
    $blocks=json_decode($draft['blocks'],true,flags:JSON_THROW_ON_ERROR);foreach($blocks as &$block){foreach($block['weeks'] as &$week){$week['id']=bin2hex(random_bytes(16));}unset($week);}unset($block);
    $revision=(string)$this->db->table('school_calendar_revisions')->insertGetId(['period_id'=>$period,'actor_id'=>$actor->identityId,'source'=>$input['source'],'blocks'=>json_encode($blocks,JSON_THROW_ON_ERROR),'operation_key'=>$boundary->toServerKey(),'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);
    if($this->pointer($period)===null)$this->db->table('school_calendar_states')->insert(['period_id'=>$period,'revision_id'=>$revision]);else $this->db->table('school_calendar_states')->where('period_id',$period)->update(['revision_id'=>$revision]);
    $this->db->table('school_calendar_drafts')->where('id',$id)->update(['state'=>'published','version'=>$input['expected_version']+1]);
    return new AcademicMutationResult($this->revision($actor,$revision),[new LifecycleEvent('period',$period,'calendar_published',null,null,['draft_id'=>$id,'revision_id'=>$revision])]);
   });
 }
}
