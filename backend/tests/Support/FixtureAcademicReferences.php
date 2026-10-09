<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Application\Academic\Authorization\AcademicReferenceReader;
/** Trusted synthetic references only; no HTTP/client construction or persisted integration claim. */
final class FixtureAcademicReferences implements AcademicReferenceReader
{
    public function __construct(private array $activities=[],private array $submissions=[]){}
    public function activity(string $id):?array {return $this->activities[$id]??null;}
    public function submission(string $id):?array {return $this->submissions[$id]??null;}
}
