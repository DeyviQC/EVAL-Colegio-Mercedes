<?php
declare(strict_types=1);
// Development-only upstream. Never serve files or infer TLS from client forwarding headers.
$secret=getenv('EVAL_PROXY_KEY');
if(getenv('EVAL_UNIT')!=='U11' || getenv('EVAL_DB_ROLE')!=='runtime' || $_SERVER['REMOTE_ADDR']!=='127.0.0.1'
    || !is_string($secret) || strlen($secret)!==64 || !hash_equals($secret,$_SERVER['HTTP_X_EVAL_PROXY_KEY']??'')){
    http_response_code(403);header('Content-Type: application/json');echo '{"error":"invalid_proxy_request"}';return;
}
try{
    require dirname(__DIR__).'/bootstrap.php';
    $origin=getenv('EVAL_RUNTIME_ORIGIN');$parts=parse_url($origin?:'');
    if(($parts['scheme']??null)!=='https'||($parts['host']??null)!=='127.0.0.1'||empty($parts['port'])||count($parts)!==3)throw new RuntimeException('Invalid isolated origin');
    $_SERVER['HTTPS']='on';$_SERVER['HTTP_HOST']='127.0.0.1:'.$parts['port'];$_SERVER['SERVER_PORT']=(string)$parts['port'];
    \Illuminate\Http\Request::setTrustedProxies([],0);
    $request=\Illuminate\Http\Request::capture();
    $db=\Illuminate\Database\Capsule\Manager::connection();
    $auth=new \App\Infrastructure\Authentication\LocalSessionAuthentication($db,
        new \Illuminate\Encryption\Encrypter(base64_decode(getenv('EVAL_RUNTIME_KEY')?:'',true),'AES-256-CBC'),
        $origin,5,5,60,new \Illuminate\Hashing\BcryptHasher(['rounds'=>4]));
    // No institutional profile binding or public U10/U11 writer. Default history fails closed.
    $compose=require dirname(__DIR__).'/routes/academic.php';
    $handler=$compose($auth,new \App\Http\Controllers\Academic\FoundationController($db,new \App\Infrastructure\Persistence\Academic\PersistedAcademicReferences($db)));
    $app=new \Illuminate\Foundation\Application(dirname(__DIR__));
    // Explicit harness providers only; do not auto-discover packages or write repository caches.
    $manifest=new \Illuminate\Foundation\PackageManifest(new \Illuminate\Filesystem\Filesystem,dirname(__DIR__),null);
    $manifest->manifest=[];$app->instance(\Illuminate\Foundation\PackageManifest::class,$manifest);
    $app->instance('config',new \Illuminate\Config\Repository(['app'=>['name'=>'EVAL','env'=>'testing','debug'=>false,'providers'=>[]]]));
    $app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class,new class implements \Illuminate\Contracts\Debug\ExceptionHandler {
        public function report(\Throwable $e){}
        public function shouldReport(\Throwable $e){return false;}
        public function render($request,\Throwable $e){return new \Symfony\Component\HttpFoundation\JsonResponse(['error'=>'local_runtime_unavailable','correlation_id'=>bin2hex(random_bytes(16)),'automatic_retry'=>false],503);}
        public function renderForConsole($output,\Throwable $e){$output->writeln('Isolated runtime unavailable');}
    });
    $router=$app->make('router');
    $router->any('/{path?}',static fn(\Illuminate\Http\Request $request)=>$handler($request))->where('path','.*');
    $kernel=new class($app,$router) extends \Illuminate\Foundation\Http\Kernel {
        protected $bootstrappers=[\Illuminate\Foundation\Bootstrap\RegisterFacades::class,\Illuminate\Foundation\Bootstrap\BootProviders::class];
    };
    $response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
}catch(\Throwable $e){
    http_response_code(503);header('Content-Type: application/json');
    echo json_encode(['error'=>'local_runtime_unavailable','correlation_id'=>bin2hex(random_bytes(16)),'automatic_retry'=>false]);
}