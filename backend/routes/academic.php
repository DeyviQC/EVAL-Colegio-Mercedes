<?php
declare(strict_types=1);
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use App\Http\Controllers\Academic\FoundationController;
use Symfony\Component\HttpFoundation\Request;
/** Explicit composition port for the isolated adapter; no Laravel kernel/listener is installed. */
return static function(LocalSessionAuthentication $authentication,FoundationController $controller):Closure {
    return static fn(Request $request)=>$authentication->handle($request,function($actor)use($request,$controller){
        if(preg_match('#^/academic/(?:my-courses/[1-9][0-9]*/materials|materials/[1-9][0-9]*(?:/file)?)$#',$request->getPathInfo())){
            $root=getenv('EVAL_MATERIAL_ROOT');if(!$root)return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));
            try{$storage=new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($root);
                return (new \App\Http\Controllers\Academic\MaterialController(\Illuminate\Database\Capsule\Manager::connection(),$storage))->handle($request,$actor);
            }catch(\Throwable){return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));}
        }return $controller->handle($request,$actor);
    });
};
