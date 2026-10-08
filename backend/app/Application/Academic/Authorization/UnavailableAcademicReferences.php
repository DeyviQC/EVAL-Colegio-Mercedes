<?php
declare(strict_types=1);
namespace App\Application\Academic\Authorization;
/** Fail closed until U10/U11 establish persisted references. */
final class UnavailableAcademicReferences implements AcademicReferenceReader
{
    public function activity(string $id):?array {return null;}
    public function submission(string $id):?array {return null;}
}
