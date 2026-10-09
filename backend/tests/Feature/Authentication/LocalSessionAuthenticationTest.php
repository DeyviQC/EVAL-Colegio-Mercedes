<?php
declare(strict_types=1);
namespace Tests\Feature\Authentication;
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class LocalSessionAuthenticationTest extends TestCase
{
    private Connection $db;
    private Connection $migration;
    private LocalSessionAuthentication $app;
    private Encrypter $encrypter;
    private int $actor;
    private string $login;
    private string $password;
    private ?string $cookie=null;
    private ?string $csrf=null;
    private string $ip;
    protected function setUp():void
    {
        $this->db=DB::connection();$this->migration=DB::connection('migration');
        $this->assertSame('eval_auth_test',$this->db->getDatabaseName());
        $this->encrypter=new Encrypter(random_bytes(32),'AES-256-CBC');
        // Explicit test-only settings; no institutional timeout/throttle policy is selected.
        $hasher=new BcryptHasher(['rounds'=>4]);
        $this->app=new LocalSessionAuthentication($this->db,$this->encrypter,'https://eval.test',5,3,60,$hasher);
        $this->actor=$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $this->ip='127.1.'.intdiv($this->actor%65536,256).'.'.($this->actor%256);
        $this->login='fixture-'.bin2hex(random_bytes(8));$this->password=bin2hex(random_bytes(24));
        $this->migration->table('local_credentials')->insert(['id'=>$this->actor,'login'=>$this->login,'password'=>$hasher->make($this->password)]);
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor,'role'=>'teacher']);
    }
    private function request(string $path,string $method='GET',array $body=[],?string $csrf=null,?\Closure $next=null):Response
    {
        $request=Request::create('https://eval.test'.$path,$method,[],
            $this->cookie===null?[]:[LocalSessionAuthentication::COOKIE=>$this->cookie],[],
            ['CONTENT_TYPE'=>'application/json','HTTP_ORIGIN'=>'https://eval.test','HTTP_X_CSRF_TOKEN'=>$csrf??$this->csrf??'','REMOTE_ADDR'=>$this->ip],json_encode($body));
        $response=$this->app->handle($request,$next??fn($actor)=>new JsonResponse(['id'=>$actor->identityId,'roles'=>$actor->roles]));
        foreach($response->headers->getCookies() as $cookie){$this->cookie=$cookie->getValue();}
        $data=json_decode($response->getContent(),true);
        if(isset($data['csrf_token'])){$this->csrf=$data['csrf_token'];}
        return $response;
    }
    private function authenticate():Response
    {
        $this->assertSame(200,$this->request('/auth/session')->getStatusCode());
        $response=$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password]);
        $this->assertSame(200,$response->getStatusCode());
        return $response;
    }
    public function testRealCredentialsEstablishRotatedLocalSessionAndServerRole():void
    {
        $this->assertSame(200,$this->request('/auth/session')->getStatusCode());$old=$this->cookie;
        $response=$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password,'actor_id'=>PHP_INT_MAX,'role'=>'director_admin']);
        $this->assertSame(200,$response->getStatusCode());
        $this->assertNotSame($old,$this->cookie);
        $data=json_decode($this->request('/protected')->getContent(),true);
        $this->assertSame((string)$this->actor,$data['id']);$this->assertSame(['teacher'],$data['roles']);
    }
    public function testInvalidCredentialsDoNotAuthenticate():void
    {
        $this->assertSame(200,$this->request('/auth/session')->getStatusCode());
        $this->assertSame(401,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>'wrong'])->getStatusCode());
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testMissingCsrfRejectsLoginAndProtectedWrites():void
    {
        $this->assertSame(200,$this->request('/auth/session')->getStatusCode());
        $this->assertSame(419,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password],'invalid')->getStatusCode());
        $this->authenticate();
        $this->assertSame(419,$this->request('/protected','POST',[],'invalid')->getStatusCode());
    }
    public function testLogoutRevokesOldSessionAndPreventsReplay():void
    {
        $this->authenticate();$old=$this->cookie;
        $this->assertSame(200,$this->request('/auth/logout','POST')->getStatusCode());
        $this->cookie=$old;
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testCredentialDeactivationRevokesExistingSessionWithoutDeletingIdentity():void
    {
        $this->authenticate();
        $this->migration->table('retained_identities')->where('id',$this->actor)->update(['credential_status'=>'deactivated']);
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
        $this->assertTrue($this->db->table('retained_identities')->where('id',$this->actor)->exists());
    }
    public function testCookieAndPayloadAreProtectedAndResponseIsNotCacheable():void
    {
        $response=$this->authenticate();$cookie=$response->headers->getCookies()[0];
        $this->assertSame(LocalSessionAuthentication::COOKIE,$cookie->getName());
        $this->assertTrue($cookie->isSecure());$this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax',$cookie->getSameSite());$this->assertNull($cookie->getDomain());$this->assertSame('/',$cookie->getPath());
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $id=$this->encrypter->decryptString($this->cookie);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/',$id);
        $raw=$this->db->table('local_sessions')->where('id',$id)->value('payload');
        $this->assertStringNotContainsString('credential_revision',$raw);
        $this->assertStringNotContainsString($this->password,$raw);
    }
    public function testTamperedCookieAndClientActorParametersCannotAuthenticate():void
    {
        $this->authenticate();$this->cookie='tampered-'.$this->cookie;
        $this->assertSame(401,$this->request('/protected?actor_id='.$this->actor.'&role=director_admin')->getStatusCode());
        $this->cookie=(string)$this->actor;
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testExpiredServerSessionDoesNotAuthenticate():void
    {
        $this->authenticate();$id=$this->encrypter->decryptString($this->cookie);
        $this->migration->table('local_sessions')->where('id',$id)->update(['last_activity'=>time()-301]);
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testPasswordChangeRevokesExistingSession():void
    {
        $this->authenticate();
        $this->migration->table('local_credentials')->where('id',$this->actor)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make('different fixture password')]);
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testCredentialRemovalDeniesLoginAndExistingSessionButRetainsIdentity():void
    {
        $this->authenticate();
        $this->migration->table('local_credentials')->where('id',$this->actor)->update(['password'=>null]);
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
        $this->request('/auth/session'); // Revocation rotates CSRF; refresh before testing credentials again.
        $this->assertSame(401,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password])->getStatusCode());
        $this->assertTrue($this->db->table('retained_identities')->where('id',$this->actor)->exists());
    }
    public function testRoleFactsAreReloadedForEachRequest():void
    {
        $this->authenticate();
        $this->migration->table('local_role_grants')->where('identity_id',$this->actor)->delete();
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor,'role'=>'student']);
        $data=json_decode($this->request('/protected')->getContent(),true);
        $this->assertSame(['student'],$data['roles']);
    }
    public function testAllFourApprovedRoleFactsResolveFromServerStorage():void
    {
        $this->authenticate();
        foreach(['director_admin','vice_principal','teacher','student'] as $role){
            $this->migration->table('local_role_grants')->where('identity_id',$this->actor)->delete();
            $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor,'role'=>$role]);
            $this->assertSame([$role],json_decode($this->request('/protected')->getContent(),true)['roles']);
        }
    }
    public function testConfiguredThrottleAndWindowAreApplied():void
    {
        $this->request('/auth/session');
        for($i=0;$i<3;$i++){$this->assertSame(401,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>'wrong'])->getStatusCode());}
        $this->assertSame(429,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password])->getStatusCode());
        $key=hash('sha256','https://eval.test|'.$this->ip);
        $this->migration->table('local_login_limits')->where('id',$key)->update(['window_started'=>time()-61]);
        $this->assertSame(200,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password])->getStatusCode());
    }
    public function testCrossOriginAndInsecureRequestsNeverReachProtectedCallback():void
    {
        $this->authenticate();$calls=0;
        foreach(['http://eval.test/protected','https://other.test/protected'] as $url){
            $request=Request::create($url,'GET',[],[LocalSessionAuthentication::COOKIE=>$this->cookie]);
            $response=$this->app->handle($request,function()use(&$calls){$calls++;return new JsonResponse([]);});
            $this->assertSame(403,$response->getStatusCode());
        }
        $request=Request::create('https://eval.test/protected','POST',[],[LocalSessionAuthentication::COOKIE=>$this->cookie],[],
            ['HTTP_ORIGIN'=>'https://other.test','HTTP_X_CSRF_TOKEN'=>$this->csrf]);
        $this->assertSame(403,$this->app->handle($request,function()use(&$calls){$calls++;return new JsonResponse([]);})->getStatusCode());
        $this->assertSame(0,$calls);
    }
    public function testStaleWriterCannotRestoreLoggedOutSession():void
    {
        $this->authenticate();$id=$this->encrypter->decryptString($this->cookie);
        $stale=new \App\Infrastructure\Authentication\LocalSessionHandler($this->db,'local_sessions',5);
        $payload=$stale->read($id);
        $this->assertSame(200,$this->request('/auth/logout','POST')->getStatusCode());
        try{$stale->write($id,$payload);$this->fail('Revoked session restored.');}
        catch(\RuntimeException $error){$this->assertSame('Session was revoked or expired.',$error->getMessage());}
        $this->assertFalse($stale->usable($id));
    }
    public function testRuntimeCannotWriteCredentialsRolesIdentitiesOrSchema():void
    {
        foreach([
            fn()=>$this->db->table('local_credentials')->where('id',$this->actor)->update(['password'=>'forbidden']),
            fn()=>$this->db->table('local_role_grants')->where('identity_id',$this->actor)->delete(),
            fn()=>$this->db->table('retained_identities')->where('id',$this->actor)->delete(),
            fn()=>$this->db->statement('ALTER TABLE local_sessions ADD COLUMN forbidden INT'),
        ] as $operation){
            try{$operation();$this->fail('Forbidden runtime privilege accepted.');}
            catch(\Illuminate\Database\QueryException $error){$this->assertContains((int)$error->errorInfo[1],[1142,1143]);}
        }
    }
    public function testCredentialsAndRoleFactsHaveRestrictiveRetainedIdentityForeignKeys():void
    {
        foreach([
            fn()=>$this->migration->table('local_credentials')->insert(['id'=>PHP_INT_MAX,'login'=>bin2hex(random_bytes(8)),'password'=>'fixture']),
            fn()=>$this->migration->table('local_role_grants')->insert(['identity_id'=>PHP_INT_MAX,'role'=>'teacher']),
        ] as $operation){
            try{$operation();$this->fail('Missing retained identity accepted.');}
            catch(\Illuminate\Database\QueryException $error){$this->assertSame(1452,(int)$error->errorInfo[1]);}
        }
    }
    public function testExplicitSecureConfigurationIsRequired():void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LocalSessionAuthentication($this->db,$this->encrypter,'http://eval.test',5,3,60,new BcryptHasher());
    }
    public function testSessionPersistenceFailureCannotAcknowledgeSuccessfulLogin():void
    {
        $this->request('/auth/session');$old=$this->cookie;
        $trigger='auth_test_failure_'.$this->actor;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON local_sessions FOR EACH ROW BEGIN IF NEW.revoked_at IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected test persistence failure'; END IF; END");
        try {
            $this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password]);
            $this->fail('Persistence failure acknowledged as successful login.');
        } catch(\Illuminate\Database\QueryException $error){$this->assertSame(1644,(int)$error->errorInfo[1]);}
        finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame($old,$this->cookie);
        $this->assertFalse($this->db->getPdo()->inTransaction());
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testInactiveIdentityCannotEstablishSession():void
    {
        $this->migration->table('retained_identities')->where('id',$this->actor)->update(['credential_status'=>'removed']);
        $this->request('/auth/session');
        $this->assertSame(401,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password])->getStatusCode());
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    public function testMalformedCredentialInputAndUnapprovedAuthEndpointsFailClosed():void
    {
        $this->request('/auth/session');
        $this->assertSame(400,$this->request('/auth/login','POST',['login'=>[$this->login],'password'=>$this->password])->getStatusCode());
        $this->assertSame(404,$this->request('/auth/register','POST')->getStatusCode());
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
    }
    private function protocolProbe(bool $revokeBeforeLocking):Response
    {
        $label='Authenticated fixture '.bin2hex(random_bytes(6));
        $grade=$this->migration->table('grades')->insertGetId(['name'=>$label,
            'name_key'=>\App\Domain\Academic\AcademicNameKey::generate($label),'is_active'=>true]);
        return $this->request('/protocol-fixture','POST',[],null,function($actor)use($grade,$revokeBeforeLocking){
            if($revokeBeforeLocking){$this->migration->table('local_role_grants')->where('identity_id',$this->actor)->delete();}
            (new \App\Infrastructure\Persistence\Academic\AcademicWriteTransaction($this->db))->execute($actor->identityId,
                fn()=>new \App\Infrastructure\Persistence\Academic\AcademicLockSet(['grades'=>[$grade]]),
                // Re-read authority while the common guard and retained identity are locked, not from request role snapshots.
                fn($context)=>$this->db->table('local_role_grants')->where('identity_id',$context->actorId)->where('role','director_admin')->exists(),
                function()use($grade){
                    $this->db->table('grades')->where('id',$grade)->update(['is_active'=>false]);
                    return new \App\Infrastructure\Persistence\Academic\AcademicMutationResult('fixture',[
                        new \App\Infrastructure\Persistence\Academic\LifecycleEvent('grade',$grade,'deactivated','active','inactive'),
                    ]);
                });
            return new JsonResponse(['verified'=>true]);
        });
    }
    public function testAuthenticatedPermanentIdentityFlowsThroughU3ToRetainedLedger():void
    {
        $this->migration->table('local_role_grants')->where('identity_id',$this->actor)->delete();
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor,'role'=>'director_admin']);
        $this->authenticate();
        $before=(new \App\Domain\Academic\AcademicWriteGuard($this->db))->getCurrentOrdinal();
        $this->assertSame(200,$this->protocolProbe(false)->getStatusCode());
        $this->assertSame($before+1,(new \App\Domain\Academic\AcademicWriteGuard($this->db))->getCurrentOrdinal());
        $this->assertSame($this->actor,(int)$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor)->value('actor_id'));
        $this->migration->table('retained_identities')->where('id',$this->actor)->update(['credential_status'=>'removed']);
        $this->assertSame(401,$this->request('/protected')->getStatusCode());
        $this->assertSame(1,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor)->count());
    }
    public function testRoleRevokedAfterAuthenticationIsDeniedUnderU3Locks():void
    {
        $this->migration->table('local_role_grants')->where('identity_id',$this->actor)->delete();
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$this->actor,'role'=>'director_admin']);
        $this->authenticate();
        $before=(new \App\Domain\Academic\AcademicWriteGuard($this->db))->getCurrentOrdinal();
        try{$this->protocolProbe(true);$this->fail('Stale authenticated role authorized mutation.');}
        catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){$this->assertSame('validation_denied',$error->category);}
        $this->assertSame($before,(new \App\Domain\Academic\AcademicWriteGuard($this->db))->getCurrentOrdinal());
        $this->assertSame(0,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor)->count());
    }
    public function testBcryptSuffixTruncationCannotAuthenticateDifferentInput():void
    {
        $this->password=bin2hex(random_bytes(36));
        $this->migration->table('local_credentials')->where('id',$this->actor)->update([
            'password'=>(new BcryptHasher(['rounds'=>4]))->make($this->password),
        ]);
        $this->request('/auth/session');
        $this->assertSame(401,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password.'suffix'])->getStatusCode());
        $this->assertSame(200,$this->request('/auth/login','POST',['login'=>$this->login,'password'=>$this->password])->getStatusCode());
    }
}
