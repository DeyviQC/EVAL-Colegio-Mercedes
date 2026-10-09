<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\Queries\ClassroomReport;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
final class ClassroomReportController {
 public function __construct(private Connection $db){}
 public function handle(Request $request,AuthenticatedActor $actor):Response {
  try{
   if($request->getPathInfo()!=='/reports/classroom')throw new AcademicCommandFailure('not_found');
   if(!$request->isMethod('GET')||$request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
   $input=$request->query->all();FoundationCommandChecks::fields($input,['period_id','grade_id','section_id','after'],['period_id','grade_id','section_id']);
   foreach($input as $value)if(!is_string($value))throw new AcademicCommandFailure('invalid_input');
   $data=(new ClassroomReport($this->db))->read($actor,$input['period_id'],$input['grade_id'],$input['section_id'],$input['after']??null);
   $response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','no-store, private');return $response;
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
  catch(AcademicCommandFailure|AcademicTransactionFailure $e){return FoundationController::failure($e);}
  catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
