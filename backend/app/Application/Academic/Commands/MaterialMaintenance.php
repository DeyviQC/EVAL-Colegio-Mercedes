<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult,LifecycleEvent};
final class MaterialMaintenance {
 public function __construct(private Connection $db){}
 private function roles(AuthenticatedActor $actor):array {FoundationCommandChecks::credentials($this->db,$actor);if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active')throw new AcademicCommandFailure('forbidden');return $this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();}
 public function current(string $id):?array {$r=$this->db->table('material_states as s')->join('material_revisions as r','r.id','=','s.current_revision_id')->where('s.material_id',$id)->first('r.*');return $r?(array)$r:null;}
 public function visible(AuthenticatedActor $actor,array $base):?array {
  $roles=$this->roles($actor);$latest=$this->current((string)$base['id']);$resolved=MaterialFiles::at($this->db,$base,$latest?(string)$latest['id']:null);
  if(in_array('teacher',$roles,true))return $latest?array_replace($resolved,['title'=>$latest['title'],'description'=>$latest['description']]):$resolved;
  if(!in_array('student',$roles,true)||($latest['state']??'active')==='withdrawn')return null;
  $a=$this->db->table('teaching_assignments')->where('id',$base['assignment_id'])->first();$p=$this->db->table('academic_periods')->where('id',$a->academic_period_id)->value('state');
  $enrollments=$this->db->table('student_enrollments')->where('student_id',$actor->identityId)->where('academic_period_id',$a->academic_period_id)->where('grade_id',$a->grade_id)->where('section_id',$a->section_id)->get();
  foreach($enrollments as $e)if($p==='active'&&$a->state==='active'&&$e->state==='active')return $latest?array_replace($resolved,['title'=>$latest['title'],'description'=>$latest['description']]):$resolved;
  $historical=$this->db->table('material_revisions')->where('material_id',$base['id'])->where(function($q)use($enrollments){$q->whereRaw('1=0');foreach($enrollments as $e)if($e->operational_start_key!==null)$q->orWhere(function($window)use($e){$window->where('operation_key','>=',$e->operational_start_key);if($e->operational_end_key!==null)$window->where('operation_key','<',$e->operational_end_key);});})->orderByDesc('id')->first();
  return $historical?array_replace(MaterialFiles::at($this->db,$base,(string)$historical->id),['title'=>$historical->title,'description'=>$historical->description]):$base;
 }
 public function canManage(AuthenticatedActor $actor,array $base):bool {
  if(!in_array('teacher',$this->roles($actor),true)||(string)$base['author_id']!==$actor->identityId)return false;
  $a=$this->db->table('teaching_assignments')->where('id',$base['assignment_id'])->first();return $a&&(string)$a->teacher_id===$actor->identityId&&$a->state==='active'&&$this->db->table('academic_periods')->where('id',$a->academic_period_id)->value('state')==='active';
 }
 public function status(AuthenticatedActor $actor,array $base):array {
  if(!in_array('teacher',$this->roles($actor),true))throw new AcademicCommandFailure('not_found');$r=$this->current((string)$base['id']);
  return ['id'=>(string)$base['id'],'revision_id'=>$r?(string)$r['id']:null,'state'=>$r['state']??'active','title'=>$r['title']??$base['title'],'description'=>$r?$r['description']:$base['description'],'can_manage'=>$this->canManage($actor,$base)];
 }
 public function revisions(AuthenticatedActor $actor,array $base,?string $after):array {
  $this->status($actor,$base);$q=$this->db->table('material_revisions')->where('material_id',$base['id']);if($after!==null)$q->where('id','>',AcademicLockSet::id($after));$rows=$q->orderBy('id')->limit(51)->get();$items=[];
  foreach($rows->take(50) as $r)$items[]=['id'=>(string)$r->id,'previous_revision_id'=>$r->previous_revision_id===null?null:(string)$r->previous_revision_id,'operation'=>$r->operation,'state'=>$r->state,'title'=>$r->title,'description'=>$r->description,'recorded_at'=>$r->recorded_at];
  return ['original'=>['title'=>$base['title'],'description'=>$base['description'],'published_at'=>$base['published_at']],'items'=>$items,'next_after'=>$rows->count()>50?end($items)['id']:null];
 }
 public function execute(AuthenticatedActor $actor,string $id,string $operation,array $input):array {
  $this->roles($actor);$id=AcademicLockSet::id($id);if(!in_array($operation,['edited','withdrawn','restored'],true))throw new AcademicCommandFailure('invalid_input');
  FoundationCommandChecks::fields($input,$operation==='edited'?['expected_revision_id','title','description']:['expected_revision_id'],$operation==='edited'?['expected_revision_id','title','description']:['expected_revision_id']);
  $expected=$input['expected_revision_id'];if($expected!==null){if(!is_string($expected))throw new AcademicCommandFailure('invalid_input');$expected=AcademicLockSet::id($expected);}
  if($operation==='edited'&&(!is_string($input['title'])||trim($input['title'])===''||mb_strlen($input['title'])>255||($input['description']!==null&&(!is_string($input['description'])||mb_strlen($input['description'])>10000))))throw new AcademicCommandFailure('invalid_input');
  $base=null;$current=null;
  return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   function()use($id){$m=$this->db->table('course_materials')->where('id',$id)->first();if(!$m)throw new AcademicCommandFailure('not_found');$a=$this->db->table('teaching_assignments')->where('id',$m->assignment_id)->first();return new AcademicLockSet(['academic_periods'=>[(string)$a->academic_period_id],'retained_identities'=>[(string)$m->author_id],'teaching_assignments'=>[(string)$a->id]]);},
   function()use($actor,$id,$expected,$operation,$input,&$base,&$current){$base=(array)$this->db->table('course_materials')->where('id',$id)->first();if(!$this->canManage($actor,$base))throw new AcademicCommandFailure('forbidden');$this->db->table('material_states')->where('material_id',$id)->lockForUpdate()->first();$current=$this->current($id);if(($current?(string)$current['id']:null)!==$expected)throw new AcademicCommandFailure('stale_material_revision');$state=$current['state']??'active';if(($operation==='restored'&&$state!=='withdrawn')||($operation!=='restored'&&$state!=='active'))throw new AcademicCommandFailure('invalid_transition');if($operation==='edited'&&$input['title']===($current['title']??$base['title'])&&$input['description']===($current?$current['description']:$base['description']))throw new AcademicCommandFailure('no_change');return true;},
   function($context,$boundary)use($actor,$id,$operation,$input,&$base,&$current){$state=$operation==='withdrawn'?'withdrawn':'active';$title=$operation==='edited'?$input['title']:($current['title']??$base['title']);$description=$operation==='edited'?$input['description']:($current?$current['description']:$base['description']);$revision=(string)$this->db->table('material_revisions')->insertGetId(['material_id'=>$id,'actor_id'=>$actor->identityId,'previous_revision_id'=>$current?(string)$current['id']:null,'operation'=>$operation,'state'=>$state,'title'=>$title,'description'=>$description,'operation_key'=>$boundary->toServerKey(),'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);if($current)$this->db->table('material_states')->where('material_id',$id)->update(['current_revision_id'=>$revision]);else $this->db->table('material_states')->insert(['material_id'=>$id,'current_revision_id'=>$revision]);return new AcademicMutationResult(['id'=>$id,'revision_id'=>$revision],[new LifecycleEvent('assignment',(string)$base['assignment_id'],'material_'.$operation,'active','active',['material_id'=>$id,'revision_id'=>$revision])]);});
 }
}
