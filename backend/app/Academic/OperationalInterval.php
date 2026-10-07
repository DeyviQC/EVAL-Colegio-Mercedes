<?php

namespace App\Academic;

class OperationalInterval
{
    public static function overlaps(int $start, ?int $end, int $otherStart, ?int $otherEnd): bool
    {
        return ($otherEnd === null || $start < $otherEnd) && ($end === null || $otherStart < $end);
    }
}
