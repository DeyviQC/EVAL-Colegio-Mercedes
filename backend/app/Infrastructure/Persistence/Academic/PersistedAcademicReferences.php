<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Database\Connection;
use App\Application\Academic\Authorization\AcademicReferenceReader;
final class PersistedAcademicReferences implements AcademicReferenceReader
{
    public function __construct(private Connection $db){}
    public function activity(string $id):?array{return (new PersistedActivityReferences($this->db))->activity($id);}
    public function submission(string $id):?array
    {$row=$this->db->table('submission_references')->where('id',AcademicLockSet::id($id))->first();if(!$row){return null;}
        $result=(array)$row;foreach($result as $field=>$value){if($field==='id'||str_ends_with($field,'_id')||str_ends_with($field,'_key')){$result[$field]=(string)$value;}}return $result;}
}
