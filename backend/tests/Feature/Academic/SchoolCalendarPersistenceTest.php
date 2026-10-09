<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\EnrollmentTestCase;
use App\Application\Academic\Commands\SchoolCalendarCommands;
final class SchoolCalendarPersistenceTest extends EnrollmentTestCase
{
 protected const DATABASE='eval_u11_test';
 private SchoolCalendarCommands $calendars;
 protected function setUp():void {
  parent::setUp();
  if(!$this->migration->getSchemaBuilder()->hasTable('school_calendar_drafts')){
   $migration=require dirname(__DIR__,3).'/database/migrations/2026_10_08_000018_create_school_calendars.php';$migration->up($this->migration);
  }
  // Persistence semantics use migration credentials; runtime grants remain separate.
  $this->calendars=new SchoolCalendarCommands($this->migration);
 }
 private function blocks():array {return [['kind'=>'teaching','start_on'=>'2026-03-16','end_on'=>'2026-03-20','weeks'=>[['number'=>1,'start_on'=>'2026-03-16','end_on'=>'2026-03-20']]]];}
 private function draft():array {return $this->calendars->create($this->actor,$this->period,['blocks'=>$this->blocks()]);}
 private function publish(array $draft):array {return $this->calendars->publish($this->actor,$draft['id'],['expected_version'=>$draft['version'],'source'=>'Synthetic PAT 2026','confirmed'=>true]);}
 public function testPublishedRevisionsRemainRetainedAndDoNotChangePeriod():void {
  $draft=$this->draft();$this->assertSame(1,$draft['version']);$this->assertNull($this->calendars->current($this->actor,$this->period));
  $published=$this->publish($draft);$next=$this->publish($this->draft());
  $this->assertNotSame($published['id'],$next['id']);$this->assertSame($next,$this->calendars->current($this->actor,$this->period));
  $this->assertSame($published,$this->calendars->revision($this->actor,$published['id']));
  $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/',$published['blocks'][0]['weeks'][0]['id']);
  $this->assertSame('active',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
 }
 public function testStaleEditsAndUnconfirmedPublicationDoNotCommit():void {
  $draft=$this->draft();$updated=$this->calendars->edit($this->actor,$draft['id'],['expected_version'=>1,'blocks'=>$this->blocks()]);$this->assertSame(2,$updated['version']);
  $this->denied('stale_calendar_version',fn()=>$this->calendars->edit($this->actor,$draft['id'],['expected_version'=>1,'blocks'=>$this->blocks()]));
  $this->denied('invalid_input',fn()=>$this->calendars->publish($this->actor,$draft['id'],['expected_version'=>2,'source'=>'PAT','confirmed'=>false]));
  $this->assertNull($this->calendars->current($this->actor,$this->period));
 }
 public function testClosedPeriodRetainsReadsButDeniesWrites():void {
  $revision=$this->publish($this->draft());$this->periods->close($this->actor,$this->period);
  $this->denied('calendar_period_closed',fn()=>$this->draft());$this->assertSame($revision,$this->calendars->current($this->actor,$this->period));
 }
 public function testOtherRolesCannotReadOrWriteDrafts():void {
  $draft=$this->draft();foreach(['teacher','student','vice_principal'] as $role){$other=$this->actor($role);
   $this->denied('forbidden',fn()=>$this->calendars->create($other,$this->period,['blocks'=>$this->blocks()]));
   $this->denied('forbidden',fn()=>$this->calendars->draft($other,$draft['id']));
  }
 }
 public function testPublishedDraftAndStoredRevisionCannotBeRewritten():void {
  $draft=$this->draft();$revision=$this->publish($draft);
  $this->denied('calendar_already_published',fn()=>$this->calendars->edit($this->actor,$draft['id'],['expected_version'=>2,'blocks'=>$this->blocks()]));
  $this->expectException(\Illuminate\Database\QueryException::class);
  $this->migration->table('school_calendar_revisions')->where('id',$revision['id'])->update(['source'=>'rewrite']);
 }
 public function testCompetingDraftPublicationDoesNotReplaceNewerCalendar():void {
  $first=$this->draft();$second=$this->draft();$revision=$this->publish($first);
  $this->denied('stale_calendar_publication',fn()=>$this->publish($second));
  $this->assertSame($revision,$this->calendars->current($this->actor,$this->period));
 }
 public function testHttpContractRejectsMalformedInputsAndUnauthorizedRoles():void {
  $controller=new \App\Http\Controllers\Academic\SchoolCalendarController($this->migration);
  $url='https://eval.test/academic/periods/'.$this->period.'/calendar-drafts';
  $response=$controller->handle(\Symfony\Component\HttpFoundation\Request::create($url,'POST',[],[],[],[],json_encode(['blocks'=>$this->blocks()])),$this->actor);
  $this->assertSame(201,$response->getStatusCode());$this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
  $draft=json_decode($response->getContent(),true)['data'];
  $this->assertSame(422,$controller->handle(\Symfony\Component\HttpFoundation\Request::create($url,'POST',[],[],[],[],'[]'),$this->actor)->getStatusCode());
  $this->assertSame(403,$controller->handle(\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/calendar-drafts/'.$draft['id']),$this->actor('student'))->getStatusCode());
  $this->assertSame(422,$controller->handle(\Symfony\Component\HttpFoundation\Request::create($url.'?actor_id=1','POST',[],[],[],[],'{}'),$this->actor)->getStatusCode());
  $listed=$controller->handle(\Symfony\Component\HttpFoundation\Request::create($url),$this->actor);
  $this->assertSame($draft['id'],json_decode($listed->getContent(),true)['data']['items'][0]['id']);
 }
 public function testInvalidPlanAndExtraAuthorityFieldsAllocateNothing():void {
  $blocks=$this->blocks();$blocks[0]['weeks'][0]['end_on']='2026-03-21';
  $this->denied('invalid_interval',fn()=>$this->calendars->create($this->actor,$this->period,['blocks'=>$blocks]));
  $this->denied('invalid_input',fn()=>$this->calendars->create($this->actor,$this->period,['blocks'=>$this->blocks(),'actor_id'=>'1']));
  $this->assertSame([],$this->calendars->drafts($this->actor,$this->period)['items']);
 }
}
