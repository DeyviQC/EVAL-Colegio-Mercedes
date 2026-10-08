<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
final class MyCoursesQuery {
    public function __construct(private Connection $db,private AcademicIdentityLabels $labels){}
    private function allowed(AuthenticatedActor $actor){
        FoundationCommandChecks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active')throw new AcademicCommandFailure('forbidden');
        $roles=$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();
        $teacher=in_array('teacher',$roles,true);$student=in_array('student',$roles,true);
        if(!$teacher&&!$student)throw new AcademicCommandFailure('forbidden');
        return $this->db->table('teaching_assignments as a')->join('academic_periods as p','p.id','=','a.academic_period_id')
            ->join('instructional_entries as i','i.id','=','a.instructional_entry_id')->join('grades as g','g.id','=','a.grade_id')->join('sections as s','s.id','=','a.section_id')
            ->where(function($query)use($actor,$teacher,$student){
                if($teacher)$query->where('a.teacher_id',$actor->identityId);
                if($student)$query->orWhere(function($scope)use($actor){$scope->where('a.state','active')->where('p.state','active')
                    ->whereExists(function($enrollment)use($actor){$enrollment->selectRaw('1')->from('student_enrollments as e')->where('e.student_id',$actor->identityId)->where('e.state','active')
                        ->whereColumn('e.academic_period_id','a.academic_period_id')->whereColumn('e.grade_id','a.grade_id')->whereColumn('e.section_id','a.section_id');});});
            });
    }
    private function fields():array{return ['a.id','a.teacher_id','a.state','p.name as period_name','p.state as period_state','i.name as subject','i.kind','g.name as grade','s.name as section'];}
    private function project(object $row,AuthenticatedActor $actor):array {
        $teacher=$this->labels->displayName((string)$row->teacher_id);if($teacher===null)throw new AcademicCommandFailure('reference_unavailable');
        return ['id'=>(string)$row->id,'subject'=>$row->subject,'kind'=>$row->kind,'grade'=>$row->grade,'section'=>$row->section,'period'=>$row->period_name,
            'period_state'=>$row->period_state,'state'=>$row->state,'teacher'=>$teacher,
            'can_publish'=>(string)$row->teacher_id===$actor->identityId&&$row->state==='active'&&$row->period_state==='active'
                &&$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','teacher')->exists()];
    }
    public function page(AuthenticatedActor $actor,?string $after):array {
        $query=$this->allowed($actor)->orderBy('a.id');if($after!==null)$query->where('a.id','>',AcademicLockSet::id($after));
        $rows=$query->limit(51)->get($this->fields())->all();$more=count($rows)>50;$rows=array_slice($rows,0,50);
        $items=array_map(fn($row)=>$this->project($row,$actor),$rows);return ['items'=>$items,'next_after'=>$more?$items[49]['id']:null];
    }
    public function detail(AuthenticatedActor $actor,string $id):array {
        $row=$this->allowed($actor)->where('a.id',AcademicLockSet::id($id))->first($this->fields());
        if(!$row)throw new AcademicCommandFailure('not_found');return $this->project($row,$actor);
    }
}
