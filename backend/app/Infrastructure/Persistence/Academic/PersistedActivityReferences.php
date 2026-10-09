<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Database\Connection;
use App\Application\Academic\Authorization\AcademicReferenceReader;
/** U10 read binding; submissions remain unavailable until explicitly approved U11. */
final class PersistedActivityReferences implements AcademicReferenceReader
{
    public function __construct(private Connection $db){}
    public function activity(string $id):?array
    {$row=$this->db->table('activity_references')->where('id',AcademicLockSet::id($id))->first(['id','teaching_assignment_id']);
        return $row?['id'=>(string)$row->id,'teaching_assignment_id'=>(string)$row->teaching_assignment_id]:null;}
    public function submission(string $id):?array{return null;}
}
