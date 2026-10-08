<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
final class ClassroomReport {
 public function __construct(private Connection $db){}
 private function authorize(AuthenticatedActor $actor):void {
  FoundationCommandChecks::credentials($this->db,$actor);
  if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'||!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','director_admin')->exists())throw new AcademicCommandFailure('forbidden');
 }
 private function count(mixed $value):int {$value=(int)$value;if($value<0||$value>9007199254740991)throw new AcademicCommandFailure('reference_unavailable');return $value;}
 public function read(AuthenticatedActor $actor,string $period,string $grade,string $section,?string $after=null):array {
  foreach([$period,$grade,$section] as $id)AcademicLockSet::id($id);if($after!==null)AcademicLockSet::id($after);
  if($this->db->transactionLevel()!==0)throw new AcademicCommandFailure('invalid_input');
  // One response has one repeatable, read-only snapshot, including authority and labels.
  $this->db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
  return $this->db->transaction(function()use($actor,$period,$grade,$section,$after){
   $this->authorize($actor);
   $p=$this->db->table('academic_periods')->where('id',$period)->first(['id','name','state']);$g=$this->db->table('grades')->where('id',$grade)->first(['id','name']);$s=$this->db->table('sections')->where('id',$section)->first(['id','name','grade_id']);
   if(!$p||!$g||!$s)throw new AcademicCommandFailure('not_found');if((string)$s->grade_id!==$grade)throw new AcademicCommandFailure('scope_mismatch');
   $work=$this->db->table('activity_deliveries as d')->join('submission_references as r','r.id','=','d.submission_id')->join('teaching_assignments as a','a.id','=','r.teaching_assignment_id')->join('activity_references as ar','ar.id','=','d.activity_id')->join('student_enrollments as e','e.id','=','r.accepted_under_enrollment_id')
    ->leftJoin('delivery_assessments as x',function($join){$join->on('x.id','=','d.current_assessment_id')->on('x.submission_id','=','d.submission_id');})
    ->where('a.academic_period_id',$period)->where('a.grade_id',$grade)->where('a.section_id',$section)
    ->whereColumn('r.student_id','d.student_id')->whereColumn('r.activity_id','d.activity_id')->whereColumn('ar.teaching_assignment_id','a.id')->whereColumn('e.student_id','d.student_id')->whereColumn('e.academic_period_id','a.academic_period_id')->whereColumn('e.grade_id','a.grade_id')->whereColumn('e.section_id','a.section_id')->select(['d.submission_id','d.student_id','x.grade']);
   $total=$this->db->query()->fromSub(clone $work,'w')->selectRaw("COUNT(*) AS received, COALESCE(SUM(grade IS NULL),0) AS pending, COALESCE(SUM(grade IS NOT NULL),0) AS graded, COALESCE(SUM(grade='AD'),0) AS AD, COALESCE(SUM(grade='A'),0) AS A, COALESCE(SUM(grade='B'),0) AS B, COALESCE(SUM(grade='C'),0) AS C")->first();
   $totals=[];foreach(['received','pending','graded','AD','A','B','C'] as $field)$totals[$field]=$this->count($total->$field);
   $current=$this->db->table('student_enrollments')->where('academic_period_id',$period)->where('grade_id',$grade)->where('section_id',$section)->where('state','active')->select('student_id');if($p->state!=='active')$current->whereRaw('1=0');
   $owners=$this->db->query()->fromSub(clone $work,'owned')->select('student_id')->distinct()->union(clone $current);
   $counts=$this->db->query()->fromSub(clone $work,'counted')->select('student_id')->selectRaw('COUNT(*) AS received, COALESCE(SUM(grade IS NULL),0) AS pending, COALESCE(SUM(grade IS NOT NULL),0) AS graded')->groupBy('student_id');
   $rows=$this->db->query()->fromSub($owners,'roster')->join('retained_identity_profiles as profiles','profiles.identity_id','=','roster.student_id')->leftJoinSub($counts,'counts','counts.student_id','=','roster.student_id')->leftJoinSub($current,'current','current.student_id','=','roster.student_id');
   if($after!==null)$rows->where('roster.student_id','>',$after);
   $found=$rows->orderBy('roster.student_id')->limit(51)->get(['roster.student_id','profiles.display_name','counts.received','counts.pending','counts.graded','current.student_id as current_student']);
   $items=$found->take(50)->map(fn($r)=>['id'=>(string)$r->student_id,'name'=>$r->display_name,'historical'=>$r->current_student===null,'received'=>$this->count($r->received??0),'pending'=>$this->count($r->pending??0),'graded'=>$this->count($r->graded??0)])->values()->all();
   return ['scope'=>['period_id'=>$period,'period_name'=>$p->name,'period_state'=>$p->state,'grade_id'=>$grade,'grade_name'=>$g->name,'section_id'=>$section,'section_name'=>$s->name],'totals'=>$totals,'items'=>$items,'next_after'=>$found->count()>50?end($items)['id']:null];
  },1);
 }
}
