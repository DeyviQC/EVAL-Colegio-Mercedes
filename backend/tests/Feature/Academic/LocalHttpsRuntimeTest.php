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
        $backend=dirname(__DIR__,3);$ini=dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini';
        $this->start([PHP_BINARY,'-c',$ini,'-d','display_errors=0','-S','127.0.0.1:'.$this->upstream,$backend.'/dev-runtime/router.php'],$environment,'php');
        // Node gets no database credentials or encryption key.
        $nodeEnvironment=array_intersect_key($environment,array_flip(['PATH','Path','SystemRoot','TEMP','TMP','EVAL_PROXY_KEY']));
        $this->start(['node',$backend.'/dev-runtime/https-proxy.mjs',(string)$port,(string)$this->upstream,$this->directory.'/cert.pem',$this->directory.'/key.pem'],$nodeEnvironment,'node');
        $deadline=microtime(true)+10;
        do{$ready=str_contains((string)file_get_contents($this->directory.'/node.out'),'READY');if(!$ready)usleep(10000);}while(!$ready&&microtime(true)<$deadline);
        $this->assertTrue($ready,'Owned HTTPS listener must start.');
    }
    private function freePort():int
    {$socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);$this->assertIsResource($socket);$port=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);return $port;}
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
        if(isset($this->directory)){foreach(glob($this->directory.'/*') as $file)unlink($file);rmdir($this->directory);}
        parent::tearDown();
    }
}
