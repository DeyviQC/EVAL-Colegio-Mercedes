<?php
declare(strict_types=1);
namespace App\Application\Academic\Commands;
use Illuminate\Database\Connection;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{AcademicLockSet,AcademicWriteTransaction,AcademicMutationResult,LifecycleEvent,AcademicTransactionFailure,LocalMaterialStorage,MaterialFileValidator};
final class CourseMaterials {
    private ?MaterialMaintenance $revisions;
    public function __construct(private Connection $db,private LocalMaterialStorage $storage){$this->revisions=$db->getSchemaBuilder()->hasTable('material_states')?new MaterialMaintenance($db):null;}
    public function publish(AuthenticatedActor $actor,string $assignment,array $input,string $path,string $filename):string {
        FoundationCommandChecks::credentials($this->db,$actor);FoundationCommandChecks::fields($input,['title','description'],['title']);
        if(!is_string($input['title'])||trim($input['title'])===''||mb_strlen($input['title'])>255
            ||isset($input['description'])&&(!is_string($input['description'])||mb_strlen($input['description'])>10000))throw new AcademicCommandFailure('invalid_input');
        $assignment=AcademicLockSet::id($assignment);$meta=(new MaterialFileValidator)->validate($path,$filename);
        [$key,$lock]=$this->storage->finalize($path);$keep=false;
        try{
            $meta+=['storage_key'=>$key,'filename'=>$filename];$this->storage->verify($meta);
            $id=(new AcademicWriteTransaction($this->db))->execute($actor->identityId,
                function($db,$actorId,$context)use($assignment){$row=$context?$context->row('teaching_assignments',$assignment):(array)$this->db->table('teaching_assignments')->where('id',$assignment)->first();
                    return new AcademicLockSet(['academic_periods'=>isset($row['academic_period_id'])?[$row['academic_period_id']]:[], 'retained_identities'=>isset($row['teacher_id'])?[$row['teacher_id']]:[],'teaching_assignments'=>[$assignment]]);},
                function($context)use($actor,$assignment){FoundationCommandChecks::credentials($this->db,$actor);$row=$context->row('teaching_assignments',$assignment);
                    if((string)$row['teacher_id']!==$actor->identityId||!$this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->where('role','teacher')->exists())throw new AcademicCommandFailure('forbidden');
                    if($row['state']!=='active'||$context->row('academic_periods',$row['academic_period_id'])['state']!=='active')throw new AcademicCommandFailure('invalid_transition');return true;},
                function($context,$boundary)use($actor,$assignment,$input,$meta){
                    $this->db->table('course_materials')->insert($meta+['assignment_id'=>$assignment,'author_id'=>$actor->identityId,'title'=>$input['title'],'description'=>$input['description']??null,
                        'publication_key'=>$boundary->toServerKey(),'published_at'=>gmdate('Y-m-d H:i:s')]);
                    $id=AcademicLockSet::id($this->db->getPdo()->lastInsertId());
                    return new AcademicMutationResult($id,[new LifecycleEvent('assignment',$assignment,'material_published','active','active',['material_id'=>$id,'sha256'=>$meta['sha256']])]);});
            $keep=true;return $id;
        }catch(AcademicTransactionFailure $error){$keep=in_array($error->category,['commit_outcome_unknown','rollback_outcome_unknown'],true);throw $error;}
        finally{try{if(!$keep)$this->storage->remove($key);}finally{$this->storage->release($key,$lock);}}
    }
    public function lineage(string $id):array {
        $ids=[];$scope=null;while($id!==null){$row=$this->db->table('teaching_assignments')->where('id',$id)->first();if(!$row||isset($ids[$id])||count($ids)>1000)throw new AcademicCommandFailure('not_found');
            $actual=[$row->academic_period_id,$row->instructional_entry_id,$row->grade_id,$row->section_id];if($scope!==null&&$scope!==$actual)throw new AcademicCommandFailure('not_found');$scope=$actual;$ids[$id]=(array)$row;$id=$row->replaces_assignment_id===null?null:(string)$row->replaces_assignment_id;}return $ids;
    }
    private function roles(AuthenticatedActor $actor):array {FoundationCommandChecks::credentials($this->db,$actor);
        if($this->db->table('retained_identities')->where('id',$actor->identityId)->value('credential_status')!=='active')throw new AcademicCommandFailure('forbidden');
        return $this->db->table('local_role_grants')->where('identity_id',$actor->identityId)->pluck('role')->all();}
    public function eligible(AuthenticatedActor $actor,array $material):bool {
        $roles=$this->roles($actor);$original=$this->db->table('teaching_assignments')->where('id',$material['assignment_id'])->first();if(!$original)return false;
        $parent=$this->db->table('academic_periods')->where('id',$original->academic_period_id)->value('state');
        if(in_array('teacher',$roles,true)){
            if((string)$original->teacher_id===$actor->identityId)return true;
            foreach($this->db->table('teaching_assignments')->where('teacher_id',$actor->identityId)->whereIn('state',['active','closed'])->where('academic_period_id',$original->academic_period_id)->get() as $assignment){
                if(in_array($parent,['active','closed'],true)&&isset($this->lineage((string)$assignment->id)[(string)$original->id]))return true;}
        }
        if(!in_array('student',$roles,true))return false;
        foreach($this->db->table('student_enrollments')->where('student_id',$actor->identityId)->where('academic_period_id',$original->academic_period_id)->where('grade_id',$original->grade_id)->where('section_id',$original->section_id)->get() as $enrollment){
            if($parent==='active'&&$original->state==='active'&&$enrollment->state==='active')return true;
            if($enrollment->operational_start_key!==null&&(int)$material['publication_key']>=(int)$enrollment->operational_start_key
                &&($enrollment->operational_end_key===null||(int)$material['publication_key']<(int)$enrollment->operational_end_key))return true;
        }return false;
    }
    public function retained(AuthenticatedActor $actor,string $id):array {
        $row=$this->db->table('course_materials')->where('id',AcademicLockSet::id($id))->first();
        if(!$row||!$this->eligible($actor,(array)$row))throw new AcademicCommandFailure('not_found');return (array)$row;
    }
    public function material(AuthenticatedActor $actor,string $id):array {
        $base=$this->retained($actor,$id);$visible=$this->revisions?$this->revisions->visible($actor,$base):$base;if($visible===null)throw new AcademicCommandFailure('not_found');return $visible;
    }
    public function view(array $row):array {
        $this->storage->verify($row);$author=(new \App\Infrastructure\Persistence\Academic\LocalIdentityLabels($this->db))->displayName((string)$row['author_id']);
        if($author===null)throw new AcademicCommandFailure('reference_unavailable');return ['id'=>(string)$row['id'],'assignment_id'=>(string)$row['assignment_id'],'author_id'=>(string)$row['author_id'],'author_name'=>$author,
            'title'=>$row['title'],'description'=>$row['description'],'filename'=>$row['filename'],'mime'=>$row['mime'],'bytes'=>(int)$row['bytes'],'published_at'=>$row['published_at']];
    }
    public function page(AuthenticatedActor $actor,string $assignment,?string $after):array {
        $this->roles($actor);$lineage=$this->lineage(AcademicLockSet::id($assignment));
        $rows=$this->db->table('course_materials')->whereIn('assignment_id',array_keys($lineage))->when($after!==null,fn($q)=>$q->where('id','>',AcademicLockSet::id($after)))->orderBy('id')->limit(50)->get();
        $items=[];foreach($rows as $row)if($this->eligible($actor,(array)$row)){$visible=$this->revisions?$this->revisions->visible($actor,(array)$row):(array)$row;if($visible!==null)$items[]=$this->view($visible);}
        if(!$items){$query=new \App\Application\Academic\Queries\MyCoursesQuery($this->db,new \App\Infrastructure\Persistence\Academic\LocalIdentityLabels($this->db));$query->detail($actor,$assignment);}
        return ['items'=>$items,'next_after'=>$rows->count()===50?(string)$rows->last()->id:null];
    }
    public function search(AuthenticatedActor $actor,string $assignment,string $term,?string $after):array {
        $this->roles($actor);$assignment=AcademicLockSet::id($assignment);$term=trim($term);
        if(mb_strlen($term)>100||preg_match('/[\x00-\x1f\x7f]/u',$term))throw new AcademicCommandFailure('invalid_input');
        (new \App\Application\Academic\Queries\MyCoursesQuery($this->db,new \App\Infrastructure\Persistence\Academic\LocalIdentityLabels($this->db)))->detail($actor,$assignment);
        $lineage=$this->lineage($assignment);$cursor=$after===null?null:AcademicLockSet::id($after);$items=[];
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';
        do {
            $query=$this->db->table('course_materials')->whereIn('assignment_id',array_keys($lineage));
            if($cursor!==null)$query->where('id','>',$cursor);
            if($term!==''&&!$this->revisions)$query->where(function($q)use($pattern){$q->whereRaw("title COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("description COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("filename COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!'",[$pattern]);});
            $rows=$query->orderBy('id')->limit(200)->get();
            foreach($rows as $row){$cursor=(string)$row->id;if(!$this->eligible($actor,(array)$row))continue;$visible=$this->revisions?$this->revisions->visible($actor,(array)$row):(array)$row;if($visible===null)continue;if($term!==''&&$this->revisions){$match=$this->db->selectOne("SELECT (? COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!') OR (? COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!') OR (? COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!') AS matched",[$visible['title'],$pattern,$visible['description']??'',$pattern,$visible['filename'],$pattern]);if(!(bool)$match->matched)continue;}$items[]=$this->view($visible);if(count($items)===51)break;}
        }while(count($items)<51&&$rows->count()===200);
        $more=count($items)>50;$items=array_slice($items,0,50);
        return ['items'=>$items,'next_after'=>$more?$items[49]['id']:null];
    }
    public function file(AuthenticatedActor $actor,string $id):array {$row=$this->material($actor,$id);return [$this->storage->verify($row),$row];}
}
