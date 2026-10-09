<?php
declare(strict_types=1);
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use App\Http\Controllers\Academic\FoundationController;
use Symfony\Component\HttpFoundation\Request;
/** Explicit composition port for the isolated adapter; no Laravel kernel/listener is installed. */
return static function(LocalSessionAuthentication $authentication,FoundationController $controller):Closure {
    return static fn(Request $request)=>$authentication->handle($request,function($actor)use($request,$controller){
        if(preg_match('#^/academic/my-courses/[1-9][0-9]*/weeks(?:/(?:material|activity))?$#',$request->getPathInfo())){
            $root=getenv('EVAL_MATERIAL_ROOT');if(!$root)return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));
            try{return (new \App\Http\Controllers\Academic\CourseWeeksController(\Illuminate\Database\Capsule\Manager::connection(),new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($root)))->handle($request,$actor);}catch(\Throwable){return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));}
        }
        if(str_starts_with($request->getPathInfo(),'/academic/resource-weeks/')){
            $root=getenv('EVAL_MATERIAL_ROOT');if(!$root)return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));
            try{return (new \App\Http\Controllers\Academic\ResourceWeekController(\Illuminate\Database\Capsule\Manager::connection(),new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($root)))->handle($request,$actor);}catch(\Throwable){return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));}
        }
        if(preg_match('#^/academic/(?:periods/[1-9][0-9]*/(?:calendar|calendar-drafts)|calendar-drafts/[1-9][0-9]*(?:/(?:edit|publish))?|calendar-revisions/[1-9][0-9]*)$#',$request->getPathInfo()))return (new \App\Http\Controllers\Academic\SchoolCalendarController(\Illuminate\Database\Capsule\Manager::connection()))->handle($request,$actor);
        if(str_starts_with($request->getPathInfo(),'/supervision/')){
            $root=getenv('EVAL_MATERIAL_ROOT');if(!$root)return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));
            return (new \App\Http\Controllers\Academic\MaterialObservationsController(\Illuminate\Database\Capsule\Manager::connection(),new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($root)))->handle($request,$actor);
        }
        if($request->getPathInfo()==='/notifications'||str_starts_with($request->getPathInfo(),'/notifications/'))return (new \App\Http\Controllers\Academic\AcademicNotificationsController(\Illuminate\Database\Capsule\Manager::connection()))->handle($request,$actor);
        if(str_starts_with($request->getPathInfo(),'/reports/'))return (new \App\Http\Controllers\Academic\ClassroomReportController(\Illuminate\Database\Capsule\Manager::connection()))->handle($request,$actor);
        if(str_starts_with($request->getPathInfo(),'/education/')){
            $root=getenv('EVAL_MATERIAL_ROOT');if(!$root)return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));
            try{return (new \App\Http\Controllers\Academic\ActivityDeliveryController(\Illuminate\Database\Capsule\Manager::connection(),new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage(dirname($root).DIRECTORY_SEPARATOR.'deliveries')))->handle($request,$actor);}catch(\Throwable){return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));}
        }
        if(preg_match('#^/(?:users(?:/.*)?|account(?:/.*)?)$#',$request->getPathInfo()))return (new \App\Http\Controllers\LocalAccountController(\Illuminate\Database\Capsule\Manager::connection()))->handle($request,$actor);
        if(preg_match('#^/academic/(?:my-courses/[1-9][0-9]*/(?:materials|library)|materials/[1-9][0-9]*(?:/(?:file|file-versions|replace-file|maintenance|revisions|edit|withdraw|restore))?)$#',$request->getPathInfo())){
            $root=getenv('EVAL_MATERIAL_ROOT');if(!$root)return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));
            try{$storage=new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($root);
                return (new \App\Http\Controllers\Academic\MaterialController(\Illuminate\Database\Capsule\Manager::connection(),$storage))->handle($request,$actor);
            }catch(\Throwable){return FoundationController::failure(new \App\Infrastructure\Persistence\Academic\AcademicTransactionFailure('storage_unavailable',bin2hex(random_bytes(16))));}
        }return $controller->handle($request,$actor);
    });
};
