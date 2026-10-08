<?php
declare(strict_types=1);
namespace App\Application\Academic\Authorization;
/** Internal server reference source; never bind client payloads to this contract. */
interface AcademicReferenceReader
{
    public function activity(string $id):?array;
    public function submission(string $id):?array;
}
