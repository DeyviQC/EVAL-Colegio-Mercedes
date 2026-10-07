<?php

namespace Tests\Unit;

use App\Academic\OperationalInterval;
use PHPUnit\Framework\TestCase;

class OperationalIntervalTest extends TestCase
{
    public function test_touching_boundaries_do_not_overlap(): void
    {
        $this->assertFalse(OperationalInterval::overlaps(1, 2, 2, null));
        $this->assertFalse(OperationalInterval::overlaps(2, null, 1, 2));
    }

    public function test_same_day_order_is_independent_of_calendar_dates(): void
    {
        $this->assertTrue(OperationalInterval::overlaps(1, 4, 2, 3));
        $this->assertTrue(OperationalInterval::overlaps(3, null, 4, null));
        $this->assertFalse(OperationalInterval::overlaps(1, 2, 3, 4));
    }
}
