<?php
declare(strict_types=1);
namespace App\Policies\Academic;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
final class AcademicPolicy
{
    public function __construct(private AcademicAuthorization $authorization){}
    public function authorize(?AuthenticatedActor $actor,string $operation,array $target=[],bool $read=false):void
    {
        if(!$actor){throw new AcademicCommandFailure('unauthenticated');}
        if(!$this->authorization->allows($actor,$operation,$target)){throw new AcademicCommandFailure($read?'not_found':'forbidden');}
    }
}
