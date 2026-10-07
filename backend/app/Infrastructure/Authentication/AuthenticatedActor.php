<?php
declare(strict_types=1);
namespace App\Infrastructure\Authentication;

/** Server-resolved facts; academic commands must still authorize their operation. */
final readonly class AuthenticatedActor
{
    public function __construct(public string $identityId,public array $roles) {}
}
