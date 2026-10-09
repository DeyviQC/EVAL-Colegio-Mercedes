<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
final class AcademicNavigationQuery
{
    public function __construct(private Connection $db){}
    public function get(AuthenticatedActor $actor):array
    {
        FoundationCommandChecks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active'){
            throw new AcademicCommandFailure('actor_inactive');
        }
        $roles=$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();
        $map=['periods'=>['director_admin'],'catalog'=>['director_admin'],'enrollments'=>['director_admin'],
            'assignments'=>['director_admin','vice_principal'],'my_assignments'=>['teacher'],'my_enrollments'=>['student']];
        return ['sections'=>array_keys(array_filter($map,static fn($required)=>count(array_intersect($roles,$required))>0))];
    }
}
