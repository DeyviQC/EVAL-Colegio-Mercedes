<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use App\Domain\Academic\DeclaredDateRange;

/** Calendar reservations are distinct from actual operational intervals. */
final class AssignmentOverlapQuery
{
    public const SCOPE=['teacher_id','academic_period_id','instructional_entry_id','grade_id','section_id'];
    public function __construct(private Connection $db){}
    public function history(array $scope):Builder
    {$query=$this->db->table('teaching_assignments');foreach(self::SCOPE as $field){$query->where($field,$scope[$field]);}return $query;}
    public function conflicts(array $rows,DeclaredDateRange $candidate,DeclaredDateRange $period,int $key,bool $activation,?string $except=null):bool
    {
        foreach($rows as $row){
            if((string)$row['id']===$except){continue;}
            // Past authority is checked using K, never hidden by a state-only filter.
            if($row['operational_end_key']!==null && (int)$row['operational_end_key']<=$key){continue;}
            if($row['operational_end_key']!==null || ($activation && $row['operational_start_key']!==null)){return true;}
            $other=new DeclaredDateRange($row['effective_from'],$row['effective_until']);
            if($candidate->from<$other->planningEndExclusive($period) && $other->from<$candidate->planningEndExclusive($period)){return true;}
        }return false;
    }
}
