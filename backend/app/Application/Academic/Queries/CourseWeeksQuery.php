<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\{CourseMaterials,ActivityDelivery,ResourceWeekAssociations};
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,LocalMaterialStorage,LocalIdentityLabels};

/** Resolve the retained assignment on the server; authorize every resource before counting it. */
final class CourseWeeksQuery {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 private function context(AuthenticatedActor $actor,string $course):array {
  $course=AcademicLockSet::id($course);(new MyCoursesQuery($this->db,new LocalIdentityLabels($this->db)))->detail($actor,$course);
  return (array)$this->db->table('teaching_assignments')->where('id',$course)->first();
 }
 private function revision(string $id):array {
  $row=$this->db->table('school_calendar_revisions')->where('id',$id)->first();if(!$row)throw new AcademicCommandFailure('reference_unavailable');
  return ['id'=>(string)$row->id,'period_id'=>(string)$row->period_id,'source'=>$row->source,'recorded_at'=>$row->recorded_at,'blocks'=>json_decode($row->blocks,true,flags:JSON_THROW_ON_ERROR)];
 }
 /** A yielding scan avoids loading the complete course or hidden resources into a response. */
 private function resources(AuthenticatedActor $actor,string $course,string $kind):\Generator {
  $materials=new CourseMaterials($this->db,$this->storage);$activities=new ActivityDelivery($this->db,$this->storage);$associations=new ResourceWeekAssociations($this->db,$this->storage);
  $query=$kind==='material'?$this->db->table('course_materials')->whereIn('assignment_id',array_keys($materials->lineage($course))):$this->db->table('activity_references')->where('teaching_assignment_id',$course);
  $after=null;
  do{$batch=clone $query;if($after!==null)$batch->where('id','>',$after);$rows=$batch->orderBy('id')->limit(200)->get(['id']);
   foreach($rows as $r){$id=(string)$r->id;$after=$id;
    try{$row=$kind==='material'?$materials->material($actor,$id):$activities->activity($actor,$id);$association=$associations->consult($actor,$kind,$id);}
    catch(AcademicCommandFailure $error){if($error->category==='not_found')continue;throw $error;}
    yield ['id'=>$id,'row'=>$row,'association'=>$association];
   }
  }while($rows->count()===200);
 }
 public function calendar(AuthenticatedActor $actor,string $course):array {
  $a=$this->context($actor,$course);$pointer=$this->db->table('school_calendar_states')->where('period_id',$a['academic_period_id'])->value('revision_id');$current=$pointer===null?null:$this->revision((string)$pointer);$retained=[];
  foreach(['material','activity'] as $kind)foreach($this->resources($actor,$course,$kind) as $resource){$id=$resource['association']['calendar_revision_id'];if($id!==null&&$id!==($current['id']??null)&&!isset($retained[$id]))$retained[$id]=$this->revision($id);}
  return ['course_id'=>$course,'period_id'=>(string)$a['academic_period_id'],'current'=>$current,'retained'=>array_values($retained)];
 }
 public function content(AuthenticatedActor $actor,string $course,string $kind,string $bucket,?string $revision,?string $week,?string $after,string $term=''):array {
  $a=$this->context($actor,$course);if(!in_array($kind,['material','activity'],true)||!in_array($bucket,['week','unassigned','earlier'],true))throw new AcademicCommandFailure('invalid_input');
  $term=trim($term);if(mb_strlen($term)>100||preg_match('/[\x00-\x1f\x7f]/u',$term))throw new AcademicCommandFailure('invalid_input');if($after!==null)$after=AcademicLockSet::id($after);
  if($bucket==='week'){
   if($revision===null||$week===null||!preg_match('/^[a-f0-9]{32}$/D',$week))throw new AcademicCommandFailure('invalid_input');$revision=AcademicLockSet::id($revision);
   $calendar=$this->calendar($actor,$course);$allowed=array_filter([$calendar['current'],...$calendar['retained']],fn($r)=>$r!==null&&$r['id']===$revision);if(!$allowed)throw new AcademicCommandFailure('not_found');
   $found=false;foreach(array_values($allowed)[0]['blocks'] as $block)foreach($block['weeks'] as $w)if($w['id']===$week)$found=true;if(!$found)throw new AcademicCommandFailure('not_found');
  }elseif($revision!==null||$week!==null)throw new AcademicCommandFailure('invalid_input');
  $items=[];$total=0;$more=false;$materials=new CourseMaterials($this->db,$this->storage);$activities=new ActivityDelivery($this->db,$this->storage);
  foreach($this->resources($actor,$course,$kind) as $r){$association=$r['association'];$matches=match($bucket){'unassigned'=>$association['week_id']===null,'earlier'=>$association['earlier_calendar'],'week'=>$association['calendar_revision_id']===$revision&&$association['week_id']===$week};
   if(!$matches)continue;$row=$r['row'];$search=$kind==='material'?$row['title'].' '.$row['description'].' '.$row['filename']:$row['title'].' '.$row['instructions'];if($term!==''&&mb_stripos($search,$term)===false)continue;$total++;
   // Canonical decimal BIGINT identities must never be narrowed to PHP integers.
   if($after!==null&&(strlen($r['id'])<=>strlen($after) ?: strcmp($r['id'],$after))<=0)continue;if(count($items)>=50){$more=true;continue;}
   $view=$kind==='material'?$materials->view($row):$activities->view($actor,$row);$items[]=['resource'=>$view,'association'=>$association];
  }
  return ['course_id'=>$course,'kind'=>$kind,'bucket'=>$bucket,'total'=>$total,'items'=>$items,'next_after'=>$more?end($items)['resource']['id']:null];
 }
}
