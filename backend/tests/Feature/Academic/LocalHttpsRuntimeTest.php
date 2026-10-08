<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use Illuminate\Hashing\BcryptHasher;

final class LocalHttpsRuntimeTest extends SubmissionReferenceTestCase
{
    private array $workers=[];
    private string $directory;
    private string $origin;
    private int $upstream;
    private ?string $cookie=null;
    private ?string $csrf=null;
    protected function setUp():void
    {
        parent::setUp();$this->directory=sys_get_temp_dir().'/eval-https-'.bin2hex(random_bytes(12));mkdir($this->directory);
        $this->upstream=$this->freePort();do{$port=$this->freePort();}while($port===$this->upstream);$this->origin='https://127.0.0.1:'.$port;
        $config="[req]\ndistinguished_name=dn\nx509_extensions=ext\n[dn]\n[ext]\nsubjectAltName=IP:127.0.0.1\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n";
        file_put_contents($this->directory.'/openssl.cnf',$config);
        $options=['config'=>$this->directory.'/openssl.cnf','private_key_bits'=>2048,'digest_alg'=>'sha256'];
        $key=openssl_pkey_new($options);$csr=openssl_csr_new(['commonName'=>'EVAL isolated loopback'],$key,$options);
        $cert=openssl_csr_sign($csr,null,$key,1,$options);$this->assertNotFalse($cert);
        openssl_x509_export($cert,$pem);openssl_pkey_export($key,$private,null,$options);
        file_put_contents($this->directory.'/cert.pem',$pem);file_put_contents($this->directory.'/key.pem',$private);
        $environment=getenv();unset($environment['EVAL_U1_MIGRATION_PASSWORD']);
        $environment['EVAL_PROXY_KEY']=bin2hex(random_bytes(32));$environment['EVAL_RUNTIME_KEY']=base64_encode(random_bytes(32));
        $environment['EVAL_RUNTIME_ORIGIN']=$this->origin;
        $environment['EVAL_UI_ENABLED']='1';
        $environment['EVAL_MATERIAL_ROOT']=$this->directory.'/private-materials';
        $backend=dirname(__DIR__,3);$ini=dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini';
        $this->start([PHP_BINARY,'-c',$ini,'-d','display_errors=0','-d','upload_max_filesize=25M','-d','post_max_size=27M','-S','127.0.0.1:'.$this->upstream,$backend.'/dev-runtime/router.php'],$environment,'php');
        // Node gets no database credentials or encryption key.
        $nodeEnvironment=array_intersect_key($environment,array_flip(['PATH','Path','SystemRoot','TEMP','TMP','EVAL_PROXY_KEY','EVAL_UI_ENABLED']));
        $this->start(['node',$backend.'/dev-runtime/https-proxy.mjs',(string)$port,(string)$this->upstream,$this->directory.'/cert.pem',$this->directory.'/key.pem'],$nodeEnvironment,'node');
        $deadline=microtime(true)+10;
        do{$ready=str_contains((string)file_get_contents($this->directory.'/node.out'),'READY');if(!$ready)usleep(10000);}while(!$ready&&microtime(true)<$deadline);
        $this->assertTrue($ready,'Owned HTTPS listener must start.');
    }
    private function freePort():int
    {$socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);$this->assertIsResource($socket);$port=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);return $port;}
    public function testExplicitTemporaryHumanPreview():void
    {
        if(getenv('EVAL_HUMAN_PREVIEW')!=='1')$this->markTestSkipped('Explicit human preview only.');
        $demo=$this->materialAccounts();$password='EVAL-demo-local-2026';
        foreach($demo as $account)$this->migration->table('local_credentials')->where('login',$account['login'])->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($password)]);
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($password)]);
        fwrite(STDOUT,"PREVIEW_URL=".$this->origin."\nPREVIEW_LOGIN=fixture-".$this->actor->identityId."\n");
        foreach($demo as $account)fwrite(STDOUT,'PREVIEW_'.$account['role'].'='.$account['login']."\n");
        // Human-requested disposable preview; owned workers terminate in teardown.
        $deadline=microtime(true)+1800;while(microtime(true)<$deadline&&!is_file($this->directory.'/stop'))usleep(250000);
        $this->assertTrue(true);
    }
    public function testApprovedBrowserSessionAndCertificateBoundary():void
    {
        if(getenv('EVAL_RUN_BROWSER')!=='1')$this->markTestSkipped('Explicit browser invocation required.');
        $frontend=dirname(__DIR__,4).'/frontend';
        $this->assertFileExists($frontend.'/dist/index.html');
        $unrelatedEnrollment=$this->enrollment();
        $demo=$this->materialAccounts();
        $password=bin2hex(random_bytes(16));
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($password)]);
        $public=openssl_pkey_get_details(openssl_pkey_get_public((string)file_get_contents($this->directory.'/cert.pem')))['key'];
        $der=base64_decode(preg_replace('/-----[^-]+-----|\s/','',$public),true);$this->assertNotFalse($der);
        $environment=array_intersect_key(getenv(),array_flip(['PATH','Path','SystemRoot','TEMP','TMP','LOCALAPPDATA','PLAYWRIGHT_BROWSERS_PATH']));
        $environment['EVAL_UI_URL']=$this->origin;$environment['EVAL_TLS_SPKI']=base64_encode(hash('sha256',$der,true));
        $environment['EVAL_BROWSER_LOGIN']='fixture-'.$this->actor->identityId;$environment['EVAL_BROWSER_PASSWORD']=$password;
        $accounts=[];
        foreach(['director_admin','vice_principal','teacher','student'] as $role){
            $actor=match($role){'director_admin'=>$this->actor,'teacher'=>$this->teacherActor,'student'=>$this->studentActor,default=>$this->actor($role)};$secret=bin2hex(random_bytes(16));
            $this->migration->table('local_credentials')->where('id',$actor->identityId)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($secret)]);
            $accounts[]=['role'=>$role,'login'=>'fixture-'.$actor->identityId,'password'=>$secret];
            if($role==='director_admin'){$environment['EVAL_BROWSER_PASSWORD']=$secret;}
        }
        $environment['EVAL_BROWSER_ACCOUNTS']=json_encode($accounts,JSON_THROW_ON_ERROR);
        $environment['EVAL_MATERIAL_DEMO_ACCOUNTS']=json_encode($demo,JSON_THROW_ON_ERROR);
        $environment['EVAL_BROWSER_DENIED_ENROLLMENT']=$unrelatedEnrollment;
        $environment['EVAL_BROWSER_ACTIVE_PERIOD']=$this->period;
        $this->migration->table('retained_identity_profiles')->insert(['identity_id'=>$this->teacherActor->identityId,'display_name'=>'Synthetic course teacher']);
        $worker=proc_open(['node',$frontend.'/node_modules/playwright/cli.js','test'],
            [0=>['file','NUL','r'],1=>['file',$this->directory.'/browser.out','w'],2=>['file',$this->directory.'/browser.err','w']],$pipes,$frontend,$environment);
        $this->assertIsResource($worker);$this->workers[]=$worker;$deadline=microtime(true)+120;
        do{$status=proc_get_status($worker);if($status['running'])usleep(100000);}while($status['running']&&microtime(true)<$deadline);
        $this->assertFalse($status['running'],'Owned browser runner exceeded its deadline.');
        $diagnostics=(string)file_get_contents($this->directory.'/browser.out').(string)file_get_contents($this->directory.'/browser.err');
        $diagnostics=preg_replace('/(__Host-eval_session=)[^\s;]+/','$1[redacted]',$diagnostics);
        foreach($accounts as $account)$diagnostics=str_replace([$account['password'],$account['login']],['[redacted]','[fixture]'],$diagnostics);
        foreach($demo as $account)$diagnostics=str_replace([$account['password'],$account['login']],['[redacted]','[fixture]'],$diagnostics);
        $this->assertSame(0,$status['exitcode'],'Browser suite failed: '.$diagnostics);
        $output=(string)file_get_contents($this->directory.'/browser.out');
        $this->assertStringContainsString('11 passed',$output);fwrite(STDOUT,"Browser suite: 11 passed (actual Chromium HTTPS two-session material publication/download and regressions).\n");
    }
    private function materialAccounts():array {
        $number=$this->db->table('academic_periods')->where('name','like','Curso escolar 2026 · Demostración %')->count()+1;
        $periodName='Curso escolar 2026 · Demostración '.$number;
        $this->migration->table('academic_periods')->where('id',$this->period)->update(['name'=>$periodName,'name_key'=>\App\Domain\Academic\AcademicNameKey::generate($periodName)]);
        $teacher=$this->actor('teacher');$student=$this->actor('student');
        $catalog=function($type,$name,$scope=[]){$table=match($type){'entry'=>'instructional_entries','grade'=>'grades','section'=>'sections'};
            $query=$this->db->table($table)->where('name_key',\App\Domain\Academic\AcademicNameKey::generate($name))->where('is_active',true);foreach($scope as $field=>$value)$query->where($field,$value);
            return (string)($query->value('id')??$this->catalog->create($this->actor,$type,['name'=>$name]+$scope));};
        $entry=$catalog('entry','Matemática',['kind'=>'subject']);$grade=$catalog('grade','2.º');$section=$catalog('section','B',['grade_id'=>$grade]);
        $assignment=$this->assignment(['teacher_id'=>$teacher->identityId,'instructional_entry_id'=>$entry,'grade_id'=>$grade,'section_id'=>$section]);$this->assignments->activate($this->actor,$assignment);
        $this->enrollment(['student_id'=>$student->identityId,'grade_id'=>$grade,'section_id'=>$section]);$accounts=[];
        foreach([['teacher',$teacher,'Lucía Torres (demo)'],['student',$student,'Ana Flores (demo)']] as [$role,$actor,$name]){
            $secret=bin2hex(random_bytes(16));$login='fixture-'.$actor->identityId;
            $this->migration->table('retained_identity_profiles')->insert(['identity_id'=>$actor->identityId,'display_name'=>$name]);
            $this->migration->table('local_credentials')->where('id',$actor->identityId)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($secret)]);
            $accounts[]=['role'=>$role,'login'=>$login,'password'=>$secret];
        }return $accounts;
    }
    private function start(array $command,array $environment,string $name):void
    {
        $worker=proc_open($command,[0=>['file','NUL','r'],1=>['file',$this->directory.'/'.$name.'.out','w'],2=>['file',$this->directory.'/'.$name.'.err','w']],$pipes,null,$environment);
        $this->assertIsResource($worker);$this->workers[]=$worker;
    }
    private function request(string $path,string $method='GET',array $body=[],array $extra=[]):array
    {
        $headers=['Origin'=>$this->origin,'Content-Type'=>'application/json','Connection'=>'close'];
        if($this->cookie!==null)$headers['Cookie']=$this->cookie;
        if($this->csrf!==null)$headers['X-CSRF-TOKEN']=$this->csrf;
        $headers=array_replace($headers,$extra);$lines=[];foreach($headers as $key=>$value)$lines[]=$key.': '.$value;
        $context=stream_context_create(['ssl'=>['cafile'=>$this->directory.'/cert.pem','verify_peer'=>true,'verify_peer_name'=>true],
            'http'=>['method'=>$method,'header'=>implode("\r\n",$lines),'content'=>$method==='GET'?'':json_encode((object)$body),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]);
        $wire=file_get_contents($this->origin.$path,false,$context);$this->assertNotFalse($wire);
        preg_match('/HTTP\/\S+ (\d+)/',$http_response_header[0],$status);
        foreach($http_response_header as $header){if(str_starts_with(strtolower($header),'set-cookie:')){
            $cookie=trim(substr($header,11));$this->cookie=explode(';',$cookie)[0];
            $this->assertStringContainsString('secure',strtolower($cookie));$this->assertStringContainsString('httponly',strtolower($cookie));$this->assertStringContainsString('samesite=lax',strtolower($cookie));}}
        $data=json_decode($wire,true,flags:JSON_THROW_ON_ERROR);$this->csrf=$data['csrf_token']??$this->csrf;
        return [(int)$status[1],$data];
    }
    private function login(string $id):void
    {
        $password=bin2hex(random_bytes(16));$this->migration->table('local_credentials')->where('id',$id)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make($password)]);
        $this->assertSame(200,$this->request('/auth/session')[0]);
        $this->assertSame(200,$this->request('/auth/login','POST',['login'=>'fixture-'.$id,'password'=>$password])[0]);
    }
    public function testRealTlsSessionScopeMutationCsrfAndCredentialRevocation():void
    {
        $submission=$this->accept();$otherEnrollment=$this->enrollment();
        $first=$this->request('/academic/periods/'.$this->period);$this->assertSame(401,$first[0],json_encode($first));$this->login($this->actor->identityId);
        [$status,$view]=$this->request('/academic/periods/'.$this->period);$this->assertSame(200,$status);$this->assertSame($this->period,$view['data']['id']);
        $key=$this->ordinal();$this->assertSame(419,$this->request('/academic/periods/'.$this->period.'/close','POST',[],['X-CSRF-TOKEN'=>'forged'])[0]);$this->assertSame($key,$this->ordinal());
        $this->assertSame(200,$this->request('/academic/periods/'.$this->period.'/close','POST')[0]);
        $this->assertSame('closed',$this->db->table('academic_periods')->where('id',$this->period)->value('state'));
        [$historyStatus,$historyError]=$this->request('/academic/submissions/'.$submission);
        $this->assertSame(503,$historyStatus);$this->assertSame('reference_unavailable',$historyError['error']);
        $this->assertFalse($historyError['automatic_retry']);
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>(new BcryptHasher(['rounds'=>4]))->make(bin2hex(random_bytes(16)))]);
        $first=$this->request('/academic/periods/'.$this->period);$this->assertSame(401,$first[0],json_encode($first));
        $this->login($this->studentActor->identityId);
        [$ownStatus,$own]=$this->request('/academic/enrollments/'.$this->enrollmentId);
        $this->assertSame(200,$ownStatus);$this->assertSame($this->studentActor->identityId,$own['data']['student_id']);
        $this->assertSame(404,$this->request('/academic/enrollments/'.$otherEnrollment)[0]);
    }
    public function testTlsTrustAndForgedProxyOriginAndDirectUpstreamAreDenied():void
    {
        $this->assertFalse(@file_get_contents($this->origin.'/auth/session',false,stream_context_create(['http'=>['timeout'=>3]])),'Untrusted test certificate must not be accepted by default.');
        foreach(['Forwarded','X-Forwarded-Proto','X-Forwarded-Host','X-Eval-Proxy-Key','X-HTTP-Method-Override'] as $header)$this->assertSame(400,$this->request('/auth/session','GET',[],[$header=>'forged'])[0]);
        $this->assertSame(400,$this->request('/auth/session','GET',[],['Host'=>'outside.test'])[0]);
        $this->assertSame(403,$this->request('/auth/login','POST',[],['Origin'=>'https://outside.test'])[0]);
        $wire=file_get_contents('http://127.0.0.1:'.$this->upstream.'/auth/session',false,stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>3]]));
        $this->assertSame(['error'=>'invalid_proxy_request'],json_decode($wire,true));
    }
    public function testActualSessionPersistenceFaultIsSanitizedByKernel():void
    {
        $this->migration->unprepared("CREATE TRIGGER eval_https_session_fail BEFORE INSERT ON local_sessions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected isolated persistence fault'");
        try {
            [$status,$error]=$this->request('/auth/session');$this->assertSame(503,$status);
            $this->assertSame(['error','correlation_id','automatic_retry'],array_keys($error));
            $this->assertSame('local_runtime_unavailable',$error['error']);$this->assertFalse($error['automatic_retry']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/',$error['correlation_id']);
            $this->assertFalse($this->db->getPdo()->inTransaction());
        }finally{$this->migration->unprepared('DROP TRIGGER eval_https_session_fail');}
    }
    protected function tearDown():void
    {
        foreach(array_reverse($this->workers) as $worker){if(proc_get_status($worker)['running'])proc_terminate($worker);proc_close($worker);}
        if(isset($this->directory))(new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }
    public function testBuiltUiAssetsUseSameVerifiedTlsOriginWithoutExternalReferences():void
    {
        $dist=dirname(__DIR__,4).'/frontend/dist';
        if(!is_file($dist.'/index.html'))$this->markTestSkipped('Run frontend npm run build before UI asset integration.');
        $context=stream_context_create(['ssl'=>['cafile'=>$this->directory.'/cert.pem','verify_peer'=>true,'verify_peer_name'=>true],
            'http'=>['ignore_errors'=>true,'timeout'=>5]]);
        $html=file_get_contents($this->origin.'/',false,$context);$this->assertNotFalse($html);$this->assertStringContainsString('<title>EVAL</title>',$html);
        preg_match_all('/(?:src|href)="([^"]+)"/',$html,$references);$this->assertCount(2,$references[1]);
        foreach($references[1] as $path){
            $this->assertMatchesRegularExpression('#^/assets/[A-Za-z0-9_-]+\.(js|css)$#',$path);
            $asset=file_get_contents($this->origin.$path,false,$context);$this->assertNotFalse($asset);$this->assertNotEmpty($asset);
            $this->assertStringContainsString('200',$http_response_header[0]);
            $this->assertContains('X-Content-Type-Options: nosniff',$http_response_header);
        }
        $traversal=file_get_contents($this->origin.'/assets/../../../backend/composer.json',false,$context);
        $this->assertStringNotContainsString('eval/academic-foundation',$traversal);
    }
    public function testRealHttpsMultipartTeacherPublishesStudentDownloadsAndUnknownStudentIsDenied():void {
        $this->migration->table('retained_identity_profiles')->insert(['identity_id'=>$this->teacherActor->identityId,'display_name'=>'Synthetic HTTPS material teacher']);
        $source=$this->directory.'/material.pdf';$bytes="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";file_put_contents($source,$bytes);
        $call=function(string $path,?array $form=null):array {
            $curl=curl_init($this->origin.$path);$headers=['Origin: '.$this->origin];if($form!==null)$headers[]='X-CSRF-TOKEN: '.$this->csrf;
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$this->directory.'/cert.pem',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>5,CURLOPT_COOKIE=>$this->cookie??'',CURLOPT_HTTPHEADER=>$headers]);
            if($form!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$form);$wire=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$mime=curl_getinfo($curl,CURLINFO_CONTENT_TYPE);curl_close($curl);
            $this->assertNotFalse($wire);return [$status,$wire,$mime];
        };
        $this->login($this->teacherActor->identityId);
        [$status,$wire]=$call('/academic/my-courses/'.$this->assignmentId.'/materials',['title'=>'Fracciones HTTPS','file'=>new \CURLFile($source,'application/octet-stream','fracciones.pdf')]);
        $this->assertSame(201,$status,$wire);$id=json_decode($wire,true)['data']['id'];
        $this->login($this->studentActor->identityId);[$status,$wire]=$call('/academic/my-courses/'.$this->assignmentId.'/materials');
        $this->assertSame(200,$status,$wire);$this->assertSame($id,json_decode($wire,true)['data']['items'][0]['id']);
        [$status,$wire,$mime]=$call('/academic/materials/'.$id.'/file?disposition=inline');$this->assertSame(200,$status);$this->assertSame($bytes,$wire);$this->assertSame('application/pdf',$mime);
        [$status]=$call('/academic/materials/'.$id.'/file?scope=other');$this->assertSame(422,$status);
        $other=$this->actor('student');$this->login($other->identityId);[$status]=$call('/academic/materials/'.$id.'/file');$this->assertSame(404,$status);
    }
}
