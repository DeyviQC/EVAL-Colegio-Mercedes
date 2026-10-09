<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Illuminate\Database\Connection;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\CatalogReferenceValidation;
use App\Domain\Academic\DeclaredDateRange;
use App\Domain\Academic\TransferTiming;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\EnrollmentOverlapQuery;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
final class TransferStudent
{
    public function __construct(private Connection $db){}
    public function execute(AuthenticatedActor $actor,int|string $id,array $destination):array
    {
        FoundationCommandChecks::fields($destination,['grade_id','section_id'],['grade_id','section_id']);
        try{
            $id=AcademicLockSet::id($id);
            $grade=AcademicLockSet::id($destination['grade_id']);$section=AcademicLockSet::id($destination['section_id']);
        }catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
        $history=new EnrollmentOverlapQuery($this->db);$timing=new TransferTiming();
        $result=(new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function($db,$actorId,$context)use($id,$grade,$section,$history){
                $row=$context?$context->row('student_enrollments',$id):(array)$this->db->table('student_enrollments')->where('id',$id)->first();
                if(!$row){return new AcademicLockSet(['student_enrollments'=>[$id]]);}
                return new AcademicLockSet([
                    'academic_periods'=>[$row['academic_period_id']],'grades'=>[$row['grade_id'],$grade],
                    'sections'=>[$row['section_id'],$section],'students'=>[$row['student_id']],
                    'student_enrollments'=>$history->history($row['academic_period_id'],$row['student_id'])->pluck('id')->all(),
                ]);
            },function($context,$key)use($actor,$id,$grade,$section,$history){
                FoundationCommandChecks::director($this->db,$context,$actor);$row=$context->row('student_enrollments',$id);
                if($row['state']!=='active'){throw new AcademicCommandFailure('invalid_transition');}
                if($context->row('academic_periods',$row['academic_period_id'])['state']!=='active'){
                    throw new AcademicCommandFailure('period_not_active');
                }
                if((string)$row['grade_id']===$grade && (string)$row['section_id']===$section){throw new AcademicCommandFailure('scope_unchanged');}
                CatalogReferenceValidation::assertActive($context,$grade,$section);
                $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Lima')))->format('Y-m-d');
                $this->dateOrder($row['effective_from'],$today);
                if($key<=(int)$row['operational_start_key']){throw new AcademicCommandFailure('invalid_interval');}
                if($history->conflictingIds($row['academic_period_id'],$row['student_id'],$key,null,$id)){
                    throw new AcademicCommandFailure('overlapping_enrollment');
                }return true;
            },function($context,$boundary)use($id,$grade,$section){
                $prior=$context->row('student_enrollments',$id);$date=$boundary->schoolLocalDate();
                $this->dateOrder($prior['effective_from'],$date);
                $this->db->table('student_enrollments')->where('id',$id)->update([
                    'state'=>'transferred','effective_until'=>$date,'operational_end_key'=>$boundary->toServerKey(),
                ]);
                $this->db->table('student_enrollments')->insert([
                    'student_id'=>$prior['student_id'],'academic_period_id'=>$prior['academic_period_id'],
                    'grade_id'=>$grade,'section_id'=>$section,'state'=>'active','effective_from'=>$date,
                    'operational_start_key'=>$boundary->toServerKey(),
                ]);
                $successor=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult(['successor_id'=>$successor,'boundary'=>$boundary],[
                    new LifecycleEvent('enrollment',$id,'transferred','active','transferred',
                        ['successor_id'=>$successor,'previous_declared_end'=>$prior['effective_until']],$date),
                    new LifecycleEvent('enrollment',$successor,'created',null,'active',['predecessor_id'=>$id],$date),
                ]);
            });
        // U3 returns only after commit; uncertain outcomes throw before exposing a successor/confirmation.
        $confirmed=$timing->confirmedEffectiveBoundary($result['boundary']);
        return ['prior_id'=>$id,'successor_id'=>$result['successor_id'],
            'operation_key'=>$confirmed->toServerKey(),'effective_on'=>$confirmed->schoolLocalDate()];
    }
    private function dateOrder(string $from,string $until):void
    {try{new DeclaredDateRange($from,$until);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_interval');}}
}
