<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse,BinaryFileResponse};
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\Commands\{ActivityDelivery,FoundationCommandChecks};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{LocalMaterialStorage,AcademicTransactionFailure,AcademicLockSet};
use Illuminate\Database\Connection;
final class ActivityDeliveryController {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 public function handle(Request $r,AuthenticatedActor $actor):Response {
  try{$service=new ActivityDelivery($this->db,$this->storage);$path=$r->getPathInfo();
   if($r->isMethod('GET')){
    FoundationCommandChecks::fields($r->query->all(),['after']);if($r->getContent()!==''||$r->files->all()||$r->request->all())throw new AcademicCommandFailure('invalid_input');$after=$r->query->all()['after']??null;if($after!==null)$after=AcademicLockSet::id($after);
    if($path==='/education/courses')$data=$service->courses($actor,$after);
    elseif(preg_match('#^/education/activities/([1-9][0-9]*)$#D',$path,$m)){if($after!==null)throw new AcademicCommandFailure('invalid_input');$data=$service->view($actor,$service->activity($actor,$m[1]));}
    elseif(preg_match('#^/education/courses/([1-9][0-9]*)/activities$#D',$path,$m))$data=$service->activities($actor,$m[1],$after);
    elseif(preg_match('#^/education/activities/([1-9][0-9]*)/deliveries$#D',$path,$m))$data=$service->deliveries($actor,$m[1],$after);
    elseif(preg_match('#^/education/deliveries/([1-9][0-9]*)/assessments$#D',$path,$m))$data=(new \App\Application\Academic\Commands\DeliveryAssessment($this->db))->history($actor,$m[1],$after);
    elseif(preg_match('#^/education/deliveries/([1-9][0-9]*)/versions$#D',$path,$m))$data=$service->versions($actor,$m[1],$after);
    elseif(preg_match('#^/education/versions/([1-9][0-9]*)/file$#D',$path,$m)){
     if($after!==null)throw new AcademicCommandFailure('invalid_input');[$file,$row]=$service->file($actor,$m[1]);$response=new BinaryFileResponse($file);$response->setContentDisposition('attachment',$row['filename'],'evidence');$response->headers->set('Content-Type',$row['mime']);$response->headers->set('X-Content-Type-Options','nosniff');$response->headers->set('Content-Security-Policy',"sandbox; default-src 'none'");$response->headers->set('Cache-Control','no-store, private');return $response;
    }else throw new AcademicCommandFailure('not_found');return new JsonResponse(['data'=>$data]);
   }
   if(!$r->isMethod('POST')||$r->query->all())throw new AcademicCommandFailure('invalid_input');
   if(preg_match('#^/education/activities/([1-9][0-9]*)/deliveries$#D',$path,$m)){
    FoundationCommandChecks::fields($r->files->all(),['file']);$file=$r->files->get('file');if($file!==null&&(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile||!$file->isValid()))throw new AcademicCommandFailure('invalid_file');$input=$r->request->all();
    if($r->getContent()!==''&&!str_starts_with($r->headers->get('Content-Type',''),'multipart/form-data'))throw new AcademicCommandFailure('invalid_input');
    $data=$service->write($actor,'submit',$m[1],$input,$file?->getPathname(),$file?->getClientOriginalName());
   }else{
    if($r->files->all())throw new AcademicCommandFailure('invalid_input');$raw=trim($r->getContent());try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}if(!str_starts_with($raw,'{')||!is_array($input))throw new AcademicCommandFailure('invalid_input');
    if(preg_match('#^/education/courses/([1-9][0-9]*)/activities$#D',$path,$m))$data=$service->write($actor,'publish',$m[1],$input);
    elseif(preg_match('#^/education/deliveries/([1-9][0-9]*)/assessments$#D',$path,$m))$data=(new \App\Application\Academic\Commands\DeliveryAssessment($this->db))->assess($actor,$m[1],$input);
    elseif(preg_match('#^/education/activities/([1-9][0-9]*)/close$#D',$path,$m))$data=$service->write($actor,'close',$m[1],$input);else throw new AcademicCommandFailure('not_found');
   }return new JsonResponse(['data'=>$data],201);
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
  catch(AcademicCommandFailure|AcademicTransactionFailure $e){return FoundationController::failure($e);}
  catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
