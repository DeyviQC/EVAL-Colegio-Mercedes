<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Database\Connection;

/** Internal scoped query. Occupied intervals include history, not only rows currently labeled active. */
final readonly class EnrollmentOverlapQuery
{
    public function __construct(private Connection $db){}
    public function history(int|string $period,int|string $student)
    {
        return $this->db->table('student_enrollments')->where('academic_period_id',AcademicLockSet::id($period))
            ->where('student_id',AcademicLockSet::id($student));
    }
    public function conflictingIds(int|string $period,int|string $student,int $start,?int $end=null,int|string|null $except=null):array
    {
        if($start<1 || ($end!==null && $end<=$start)){throw new \InvalidArgumentException('Invalid operational interval.');}
        $query=$this->history($period,$student)->where(function($query)use($start){
            $query->whereNull('operational_end_key')->orWhere('operational_end_key','>',$start);
        });
        if($end!==null){$query->where('operational_start_key','<',$end);}
        if($except!==null){$query->where('id','<>',AcademicLockSet::id($except));}
        return array_map('strval',$query->orderBy('id')->pluck('id')->all());
    }
}
