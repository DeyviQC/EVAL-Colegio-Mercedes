<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult,LifecycleEvent,LocalMaterialStorage,MaterialFileValidator};
final class MaterialFiles {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 public static function at(Connection $db,array $base,?string $revision):array {
  if($revision===null||!$db->getSchemaBuilder()->hasTable('material_file_versions'))return $base;
  $selected=$db->table('material_revisions')->where('id',$revision)->where('material_id',$base['id'])->first();if(!$selected)throw new AcademicCommandFailure('not_found');
  $f=$db->table('material_file_versions as f')->join('material_revisions as r','r.id','=','f.revision_id')->where('f.material_id',$base['id'])->where('r.operation_key','<=',$selected->operation_key)->orderByDesc('r.operation_key')->first('f.*');
  return $f?array_replace($base,array_intersect_key((array)$f,array_flip(['filename','mime','bytes','sha256','storage_key']))):$base;
 }
 public function replace(AuthenticatedActor $actor,string $id,array $input,string $source,string $name):array {
  $id=AcademicLockSet::id($id);FoundationCommandChecks::fields($input,['expected_revision_id'],['expected_revision_id']);$expected=$input['expected_revision_id'];if($expected!==null){if(!is_string($expected))throw new AcademicCommandFailure('invalid_input');$expected=AcademicLockSet::id($expected);}
  $maintenance=new MaterialMaintenance($this->db);$base=(new CourseMaterials($this->db,$this->storage))->retained($actor,$id);if(!$maintenance->canManage($actor,$base))throw new AcademicCommandFailure('forbidden');
  $meta=(new MaterialFileValidator)->validate($source,$name);[$key,$lock]=$this->storage->finalize($source);$meta+=['filename'=>$name,'storage_key'=>$key];
  try{$this->storage->verify($meta);return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   function()use($base){$a=$this->db->table('teaching_assignments')->where('id',$base['assignment_id'])->first();return new AcademicLockSet(['academic_periods'=>[(string)$a->academic_period_id],'retained_identities'=>[(string)$base['author_id']],'teaching_assignments'=>[(string)$a->id]]);},
   function()use($actor,$maintenance,$base,$expected,$meta){if(!$maintenance->canManage($actor,$base))throw new AcademicCommandFailure('forbidden');$this->db->table('material_states')->where('material_id',$base['id'])->lockForUpdate()->first();$current=$maintenance->current((string)$base['id']);$revision=$current?(string)$current['id']:null;if($revision!==$expected)throw new AcademicCommandFailure('stale_material_revision');if(($current['state']??'active')!=='active')throw new AcademicCommandFailure('invalid_transition');$visible=self::at($this->db,$base,$revision);$this->storage->verify($visible);if(hash_equals($visible['sha256'],$meta['sha256']))throw new AcademicCommandFailure('no_change');return true;},
   function($context,$boundary)use($actor,$maintenance,$base,$meta){$current=$maintenance->current((string)$base['id']);$revision=(string)$this->db->table('material_revisions')->insertGetId(['material_id'=>$base['id'],'actor_id'=>$actor->identityId,'previous_revision_id'=>$current?(string)$current['id']:null,'operation'=>'file_replaced','state'=>'active','title'=>$current['title']??$base['title'],'description'=>$current?$current['description']:$base['description'],'operation_key'=>$boundary->toServerKey(),'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);$this->db->table('material_file_versions')->insert($meta+['material_id'=>$base['id'],'revision_id'=>$revision]);if($current)$this->db->table('material_states')->where('material_id',$base['id'])->update(['current_revision_id'=>$revision]);else $this->db->table('material_states')->insert(['material_id'=>$base['id'],'current_revision_id'=>$revision]);return new AcademicMutationResult(['id'=>(string)$base['id'],'revision_id'=>$revision],[new LifecycleEvent('assignment',(string)$base['assignment_id'],'material_file_replaced','active','active',['material_id'=>(string)$base['id'],'revision_id'=>$revision])]);});
  }finally{$this->storage->release($key,$lock);}
  // Failed/uncertain candidates are retained for guarded reconciliation; no possibly referenced bytes are deleted.
 }
 public function file(AuthenticatedActor $a,string $id,?string $revision,bool $supervision=false):array {
  $courses=new CourseMaterials($this->db,$this->storage);$base=$supervision?(new MaterialObservations($this->db,$this->storage))->material($a,$id):$courses->retained($a,$id);
  $roles=$this->db->table('local_role_grants')->where('identity_id',$a->identityId)->pluck('role')->all();if($supervision&&!in_array('vice_principal',$roles,true))throw new AcademicCommandFailure('not_found');
  if(in_array('student',$roles,true)&&!in_array('teacher',$roles,true)){
   $visible=$courses->material($a,$id);$requested=self::at($this->db,$base,$revision);if(!hash_equals($visible['storage_key'],$requested['storage_key']))throw new AcademicCommandFailure('not_found');$base=$visible;
  }else $base=self::at($this->db,$base,$revision);
  return [$this->storage->verify($base),$base];
 }
 public function page(AuthenticatedActor $a,string $id,?string $after,bool $supervision=false):array {
  $base=$supervision?(new MaterialObservations($this->db,$this->storage))->material($a,$id):(new CourseMaterials($this->db,$this->storage))->retained($a,$id);$roles=$this->db->table('local_role_grants')->where('identity_id',$a->identityId)->pluck('role')->all();if(!in_array($supervision?'vice_principal':'teacher',$roles,true))throw new AcademicCommandFailure('not_found');
  $q=$this->db->table('material_file_versions as f')->join('material_revisions as r','r.id','=','f.revision_id')->where('f.material_id',$id);if($after!==null)$q->where('f.revision_id','>',AcademicLockSet::id($after));$rows=$q->orderBy('f.revision_id')->limit(51)->get(['f.filename','f.mime','f.bytes','f.revision_id','r.recorded_at']);$items=[];foreach($rows->take(50) as $r)$items[]=['revision_id'=>(string)$r->revision_id,'filename'=>$r->filename,'mime'=>$r->mime,'bytes'=>(int)$r->bytes,'recorded_at'=>$r->recorded_at];return ['id'=>(string)$base['id'],'original'=>['filename'=>$base['filename'],'mime'=>$base['mime'],'bytes'=>(int)$base['bytes'],'recorded_at'=>$base['published_at']],'items'=>$items,'next_after'=>$rows->count()>50?end($items)['revision_id']:null];
 }
}
