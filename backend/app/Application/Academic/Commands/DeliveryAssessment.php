<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult,LifecycleEvent};
final class DeliveryAssessment {
 public function __construct(private Connection $db){}
 private function roles(AuthenticatedActor $actor):array {
  FoundationCommandChecks::credentials($this->db,$actor);
  if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active')throw new AcademicCommandFailure('forbidden');
  return $this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();
 }
 private function delivery(string $id):array {
  $r=$this->db->table('activity_deliveries as d')->join('submission_references as s','s.id','=','d.submission_id')->join('teaching_assignments as a','a.id','=','s.teaching_assignment_id')->join('academic_periods as p','p.id','=','a.academic_period_id')->where('d.submission_id',AcademicLockSet::id($id))->first(['d.*','s.teaching_assignment_id','a.teacher_id','a.academic_period_id','p.state as period_state']);
  if(!$r)throw new AcademicCommandFailure('not_found');return (array)$r;
 }
 private function authorize(AuthenticatedActor $actor,array $r):array {
  $roles=$this->roles($actor);
  if(!((in_array('teacher',$roles,true)&&(string)$r['teacher_id']===$actor->identityId)||(in_array('student',$roles,true)&&(string)$r['student_id']===$actor->identityId)))throw new AcademicCommandFailure('not_found');return $roles;
 }
 private function view(array $r):array {return ['id'=>(string)$r['id'],'version_id'=>(string)$r['version_id'],'grade'=>$r['grade'],'feedback'=>$r['feedback'],'recorded_at'=>$r['recorded_at']];}
 public function projection(AuthenticatedActor $actor,string $id):array {
  $r=$this->delivery($id);$roles=$this->authorize($actor,$r);$current=null;if(!$this->db->getSchemaBuilder()->hasTable('delivery_assessments'))return ['assessment'=>null,'can_assess'=>false];
  if($r['current_assessment_id']!==null){$row=$this->db->table('delivery_assessments')->where('id',$r['current_assessment_id'])->where('submission_id',$id)->first();if(!$row)throw new AcademicCommandFailure('reference_unavailable');$current=$this->view((array)$row);}
  return ['assessment'=>$current,'can_assess'=>$r['period_state']==='active'&&(string)$r['teacher_id']===$actor->identityId&&in_array('teacher',$roles,true)];
 }
 public function history(AuthenticatedActor $actor,string $id,?string $after):array {
  $this->authorize($actor,$this->delivery($id));$q=$this->db->table('delivery_assessments')->where('submission_id',$id);if($after!==null)$q->where('id','>',AcademicLockSet::id($after));$rows=$q->orderBy('id')->limit(51)->get();$items=$rows->take(50)->map(fn($r)=>$this->view((array)$r))->values()->all();return ['items'=>$items,'next_after'=>$rows->count()>50?end($items)['id']:null];
 }
 public function assess(AuthenticatedActor $actor,string $id,array $input):array {
  $this->roles($actor);$id=AcademicLockSet::id($id);FoundationCommandChecks::fields($input,['grade','feedback','expected_version_id','expected_assessment_id'],['grade','feedback','expected_version_id','expected_assessment_id']);
  if(!is_string($input['grade'])||!in_array($input['grade'],['AD','A','B','C'],true)||!is_string($input['feedback'])||!mb_check_encoding($input['feedback'],'UTF-8')||trim($input['feedback'])===''||mb_strlen($input['feedback'])>5000||!is_string($input['expected_version_id'])||($input['expected_assessment_id']!==null&&!is_string($input['expected_assessment_id'])))throw new AcademicCommandFailure('invalid_input');
  $expectedVersion=AcademicLockSet::id($input['expected_version_id']);$expectedAssessment=$input['expected_assessment_id']===null?null:AcademicLockSet::id($input['expected_assessment_id']);$delivery=null;
  return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
   function()use($id){$r=$this->delivery($id);return new AcademicLockSet(['academic_periods'=>[$r['academic_period_id']],'retained_identities'=>[$r['teacher_id'],$r['student_id']],'teaching_assignments'=>[$r['teaching_assignment_id']],'activity_references'=>[$r['activity_id']],'submission_references'=>[$id]]);},
   function($context)use($actor,$id,$expectedVersion,$expectedAssessment,&$delivery){$roles=$this->roles($actor);$delivery=$this->delivery($id);$a=$context->row('teaching_assignments',$delivery['teaching_assignment_id']);if(!in_array('teacher',$roles,true)||(string)$a['teacher_id']!==$actor->identityId)throw new AcademicCommandFailure('forbidden');if($context->row('academic_periods',$a['academic_period_id'])['state']!=='active')throw new AcademicCommandFailure('period_not_active');if((string)$delivery['current_version_id']!==$expectedVersion)throw new AcademicCommandFailure('stale_delivery_version');$current=$delivery['current_assessment_id']===null?null:(string)$delivery['current_assessment_id'];if($current!==$expectedAssessment)throw new AcademicCommandFailure('stale_assessment');return true;},
   function($context,$boundary)use($actor,$id,$input,&$delivery){$revision=(string)$this->db->table('delivery_assessments')->insertGetId(['submission_id'=>$id,'version_id'=>$delivery['current_version_id'],'teacher_id'=>$actor->identityId,'grade'=>$input['grade'],'feedback'=>$input['feedback'],'operation_key'=>$boundary->toServerKey(),'recorded_at'=>$boundary->timestamp->format('Y-m-d H:i:s.u')]);$this->db->table('activity_deliveries')->where('submission_id',$id)->update(['current_assessment_id'=>$revision]);$event=$delivery['current_assessment_id']===null?'delivery_assessed':'assessment_corrected';(new AcademicNotifications($this->db))->emit($event,[(string)$delivery['student_id']],null,$id,$boundary->toServerKey(),$boundary->timestamp->format('Y-m-d H:i:s.u'));return new AcademicMutationResult(['id'=>$id,'assessment_id'=>$revision],[new LifecycleEvent('assignment',(string)$delivery['teaching_assignment_id'],$delivery['current_assessment_id']===null?'delivery_assessed':'assessment_corrected',null,null,['resource_id'=>$id])]);});
 }
}
