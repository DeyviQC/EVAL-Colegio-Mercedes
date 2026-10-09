<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse,BinaryFileResponse};
use Illuminate\Database\Connection;
use App\Application\Academic\Commands\{MaterialObservations,FoundationCommandChecks};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{LocalMaterialStorage,AcademicTransactionFailure};
final class MaterialObservationsController {
 public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
 public function handle(Request $r,AuthenticatedActor $actor):Response {
  try{$service=new MaterialObservations($this->db,$this->storage);$path=$r->getPathInfo();
   if($r->isMethod('GET')){
    if($r->getContent()!==''||$r->request->all()||$r->files->all())throw new AcademicCommandFailure('invalid_input');
    if(preg_match('#^/supervision/materials/([1-9][0-9]*)/file$#',$path,$m)){
     FoundationCommandChecks::fields($r->query->all(),['disposition','revision']);$disposition=$r->query->all()['disposition']??'attachment';if(!in_array($disposition,['inline','attachment'],true))throw new AcademicCommandFailure('invalid_input');$revision=$r->query->all()['revision']??null;if($revision!==null&&!is_string($revision))throw new AcademicCommandFailure('invalid_input');[$file,$row]=$revision===null?$service->file($actor,$m[1]):(new \App\Application\Academic\Commands\MaterialFiles($this->db,$this->storage))->file($actor,$m[1],$revision==='original'?null:\App\Infrastructure\Persistence\Academic\AcademicLockSet::id($revision),true);if($disposition==='inline'&&!in_array($row['mime'],['application/pdf','image/jpeg','image/png'],true))$disposition='attachment';$response=new BinaryFileResponse($file);$response->setContentDisposition($disposition,$row['filename'],'material');$response->headers->set('Content-Type',$row['mime']);$response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Content-Type-Options','nosniff');$response->headers->set('Content-Security-Policy',"sandbox; default-src 'none'");return $response;
    }
    if(preg_match('#^/supervision/materials/([1-9][0-9]*)/file-versions$#',$path,$files)){FoundationCommandChecks::fields($r->query->all(),['after']);$after=$r->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');$data=(new \App\Application\Academic\Commands\MaterialFiles($this->db,$this->storage))->page($actor,$files[1],$after,true);}
    elseif($path==='/supervision/courses'||preg_match('#^/supervision/(?:courses/([1-9][0-9]*)/materials|materials/([1-9][0-9]*)/observations)$#',$path,$m)){
     FoundationCommandChecks::fields($r->query->all(),['after']);$after=$r->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');$data=$path==='/supervision/courses'?$service->courses($actor,$after):(isset($m[1])&&$m[1]!==''?$service->materials($actor,$m[1],$after):$service->page($actor,$m[2],$after));
    }elseif(preg_match('#^/supervision/observations/([1-9][0-9]*)$#',$path,$m)){FoundationCommandChecks::fields($r->query->all(),[]);$data=$service->detail($actor,$m[1]);}else throw new AcademicCommandFailure('not_found');
   }elseif($r->isMethod('POST')){
    if($r->query->all()||$r->files->all())throw new AcademicCommandFailure('invalid_input');$raw=trim($r->getContent());try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}if(!str_starts_with($raw,'{')||!is_array($input))throw new AcademicCommandFailure('invalid_input');
    if(preg_match('#^/supervision/materials/([1-9][0-9]*)/observations$#',$path,$m))$data=$service->execute($actor,$m[1],'observe',$input);
    elseif(preg_match('#^/supervision/observations/([1-9][0-9]*)/(reply|review)$#',$path,$m))$data=$service->execute($actor,$m[1],$m[2],$input);else throw new AcademicCommandFailure('not_found');
   }else throw new AcademicCommandFailure('not_found');$response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','private, no-store');return $response;
  }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}catch(AcademicCommandFailure|AcademicTransactionFailure $e){return FoundationController::failure($e);}catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));}
 }
}
