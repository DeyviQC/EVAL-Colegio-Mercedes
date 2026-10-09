<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use App\Application\Academic\Commands\{ResourceWeekAssociations,FoundationCommandChecks};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{AcademicTransactionFailure,LocalMaterialStorage};
use Illuminate\Database\Connection;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
final class ResourceWeekController {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 public function handle(Request $request,AuthenticatedActor $actor):JsonResponse {
  try{
   if($request->files->all()||!preg_match('#^/academic/resource-weeks/(material|activity)/([1-9][0-9]*)(/history)?$#',$request->getPathInfo(),$m))throw new AcademicCommandFailure('not_found');
   $service=new ResourceWeekAssociations($this->db,$this->storage);$history=isset($m[3]);
   if($request->isMethod('GET')){
    FoundationCommandChecks::fields($request->query->all(),$history?['after']:[]);if(trim($request->getContent())!=='')throw new AcademicCommandFailure('invalid_input');
    $after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');
    $data=$history?$service->history($actor,$m[1],$m[2],$after):$service->consult($actor,$m[1],$m[2]);
   }elseif($request->isMethod('POST')&&!$history){
    FoundationCommandChecks::fields($request->query->all(),[]);$raw=trim($request->getContent());if(strlen($raw)>2048||!str_starts_with($raw,'{'))throw new AcademicCommandFailure('invalid_input');
    try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}
    if(!is_array($input))throw new AcademicCommandFailure('invalid_input');$data=$service->set($actor,$m[1],$m[2],$input);
   }else throw new AcademicCommandFailure('not_found');
   $response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','private, no-store');return $response;
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
  catch(AcademicCommandFailure|AcademicTransactionFailure $error){return FoundationController::failure($error);}
  catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
