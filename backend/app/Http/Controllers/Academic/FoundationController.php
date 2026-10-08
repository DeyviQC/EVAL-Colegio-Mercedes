<?php
declare(strict_types=1);
namespace App\Http\Controllers\Academic;
use Illuminate\Database\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Policies\Academic\AcademicPolicy;
use App\Application\Academic\Authorization\AcademicAuthorization;
use App\Application\Academic\Authorization\AcademicReferenceReader;
use App\Application\Academic\Authorization\UnavailableAcademicReferences;
use App\Application\Academic\Queries\AcademicIdentityLabels;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\AcademicPeriodCommands;
use App\Application\Academic\Commands\AcademicCatalogCommands;
use App\Application\Academic\Commands\EnrollmentCommands;
use App\Application\Academic\Commands\TeachingAssignmentCommands;
use App\Application\Academic\Commands\TransferStudent;
use App\Application\Academic\Commands\ReplaceTeacher;
use App\Application\Academic\Commands\FoundationCommandChecks;
use App\Application\Academic\Queries\AcademicScopeQuery;
use App\Application\Academic\Queries\TeachingAssignmentSupportQuery;
use App\Application\Academic\Queries\OwnHistoricalSubmissionQuery;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
use App\Infrastructure\Persistence\Academic\AcademicLockSet;
final class FoundationController
{
    public function __construct(private Connection $db,private AcademicReferenceReader $references=new UnavailableAcademicReferences(),
        private ?AcademicIdentityLabels $labels=null){}
    public function handle(Request $request,?AuthenticatedActor $actor):JsonResponse
    {
        try{
            if(!$actor){throw new AcademicCommandFailure('unauthenticated');}
            $authorization=new AcademicAuthorization($this->db,$this->references);$policy=new AcademicPolicy($authorization);
            $path=$request->getPathInfo();$method=$request->getMethod();$input=$this->input($request);
            $directory=$method==='GET' && ($path==='/academic/periods'||preg_match('#^/academic/catalog/(entry|grade|section)$#',$path));
            if($directory){
                FoundationCommandChecks::fields($input,[]);FoundationCommandChecks::fields($request->query->all(),['after']);
                $after=$request->query->all()['after']??null;if($after!==null&&!is_string($after))throw new AcademicCommandFailure('invalid_input');
                $kind=$path==='/academic/periods'?'period':basename($path);
                return $this->ok((new \App\Application\Academic\Queries\FoundationDirectoryQuery($this->db))->page($actor,$kind,$after));
            }
            if($request->query->all()){throw new AcademicCommandFailure('invalid_input');}
            if($method==='GET'){
                FoundationCommandChecks::fields($input,[]);
                if($path==='/academic/navigation'){
                    return $this->ok((new \App\Application\Academic\Queries\AcademicNavigationQuery($this->db))->get($actor));
                }
                if(preg_match('#^/academic/(periods|enrollments|assignments|submissions|activities)/([0-9]+)$#',$path,$match)){
                    $id=$this->locator($match[2]);$kind=match($match[1]){'periods'=>'period','enrollments'=>'enrollment','assignments'=>'assignment','submissions'=>'submission','activities'=>'activity'};
                    $policy->authorize($actor,$kind.'.read',[$kind.'_id'=>$id],true);
                    if($kind==='submission'){
                        if(!$this->labels){throw new AcademicCommandFailure('reference_unavailable');}
                        return $this->ok((new OwnHistoricalSubmissionQuery($this->db,$authorization,$this->references,$this->labels))->getForAuthorizedHistory($actor,$id));
                    }
                    if($kind==='activity'){
                        $reference=$this->references->activity($id);
                        return $this->ok(['id'=>$id,'teaching_assignment_id'=>(string)$reference['teaching_assignment_id']]);
                    }
                    return $this->ok((new AcademicScopeQuery($this->db,$authorization))->record($actor,$kind,$id));
                }
            }
            if($method==='POST' && $path==='/academic/assignment-support'){
                FoundationCommandChecks::fields($input,['duty','target'],['duty','target']);
                if(!is_string($input['duty']) || !is_array($input['target'])){throw new AcademicCommandFailure('invalid_input');}
                return $this->ok((new TeachingAssignmentSupportQuery($this->db,$authorization))->forAssignmentOperation($actor,$input['duty'],$input['target']));
            }
            if($method==='POST'){
                if($path==='/academic/periods'){return $this->ok(['id'=>(new AcademicPeriodCommands($this->db))->create($actor,$input)],201);}
                if($path==='/academic/enrollments'){return $this->ok(['id'=>(new EnrollmentCommands($this->db))->create($actor,$input)],201);}
                if($path==='/academic/assignments'){return $this->ok(['id'=>(new TeachingAssignmentCommands($this->db))->create($actor,$input)],201);}
                if(preg_match('#^/academic/periods/([0-9]+)/(activate|close)$#',$path,$match)){
                    FoundationCommandChecks::fields($input,[]);$commands=new AcademicPeriodCommands($this->db);
                    return $this->ok(['id'=>$commands->{$match[2]}($actor,$this->locator($match[1]))]);
                }
                if(preg_match('#^/academic/(enrollments|assignments)/([0-9]+)/(activate|close|transfer|replace)$#',$path,$match)){
                    $id=$this->locator($match[2]);$assignment=$match[1]==='assignments';$action=$match[3];
                    if($action==='close'){
                        FoundationCommandChecks::fields($input,['effective_until'],['effective_until']);
                        if(!is_string($input['effective_until'])){throw new AcademicCommandFailure('invalid_interval');}
                        $commands=$assignment?new TeachingAssignmentCommands($this->db):new EnrollmentCommands($this->db);
                        return $this->ok(['id'=>$commands->close($actor,$id,$input['effective_until'])]);
                    }
                    if($assignment && $action==='activate'){
                        FoundationCommandChecks::fields($input,[]);return $this->ok(['id'=>(new TeachingAssignmentCommands($this->db))->activate($actor,$id)]);
                    }
                    if($assignment && $action==='replace'){return $this->ok((new ReplaceTeacher($this->db))->execute($actor,$id,$input));}
                    if(!$assignment && $action==='transfer'){return $this->ok((new TransferStudent($this->db))->execute($actor,$id,$input));}
                }
                // Admission-only reserved contracts: no U10/U11 writer exists or runs here.
                if(in_array($path,['/academic/activities','/academic/submissions','/academic/submissions/late'],true)){
                    $field=$path==='/academic/activities'?'assignment_id':'activity_id';FoundationCommandChecks::fields($input,[$field],[$field]);
                    $operation=match($path){'/academic/activities'=>'activity.create','/academic/submissions'=>'submission.accept',default=>'submission.accept_late'};
                    $policy->authorize($actor,$operation,[$field=>$this->locator($input[$field])]);
                    throw new AcademicCommandFailure('not_found');
                }
            }
            if(preg_match('#^/academic/catalog/(entry|grade|section)(?:/([0-9]+))?$#',$path,$match)){
                $commands=new AcademicCatalogCommands($this->db);
                if($method==='POST' && !isset($match[2])){return $this->ok(['id'=>$commands->create($actor,$match[1],$input)],201);}
                if($method==='PATCH' && isset($match[2])){return $this->ok(['id'=>$commands->update($actor,$match[1],$this->locator($match[2]),$input)]);}
            }
            throw new AcademicCommandFailure('not_found');
        }catch(AcademicCommandFailure|AcademicTransactionFailure $error){return self::failure($error);}
        catch(\Illuminate\Database\QueryException $error){
            // Read-side infrastructure failures must not disclose SQL/bindings to the caller.
            return self::failure(new AcademicTransactionFailure('database_unavailable',bin2hex(random_bytes(16))));
        }
    }
    public static function failure(AcademicCommandFailure|AcademicTransactionFailure $error):JsonResponse
    {
        $category=$error->category;
        $status=match($category){
            'unauthenticated'=>401,'forbidden','actor_inactive'=>403,
            'not_found','missing_lock_target','missing_locked_context'=>404,
            'invalid_input','invalid_interval','invalid_name','invalid_kind','scope_mismatch','inactive_catalog_reference'=>422,
            'commit_outcome_unknown','rollback_outcome_unknown','retry_exhausted','ordinal_exhausted','reference_unavailable','database_unavailable'=>503,
            default=>409,
        };
        $body=['error'=>$category];
        if($status===503){$body['correlation_id']=$error instanceof AcademicTransactionFailure?$error->correlationId:null;$body['automatic_retry']=false;}
        $response=new JsonResponse($body,$status);$response->headers->set('Cache-Control','no-store, private');return $response;
    }
    private function input(Request $request):array
    {
        $raw=trim($request->getContent());if($raw===''){return [];}
        try{$input=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){throw new AcademicCommandFailure('invalid_input');}
        if(!str_starts_with($raw,'{') || !is_array($input)){throw new AcademicCommandFailure('invalid_input');}return $input;
    }
    private function locator(mixed $value):string
    {try{return AcademicLockSet::id($value);}catch(\InvalidArgumentException){throw new AcademicCommandFailure('invalid_input');}}
    private function ok(array $data,int $status=200):JsonResponse {return new JsonResponse(['data'=>$data],$status);}
}
