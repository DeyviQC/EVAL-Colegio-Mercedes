<?php
declare(strict_types=1);
namespace App\Application\Academic\Queries;
/** Server-owned retained identity/profile projection; no client labels or ID-as-name fallback. */
interface AcademicIdentityLabels
{
    public function displayName(string $identityId):?string;
}
