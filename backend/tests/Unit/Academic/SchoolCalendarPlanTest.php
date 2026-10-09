<?php
declare(strict_types=1);
namespace Tests\Unit\Academic;

use App\Domain\Academic\DeclaredDateRange;
use App\Domain\Academic\SchoolCalendarPlan;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchoolCalendarPlanTest extends TestCase
{
    private static function plan(): array
    {
        return [
            ['kind'=>'management','start_on'=>'2026-03-02','end_on'=>'2026-03-13','weeks'=>[]],
            ['kind'=>'teaching','start_on'=>'2026-03-16','end_on'=>'2026-03-27','weeks'=>[
                ['number'=>1,'start_on'=>'2026-03-16','end_on'=>'2026-03-20'],
                ['number'=>2,'start_on'=>'2026-03-23','end_on'=>'2026-03-27'],
            ]],
            ['kind'=>'management','start_on'=>'2026-03-30','end_on'=>'2026-04-03','weeks'=>[]],
            ['kind'=>'teaching','start_on'=>'2026-04-06','end_on'=>'2026-04-08','weeks'=>[
                ['number'=>3,'start_on'=>'2026-04-06','end_on'=>'2026-04-08'],
            ]],
        ];
    }
    public function testAcceptsManagementGapsAndShortInstitutionalWeeks(): void
    {
        $input=self::plan();
        $plan=new SchoolCalendarPlan(new DeclaredDateRange('2026-03-01','2026-12-31'),$input);
        $this->assertSame($input,$plan->blocks);
        $this->assertSame(3,$plan->teachingWeekCount());
        $this->assertSame($input,self::plan());
    }
    public static function invalidPlans(): iterable
    {
        $cases=[];
        $p=self::plan();$p[0]['kind']='holiday';$cases['unknown block type']=$p;
        $p=self::plan();$p[0]['weeks']=[['number'=>1,'start_on'=>'2026-03-02','end_on'=>'2026-03-06']];$cases['management cannot contain teaching weeks']=$p;
        $p=self::plan();$p[1]['weeks']=[];$cases['teaching block requires weeks']=$p;
        $p=self::plan();$p[0]['start_on']='2026-02-28';$cases['outside parent period']=$p;
        $p=self::plan();$p[1]['start_on']='2026-03-13';$cases['inclusive block overlap']=$p;
        $p=self::plan();$p[3]['weeks'][0]['number']=2;$cases['duplicate week number']=$p;
        $p=self::plan();$p[3]['weeks'][0]['number']=4;$cases['skipped week number']=$p;
        $p=self::plan();$p[1]['weeks'][1]['start_on']='2026-03-20';$cases['inclusive week overlap']=$p;
        $p=self::plan();$p[1]['weeks'][0]['start_on']='2026-03-15';$cases['week outside block']=$p;
        $p=self::plan();$p[1]['weeks'][0]['end_on']='2026-03-14';$cases['inverted dates']=$p;
        $p=self::plan();$p[1]['weeks'][0]['start_on']='2026-02-30';$cases['invalid calendar date']=$p;
        $p=self::plan();$p[1]['weeks'][0]['number']='1';$cases['week number must be integer']=$p;
        $p=self::plan();$p[0]['actor_id']='1';$cases['unexpected authority field']=$p;
        $p=self::plan();unset($p[1]['weeks'][0]['end_on']);$cases['missing week date']=$p;
        $p=self::plan();$p[0]['start_on']=null;$cases['date must be string']=$p;
        $cases['empty plan']=[];
        $cases['blocks must be a list']=['arbitrary'=>self::plan()[0]];
        $cases['management only']=[self::plan()[0]];
        $p=self::plan();$p[1]['weeks'][0]['end_on']='2026-03-28';$cases['week exceeds containing block']=$p;
        $p=self::plan();$p[1]['weeks']['extra']=$p[1]['weeks'][0];$cases['weeks must be list']=$p;
        foreach($cases as $name=>$plan){yield $name=>[$plan];}
    }
    #[DataProvider('invalidPlans')]
    public function testRejectsInvalidPlan(array $blocks): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SchoolCalendarPlan(new DeclaredDateRange('2026-03-01','2026-12-31'),$blocks);
    }
    public function testRejectsParentWithoutEndDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SchoolCalendarPlan(new DeclaredDateRange('2026-03-01'),self::plan());
    }
    public function testDatesAreNotLockedTo2026OrThirtySixWeeks(): void
    {
        $blocks=[['kind'=>'teaching','start_on'=>'2028-02-28','end_on'=>'2028-03-03','weeks'=>[
            ['number'=>1,'start_on'=>'2028-02-28','end_on'=>'2028-03-03'],
        ]]];
        $plan=new SchoolCalendarPlan(new DeclaredDateRange('2028-01-01','2028-12-31'),$blocks);
        $this->assertSame(1,$plan->teachingWeekCount());
        $this->assertSame('2028-02-28',$plan->blocks[0]['weeks'][0]['start_on']);
    }

    public function testPlanRetainsItsValidatedSnapshotWhenCallerChangesInput(): void
    {
        $input=self::plan();
        $plan=new SchoolCalendarPlan(new DeclaredDateRange('2026-03-01','2026-12-31'),$input);
        $input[1]['weeks'][0]['number']=99;
        $this->assertSame(1,$plan->blocks[1]['weeks'][0]['number']);
        $this->expectException(\Error::class);
        $plan->blocks[1]['weeks'][0]['number']=99;
    }
}
