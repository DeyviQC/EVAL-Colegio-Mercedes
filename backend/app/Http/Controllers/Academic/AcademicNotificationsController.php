<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\{AcademicNotifications,FoundationCommandChecks};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
final class AcademicNotificationsController {
 public function __construct(private Connection $db){}
 public function handle(Request $r,AuthenticatedActor $actor):Response {
  try{$service=new AcademicNotifications($this->db);
   if($r->isMethod('GET')&&$r->getPathInfo()==='/notifications'){FoundationCommandChecks::fields($r->query->all(),['before']);if($r->getContent()!==''||$r->request->all()||$r->files->all())throw new AcademicCommandFailure('invalid_input');$before=$r->query->all()['before']??null;if($before!==null&&!is_string($before))throw new AcademicCommandFailure('invalid_input');$data=$service->feed($actor,$before);}
   elseif($r->isMethod('POST')&&$r->getPathInfo()==='/notifications/read'){if($r->query->all()||$r->files->all())throw new AcademicCommandFailure('invalid_input');$raw=trim($r->getContent());try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}if(!str_starts_with($raw,'{')||!is_array($input))throw new AcademicCommandFailure('invalid_input');$data=$service->markRead($actor,$input);}
   else throw new AcademicCommandFailure('not_found');$response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','no-store, private');return $response;
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
  catch(AcademicCommandFailure|AcademicTransactionFailure $e){return FoundationController::failure($e);}
  catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
