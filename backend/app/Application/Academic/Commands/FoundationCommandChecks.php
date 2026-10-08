<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use App\Application\Academic\AcademicCommandFailure;
use App\Domain\Academic\AcademicNameKey;
use App\Infrastructure\Persistence\Academic\LockedAcademicContext;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;

final class FoundationCommandChecks
{
    public static function director(Connection $db,LockedAcademicContext $context,AuthenticatedActor $actor):void
    {
        self::credentials($db,$actor);
        if(!$db->table('local_role_grants')->where('identity_id',$context->actorId)->where('role','director_admin')->exists()){
            throw new AcademicCommandFailure('forbidden');
        }
    }
    public static function credentials(Connection $db,AuthenticatedActor $actor):void
    {
        $password=$db->table('local_credentials')->where('id',$actor->identityId)->value('password');
        if(!is_string($password) || $actor->credentialRevision()===null
            || !hash_equals($actor->credentialRevision(),\App\Infrastructure\Authentication\CredentialRevision::current($db,$actor->identityId,$password))){throw new AcademicCommandFailure('forbidden');}
    }
    public static function fields(array $input,array $allowed,array $required=[]):void
    {
        if(array_diff(array_keys($input),$allowed) || array_diff($required,array_keys($input))){throw new AcademicCommandFailure('invalid_input');}
    }
    public static function name(mixed $label):array
    {
        if(!is_string($label)){throw new AcademicCommandFailure('invalid_name');}
        try{$key=AcademicNameKey::generate($label);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_name');}
        if($key==='' || strlen($key)>2048 || mb_strlen($label,'UTF-8')>255){throw new AcademicCommandFailure('invalid_name');}
        return ['name'=>$label,'name_key'=>$key];
    }
}
