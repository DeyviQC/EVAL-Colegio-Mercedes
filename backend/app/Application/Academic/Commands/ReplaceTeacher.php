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
use App\Infrastructure\Persistence\Academic\AssignmentOverlapQuery;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
final class ReplaceTeacher
{
    public function __construct(private Connection $db){}
    public function execute(AuthenticatedActor $actor,int|string $id,array $destination):array
    {
        FoundationCommandChecks::fields($destination,['teacher_id','effective_on'],['teacher_id']);
        try{$id=AcademicLockSet::id($id);$teacher=AcademicLockSet::id($destination['teacher_id']);}
        catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
        $history=new AssignmentOverlapQuery($this->db);$date=null;
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function($db,$actorId,$context)use($id,$teacher,$history){
                $row=$context?$context->row('teaching_assignments',$id):(array)$this->db->table('teaching_assignments')->where('id',$id)->first();
                if(!$row){return new AcademicLockSet(['teaching_assignments'=>[$id]]);}
                $next=array_replace($row,['teacher_id'=>$teacher]);
                return new AcademicLockSet(['academic_periods'=>[$row['academic_period_id']],
                    'instructional_entries'=>[$row['instructional_entry_id']],'grades'=>[$row['grade_id']],'sections'=>[$row['section_id']],
                    'teachers'=>[$row['teacher_id'],$teacher],
                    'teaching_assignments'=>array_merge($history->history($row)->pluck('id')->all(),$history->history($next)->pluck('id')->all())]);
            },function($context,$key)use($actor,$id,$teacher,$destination,$history,&$date){
                FoundationCommandChecks::credentials($this->db,$actor);
                if(!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->whereIn('role',['director_admin','vice_principal'])->exists()){
                    throw new AcademicCommandFailure('forbidden');
                }
                $row=$context->row('teaching_assignments',$id);$period=$context->row('academic_periods',$row['academic_period_id']);
                if($period['state']!=='active'){throw new AcademicCommandFailure('period_not_active');}
                if($row['state']!=='active'){throw new AcademicCommandFailure('invalid_transition');}
                if((string)$row['teacher_id']===$teacher){throw new AcademicCommandFailure('teacher_unchanged');}
                CatalogReferenceValidation::assertActive($context,$row['grade_id'],$row['section_id'],$row['instructional_entry_id']);
                $date=(new \DateTimeImmutable('now',new \DateTimeZone('America/Lima')))->format('Y-m-d');
                if(array_key_exists('effective_on',$destination) && $destination['effective_on']!==$date){throw new AcademicCommandFailure('invalid_interval');}
                try{
                    $parent=new DeclaredDateRange($period['start_on'],$period['end_on']);
                    (new DeclaredDateRange($row['effective_from'],$date))->assertAssignmentContainedIn($parent);
                    $candidate=new DeclaredDateRange($date);$candidate->assertAssignmentContainedIn($parent);
                }catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_interval');}
                if($key<=(int)$row['operational_start_key']){throw new AcademicCommandFailure('invalid_interval');}
                $targetRows=array_filter($context->rows('teaching_assignments'),fn($record)=>(string)$record['teacher_id']===$teacher);
                if($history->conflicts($targetRows,$candidate,$parent,$key,true)){throw new AcademicCommandFailure('overlapping_assignment');}
                return true;
            },function($context,$boundary)use($id,$teacher,&$date){
                // A midnight rollover must not change the reviewed admission date after validation.
                if($date!==$boundary->schoolLocalDate()){throw new AcademicCommandFailure('date_changed');}
                $prior=$context->row('teaching_assignments',$id);$key=$boundary->toServerKey();
                $this->db->table('teaching_assignments')->where('id',$id)->update(['state'=>'closed','effective_until'=>$date,'operational_end_key'=>$key]);
                $this->db->table('teaching_assignments')->insert([
                    'teacher_id'=>$teacher,'academic_period_id'=>$prior['academic_period_id'],'instructional_entry_id'=>$prior['instructional_entry_id'],
                    'grade_id'=>$prior['grade_id'],'section_id'=>$prior['section_id'],'state'=>'active','effective_from'=>$date,
                    'operational_start_key'=>$key,'replaces_assignment_id'=>$id,
                ]);
                $next=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult(['prior_id'=>$id,'successor_id'=>$next,'operation_key'=>$key,'effective_on'=>$date],[
                    new LifecycleEvent('assignment',$id,'replaced','active','closed',['successor_id'=>$next,'previous_declared_end'=>$prior['effective_until']],$date),
                    new LifecycleEvent('assignment',$next,'created',null,'active',['predecessor_id'=>$id],$date),
                ]);
            });
    }
}
