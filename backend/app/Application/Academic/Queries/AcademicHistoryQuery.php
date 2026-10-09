<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
final class AcademicHistoryQuery {
 public function __construct(private Connection $db){}
 public function page(AuthenticatedActor $actor,string $kind,?string $after):array {
  FoundationCommandChecks::credentials($this->db,$actor);
  if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active')throw new AcademicCommandFailure('forbidden');
  $roles=$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();
  if($kind==='enrollments'){
   if(in_array('director_admin',$roles,true))return (new EnrollmentDirectoryQuery($this->db))->page($actor,'enrollments',$after);
   if(!in_array('student',$roles,true))throw new AcademicCommandFailure('forbidden');
   $q=$this->db->table('student_enrollments as e')->join('academic_periods as p','p.id','=','e.academic_period_id')->join('grades as g','g.id','=','e.grade_id')->join('sections as s','s.id','=','e.section_id')->leftJoin('retained_identity_profiles as n','n.identity_id','=','e.student_id')->where('e.student_id',$actor->identityId);
   $id='e.id';$fields=['e.id','e.student_id','n.display_name as student_name','e.academic_period_id','p.name as period_name','e.grade_id','g.name as grade_name','e.section_id','s.name as section_name','e.state','e.effective_from','e.effective_until'];$name='student_name';
  }elseif($kind==='assignments'){
   if(array_intersect($roles,['director_admin','vice_principal']))return (new AssignmentDirectoryQuery($this->db))->page($actor,'assignments',$after);
   if(!in_array('teacher',$roles,true))throw new AcademicCommandFailure('forbidden');
   $q=$this->db->table('teaching_assignments as a')->join('academic_periods as p','p.id','=','a.academic_period_id')->join('instructional_entries as i','i.id','=','a.instructional_entry_id')->join('grades as g','g.id','=','a.grade_id')->join('sections as s','s.id','=','a.section_id')->leftJoin('retained_identity_profiles as n','n.identity_id','=','a.teacher_id')->where('a.teacher_id',$actor->identityId);
   $id='a.id';$fields=['a.id','a.teacher_id','n.display_name as teacher_name','a.academic_period_id','p.name as period_name','p.state as period_state','a.instructional_entry_id','i.name as entry_name','a.grade_id','g.name as grade_name','a.section_id','s.name as section_name','a.state','a.effective_from','a.effective_until','a.replaces_assignment_id'];$name='teacher_name';
  }else throw new AcademicCommandFailure('invalid_input');
  if($after!==null)$q->where($id,'>',AcademicLockSet::id($after));$rows=$q->orderBy($id)->limit(51)->get($fields);$items=[];
  foreach($rows->take(50) as $row){$item=(array)$row;if(!is_string($item[$name])||trim($item[$name])==='')throw new AcademicCommandFailure('reference_unavailable');foreach($item as $k=>$v)if($k==='id'||str_ends_with($k,'_id'))$item[$k]=$v===null?null:(string)$v;$items[]=$item;}
  return ['items'=>$items,'next_after'=>$rows->count()>50?end($items)['id']:null];
 }
}
