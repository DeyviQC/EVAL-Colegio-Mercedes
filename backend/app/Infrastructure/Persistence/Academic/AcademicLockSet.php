<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Database\Connection;
use InvalidArgumentException;

final readonly class AcademicLockSet
{
    public const RANKS = ['academic_periods','instructional_entries','grades','sections',
        'retained_identities','student_enrollments','teaching_assignments','activity_references','submission_references'];
    private array $rows;

    public function __construct(array $rows = [])
    {
        $union=[];
        foreach ($rows as $table=>$ids) {
            $table=in_array($table,['actors','students','teachers'],true) ? 'retained_identities' : $table;
            if (!in_array($table,self::RANKS,true) || !is_array($ids)) {
                throw new InvalidArgumentException('Unknown lock table or invalid ID set.');
            }
            foreach ($ids as $id) { $union[$table][]=self::id($id); }
        }
        $ordered=[];
        foreach (self::RANKS as $table) {
            if (empty($union[$table])) { continue; }
            $ids=array_values(array_unique($union[$table]));
            usort($ids,fn (string $a,string $b)=>strlen($a)<=>strlen($b) ?: strcmp($a,$b));
            $ordered[$table]=$ids;
        }
        $this->rows=$ordered;
    }

    public function ordered(): array { return $this->rows; }

    public static function id(mixed $id): string
    {
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/',(string)$id)) {
            throw new InvalidArgumentException('Invalid positive decimal BIGINT ID.');
        }
        $id=(string)$id;
        if (strlen($id)>20 || (strlen($id)===20 && strcmp($id,'18446744073709551615')>0)) {
            throw new InvalidArgumentException('ID exceeds unsigned BIGINT.');
        }
        return $id;
    }

    public function withActor(int|string $actorId): self
    {
        $rows=$this->rows;
        $rows['retained_identities'][]=self::id($actorId);
        return new self($rows);
    }

    public function acquire(Connection $connection): array
    {
        $records=[];
        foreach ($this->rows as $table=>$ids) {
            $rows=$connection->table($table)->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            if ($rows->count()!==count($ids)) {
                throw new AcademicTransactionFailure('missing_lock_target');
            }
            foreach ($rows as $row) { $records[$table][(string)$row->id]=(array)$row; }
        }
        return $records;
    }
}
