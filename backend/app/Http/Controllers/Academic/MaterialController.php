<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse,BinaryFileResponse,ResponseHeaderBag};
use App\Application\Academic\Commands\{CourseMaterials,FoundationCommandChecks};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Persistence\Academic\{AcademicTransactionFailure,LocalMaterialStorage};
use Illuminate\Database\Connection;
final class MaterialController {
    public function __construct(private Connection $db,private LocalMaterialStorage $storage){}
    private function response(array $data,int $status=200):JsonResponse {$r=new JsonResponse(['data'=>$data],$status);$r->headers->set('Cache-Control','private, no-store');return $r;}
    public function handle(Request $request,AuthenticatedActor $actor):Response {
        try{
            $service=new CourseMaterials($this->db,$this->storage);$path=$request->getPathInfo();
            if(preg_match('#^/academic/materials/([1-9][0-9]*)/(replace-file|file-versions)$#',$path,$version)){
                $files=new \App\Application\Academic\Commands\MaterialFiles($this->db,$this->storage);
                if($version[2]==='replace-file'&&$request->isMethod('POST')){FoundationCommandChecks::fields($request->query->all(),[]);FoundationCommandChecks::fields($request->files->all(),['file'],['file']);$file=$request->files->get('file');if(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile||!$file->isValid())throw new AcademicCommandFailure('invalid_file');$input=$request->request->all();if(($input['expected_revision_id']??null)==='original')$input['expected_revision_id']=null;return $this->response($files->replace($actor,$version[1],$input,$file->getPathname(),$file->getClientOriginalName()),201);}
                if($version[2]==='file-versions'&&$request->isMethod('GET')){FoundationCommandChecks::fields($request->query->all(),['after']);if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');$after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');return $this->response($files->page($actor,$version[1],$after));}throw new AcademicCommandFailure('not_found');
            }
            if(preg_match('#^/academic/materials/([1-9][0-9]*)/(maintenance|revisions|edit|withdraw|restore)$#',$path,$maintenance)){
                $handler=new \App\Application\Academic\Commands\MaterialMaintenance($this->db);$id=$maintenance[1];$action=$maintenance[2];
                if($request->isMethod('GET')&&in_array($action,['maintenance','revisions'],true)){
                    FoundationCommandChecks::fields($request->query->all(),$action==='revisions'?['after']:[]);if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
                    $base=$service->retained($actor,$id);$after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');$data=$action==='maintenance'?$handler->status($actor,$base):$handler->revisions($actor,$base,$after);
                }elseif($request->isMethod('POST')&&in_array($action,['edit','withdraw','restore'],true)){
                    if($request->query->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');$raw=trim($request->getContent());try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}if(!str_starts_with($raw,'{')||!is_array($input))throw new AcademicCommandFailure('invalid_input');
                    $data=$handler->execute($actor,$id,match($action){'edit'=>'edited','withdraw'=>'withdrawn','restore'=>'restored'},$input);
                }else throw new AcademicCommandFailure('not_found');$response=new JsonResponse(['data'=>$data]);$response->headers->set('Cache-Control','private, no-store');return $response;
            }
            if($request->isMethod('GET')&&preg_match('#^/academic/my-courses/([1-9][0-9]*)/library$#',$path,$library)){
                FoundationCommandChecks::fields($request->query->all(),['q','after']);if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
                $term=$request->query->all()['q']??'';$after=$request->query->all()['after']??null;if(!is_string($term)||($after!==null&&!is_string($after)))throw new AcademicCommandFailure('invalid_input');
                $response=new JsonResponse(['data'=>$service->search($actor,$library[1],$term,$after)]);$response->headers->set('Cache-Control','private, no-store');return $response;
            }
            if(preg_match('#^/academic/my-courses/([1-9][0-9]*)/materials$#',$path,$match)){
                if($request->isMethod('GET')){FoundationCommandChecks::fields($request->query->all(),['after']);if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
                    $after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');
                    return $this->response($service->page($actor,$match[1],$after));}
                if($request->isMethod('POST')){FoundationCommandChecks::fields($request->query->all(),[]);FoundationCommandChecks::fields($request->files->all(),['file'],['file']);
                    $file=$request->files->get('file');if(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile||!$file->isValid())throw new AcademicCommandFailure('invalid_file');
                    $id=$service->publish($actor,$match[1],$request->request->all(),$file->getPathname(),$file->getClientOriginalName());return $this->response(['id'=>$id],201);}
            }
            if($request->isMethod('GET')&&preg_match('#^/academic/materials/([1-9][0-9]*)(/file)?$#',$path,$match)){
                FoundationCommandChecks::fields($request->query->all(),isset($match[2])?['disposition','revision']:[]);
                if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
                if(!isset($match[2]))return $this->response($service->view($service->material($actor,$match[1])));
                $disposition=$request->query->all()['disposition']??'attachment';if(!in_array($disposition,['attachment','inline'],true))throw new AcademicCommandFailure('invalid_input');
                $revision=$request->query->all()['revision']??null;if($revision!==null&&!is_string($revision))throw new AcademicCommandFailure('invalid_input');[$file,$row]=$revision===null?$service->file($actor,$match[1]):(new \App\Application\Academic\Commands\MaterialFiles($this->db,$this->storage))->file($actor,$match[1],$revision==='original'?null:\App\Infrastructure\Persistence\Academic\AcademicLockSet::id($revision));if($disposition==='inline'&&!in_array($row['mime'],['application/pdf','image/jpeg','image/png'],true))$disposition='attachment';
                $response=new BinaryFileResponse($file);$response->setContentDisposition($disposition,$row['filename'],'material');
                $response->headers->set('Content-Type',$row['mime']);$response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Content-Type-Options','nosniff');
                $response->headers->set('Content-Security-Policy',"sandbox; default-src 'none'");return $response;
            }
            throw new AcademicCommandFailure('not_found');
        }catch(\InvalidArgumentException){return FoundationController::failure(new AcademicCommandFailure('invalid_input'));}
        catch(AcademicCommandFailure|AcademicTransactionFailure $error){return FoundationController::failure($error);}
        catch(\Throwable){return FoundationController::failure(new AcademicTransactionFailure('material_unavailable',bin2hex(random_bytes(16))));}
    }
}
