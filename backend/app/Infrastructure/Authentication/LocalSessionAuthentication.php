<?php

declare(strict_types=1);
namespace App\Infrastructure\Authentication;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieJar;
use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Session\EncryptedStore;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Request-scoped authentication adapter; no listener, registration or academic endpoint. */
final class LocalSessionAuthentication
{
    public const COOKIE='__Host-eval_session';

    public function __construct(private Connection $db,private Encrypter $encrypter,private string $origin,
        private int $minutes,private int $loginAttempts,private int $loginWindow,private BcryptHasher $hasher)
    {
        $parts=parse_url($origin);
        if(($parts['scheme']??null)!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || $minutes<1 || $loginAttempts<1 || $loginWindow<1){
            throw new \InvalidArgumentException('Explicit HTTPS origin and positive session/throttle settings required.');
        }
    }

    public function handle(Request $request,Closure $next):Response
    {
        if($request->getSchemeAndHttpHost()!==$this->origin){return $this->error('invalid_origin',403);}
        $unsafe=!in_array($request->getMethod(),['GET','HEAD','OPTIONS'],true);
        if($unsafe && $request->headers->get('Origin')!==$this->origin){return $this->error('invalid_origin',403);}
        $handler=new LocalSessionHandler($this->db,'local_sessions',$this->minutes);
        $id=null;
        try {
            $cookie=$request->cookies->get(self::COOKIE);
            if(is_string($cookie)){
                $candidate=$this->encrypter->decryptString($cookie);
                if(preg_match('/^[A-Za-z0-9]{40}$/',$candidate) && $handler->usable($candidate)){$id=$candidate;}
            }
        } catch(\Illuminate\Contracts\Encryption\DecryptException){} // Untrusted cookie has no authority.
        $session=new EncryptedStore(self::COOKIE,$handler,$this->encrypter,$id,'json');
        $session->start();
        $provider=new LocalUserProvider($this->db,$this->hasher,'local_credentials');
        $guard=new SessionGuard('eval_local',$provider,$session,$request,rehashOnLogin:false);
        $guard->setCookieJar(new CookieJar());
        $user=$guard->user();
        if($session->has($guard->getName()) && (!$user || !hash_equals(
            (string)$session->get('credential_revision',''),$provider->actor($user)->credentialRevision()))) {
            $guard->logoutCurrentDevice();$session->invalidate();$session->regenerateToken();$user=null;
        }
        if($unsafe && (!is_string($token=$request->headers->get('X-CSRF-TOKEN')) || !hash_equals($session->token(),$token))){
            return $this->finish($session,$this->error('csrf_mismatch',419));
        }
        $path=$request->getPathInfo();
        if($path==='/auth/session' && $request->isMethod('GET')){
            $response=new JsonResponse(['authenticated'=>$user!==null,'csrf_token'=>$session->token()]);
        } elseif($path==='/auth/login' && $request->isMethod('POST')){
            $response=$this->login($request,$guard,$session);
        } elseif($path==='/auth/logout' && $request->isMethod('POST')){
            $guard->logoutCurrentDevice();$session->invalidate();$session->regenerateToken();
            $response=new JsonResponse(['authenticated'=>false,'csrf_token'=>$session->token()]);
        } elseif(str_starts_with($path,'/auth/')){
            $response=$this->error('not_found',404);
        } else {
            $response=$user?$next($provider->actor($user)):$this->error('unauthenticated',401);
        }
        return $this->finish($session,$response);
    }

    private function login(Request $request,SessionGuard $guard,EncryptedStore $session):Response
    {
        // No forwarded IP header is trusted by this adapter.
        $limit=hash('sha256',$this->origin.'|'.($request->getClientIp()??'unknown'));
        $allowed=$this->db->transaction(function()use($limit){
            $table=$this->db->table('local_login_limits');
            $table->insertOrIgnore(['id'=>$limit,'attempts'=>0,'window_started'=>time()]);
            $row=$table->where('id',$limit)->lockForUpdate()->first();
            $attempts=(time()-(int)$row->window_started>=$this->loginWindow)?1:(int)$row->attempts+1;
            $table->where('id',$limit)->update(['attempts'=>$attempts,
                'window_started'=>$attempts===1?time():$row->window_started]);
            return $attempts<=$this->loginAttempts;
        });
        if(!$allowed){return $this->error('too_many_attempts',429);}
        try{$body=json_decode($request->getContent(),true,flags:JSON_THROW_ON_ERROR);}
        catch(\JsonException){return $this->error('invalid_request',400);}
        if(!is_array($body) || !is_string($body['login']??null) || !is_string($body['password']??null)){
            return $this->error('invalid_request',400);
        }
        // Client actor IDs and roles never reach the credential provider.
        if(!$guard->attempt(['login'=>$body['login'],'password'=>$body['password']],false)){
            return $this->error('invalid_credentials',401);
        }
        $session->put('credential_revision',CredentialRevision::current($this->db,(string)$guard->user()->getAuthIdentifier(),$guard->user()->getAuthPassword()));
        $session->regenerateToken();
        $this->db->table('local_login_limits')->where('id',$limit)->update(['attempts'=>0,'window_started'=>time()]);
        return new JsonResponse(['authenticated'=>true,'csrf_token'=>$session->token()]);
    }

    private function finish(EncryptedStore $session,Response $response):Response
    {
        // Persist before issuing an authentication response/cookie; failures propagate without acknowledgement.
        $session->save();
        $response->headers->setCookie(Cookie::create(self::COOKIE,$this->encrypter->encryptString($session->getId()),
            0,'/',null,true,true,false,Cookie::SAMESITE_LAX));
        $response->headers->set('Cache-Control','no-store, private');
        return $response;
    }

    private function error(string $category,int $status):Response {return new JsonResponse(['error'=>$category],$status);}
}
