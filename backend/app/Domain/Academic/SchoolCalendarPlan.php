<?php
declare(strict_types=1);
namespace App\Domain\Academic;

use InvalidArgumentException;

/** Date-only calendar validation; allocates no authority or academic transitions. */
final readonly class SchoolCalendarPlan
{
    public function __construct(DeclaredDateRange $period, public array $blocks)
    {
        if ($period->until === null || !array_is_list($blocks) || $blocks === []) {
            throw new InvalidArgumentException('A bounded period and ordered blocks are required.');
        }
        $previousBlockEnd=null;
        $expectedWeek=1;
        foreach ($blocks as $block) {
            self::fields($block,['kind','start_on','end_on','weeks']);
            if (!in_array($block['kind'],['teaching','management'],true)
                || !is_array($block['weeks']) || !array_is_list($block['weeks'])) {
                throw new InvalidArgumentException('Invalid block type or week list.');
            }
            $range=self::range($block);
            $range->assertAssignmentContainedIn($period);
            if ($previousBlockEnd !== null && $range->from <= $previousBlockEnd) {
                throw new InvalidArgumentException('Blocks must be chronological and cannot overlap.');
            }
            $previousBlockEnd=$range->until;
            if ($block['kind']==='management') {
                if ($block['weeks']!==[]) {
                    throw new InvalidArgumentException('Management blocks cannot contain teaching weeks.');
                }
                continue;
            }
            if ($block['weeks']===[]) {
                throw new InvalidArgumentException('Teaching blocks require teaching weeks.');
            }
            $previousWeekEnd=null;
            foreach ($block['weeks'] as $week) {
                self::fields($week,['number','start_on','end_on']);
                if (!is_int($week['number']) || $week['number']!==$expectedWeek) {
                    throw new InvalidArgumentException('Teaching weeks must be consecutively numbered from one.');
                }
                $weekRange=self::range($week);
                $weekRange->assertAssignmentContainedIn($range);
                if ($previousWeekEnd!==null && $weekRange->from <= $previousWeekEnd) {
                    throw new InvalidArgumentException('Teaching weeks cannot overlap.');
                }
                $previousWeekEnd=$weekRange->until;
                $expectedWeek++;
            }
        }
        if ($expectedWeek===1) {
            throw new InvalidArgumentException('A calendar requires at least one teaching week.');
        }
    }

    public function teachingWeekCount(): int
    {
        return array_sum(array_map(static fn(array $block):int=>count($block['weeks']),$this->blocks));
    }

    private static function fields(mixed $value,array $keys): void
    {
        if (!is_array($value) || array_diff(array_keys($value),$keys) || array_diff($keys,array_keys($value))) {
            throw new InvalidArgumentException('Unexpected or missing calendar fields.');
        }
    }

    private static function range(array $value): DeclaredDateRange
    {
        if (!is_string($value['start_on']) || !is_string($value['end_on'])) {
            throw new InvalidArgumentException('Calendar dates must be strings.');
        }
        return new DeclaredDateRange($value['start_on'],$value['end_on']);
    }
}
