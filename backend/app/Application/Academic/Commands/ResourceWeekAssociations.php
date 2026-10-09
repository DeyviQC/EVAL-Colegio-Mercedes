<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult,LifecycleEvent,LocalMaterialStorage};

/** Dedicated organization commands never publish or mutate the underlying resource. */
final class ResourceWeekAssociations {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 private function kind(string $kind):void {if(!in_array($kind,['material','activity'],true))throw new AcademicCommandFailure('invalid_input');}
 private function resource(AuthenticatedActor $actor,string $kind,string $id):array {
  $this->kind($kind);$id=AcademicLockSet::id($id);
  if($kind==='material'){$row=(new CourseMaterials($this->db,$this->storage))->material($actor,$id);$assignment=$row['assignment_id'];}
  else{$row=(new ActivityDelivery($this->db,$this->storage))->activity($actor,$id);$assignment=$row['teaching_assignment_id'];}
  $a=$this->db->table('teaching_assignments')->where('id',$assignment)->first();if(!$a)throw new AcademicCommandFailure('not_found');
  return ['row'=>$row,'assignment'=>(array)$a];
 }
 private function change(string $kind,string $id):?array {
  $row=$this->db->table($kind.'_week_states as s')->join($kind.'_week_changes as c','c.id','=','s.change_id')->where('s.resource_id',$id)->first(['c.*']);
  return $row===null?null:(array)$row;
 }
 private function projection(string $kind,string $id,array $a,?array $change):array {
  $revision=$change===null||$change['calendar_revision_id']===null?null:(string)$change['calendar_revision_id'];
  $current=$this->db->table('school_calendar_states')->where('period_id',$a['academic_period_id'])->value('revision_id');
  return ['kind'=>$kind,'resource_id'=>$id,'assignment_id'=>(string)$a['id'],'period_id'=>(string)$a['academic_period_id'],
   'version'=>$change===null?0:(int)$change['version'],'calendar_revision_id'=>$revision,'week_id'=>$change['week_id']??null,
   'earlier_calendar'=>$revision!==null&&$revision!==($current===null?null:(string)$current)];
 }
 public function consult(AuthenticatedActor $actor,string $kind,string $id):array {
  $resource=$this->resource($actor,$kind,$id);return $this->projection($kind,$id,$resource['assignment'],$this->change($kind,$id));
 }
 public function history(AuthenticatedActor $actor,string $kind,string $id,?string $after=null):array {
  $resource=$this->resource($actor,$kind,$id);$query=$this->db->table($kind.'_week_changes')->where('resource_id',$id)->orderBy('id');
  if($after!==null)$query->where('id','>',AcademicLockSet::id($after));$rows=$query->limit(51)->get();$items=[];
  foreach($rows->take(50) as $row)$items[]=$this->projection($kind,$id,$resource['assignment'],(array)$row)+['id'=>(string)$row->id,'recorded_at'=>$row->recorded_at];
  return ['items'=>$items,'next_after'=>$rows->count()>50?end($items)['id']:null];
 }
 public function set(AuthenticatedActor $actor,string $kind,string $id,array $input):array {
  $this->kind($kind);$id=AcademicLockSet::id($id);FoundationCommandChecks::fields($input,['expected_version','calendar_revision_id','week_id'],['expected_version','calendar_revision_id','week_id']);
  if(!is_int($input['expected_version'])||$input['expected_version']<0||$input['expected_version']>=2147483647)throw new AcademicCommandFailure('invalid_input');
  $clear=$input['calendar_revision_id']===null&&$input['week_id']===null;
  if(!$clear){if(!is_string($input['calendar_revision_id'])||!is_string($input['week_id'])||!preg_match('/^[a-f0-9]{32}$/D',$input['week_id']))throw new AcademicCommandFailure('invalid_input');AcademicLockSet::id($input['calendar_revision_id']);}
  $resource=$this->resource($actor,$kind,$id);$a=$resource['assignment'];
  return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   fn()=>new AcademicLockSet(['academic_periods'=>[$a['academic_period_id']],'retained_identities'=>[$a['teacher_id']],'teaching_assignments'=>[$a['id']]]),
   function($context)use($actor,$kind,$id,$input,$clear,$a){
    FoundationCommandChecks::credentials($this->db,$actor);$fresh=$context->row('teaching_assignments',$a['id']);
    if((string)$fresh['teacher_id']!==$actor->identityId||!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','teacher')->exists())throw new AcademicCommandFailure('forbidden');
    if($fresh['state']!=='active'||$context->row('academic_periods',$fresh['academic_period_id'])['state']!=='active')throw new AcademicCommandFailure('historical_read_only');
    $this->resource($actor,$kind,$id);$change=$this->change($kind,$id);
    if(($change===null?0:(int)$change['version'])!==$input['expected_version'])throw new AcademicCommandFailure('stale_week_association');
    if(!$clear){
     $current=$this->db->table('school_calendar_states')->where('period_id',$fresh['academic_period_id'])->value('revision_id');
     if($current===null)throw new AcademicCommandFailure('calendar_not_configured');
     if((string)$current!==$input['calendar_revision_id'])throw new AcademicCommandFailure('stale_calendar_publication');
     $revision=$this->db->table('school_calendar_revisions')->where('id',$current)->where('period_id',$fresh['academic_period_id'])->first();if(!$revision)throw new AcademicCommandFailure('reference_unavailable');
     $found=false;foreach(json_decode($revision->blocks,true,flags:JSON_THROW_ON_ERROR) as $block)foreach($block['weeks'] as $week)if($week['id']===$input['week_id'])$found=true;
     if(!$found)throw new AcademicCommandFailure('invalid_input');
    }
    return true;
   },
   function($context,$boundary)use($actor,$kind,$id,$input,$a){
    $change=(string)$this->db->table($kind.'_week_changes')->insertGetId(['resource_id'=>$id,'period_id'=>$a['academic_period_id'],'actor_id'=>$actor->identityId,
     'calendar_revision_id'=>$input['calendar_revision_id'],'week_id'=>$input['week_id'],'version'=>$input['expected_version']+1,
     'operation_key'=>$boundary->toServerKey(),'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);
    if($this->change($kind,$id)===null)$this->db->table($kind.'_week_states')->insert(['resource_id'=>$id,'change_id'=>$change]);
    else $this->db->table($kind.'_week_states')->where('resource_id',$id)->update(['change_id'=>$change]);
    return new AcademicMutationResult($this->consult($actor,$kind,$id),[new LifecycleEvent('assignment',(string)$a['id'],$kind.'_week_changed',null,null,['resource_id'=>$id,'association_version'=>$input['expected_version']+1])]);
   });
 }
}
