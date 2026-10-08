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
    public function handle(Request $request,AuthenticatedActor $actor):Response {
        try{
            $service=new CourseMaterials($this->db,$this->storage);$path=$request->getPathInfo();
            if(preg_match('#^/academic/my-courses/([1-9][0-9]*)/materials$#',$path,$match)){
                if($request->isMethod('GET')){FoundationCommandChecks::fields($request->query->all(),['after']);if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
                    $after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');
                    return new JsonResponse(['data'=>$service->page($actor,$match[1],$after)]);}
                if($request->isMethod('POST')){FoundationCommandChecks::fields($request->query->all(),[]);FoundationCommandChecks::fields($request->files->all(),['file'],['file']);
                    $file=$request->files->get('file');if(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile||!$file->isValid())throw new AcademicCommandFailure('invalid_file');
                    $id=$service->publish($actor,$match[1],$request->request->all(),$file->getPathname(),$file->getClientOriginalName());return new JsonResponse(['data'=>['id'=>$id]],201);}
            }
            if($request->isMethod('GET')&&preg_match('#^/academic/materials/([1-9][0-9]*)(/file)?$#',$path,$match)){
                FoundationCommandChecks::fields($request->query->all(),isset($match[2])?['disposition']:[]);
                if($request->getContent()!==''||$request->request->all()||$request->files->all())throw new AcademicCommandFailure('invalid_input');
                if(!isset($match[2]))return new JsonResponse(['data'=>$service->view($service->material($actor,$match[1]))]);
                $disposition=$request->query->all()['disposition']??'attachment';if(!in_array($disposition,['attachment','inline'],true))throw new AcademicCommandFailure('invalid_input');
                [$file,$row]=$service->file($actor,$match[1]);if($disposition==='inline'&&!in_array($row['mime'],['application/pdf','image/jpeg','image/png'],true))$disposition='attachment';
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
