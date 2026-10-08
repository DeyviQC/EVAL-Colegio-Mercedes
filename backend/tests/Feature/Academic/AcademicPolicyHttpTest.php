<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\AssignmentTestCase;
use App\Http\Controllers\Academic\FoundationController;
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use Illuminate\Encryption\Encrypter;
use Illuminate\Hashing\BcryptHasher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class AcademicPolicyHttpTest extends AssignmentTestCase
{
    protected const DATABASE='eval_u9_test';
    public function testPersistedSubmissionHttpRoleMatrixAfterPeriodClosure():void
    {
        if(static::DATABASE!=='eval_u11_test'){$this->markTestSkipped('Persisted reference integration runs in U11 only.');}
        $assignment=$this->assignment(['teacher_id'=>$this->actor->identityId]);$this->assignments->activate($this->actor,$assignment);
        $this->enrollment(['student_id'=>$this->actor->identityId]);$this->role('teacher');
        $activity=(new \App\Application\Academic\Commands\CreateActivityReference($this->db))->execute($this->actor,['assignment_id'=>$assignment]);
        $this->role('student');$submission=(new \App\Application\Academic\Commands\AcceptSubmissionReference($this->db))->execute($this->actor,['activity_id'=>$activity]);
        $this->role('director_admin');$this->periods->close($this->actor,$this->period);
        $labels=new class implements \App\Application\Academic\Queries\AcademicIdentityLabels {public function displayName(string $id):?string{return 'Synthetic teacher';}};
        $this->compose(new FoundationController($this->db,new \App\Infrastructure\Persistence\Academic\PersistedAcademicReferences($this->db),$labels));$this->login();
        foreach(['director_admin','teacher','student'] as $role){$this->role($role);$this->assertSame(200,$this->request('/academic/submissions/'.$submission)->getStatusCode());}
        $this->role('vice_principal');$this->deniedHttp(404,fn()=>$this->request('/academic/submissions/'.$submission));
    }
    private \Closure $app;
    private LocalSessionAuthentication $authentication;
    private ?string $cookie=null;
    private ?string $csrf=null;
    private string $password;
    protected function setUp():void
    {
        parent::setUp();$this->password=bin2hex(random_bytes(12));
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($this->password)]);
        $provider=new \App\Infrastructure\Authentication\LocalUserProvider($this->db,new BcryptHasher(['rounds'=>4]),'local_credentials');
        $this->actor=$provider->actor($provider->retrieveById($this->actor->identityId));
        $this->authentication=new LocalSessionAuthentication($this->db,new Encrypter(random_bytes(32),'AES-256-CBC'),'https://eval.test',5,5,60,new BcryptHasher(['rounds'=>4]));
        $this->compose(new FoundationController($this->db));
    }
    private function compose(FoundationController $controller):void
    {$compose=require dirname(__DIR__,3).'/routes/academic.php';$this->app=$compose($this->authentication,$controller);}
    private function role(string $role):void
    {$this->migration->table('local_role_grants')->where('identity_id',$this->actor->identityId)->delete();
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor->identityId,'role'=>$role]);}
    private function request(string $path,string $method='GET',array $body=[],?string $token=null,?string $raw=null):Response
    {
        $request=Request::create('https://eval.test'.$path,$method,[],
            $this->cookie===null?[]:[LocalSessionAuthentication::COOKIE=>$this->cookie],[],
            ['CONTENT_TYPE'=>'application/json','HTTP_ORIGIN'=>'https://eval.test','HTTP_X_CSRF_TOKEN'=>$token??$this->csrf??'',
                'REMOTE_ADDR'=>'127.2.'.intdiv((int)$this->actor->identityId%65536,256).'.'.((int)$this->actor->identityId%256)],$raw??json_encode((object)$body));
        $response=($this->app)($request);
        foreach($response->headers->getCookies() as $cookie){$this->cookie=$cookie->getValue();}
        $data=json_decode($response->getContent(),true);if(isset($data['csrf_token'])){$this->csrf=$data['csrf_token'];}return $response;
    }
    private function login():void
    {
        $this->assertSame(200,$this->request('/auth/session')->getStatusCode());
        $this->assertSame(200,$this->request('/auth/login','POST',['login'=>'fixture-'.$this->actor->identityId,'password'=>$this->password])->getStatusCode());
    }
    public function testUnauthenticatedReadIsDeniedAndLocalSessionAllowsDirectorRead():void
    {
        $this->assertSame(401,$this->request('/academic/periods/'.$this->period)->getStatusCode());$this->login();
        $response=$this->request('/academic/periods/'.$this->period);$this->assertSame(200,$response->getStatusCode());
        $this->assertSame($this->period,json_decode($response->getContent(),true)['data']['id']);
    }
    public function testPlannedParentCreationUsesGuardedAssignmentCommand():void
    {
        $period=$this->periods->create($this->actor,['name'=>'Plan '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
        $this->login();$response=$this->request('/academic/assignments','POST',$this->assignmentInput(['academic_period_id'=>$period]));
        $this->assertSame(201,$response->getStatusCode());$id=json_decode($response->getContent(),true)['data']['id'];
        $this->assertSame('planned',$this->db->table('teaching_assignments')->where('id',$id)->value('state'));
        $this->assertNull($this->db->table('teaching_assignments')->where('id',$id)->value('operational_start_key'));
    }
    private function deniedHttp(int $status,callable $operation):Response
    {
        $ordinal=$this->ordinal();$events=$this->db->table('academic_lifecycle_events')->count();
        $response=$operation();$this->assertSame($status,$response->getStatusCode(),$response->getContent());
        $this->assertSame($ordinal,$this->ordinal());$this->assertSame($events,$this->db->table('academic_lifecycle_events')->count());
        $this->assertFalse($this->db->getPdo()->inTransaction());return $response;
    }
    public function testVicePrincipalHasAssignmentCleanupAndExactSupportButNoEnrollmentOrCatalogRights():void
    {
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);$enrollment=$this->enrollment();$this->periods->close($this->actor,$this->period);
        $this->migration->table('grades')->where('id',$this->grade)->update(['is_active'=>false]);$this->role('vice_principal');$this->login();
        $support=$this->request('/academic/assignment-support','POST',['duty'=>'close','target'=>['assignment_id'=>$id]]);
        $this->assertSame(200,$support->getStatusCode());$view=json_decode($support->getContent(),true)['data'];
        $this->assertFalse($view['grade']['is_active']);$this->assertArrayNotHasKey('student_id',$view['enrollment_scope']);
        $this->assertSame(200,$this->request('/academic/assignments/'.$id.'/close','POST',['effective_until'=>$this->today()])->getStatusCode());
        $this->deniedHttp(403,fn()=>$this->request('/academic/enrollments/'.$enrollment.'/close','POST',['effective_until'=>$this->today()]));
        $this->deniedHttp(403,fn()=>$this->request('/academic/catalog/grade','POST',['name'=>'Forbidden']));
        $this->deniedHttp(404,fn()=>$this->request('/academic/periods/'.$this->period));
        $this->deniedHttp(404,fn()=>$this->request('/academic/enrollments/'.$enrollment));
        $this->deniedHttp(403,fn()=>$this->request('/academic/assignment-support','POST',['duty'=>'material.manage','target'=>['assignment_id'=>$id]]));
    }
    public function testClosedParentRejectsFoundationNewWorkButKeepsDirectorCleanupAndHistory():void
    {
        $id=$this->assignment();$this->assignments->activate($this->actor,$id);$planned=$this->assignment(['teacher_id'=>$this->student]);
        $enrollment=$this->enrollment();$this->periods->close($this->actor,$this->period);$this->login();
        foreach([
            ['/academic/enrollments',$this->input()],['/academic/enrollments/'.$enrollment.'/transfer',['grade_id'=>$this->grade,'section_id'=>$this->section]],
            ['/academic/assignments',$this->assignmentInput()],['/academic/assignments/'.$planned.'/activate',[]],
            ['/academic/assignments/'.$id.'/replace',['teacher_id'=>$this->student]],
        ] as [$path,$body]){$this->deniedHttp(409,fn()=>$this->request($path,'POST',$body));}
        $this->assertSame(200,$this->request('/academic/enrollments/'.$enrollment.'/close','POST',['effective_until'=>$this->today()])->getStatusCode());
        $this->assertSame(200,$this->request('/academic/assignments/'.$id.'/close','POST',['effective_until'=>$this->today()])->getStatusCode());
        $this->assertSame(200,$this->request('/academic/assignments/'.$id)->getStatusCode());
        $this->assertSame(201,$this->request('/academic/catalog/grade','POST',['name'=>'Other '.$this->suffix])->getStatusCode());
    }
    public function testTeacherOwnHistoryIsReadableButOtherScopeAndManagementAreDenied():void
    {
        $own=$this->assignment(['teacher_id'=>$this->actor->identityId]);$other=$this->assignment();$this->periods->close($this->actor,$this->period);
        $this->role('teacher');$this->login();$this->assertSame(200,$this->request('/academic/assignments/'.$own)->getStatusCode());
        $this->deniedHttp(404,fn()=>$this->request('/academic/assignments/'.$other));
        $this->deniedHttp(403,fn()=>$this->request('/academic/catalog/grade','POST',['name'=>'Denied']));
        $this->deniedHttp(403,fn()=>$this->request('/academic/assignments/'.$own.'/close','POST',['effective_until'=>$this->today()]));
    }
    public function testStudentHistoryIsOwnedAndMissingAccountOrSessionCannotGrantScope():void
    {
        $own=$this->enrollment(['student_id'=>$this->actor->identityId]);$other=$this->enrollment();$this->commands->close($this->actor,$own,$this->today());
        $this->role('student');$this->login();$this->assertSame(200,$this->request('/academic/enrollments/'.$own)->getStatusCode());
        $this->deniedHttp(404,fn()=>$this->request('/academic/enrollments/'.$other));
        $this->deniedHttp(403,fn()=>$this->request('/academic/enrollments','POST',$this->input(['student_id'=>$this->actor->identityId])));
        $this->migration->table('retained_identities')->where('id',$this->actor->identityId)->update(['credential_status'=>'removed']);
        $this->deniedHttp(401,fn()=>$this->request('/academic/enrollments/'.$own));
    }
    public function testExtraScopeRecipientRoleAndUnsupportedTransferTimingAreRejected():void
    {
        $enrollment=$this->enrollment();$id=$this->assignment();$this->login();
        foreach(['actor_id'=>$this->actor->identityId,'roles'=>['director_admin'],'recipient_id'=>$this->teacher,'operational_start_key'=>1] as $field=>$value){
            $this->deniedHttp(422,fn()=>$this->request('/academic/assignments','POST',$this->assignmentInput([$field=>$value])));
        }
        foreach(['effective_on'=>'2026-03-01','scheduled_for'=>'2026-12-20','correction'=>true] as $field=>$value){
            $this->deniedHttp(422,fn()=>$this->request('/academic/enrollments/'.$enrollment.'/transfer','POST',
                ['grade_id'=>$this->grade,'section_id'=>$this->section,$field=>$value]));
        }
        $this->deniedHttp(422,fn()=>$this->request('/academic/assignments/'.$id.'?teacher_id='.$this->student));
        $this->deniedHttp(422,fn()=>$this->request('/academic/assignment-support','POST',['duty'=>'history','target'=>['assignment_id'=>$id,'period_id'=>$this->period]]));
        $this->deniedHttp(422,fn()=>$this->request('/academic/assignments','POST',[],null,'{broken'));
        $this->deniedHttp(422,fn()=>$this->request('/academic/assignments','POST',[],null,'[]'));
    }
    public function testCsrfAndOriginAreCheckedBeforeDomainCommand():void
    {
        $this->login();$this->deniedHttp(419,fn()=>$this->request('/academic/assignments','POST',$this->assignmentInput(),'wrong'));
        $request=Request::create('https://eval.test/academic/assignments','POST',[],[LocalSessionAuthentication::COOKIE=>$this->cookie],[],
            ['HTTP_ORIGIN'=>'https://untrusted.test','HTTP_X_CSRF_TOKEN'=>$this->csrf],json_encode($this->assignmentInput()));
        $this->deniedHttp(403,fn()=>($this->app)($request));
    }
    public function testConflictAndInvalidDateErrorsAreSanitized():void
    {
        $id=$this->assignment();$this->login();$this->deniedHttp(409,fn()=>$this->request('/academic/assignments','POST',$this->assignmentInput()));
        $response=$this->deniedHttp(422,fn()=>$this->request('/academic/assignments/'.$id.'/close','POST',['effective_until'=>'2026-02-30']));
        $this->assertSame(['error'=>'invalid_interval'],json_decode($response->getContent(),true));
        foreach(['SQLSTATE','password','trace','SELECT'] as $needle){$this->assertStringNotContainsString($needle,$response->getContent());}
    }
    public function testReadPolicyAndAlternateCommandEntryBothRejectRevokedRoles():void
    {
        $this->login();$this->role('student');$this->deniedHttp(404,fn()=>$this->request('/academic/periods/'.$this->period));
        $this->deniedHttp(403,fn()=>$this->request('/academic/catalog/grade','POST',['name'=>'Forged']));
        $forged=new \App\Infrastructure\Authentication\AuthenticatedActor($this->actor->identityId,['director_admin'],$this->actor->credentialRevision());
        $this->assignmentDenied('forbidden',fn()=>$this->catalog->create($forged,'grade',['name'=>'Alternate']));
    }
    public function testActualCommitWithLostAcknowledgementReturnsCorrelationAndNeverReplays():void
    {
        $this->login();$connection=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public int $commits=0;public function commit(){$this->commits++;parent::commit();throw new \RuntimeException('Injected lost acknowledgement with SQL secret');}
        };
        $this->compose(new FoundationController($connection));$before=$this->ordinal();
        $response=$this->request('/academic/assignments','POST',$this->assignmentInput());$this->assertSame(503,$response->getStatusCode());
        $data=json_decode($response->getContent(),true);$this->assertSame('commit_outcome_unknown',$data['error']);
        $this->assertFalse($data['automatic_retry']);$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/',$data['correlation_id']);
        $this->assertSame(1,$connection->commits);$this->assertSame($before+1,$this->ordinal());
        $this->assertSame(1,$this->db->table('teaching_assignments')->where('teacher_id',$this->teacher)->count());
        $this->assertSame($data['correlation_id'],$this->db->table('academic_lifecycle_events')->where('operation_key',$before+1)->value('correlation_id'));
        $this->assertStringNotContainsString('SQL secret',$response->getContent());
    }
    private function referenceFixture():array
    {
        $id=$this->assignment(['teacher_id'=>$this->actor->identityId]);$this->assignments->activate($this->actor,$id);
        $own=$this->enrollment(['student_id'=>$this->actor->identityId]);$other=$this->enrollment();
        $refs=new \Tests\Support\FixtureAcademicReferences(['1'=>['id'=>'1','teaching_assignment_id'=>$id]],[
            '1'=>['id'=>'1','student_id'=>$this->actor->identityId,'teaching_assignment_id'=>$id,'activity_id'=>'1','accepted_under_enrollment_id'=>$own,'accepted_at'=>'2026-10-07 12:00:00.000000','grade'=>'AD'],
            '2'=>['id'=>'2','student_id'=>$this->student,'teaching_assignment_id'=>$id,'activity_id'=>'1','accepted_under_enrollment_id'=>$other,'accepted_at'=>'2026-10-07 12:00:00.000000'],
        ]);
        $labels=new class implements \App\Application\Academic\Queries\AcademicIdentityLabels {
            public function displayName(string $identityId):?string{return 'Synthetic teacher';}
        };
        $this->compose(new FoundationController($this->db,$refs,$labels));return [$id,$own,$other];
    }
    public function testClosedParentDeniesTeacherActivityAndNormalOrLateStudentAcceptance():void
    {
        [$id]=$this->referenceFixture();$this->periods->close($this->actor,$this->period);$this->login();
        $this->role('teacher');$this->deniedHttp(403,fn()=>$this->request('/academic/activities','POST',['assignment_id'=>$id]));
        $this->role('student');foreach(['/academic/submissions','/academic/submissions/late'] as $path){
            $this->deniedHttp(403,fn()=>$this->request($path,'POST',['activity_id'=>'1']));
        }
    }
    public function testReferenceContractsCannotPerformAcceptanceBeforeU10U11():void
    {
        [$id]=$this->referenceFixture();$this->login();$this->role('teacher');
        $this->deniedHttp(404,fn()=>$this->request('/academic/activities','POST',['assignment_id'=>$id]));
        $this->role('student');$this->deniedHttp(404,fn()=>$this->request('/academic/submissions','POST',['activity_id'=>'1']));
        $this->deniedHttp(422,fn()=>$this->request('/academic/submissions','POST',['activity_id'=>'1','recipient_id'=>$this->teacher]));
    }
    public function testSyntheticSubmissionHistoryIsRoleBoundedAndOriginalContextSurvivesClosure():void
    {
        [$id,$own]=$this->referenceFixture();$this->commands->close($this->actor,$own,$this->today());
        $this->assignments->close($this->actor,$id,$this->today());$this->periods->close($this->actor,$this->period);$this->login();
        foreach(['director_admin','teacher','student'] as $role){
            $this->role($role);$response=$this->request('/academic/submissions/1');$this->assertSame(200,$response->getStatusCode());
            $view=json_decode($response->getContent(),true)['data'];$this->assertSame($own,$view['acceptedUnderEnrollment']['id']);
            $this->assertSame($id,$view['originalTeachingAssignment']['id']);$this->assertArrayNotHasKey('grade',$view['submission']);
        }
        $this->deniedHttp(404,fn()=>$this->request('/academic/submissions/2'));
        $this->role('teacher');$this->assertSame(200,$this->request('/academic/submissions/2')->getStatusCode());
        $this->role('vice_principal');$this->deniedHttp(404,fn()=>$this->request('/academic/submissions/1'));
        $this->deniedHttp(404,fn()=>$this->request('/academic/submissions/1/process','POST'));
        $this->deniedHttp(404,fn()=>$this->request('/academic/materials','POST'));
        $this->deniedHttp(404,fn()=>$this->request('/academic/observations','POST'));
    }
    public function testDefaultReferenceBindingAndUnknownRouteFailClosed():void
    {
        $this->login();$this->deniedHttp(404,fn()=>$this->request('/academic/submissions/1'));
        $this->deniedHttp(404,fn()=>$this->request('/academic/unknown'));
        $response=(new FoundationController($this->db))->handle(Request::create('/academic/periods/'.$this->period),null);
        $this->assertSame(401,$response->getStatusCode());
    }
}
