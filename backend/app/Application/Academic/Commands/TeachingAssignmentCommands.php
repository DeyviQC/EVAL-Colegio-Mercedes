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
use App\Infrastructure\Persistence\Academic\AssignmentOverlapQuery;
final class TeachingAssignmentCommands
{
    public function __construct(private Connection $db){}
    private const SCOPE=AssignmentOverlapQuery::SCOPE;
    public function create(AuthenticatedActor $actor,array $input):string
    {
        FoundationCommandChecks::fields($input,[...self::SCOPE,'effective_from','effective_until'],[...self::SCOPE,'effective_from']);
        foreach(self::SCOPE as $field){$input[$field]=$this->id($input[$field]);}
        $input['effective_until']=$input['effective_until']??null;$this->range($input);
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            fn()=>$this->locks($input),function($context,$key)use($actor,$input){
                $this->manager($actor);$period=$context->row('academic_periods',$input['academic_period_id']);
                if(!in_array($period['state'],['planned','active'],true)){throw new AcademicCommandFailure('period_closed');}
                CatalogReferenceValidation::assertActive($context,$input['grade_id'],$input['section_id'],$input['instructional_entry_id']);
                $this->conflicts($context,$input,$period,$key,false);return true;
            },function($context,$boundary)use($input){
                $this->db->table('teaching_assignments')->insert($input+['state'=>'planned']);
                $id=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                return new AcademicMutationResult($id,[new LifecycleEvent('assignment',$id,'created',null,'planned',
                    ['effective_from'=>$input['effective_from'],'effective_until'=>$input['effective_until']],$input['effective_from'])]);
            });
    }
    public function activate(AuthenticatedActor $actor,int|string $id):string
    {return $this->transition($actor,$this->id($id),null);}
    public function close(AuthenticatedActor $actor,int|string $id,string $end):string
    {$this->range(['effective_from'=>$end]);return $this->transition($actor,$this->id($id),$end);}
    private function transition(AuthenticatedActor $actor,string $id,?string $end):string
    {
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function($db,$actorId,$context)use($id){
                $row=$context?$context->row('teaching_assignments',$id):(array)$this->db->table('teaching_assignments')->where('id',$id)->first();
                return $row?$this->locks($row):new AcademicLockSet(['teaching_assignments'=>[$id]]);
            },function($context,$key)use($actor,$id,$end){
                $this->manager($actor);$row=$context->row('teaching_assignments',$id);
                $period=$context->row('academic_periods',$row['academic_period_id']);
                if($row['state']!==($end===null?'planned':'active')){throw new AcademicCommandFailure('invalid_transition');}
                if($end===null){
                    if($period['state']!=='active'){throw new AcademicCommandFailure('period_not_active');}
                    CatalogReferenceValidation::assertActive($context,$row['grade_id'],$row['section_id'],$row['instructional_entry_id']);
                    if($row['effective_from']>$this->today()){throw new AcademicCommandFailure('invalid_interval');}
                    $this->conflicts($context,$row,$period,$key,true,$id);
                }else{
                    $this->contained($this->range(['effective_from'=>$row['effective_from'],'effective_until'=>$end]),$period);
                    if($end>$this->today() || $key<=(int)$row['operational_start_key']){throw new AcademicCommandFailure('invalid_interval');}
                }return true;
            },function($context,$boundary)use($id,$end){
                $row=$context->row('teaching_assignments',$id);$state=$end===null?'active':'closed';
                $updates=$end===null?['state'=>$state,'operational_start_key'=>$boundary->toServerKey()]:
                    ['state'=>$state,'effective_until'=>$end,'operational_end_key'=>$boundary->toServerKey()];
                $this->db->table('teaching_assignments')->where('id',$id)->update($updates);
                return new AcademicMutationResult($id,[new LifecycleEvent('assignment',$id,$end===null?'activated':'closed',
                    $row['state'],$state,['previous_declared_end'=>$row['effective_until']],$end)]);
            });
    }
    private function history(array $row):\Illuminate\Database\Query\Builder
    {return (new AssignmentOverlapQuery($this->db))->history($row);}
    private function locks(array $row):AcademicLockSet
    {return new AcademicLockSet(['academic_periods'=>[$row['academic_period_id']],'instructional_entries'=>[$row['instructional_entry_id']],
        'grades'=>[$row['grade_id']],'sections'=>[$row['section_id']],'teachers'=>[$row['teacher_id']],
        'teaching_assignments'=>$this->history($row)->pluck('id')->all()]);}
    private function conflicts($context,array $candidate,array $period,int $key,bool $activation,?string $except=null):void
    {
        $range=$this->range($candidate);$parent=new DeclaredDateRange($period['start_on'],$period['end_on']);$this->contained($range,$period);
        if((new AssignmentOverlapQuery($this->db))->conflicts($context->rows('teaching_assignments'),$range,$parent,$key,$activation,$except)){
            throw new AcademicCommandFailure('overlapping_assignment');
        }
    }
    private function manager(AuthenticatedActor $actor):void
    {FoundationCommandChecks::credentials($this->db,$actor);
        if(!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->whereIn('role',['director_admin','vice_principal'])->exists()){
            throw new AcademicCommandFailure('forbidden');
        }}
    private function range(array $row):DeclaredDateRange
    {try{return new DeclaredDateRange($row['effective_from'],$row['effective_until']??null);}
        catch(\InvalidArgumentException|\TypeError){throw new AcademicCommandFailure('invalid_interval');}}
    private function contained(DeclaredDateRange $range,array $period):void
    {try{$range->assertAssignmentContainedIn(new DeclaredDateRange($period['start_on'],$period['end_on']));}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_interval');}}
    private function id(mixed $id):string
    {try{return AcademicLockSet::id($id);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}}
    private function today():string
    {return (new \DateTimeImmutable('now',new \DateTimeZone('America/Lima')))->format('Y-m-d');}
}
