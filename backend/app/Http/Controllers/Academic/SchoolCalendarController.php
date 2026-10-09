<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use App\Application\Academic\Commands\{SchoolCalendarCommands,FoundationCommandChecks};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
use Illuminate\Database\Connection;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
final class SchoolCalendarController
{
 public function __construct(private Connection $db){}
 public function handle(Request $request,AuthenticatedActor $actor):JsonResponse {
  try{
   if($request->files->all())throw new AcademicCommandFailure('invalid_input');
   $service=new SchoolCalendarCommands($this->db);$path=$request->getPathInfo();$status=200;
   if($request->isMethod('GET')){
    $draftList=preg_match('#^/academic/periods/([1-9][0-9]*)/calendar-drafts$#',$path,$list);
    FoundationCommandChecks::fields($request->query->all(),$draftList?['after']:[]);
    if(trim($request->getContent())!=='')throw new AcademicCommandFailure('invalid_input');
    if($draftList){$after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');$data=$service->drafts($actor,$list[1],$after);}
    elseif(preg_match('#^/academic/periods/([1-9][0-9]*)/calendar$#',$path,$m))$data=$service->current($actor,$m[1]);
    elseif(preg_match('#^/academic/calendar-drafts/([1-9][0-9]*)$#',$path,$m))$data=$service->draft($actor,$m[1]);
    elseif(preg_match('#^/academic/calendar-revisions/([1-9][0-9]*)$#',$path,$m))$data=$service->revision($actor,$m[1]);
    else throw new AcademicCommandFailure('not_found');
   }elseif($request->isMethod('POST')){
    FoundationCommandChecks::fields($request->query->all(),[]);
    $raw=trim($request->getContent());if(strlen($raw)>262144||!str_starts_with($raw,'{'))throw new AcademicCommandFailure('invalid_input');
    try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}
    if(!is_array($input))throw new AcademicCommandFailure('invalid_input');
    if(preg_match('#^/academic/periods/([1-9][0-9]*)/calendar-drafts$#',$path,$m)){$data=$service->create($actor,$m[1],$input);$status=201;}
    elseif(preg_match('#^/academic/calendar-drafts/([1-9][0-9]*)/(edit|publish)$#',$path,$m))$data=$service->{$m[2]}($actor,$m[1],$input);
    else throw new AcademicCommandFailure('not_found');
   }else throw new AcademicCommandFailure('not_found');
   $response=new JsonResponse(['data'=>$data],$status);$response->headers->set('Cache-Control','private, no-store');return $response;
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
  catch(AcademicCommandFailure|AcademicTransactionFailure $error){return FoundationController::failure($error);}
  catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
