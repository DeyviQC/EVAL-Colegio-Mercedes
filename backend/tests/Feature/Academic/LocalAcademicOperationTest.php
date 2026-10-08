<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
use App\Infrastructure\Authentication\AuthenticatedActor;
use Symfony\Component\HttpFoundation\Response;

/** In-process approved role journeys, not a browser/network/outage test. */
final class LocalAcademicOperationTest extends SubmissionReferenceTestCase
{
    public function testDirectorDirectoriesAreBoundedRetainedAndRoleRestricted():void
    {
        $controller=new \App\Http\Controllers\Academic\FoundationController($this->db);
        $query=new \App\Application\Academic\Queries\FoundationDirectoryQuery($this->db);
        $commands=new \App\Application\Academic\Commands\AcademicPeriodCommands($this->db);
        $before=(string)$this->db->table('academic_periods')->max('id');
        for($i=0;$i<51;$i++)$commands->create($this->actor,['name'=>'Directory '.$this->suffix.' '.$i,'start_on'=>'2026-01-01','end_on'=>'2026-12-31']);
        $page=$query->page($this->actor,'period',$before);$this->assertCount(50,$page['items']);$this->assertSame($page['items'][49]['id'],$page['next_after']);
        $next=$query->page($this->actor,'period',$page['next_after']);$this->assertCount(1,$next['items']);$this->assertNull($next['next_after']);
        $this->assertSame(['id','name','start_on','end_on','state'],array_keys($page['items'][0]));
        $inactive=$this->catalog->create($this->actor,'grade',['name'=>'Inactive '.$this->suffix,'is_active'=>false]);
        $grades=$query->page($this->actor,'grade',(string)((int)$inactive-1))['items'];$row=array_values(array_filter($grades,fn($row)=>$row['id']===$inactive))[0];$this->assertFalse($row['is_active']);
        foreach(['vice_principal','teacher','student'] as $role){$actor=$this->actor($role);
            foreach(['/academic/periods','/academic/catalog/entry','/academic/catalog/grade','/academic/catalog/section'] as $path){
                $this->assertSame(403,$controller->handle(\Symfony\Component\HttpFoundation\Request::create('https://eval.test'.$path),$actor)->getStatusCode());
            }
        }
        foreach(['?after=0','?after[]=1','?role=director_admin'] as $suffix)$this->assertSame(422,$controller->handle(\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/periods'.$suffix),$this->actor)->getStatusCode());
        $this->assertSame(401,$controller->handle(\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/periods'),null)->getStatusCode());
        $this->assertSame(200,$controller->handle(\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/catalog/section'),$this->actor)->getStatusCode());
    }
    public function testNavigationUsesPersistedRolesAndExactTransport():void
    {
        $controller=new \App\Http\Controllers\Academic\FoundationController($this->db);
        $request=\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/navigation');
        $this->assertSame(401,$controller->handle($request,null)->getStatusCode());
        foreach(['director_admin'=>['periods','catalog','enrollments','assignments'],'vice_principal'=>['assignments'],
            'teacher'=>['my_assignments'],'student'=>['my_enrollments']] as $role=>$expected){
            $actor=$this->actor($role);$query=new \App\Application\Academic\Queries\AcademicNavigationQuery($this->db);
            $this->assertSame(['sections'=>$expected],$query->get($actor));
            $forged=new AuthenticatedActor($actor->identityId,['director_admin'],$actor->credentialRevision());
            $this->assertSame(['sections'=>$expected],$query->get($forged));
            $this->assertSame(['sections'=>$expected],$this->data($controller->handle($request,$actor)));
        }
        $actor=$this->actor('teacher');$query=new \App\Application\Academic\Queries\AcademicNavigationQuery($this->db);
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$actor->identityId,'role'=>'student']);
        $this->assertSame(['sections'=>['my_assignments','my_enrollments']],$query->get($actor));
        $this->migration->table('local_role_grants')->where('identity_id',$actor->identityId)->delete();
        $this->assertSame(['sections'=>[]],$query->get($actor));
        $bad=\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/navigation?role=director_admin');
        $this->assertSame(422,$controller->handle($bad,$actor)->getStatusCode());
        $bad=\Symfony\Component\HttpFoundation\Request::create('https://eval.test/academic/navigation','GET',[],[],[],[], '{"role":"director_admin"}');
        $this->assertSame(422,$controller->handle($bad,$actor)->getStatusCode());
        $this->migration->table('retained_identities')->where('id',$actor->identityId)->update(['credential_status'=>'deactivated']);
        $this->assertSame(403,$controller->handle($request,$actor)->getStatusCode());
        $this->assertSame(403,$controller->handle($request,new AuthenticatedActor($this->actor->identityId,[],str_repeat('0',64)))->getStatusCode());
        $broken=$this->createMock(\Illuminate\Database\Connection::class);
        $broken->expects($this->once())->method('table')->willThrowException(new \Illuminate\Database\QueryException('isolated','SELECT private_fixture',[],new \PDOException('Injected')));
        $failure=(new \App\Http\Controllers\Academic\FoundationController($broken))->handle($request,$this->actor);
        $this->assertSame(503,$failure->getStatusCode());$body=json_decode($failure->getContent(),true);
        $this->assertSame('database_unavailable',$body['error']);$this->assertFalse($body['automatic_retry']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/',$body['correlation_id']);
        $this->assertStringNotContainsString('private_fixture',$failure->getContent());
    }
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
