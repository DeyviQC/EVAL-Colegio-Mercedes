<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use App\Application\Academic\Queries\CourseWeeksQuery;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{AcademicTransactionFailure,LocalMaterialStorage};
use Illuminate\Database\Connection;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
final class CourseWeeksController {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 public function handle(Request $request,AuthenticatedActor $actor):JsonResponse {
  try{
   if(!$request->isMethod('GET')||$request->files->all()||trim($request->getContent())!==''||!preg_match('#^/academic/my-courses/([1-9][0-9]*)/weeks(?:/(material|activity))?$#',$request->getPathInfo(),$m))throw new AcademicCommandFailure('not_found');
   $q=$request->query->all();FoundationCommandChecks::fields($q,isset($m[2])?['bucket','revision','week','after','term']:[],isset($m[2])?['bucket']:[]);foreach($q as $value)if(!is_string($value))throw new AcademicCommandFailure('invalid_input');
   $service=new CourseWeeksQuery($this->db,$this->storage);$data=isset($m[2])?$service->content($actor,$m[1],$m[2],$q['bucket'],$q['revision']??null,$q['week']??null,$q['after']??null,$q['term']??''):$service->calendar($actor,$m[1]);
   $response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','private, no-store');return $response;
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
  catch(AcademicCommandFailure|AcademicTransactionFailure $error){return FoundationController::failure($error);}
  catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
