<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult};
final class AcademicNotifications {
 public function __construct(private Connection $db){}
 private function roles(AuthenticatedActor $actor):array {
  FoundationCommandChecks::credentials($this->db,$actor);$roles=$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();
  if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'||!array_intersect($roles,['director_admin','vice_principal','teacher','student']))throw new AcademicCommandFailure('forbidden');return $roles;
 }
 /** Internal academic mutation callback only. Not a public recipient-selection API. */
 public function emit(string $event,array $recipients,?string $activity,?string $submission,string $key,string $stamp):void {
  if($this->db->transactionLevel()!==1)throw new AcademicCommandFailure('invalid_transition');
  foreach(array_unique(array_map(fn($id)=>AcademicLockSet::id($id),$recipients)) as $recipient)$this->db->table('academic_notifications')->insert(['recipient_id'=>$recipient,'event'=>$event,'activity_id'=>$activity,'submission_id'=>$submission,'operation_key'=>$key,'created_at'=>$stamp]);
 }
 public function publicationRecipients(array $assignment,string $key):array {
  return $this->db->table('student_enrollments as e')->join('retained_identities as i','i.id','=','e.student_id')->join('local_role_grants as r','r.identity_id','=','e.student_id')->where('e.academic_period_id',$assignment['academic_period_id'])->where('e.grade_id',$assignment['grade_id'])->where('e.section_id',$assignment['section_id'])->where('e.state','active')->where('e.operational_start_key','<=',$key)->whereNull('e.operational_end_key')->where('i.credential_status','active')->where('r.role','student')->distinct()->pluck('e.student_id')->all();
 }
 public function feed(AuthenticatedActor $actor,?string $before=null):array {
  if($before!==null)AcademicLockSet::id($before);if($this->db->transactionLevel()!==0)throw new AcademicCommandFailure('invalid_input');
  $this->db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
  return $this->db->transaction(function()use($actor,$before){$roles=$this->roles($actor);$own=$this->db->table('academic_notifications')->where('recipient_id',$actor->identityId);$latest=(clone $own)->max('id');$unread=(clone $own)->whereNull('read_at')->count();if($unread>9007199254740991)throw new AcademicCommandFailure('reference_unavailable');if($before!==null)$own->where('id','<',$before);$rows=$own->orderByDesc('id')->limit(51)->get(['id','event','created_at','read_at']);
   $items=$rows->take(50)->map(function($r)use($roles){$message=match($r->event){'activity_published'=>'Hay una nueva actividad disponible.','delivery_accepted'=>'Has recibido una nueva entrega.','delivery_updated'=>'Una entrega recibida fue actualizada.','delivery_assessed'=>'Hay una calificación disponible.','assessment_corrected'=>'Una calificación fue actualizada.'};$section=match($r->event){'activity_published'=>in_array('student',$roles,true)?'activities':null,'delivery_accepted','delivery_updated'=>in_array('teacher',$roles,true)?'deliveries':null,default=>in_array('student',$roles,true)?'grades':null};return ['id'=>(string)$r->id,'event'=>$r->event,'message'=>$message,'created_at'=>$r->created_at,'read_at'=>$r->read_at,'section'=>$section];})->values()->all();
   return ['unread'=>$unread,'latest_id'=>$latest===null?null:(string)$latest,'items'=>$items,'next_before'=>$rows->count()>50?end($items)['id']:null];
  },1);
 }
 public function markRead(AuthenticatedActor $actor,array $input):array {
  $this->roles($actor);FoundationCommandChecks::fields($input,['up_to_id'],['up_to_id']);if(!is_string($input['up_to_id']))throw new AcademicCommandFailure('invalid_input');$id=AcademicLockSet::id($input['up_to_id']);
  return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   fn()=>new AcademicLockSet(),
   function()use($actor,$id){$this->roles($actor);if(!$this->db->table('academic_notifications')->where('recipient_id',$actor->identityId)->where('id',$id)->exists())throw new AcademicCommandFailure('not_found');return true;},
   function($context,$boundary)use($actor,$id){$this->db->table('academic_notifications')->where('recipient_id',$actor->identityId)->where('id','<=',$id)->whereNull('read_at')->update(['read_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);return new AcademicMutationResult(['up_to_id'=>$id],[]);});
 }
}
