<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
use App\Infrastructure\Persistence\Academic\AcademicWriteTransaction;
use App\Infrastructure\Persistence\Academic\AcademicMutationResult;
use App\Infrastructure\Persistence\Academic\LifecycleEvent;
final class CreateActivityReference
{
    public function __construct(private Connection $db){}
    public function execute(AuthenticatedActor $actor,array $input):string
    {
        FoundationCommandChecks::fields($input,['assignment_id'],['assignment_id']);
        try{$id=AcademicLockSet::id($input['assignment_id']);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
        return (new AcademicWriteTransaction($this->db))->execute($actor->identityId,
            function($db,$actorId,$context)use($id){
                $row=$context?$context->row('teaching_assignments',$id):(array)$this->db->table('teaching_assignments')->where('id',$id)->first();
                if(!$row){return new AcademicLockSet(['teaching_assignments'=>[$id]]);}
                return new AcademicLockSet(['academic_periods'=>[$row['academic_period_id']],'teachers'=>[$row['teacher_id']], 'teaching_assignments'=>[$id]]);
            },function($context)use($actor,$id){
                FoundationCommandChecks::credentials($this->db,$actor);$row=$context->row('teaching_assignments',$id);
                if((string)$row['teacher_id']!==$actor->identityId || !$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','teacher')->exists()){
                    throw new AcademicCommandFailure('forbidden');
                }
                if($context->row('academic_periods',$row['academic_period_id'])['state']!=='active'){throw new AcademicCommandFailure('period_not_active');}
                if($row['state']!=='active'){throw new AcademicCommandFailure('invalid_transition');}return true;
            },function()use($id){
                $this->db->table('activity_references')->insert(['teaching_assignment_id'=>$id]);
                $activity=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                // Evidence targets the original assignment; no new activity lifecycle is introduced.
                return new AcademicMutationResult($activity,[new LifecycleEvent('assignment',$id,'activity_reference_created','active','active',['activity_reference_id'=>$activity])]);
            });
    }
}
