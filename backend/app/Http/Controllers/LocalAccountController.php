<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Illuminate\Database\Connection;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Accounts\LocalAccountService;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
use App\Http\Controllers\Academic\FoundationController;
final class LocalAccountController {
    public function __construct(private Connection $db){}
    public function handle(Request $request,?AuthenticatedActor $actor):JsonResponse {
        try{
            if(!$actor)throw new AcademicCommandFailure('unauthenticated');$service=new LocalAccountService($this->db);$path=$request->getPathInfo();$raw=trim($request->getContent());$input=[];
            if($raw!==''){try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}if(!str_starts_with($raw,'{')||!is_array($input))throw new AcademicCommandFailure('invalid_input');}
            if($request->isMethod('GET')&&$input===[]){
                if($path==='/account'&&$request->query->all()===[])$data=$service->own($actor);
                elseif($path==='/users')$data=$service->directory($actor,$request->query->all());else throw new AcademicCommandFailure('not_found');
            }elseif($request->isMethod('POST')&&$request->query->all()===[]){
                if($path==='/users')$data=$service->execute($actor,'created',null,$input);
                elseif($path==='/account/password')$data=$service->execute($actor,'password_changed',null,$input);
                elseif(preg_match('#^/users/([1-9][0-9]*)/(reset-password|deactivate|reactivate)$#D',$path,$m)){
                    try{$id=\App\Infrastructure\Persistence\Academic\AcademicLockSet::id($m[1]);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}
                    $data=$service->execute($actor,match($m[2]){'reset-password'=>'password_reset','deactivate'=>'deactivated','reactivate'=>'reactivated'},$id,$input);
                }else throw new AcademicCommandFailure('not_found');
            }else throw new AcademicCommandFailure('invalid_input');
            $response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','no-store, private');return $response;
        }catch(AcademicCommandFailure|AcademicTransactionFailure $e){return FoundationController::failure($e);}
        catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
    }
}
