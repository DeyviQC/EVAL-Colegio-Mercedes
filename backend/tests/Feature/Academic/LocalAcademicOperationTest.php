<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Symfony\Component\HttpFoundation\Response;

/** In-process approved role journeys, not a browser/network/outage test. */
final class LocalAcademicOperationTest extends SubmissionReferenceTestCase
{
    private function session(AuthenticatedActor $actor):\Closure
    {
        $hasher=new \Illuminate\Hashing\BcryptHasher(['rounds'=>4]);$password=bin2hex(random_bytes(16));
        $this->migration->table('local_credentials')->where('id',$actor->identityId)->update(['password'=>$hasher->make($password)]);
        $labels=new class implements \App\Application\Academic\Queries\AcademicIdentityLabels {
            public function displayName(string $id):?string{return 'Synthetic retained teacher';}
        };
        $auth=new \App\Infrastructure\Authentication\LocalSessionAuthentication($this->db,new \Illuminate\Encryption\Encrypter(random_bytes(32),'AES-256-CBC'),'https://eval.test',5,5,60,$hasher);
        $compose=require dirname(__DIR__,3).'/routes/academic.php';
        $app=$compose($auth,new \App\Http\Controllers\Academic\FoundationController($this->db,new \App\Infrastructure\Persistence\Academic\PersistedAcademicReferences($this->db),$labels));
        $cookie=null;$csrf=null;
        $request=function(string $path,string $method='GET',array $body=[])use($app,&$cookie,&$csrf,$actor):Response {
            $response=$app(\Symfony\Component\HttpFoundation\Request::create('https://eval.test'.$path,$method,[],
                $cookie===null?[]:[\App\Infrastructure\Authentication\LocalSessionAuthentication::COOKIE=>$cookie],[],
                ['HTTP_ORIGIN'=>'https://eval.test','HTTP_X_CSRF_TOKEN'=>$csrf??'','CONTENT_TYPE'=>'application/json',
                    'REMOTE_ADDR'=>'127.4.'.intdiv((int)$actor->identityId%65536,256).'.'.((int)$actor->identityId%256)],json_encode((object)$body)));
            foreach($response->headers->getCookies() as $item){$cookie=$item->getValue();}
            $body=json_decode($response->getContent(),true,flags:JSON_THROW_ON_ERROR);$csrf=$body['csrf_token']??$csrf;return $response;
        };
        $this->assertSame(200,$request('/auth/session')->getStatusCode());
        $this->assertSame(200,$request('/auth/login','POST',['login'=>'fixture-'.$actor->identityId,'password'=>$password])->getStatusCode());
        return $request;
    }
    private function data(Response $response,int $status=200):array
    {$this->assertSame($status,$response->getStatusCode(),$response->getContent());return json_decode($response->getContent(),true,flags:JSON_THROW_ON_ERROR)['data'];}
    private function deniedJourney(\Closure $request,string $path,array $input,int $status=409):void
    {
        $key=$this->ordinal();$events=$this->db->table('academic_lifecycle_events')->count();
        $this->assertSame($status,$request($path,'POST',$input)->getStatusCode());
        $this->assertSame($key,$this->ordinal());$this->assertSame($events,$this->db->table('academic_lifecycle_events')->count());
        $this->assertFalse($this->db->getPdo()->inTransaction());
    }
    public function testDirectorEqualityPlanningUncontainedEnrollmentAndImmediateTransferJourney():void
    {
        $director=$this->session($this->actor);
        $name='  ÁREA '.$this->suffix.'  ';
        $period=$this->data($director('/academic/periods','POST',['name'=>$name,'start_on'=>'2026-01-01','end_on'=>'2026-12-31']),201)['id'];
        $this->deniedJourney($director,'/academic/periods',['name'=>'área '.$this->suffix,'start_on'=>'2026-01-01','end_on'=>'2026-12-31']);
        $this->data($director('/academic/periods','POST',['name'=>'area '.$this->suffix,'start_on'=>'2026-01-01','end_on'=>'2026-12-31']),201);
        $catalogInput=['name'=>'  GRÁDO '.$this->suffix.'  '];
        $this->data($director('/academic/catalog/grade','POST',$catalogInput),201);
        $this->deniedJourney($director,'/academic/catalog/grade',['name'=>'grádo '.$this->suffix]);
        $this->data($director('/academic/catalog/grade','POST',['name'=>'grado '.$this->suffix]),201);
        $plan=$this->data($director('/academic/assignments','POST',$this->assignmentInput(['academic_period_id'=>$period])),201)['id'];
        $view=$this->data($director('/academic/assignments/'.$plan));$this->assertSame('planned',$view['state']);$this->assertNull($view['operational_start_key']);
        $this->deniedJourney($director,'/academic/assignments/'.$plan.'/activate',[]);
        $student=$this->actor('student');
        $input=['student_id'=>$student->identityId,'academic_period_id'=>$this->period,'grade_id'=>$this->grade,'section_id'=>$this->section,'effective_from'=>'2025-01-01','effective_until'=>null];
        $enrollment=$this->data($director('/academic/enrollments','POST',$input),201)['id'];
        $this->assertNull($this->data($director('/academic/enrollments/'.$enrollment))['effective_until']);
        $destination=$this->catalog->create($this->freshActor($this->actor),'section',['name'=>'Destination '.$this->suffix,'grade_id'=>$this->grade]);
        foreach(['scheduled_on','effective_on','correction','operation_key','student_id'] as $field){
            $this->deniedJourney($director,'/academic/enrollments/'.$enrollment.'/transfer',['grade_id'=>$this->grade,'section_id'=>$destination,$field=>'2026-10-08'],422);}
        $confirmed=$this->data($director('/academic/enrollments/'.$enrollment.'/transfer','POST',['grade_id'=>$this->grade,'section_id'=>$destination]));
        $this->assertSame($enrollment,$confirmed['prior_id']);$this->assertIsString($confirmed['operation_key']);
        $current=$this->data($director('/academic/enrollments/'.$confirmed['successor_id']));$this->assertSame($destination,$current['section_id']);
        $prior=$this->data($director('/academic/enrollments/'.$enrollment));$this->assertSame('transferred',$prior['state']);$this->assertSame($this->section,$prior['section_id']);
        $this->assertSame($prior['operational_end_key'],$current['operational_start_key']);
    }
    public function testClosedParentRetainsFourRoleHistoryAndPermitsOnlyValidCleanup():void
    {
        $id=$this->accept();$director=$this->session($this->actor);
        $this->data($director('/academic/periods/'.$this->period.'/close','POST'));
        $this->assertSame('active',$this->db->table('teaching_assignments')->where('id',$this->assignmentId)->value('state'));
        $vice=$this->session($this->actor('vice_principal'));
        $this->deniedJourney($vice,'/academic/enrollments/'.$this->enrollmentId.'/close',['effective_until'=>'2026-10-07'],403);
        $this->data($vice('/academic/assignments/'.$this->assignmentId.'/close','POST',['effective_until'=>'2026-10-07']));
        $this->data($director('/academic/enrollments/'.$this->enrollmentId.'/close','POST',['effective_until'=>'2026-10-07']));
        $teacher=$this->session($this->teacherActor);$student=$this->session($this->studentActor);
        foreach([$director,$teacher,$student] as $request){
            $view=$this->data($request('/academic/submissions/'.$id));$this->assertSame($this->assignmentId,$view['originalTeachingAssignment']['id']);
            $this->assertSame($this->enrollmentId,$view['acceptedUnderEnrollment']['id']);$this->assertArrayNotHasKey('payload',$view['submission']);}
        $this->assertSame(404,$vice('/academic/submissions/'.$id)->getStatusCode());
        $this->deniedJourney($teacher,'/academic/activities',['assignment_id'=>$this->assignmentId],403);
        foreach(['/academic/submissions','/academic/submissions/late'] as $path){$this->deniedJourney($student,$path,['activity_id'=>$this->activityId],403);}
        $this->deniedJourney($director,'/academic/assignments',$this->assignmentInput(),409);
    }
    public function testClosedAssignmentAllowsInternalOriginalRouteButDoesNotExposeReservedWriter():void
    {
        $director=$this->session($this->actor);
        $this->data($director('/academic/assignments/'.$this->assignmentId.'/close','POST',['effective_until'=>'2026-10-07']));
        $id=$this->accept();$student=$this->session($this->studentActor);
        $this->assertSame($this->assignmentId,$this->data($student('/academic/submissions/'.$id))['originalTeachingAssignment']['id']);
        $this->deniedJourney($student,'/academic/submissions',['activity_id'=>$this->activityId],404);
        $other=$this->session($this->actor('student'));$this->assertSame(404,$other('/academic/submissions/'.$id)->getStatusCode());
    }
    private function freshActor(AuthenticatedActor $actor):AuthenticatedActor
    {
        $provider=new \App\Infrastructure\Authentication\LocalUserProvider($this->db,new \Illuminate\Hashing\BcryptHasher(['rounds'=>4]),'local_credentials');
        return $provider->actor($provider->retrieveById($actor->identityId));
    }
}
