<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
use App\Application\Academic\Commands\{ResourceWeekAssociations,SchoolCalendarCommands,ActivityDelivery,CourseMaterials,ReplaceTeacher};
use App\Infrastructure\Persistence\Academic\LocalMaterialStorage;
use Symfony\Component\HttpFoundation\Request;
final class ResourceWeekAssociationsTest extends SubmissionReferenceTestCase {
 private string $directory='';private LocalMaterialStorage $storage;private ResourceWeekAssociations $weeks;private SchoolCalendarCommands $calendars;private array $revision;private string $publishedActivity;private string $material;
 protected function setUp():void {
  parent::setUp();
  // Additional existing education migrations are fixture-only; do not broaden runtime grants.
  $manager=\Illuminate\Database\Eloquent\Model::getConnectionResolver();$default=$manager->getDefaultConnection();
  try{$manager->setDefaultConnection('migration');foreach(['2026_10_08_000012_create_activity_delivery_content.php'=>'activity_contents','2026_10_08_000014_create_academic_notifications.php'=>'academic_notifications'] as $file=>$table){if(!$this->migration->getSchemaBuilder()->hasTable($table))(require dirname(__DIR__,3).'/database/migrations/'.$file)->up();}}
  finally{$manager->setDefaultConnection($default);}
  foreach(['2026_10_08_000018_create_school_calendars.php'=>'school_calendar_drafts','2026_10_08_000019_create_resource_week_associations.php'=>'material_week_changes'] as $file=>$table){
   if(!$this->migration->getSchemaBuilder()->hasTable($table)){(require dirname(__DIR__,3).'/database/migrations/'.$file)->up($this->migration);}
  }
  $this->directory=sys_get_temp_dir().'/eval-week-associations-'.bin2hex(random_bytes(8));mkdir($this->directory);$this->storage=new LocalMaterialStorage($this->directory.'/private');
  $this->migration->table('retained_identity_profiles')->insert(['identity_id'=>$this->teacherActor->identityId,'display_name'=>'Synthetic week teacher']);
  $this->weeks=new ResourceWeekAssociations($this->migration,$this->storage);$this->calendars=new SchoolCalendarCommands($this->migration);$this->revision=$this->publishCalendar();
  $this->publishedActivity=(new ActivityDelivery($this->migration,$this->storage))->write($this->teacherActor,'publish',$this->assignmentId,['title'=>'Synthetic task','instructions'=>'Keep routing unchanged'])['id'];
  $pdf=$this->directory.'/sample.pdf';file_put_contents($pdf,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
  $this->material=(new CourseMaterials($this->db,$this->storage))->publish($this->teacherActor,$this->assignmentId,['title'=>'Synthetic material'],$pdf,'sample.pdf');
 }
 protected function tearDown():void {if($this->directory!=='')(new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->directory);parent::tearDown();}
 private function publishCalendar(?string $period=null):array {
  $draft=$this->calendars->create($this->actor,$period??$this->period,['blocks'=>[['kind'=>'teaching','start_on'=>'2026-03-16','end_on'=>'2026-03-20','weeks'=>[['number'=>1,'start_on'=>'2026-03-16','end_on'=>'2026-03-20']]]]]);
  return $this->calendars->publish($this->actor,$draft['id'],['expected_version'=>1,'source'=>'Synthetic calendar','confirmed'=>true]);
 }
 private function weekInput(int $version=0,?array $revision=null):array {$r=$revision??$this->revision;return ['expected_version'=>$version,'calendar_revision_id'=>$r['id'],'week_id'=>$r['blocks'][0]['weeks'][0]['id']];}
 public function testTypedAssociationsRetainSetClearHistoryAndOriginalContent():void {
  foreach(['material'=>$this->material,'activity'=>$this->publishedActivity] as $kind=>$id){
   $table=$kind==='material'?'course_materials':'activity_contents';$key=$kind==='material'?'id':'activity_id';$before=(array)$this->db->table($table)->where($key,$id)->first();
   $this->assertSame(0,$this->weeks->consult($this->studentActor,$kind,$id)['version']);
   $set=$this->weeks->set($this->teacherActor,$kind,$id,$this->weekInput());$this->assertSame(1,$set['version']);$this->assertFalse($set['earlier_calendar']);
   $cleared=$this->weeks->set($this->teacherActor,$kind,$id,['expected_version'=>1,'calendar_revision_id'=>null,'week_id'=>null]);$this->assertNull($cleared['week_id']);$this->assertSame(2,$cleared['version']);
   $history=$this->weeks->history($this->studentActor,$kind,$id);$this->assertCount(2,$history['items']);$this->assertSame($set['week_id'],$history['items'][0]['week_id']);
   $this->assertSame($before,(array)$this->db->table($table)->where($key,$id)->first());
  }
 }
 public function testStaleEditsAndInvalidWeeksAllocateNothing():void {
  $this->weeks->set($this->teacherActor,'activity',$this->publishedActivity,$this->weekInput());
  $this->denied('stale_week_association',fn()=>$this->weeks->set($this->teacherActor,'activity',$this->publishedActivity,$this->weekInput()));
  $invalid=$this->weekInput(1);$invalid['week_id']=str_repeat('a',32);$this->denied('invalid_input',fn()=>$this->weeks->set($this->teacherActor,'activity',$this->publishedActivity,$invalid));
  $this->assertCount(1,$this->weeks->history($this->teacherActor,'activity',$this->publishedActivity)['items']);
 }
 public function testCalendarCorrectionRetainsEarlierReferencesAndRejectsOldOrOtherPeriod():void {
  $this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput());$next=$this->publishCalendar();
  $this->assertTrue($this->weeks->consult($this->studentActor,'material',$this->material)['earlier_calendar']);
  $this->denied('stale_calendar_publication',fn()=>$this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput(1)));
  $other=$this->periods->create($this->actor,['name'=>'Other '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);$otherRevision=$this->publishCalendar($other);
  $this->denied('stale_calendar_publication',fn()=>$this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput(1,$otherRevision)));
  $this->assertFalse($this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput(1,$next))['earlier_calendar']);
 }
 public function testStudentReadDoesNotGrantWriteAndOutsidersCannotConsult():void {
  $this->denied('forbidden',fn()=>$this->weeks->set($this->studentActor,'material',$this->material,$this->weekInput()));
  foreach(['student','teacher','director_admin','vice_principal'] as $role)$this->denied('not_found',fn()=>$this->weeks->consult($this->actor($role),'activity',$this->publishedActivity));
  $extra=$this->weekInput();$extra['assignment_id']=$this->assignmentId;$this->denied('invalid_input',fn()=>$this->weeks->set($this->teacherActor,'material',$this->material,$extra));
 }
 public function testClosedPeriodIsReadOnly():void {
  $this->weeks->set($this->teacherActor,'activity',$this->publishedActivity,$this->weekInput());$this->periods->close($this->actor,$this->period);
  $this->assertSame(1,$this->weeks->consult($this->studentActor,'activity',$this->publishedActivity)['version']);
  $this->denied('historical_read_only',fn()=>$this->weeks->set($this->teacherActor,'activity',$this->publishedActivity,$this->weekInput(1)));
 }
 public function testReplacementAllowsMaterialReadingButNoOrganizationWrites():void {
  $next=$this->actor('teacher');(new ReplaceTeacher($this->db))->execute($this->actor,$this->assignmentId,['teacher_id'=>$next->identityId]);
  $this->assertSame(0,$this->weeks->consult($next,'material',$this->material)['version']);
  $this->denied('forbidden',fn()=>$this->weeks->set($next,'material',$this->material,$this->weekInput()));
  $this->denied('historical_read_only',fn()=>$this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput()));
 }
 public function testHistoryPaginationAndMissingCalendarAreExplicit():void {
  for($i=0;$i<52;$i++)$this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput($i));
  $first=$this->weeks->history($this->studentActor,'material',$this->material);$this->assertCount(50,$first['items']);$this->assertNotNull($first['next_after']);
  $second=$this->weeks->history($this->studentActor,'material',$this->material,$first['next_after']);$this->assertCount(2,$second['items']);$this->assertNull($second['next_after']);
  // Absence is tested without deleting retained calendar evidence.
  $connection=new class($this->migration->getPdo(),$this->migration->getDatabaseName(),'',$this->migration->getConfig()) extends \Illuminate\Database\MySqlConnection {
   public function table($table,$as=null){$query=parent::table($table,$as);if($table==='school_calendar_states')$query->whereRaw('1=0');return $query;}
  };
  $this->denied('calendar_not_configured',fn()=>(new ResourceWeekAssociations($connection,$this->storage))->set($this->teacherActor,'activity',$this->publishedActivity,$this->weekInput()));
 }
 public function testLostAcknowledgementCanBeConsultedWithoutRepublishing():void {
  $connection=new class($this->migration->getPdo(),$this->migration->getDatabaseName(),'',$this->migration->getConfig()) extends \Illuminate\Database\MySqlConnection {public function commit(){parent::commit();throw new \RuntimeException('Injected lost acknowledgement');}};
  $count=$this->db->table('course_materials')->count();$service=new ResourceWeekAssociations($connection,$this->storage);
  try{$service->set($this->teacherActor,'material',$this->material,$this->weekInput());$this->fail('Expected unknown outcome');}catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){$this->assertSame('commit_outcome_unknown',$error->category);}
  $this->assertSame(1,$this->weeks->consult($this->teacherActor,'material',$this->material)['version']);$this->assertSame($count,$this->db->table('course_materials')->count());
  $this->denied('stale_week_association',fn()=>$this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput()));
 }
 public function testHistoryCannotBeRewritten():void {
  $this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput());$this->expectException(\Illuminate\Database\QueryException::class);
  $this->migration->table('material_week_changes')->where('resource_id',$this->material)->update(['week_id'=>null]);
 }
 public function testDiscoveryCountsOnlyAuthorizedResourcesAndKeepsEarlierAndUnassigned():void {
  $query=new \App\Application\Academic\Queries\CourseWeeksQuery($this->migration,$this->storage);
  $empty=$query->content($this->studentActor,$this->assignmentId,'material','unassigned',null,null,null);$this->assertSame(1,$empty['total']);
  $this->weeks->set($this->teacherActor,'material',$this->material,$this->weekInput());$this->weeks->set($this->teacherActor,'activity',$this->publishedActivity,$this->weekInput());
  $page=$query->content($this->studentActor,$this->assignmentId,'activity','week',$this->revision['id'],$this->weekInput()['week_id'],null);$this->assertSame(1,$page['total']);$this->assertTrue($page['items'][0]['resource']['can_submit']);
  $this->publishCalendar();$calendar=$query->calendar($this->studentActor,$this->assignmentId);$this->assertSame($this->revision['id'],$calendar['retained'][0]['id']);
  $this->assertSame(1,$query->content($this->studentActor,$this->assignmentId,'material','earlier',null,null,null)['total']);
  $this->assertSame(0,$query->content($this->studentActor,$this->assignmentId,'activity','earlier',null,null,null,'absent')['total']);
  $this->denied('not_found',fn()=>$query->calendar($this->actor('student'),$this->assignmentId));
 }
 public function testDiscoveryHasSeparatePaginationAndFullCounts():void {
  for($i=0;$i<51;$i++)(new ActivityDelivery($this->migration,$this->storage))->write($this->teacherActor,'publish',$this->assignmentId,['title'=>'Page '.$i,'instructions'=>'Synthetic']);
  $query=new \App\Application\Academic\Queries\CourseWeeksQuery($this->migration,$this->storage);$first=$query->content($this->studentActor,$this->assignmentId,'activity','unassigned',null,null,null);$this->assertSame(52,$first['total']);$this->assertCount(50,$first['items']);
  $next=$query->content($this->studentActor,$this->assignmentId,'activity','unassigned',null,null,$first['next_after']);$this->assertSame(52,$next['total']);$this->assertCount(2,$next['items']);$this->assertNull($next['next_after']);
  $this->assertSame(1,$query->content($this->studentActor,$this->assignmentId,'material','unassigned',null,null,null)['total']);
  $pastEnd=$query->content($this->studentActor,$this->assignmentId,'activity','unassigned',null,null,'18446744073709551615');
  $this->assertSame(52,$pastEnd['total']);$this->assertSame([],$pastEnd['items']);$this->assertNull($pastEnd['next_after']);
 }
 public function testHistoricalStudentCountsExcludeLaterPublications():void {
  $section=$this->catalog->create($this->actor,'section',['name'=>'Other','grade_id'=>$this->grade]);
  (new \App\Application\Academic\Commands\TransferStudent($this->db))->execute($this->actor,$this->enrollmentId,['grade_id'=>$this->grade,'section_id'=>$section]);
  $hidden=(new ActivityDelivery($this->migration,$this->storage))->write($this->teacherActor,'publish',$this->assignmentId,['title'=>'Later hidden task','instructions'=>'Outside original enrollment'])['id'];
  $this->weeks->set($this->teacherActor,'activity',$hidden,$this->weekInput());$this->publishCalendar();
  $query=new \App\Application\Academic\Queries\CourseWeeksQuery($this->migration,$this->storage);
  $this->assertSame(1,$query->content($this->studentActor,$this->assignmentId,'activity','unassigned',null,null,null)['total']);
  $this->assertSame(0,$query->content($this->studentActor,$this->assignmentId,'activity','earlier',null,null,null)['total']);
  $this->assertSame([],$query->calendar($this->studentActor,$this->assignmentId)['retained']);
  $this->assertSame(1,$query->content($this->teacherActor,$this->assignmentId,'activity','earlier',null,null,null)['total']);
 }
 public function testHttpContractsAreDedicatedStrictAndPrivate():void {
  $controller=new \App\Http\Controllers\Academic\ResourceWeekController($this->migration,$this->storage);$url='https://eval.test/academic/resource-weeks/activity/'.$this->publishedActivity;
  $response=$controller->handle(Request::create($url,'POST',[],[],[],[],json_encode($this->weekInput())),$this->teacherActor);$this->assertSame(200,$response->getStatusCode());$this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
  $this->assertSame(422,$controller->handle(Request::create($url,'POST',[],[],[],[],'[]'),$this->teacherActor)->getStatusCode());
  $this->assertSame(403,$controller->handle(Request::create($url,'POST',[],[],[],[],json_encode($this->weekInput(1))),$this->studentActor)->getStatusCode());
  $this->assertSame(200,$controller->handle(Request::create($url.'/history'),$this->studentActor)->getStatusCode());
  $this->assertSame(422,$controller->handle(Request::create($url.'?actor_id=1'),$this->teacherActor)->getStatusCode());
 }
}
