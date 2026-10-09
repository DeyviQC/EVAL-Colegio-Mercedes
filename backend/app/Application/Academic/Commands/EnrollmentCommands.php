<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Connection;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\CatalogReferenceValidation;
use App\Domain\Academic\DeclaredDateRange;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
use App\Infrastructure\Persistence\Academic\EnrollmentOverlapQuery;
final class EnrollmentCommands
{
    public function __construct(private Connection $db){}
    public function create(AuthenticatedActor $actor,array $input):string
    {
        $required=['student_id','academic_period_id','grade_id','section_id','effective_from'];
        FoundationCommandChecks::fields($input,array_merge($required,['effective_until']),$required);
        foreach(['student_id','academic_period_id','grade_id','section_id'] as $column){$input[$column]=$this->id($input[$column]);}
        $input['effective_until']=$input['effective_until']??null;
        $this->dates($input['effective_from'],$input['effective_until']);
        $history=new EnrollmentOverlapQuery($this->db);
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            fn()=>new AcademicLockSet([
                'academic_periods'=>[$input['academic_period_id']],'grades'=>[$input['grade_id']],'sections'=>[$input['section_id']],
                'students'=>[$input['student_id']],
                'student_enrollments'=>$history->history($input['academic_period_id'],$input['student_id'])->pluck('id')->all(),
            ]),function($context,$key)use($actor,$input,$history){
                FoundationCommandChecks::director($this->db,$context,$actor);
                if($context->row('academic_periods',$input['academic_period_id'])['state']!=='active'){
                    throw new AcademicCommandFailure('period_not_active');
                }
                CatalogReferenceValidation::assertActive($context,$input['grade_id'],$input['section_id']);
                if($history->conflictingIds($input['academic_period_id'],$input['student_id'],$key)){
                    throw new AcademicCommandFailure('overlapping_enrollment');
                }return true;
            },function($context,$boundary)use($input){
                $this->db->table('student_enrollments')->insert($input+['state'=>'active','operational_start_key'=>$boundary->toServerKey()]);
                $id=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult($id,[new LifecycleEvent('enrollment',$id,'created',null,'active',
                    ['effective_from'=>$input['effective_from'],'effective_until'=>$input['effective_until']],$input['effective_from'])]);
            });
    }
    public function close(AuthenticatedActor $actor,int|string $id,string $end):string
    {
        $id=$this->id($id);$this->dates($end,null);$history=new EnrollmentOverlapQuery($this->db);
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function($db,$actorId,$context)use($id,$history){
                $row=$context?$context->row('student_enrollments',$id):(array)$this->db->table('student_enrollments')->where('id',$id)->first();
                if(!$row){return new AcademicLockSet(['student_enrollments'=>[$id]]);}
                return new AcademicLockSet([
                    'academic_periods'=>[$row['academic_period_id']],'grades'=>[$row['grade_id']],'sections'=>[$row['section_id']],
                    'students'=>[$row['student_id']],
                    'student_enrollments'=>$history->history($row['academic_period_id'],$row['student_id'])->pluck('id')->all(),
                ]);
            },function($context,$key)use($id,$end,$actor){
                FoundationCommandChecks::director($this->db,$context,$actor);$row=$context->row('student_enrollments',$id);
                if($row['state']!=='active'){throw new AcademicCommandFailure('invalid_transition');}
                $this->dates($row['effective_from'],$end);
                $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Lima')))->format('Y-m-d');
                if($end>$today || $key<=(int)$row['operational_start_key']){throw new AcademicCommandFailure('invalid_interval');}
                // No active-parent/catalog/subject-credential predicate: valid residual cleanup is expressly permitted.
                return true;
            },function($context,$boundary)use($id,$end){
                $row=$context->row('student_enrollments',$id);
                $this->db->table('student_enrollments')->where('id',$id)->update([
                    'state'=>'closed','effective_until'=>$end,'operational_end_key'=>$boundary->toServerKey(),
                ]);
                return new AcademicMutationResult($id,[new LifecycleEvent('enrollment',$id,'closed','active','closed',
                    ['previous_declared_end'=>$row['effective_until'],'declared_end'=>$end],$end)]);
            });
    }
    private function id(mixed $id):string
    {try{return AcademicLockSet::id($id);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}}
    private function dates(mixed $start,mixed $end):void
    {
        try{
            if(!is_string($start) || ($end!==null && !is_string($end))){throw new \InvalidArgumentException();}
            new DeclaredDateRange($start,$end);
        }catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_interval');}
    }
}
