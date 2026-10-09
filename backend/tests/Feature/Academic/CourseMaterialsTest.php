<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
use App\Application\Academic\Commands\{CourseMaterials,ReplaceTeacher,TransferStudent};
use App\Application\Academic\AcademicCommandFailure;
use App\Infrastructure\Persistence\Academic\{LocalMaterialStorage,MaterialFileValidator,AcademicTransactionFailure};
use Symfony\Component\HttpFoundation\{Request,BinaryFileResponse};
final class CourseMaterialsTest extends SubmissionReferenceTestCase {
    private string $directory;private string $pdf;private LocalMaterialStorage $storage;private CourseMaterials $materials;
    protected function setUp():void {parent::setUp();$this->directory=sys_get_temp_dir().'/eval-materials-'.bin2hex(random_bytes(12));mkdir($this->directory);
        $this->pdf=$this->directory.'/sample.pdf';file_put_contents($this->pdf,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $this->storage=new LocalMaterialStorage($this->directory.'/private');$this->materials=new CourseMaterials($this->db,$this->storage);
        $this->migration->table('retained_identity_profiles')->insert(['identity_id'=>$this->teacherActor->identityId,'display_name'=>'Synthetic material teacher']);}
    protected function tearDown():void {$files=new \Illuminate\Filesystem\Filesystem;$files->deleteDirectory($this->directory);parent::tearDown();}
    private function publish(?CourseMaterials $service=null):string {return ($service??$this->materials)->publish($this->teacherActor,$this->assignmentId,['title'=>'Material real de prueba'],$this->pdf,'fracciones.pdf');}
    private function reject(callable $operation,string $category):void {try{$operation();$this->fail('Expected denial');}catch(AcademicCommandFailure|AcademicTransactionFailure $error){$this->assertSame($category,$error->category);}}
    public function testRealPublicationConsultationAndProtectedBytes():void {
        $id=$this->publish();$row=$this->materials->material($this->studentActor,$id);$this->assertSame($this->teacherActor->identityId,(string)$row['author_id']);
        $this->assertSame($this->assignmentId,(string)$row['assignment_id']);$this->assertSame(1,count($this->materials->page($this->studentActor,$this->assignmentId,null)['items']));
        [$path,$file]=$this->materials->file($this->studentActor,$id);$this->assertSame(file_get_contents($this->pdf),file_get_contents($path));
        $view=$this->materials->view($row);$this->assertArrayNotHasKey('storage_key',$view);$this->assertArrayNotHasKey('publication_key',$view);
        $controller=new \App\Http\Controllers\Academic\MaterialController($this->db,$this->storage);
        $response=$controller->handle(Request::create('https://eval.test/academic/materials/'.$id.'/file?disposition=inline'),$this->studentActor);
        $this->assertInstanceOf(BinaryFileResponse::class,$response);$this->assertSame('application/pdf',$response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline',$response->headers->get('Content-Disposition'));$this->assertSame('nosniff',$response->headers->get('X-Content-Type-Options'));
        $this->assertSame(404,$controller->handle(Request::create('https://eval.test/academic/materials/'.$id.'/file'),$this->actor('student'))->getStatusCode());
        $this->assertSame(422,$controller->handle(Request::create('https://eval.test/academic/materials/'.$id.'/file?disposition=../../private'),$this->studentActor)->getStatusCode());
        $this->assertSame(404,$controller->handle(Request::create('https://eval.test/academic/materials/18446744073709551615/file'),$this->studentActor)->getStatusCode());
        foreach([$this->actor,$this->actor('vice_principal'),$this->actor('teacher')] as $other)$this->assertSame(404,$controller->handle(Request::create('https://eval.test/academic/materials/'.$id.'/file'),$other)->getStatusCode());
    }
    public function testOutsideAuthorityAndExtraScopeAreDeniedWithoutPublishing():void {
        $before=$this->db->table('course_materials')->count();$key=$this->ordinal();
        foreach([$this->actor,$this->studentActor,$this->actor('teacher'),$this->actor('vice_principal')] as $actor)$this->reject(fn()=>$this->materials->publish($actor,$this->assignmentId,['title'=>'Invalid'],$this->pdf,'valid.pdf'),'forbidden');
        $this->reject(fn()=>$this->materials->publish($this->teacherActor,$this->assignmentId,['title'=>'Invalid','teacher_id'=>$this->teacherActor->identityId],$this->pdf,'valid.pdf'),'invalid_input');
        $this->assertSame($before,$this->db->table('course_materials')->count());$this->assertSame($key,$this->ordinal());
    }
    private function office(string $extension,bool $macro=false):string {
        $path=$this->directory.'/file.'.$extension;$zip=new \ZipArchive;$zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE);
        [$part,$type]=match($extension){'docx'=>['word/document.xml','wordprocessingml.document'],'pptx'=>['ppt/presentation.xml','presentationml.presentation'],'xlsx'=>['xl/workbook.xml','spreadsheetml.sheet']};
        $zip->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/'.$part.'" ContentType="application/vnd.openxmlformats-officedocument.'.$type.'.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<Relationships/>');$zip->addFromString($part,'<document/>');if($macro)$zip->addFromString('word/vbaProject.bin','macro payload');$zip->close();return $path;
    }
    private function compoundFixture(string $type,bool $macro=false):string {
        $header=str_repeat("\0",512);$put=function($offset,$bytes)use(&$header){$header=substr_replace($header,$bytes,$offset,strlen($bytes));};
        $put(0,hex2bin('d0cf11e0a1b11ae1'));$put(24,pack('v4',0x003e,3,0xfffe,9));$put(32,pack('v',6));
        foreach([44=>1,48=>1,56=>4096,60=>0xfffffffe,68=>0xfffffffe] as $offset=>$value)$put($offset,pack('V',$value));
        $put(76,pack('V',0).str_repeat("\xff",108*4));$fat=[0xfffffffd,0xfffffffe,3,4,5,6,7,8,9,0xfffffffe];while(count($fat)<128)$fat[]=0xffffffff;
        $entry=function($name,$kind,$start,$size){$bytes=str_repeat("\0",128);$wide=mb_convert_encoding($name."\0",'UTF-16LE','UTF-8');$bytes=substr_replace($bytes,$wide,0,strlen($wide));
            $bytes=substr_replace($bytes,pack('v',strlen($wide)),64,2);$bytes[66]=chr($kind);$bytes=substr_replace($bytes,pack('V2',$start,$size),116,8);return $bytes;};
        $name=match($type){'doc'=>'WordDocument','ppt'=>'PowerPoint Document','xls'=>'Workbook'};
        $directory=$entry('Root Entry',5,0xfffffffe,0).$entry($name,2,2,4096).($macro?$entry('VBA',1,0xfffffffe,0):str_repeat("\0",128)).str_repeat("\0",128);
        $payload=match($type){'doc'=>pack('v2',0xa5ec,0x00c1),'ppt'=>pack('v2V',15,1000,4088),'xls'=>pack('v4',0x0809,4,0x0600,5).pack('v2',0x000a,0)};
        $path=$this->directory.'/legacy.'.$type;file_put_contents($path,$header.pack('V*',...$fat).$directory.str_pad($payload,4096,"\0"));return $path;
    }
    public function testLegacyCompoundTypesAndMacroStorages():void {
        $validator=new MaterialFileValidator;
        foreach(['doc','ppt','xls'] as $type){$path=$this->compoundFixture($type);$this->assertGreaterThan(0,$validator->validate($path,'legacy.'.$type)['bytes']);
            $macro=$this->compoundFixture($type,true);$this->reject(fn()=>$validator->validate($macro,'legacy.'.$type),'invalid_file');}
        $png=$this->directory.'/image.png';file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='));
        $this->assertSame('image/png',$validator->validate($png,'image.PNG')['mime']);
        $xls=$this->compoundFixture('xls');$bytes=file_get_contents($xls);$macroSheet=pack('v4',0x0809,4,0x0600,5).pack('v2',0x0085,6).pack('VCC',0,0,1);
        file_put_contents($xls,substr_replace($bytes,$macroSheet,1536,strlen($macroSheet)));$this->reject(fn()=>$validator->validate($xls,'ordinary.xls'),'invalid_file');
        $jpeg=$this->directory.'/photo.jpg';file_put_contents($jpeg,hex2bin('ffd8ffe000104a46494600010100000100010000ffc00011080001000103011100021100031100ffda000c03010002110311003f0000ffd9'));
        $this->assertSame('image/jpeg',$validator->validate($jpeg,'photo.JPEG')['mime']);
    }
    public function testSupportedModernFormatsAndInvalidMacroSizeAndSpoofedFiles():void {
        $validator=new MaterialFileValidator;$this->assertSame('application/pdf',$validator->validate($this->pdf,'sample.PDF')['mime']);
        foreach(['docx','pptx','xlsx'] as $type){$path=$this->office($type);$this->assertGreaterThan(0,$validator->validate($path,'file.'.$type)['bytes']);}
        $macro=$this->office('docx',true);$this->reject(fn()=>$validator->validate($macro,'innocent.docx'),'invalid_file');
        foreach(['docm','xlsm','pptm','exe','doc','xls','ppt','png'] as $name)$this->reject(fn()=>$validator->validate($this->pdf,'sample.'.$name),'invalid_file');
        $this->reject(fn()=>$validator->validate($this->pdf,'../sample.pdf'),'invalid_file');
        $oversize=$this->directory.'/large.pdf';$file=fopen($oversize,'wb');ftruncate($file,MaterialFileValidator::LIMIT+1);fclose($file);
        $this->reject(fn()=>$validator->validate($oversize,'large.pdf'),'invalid_file');
        $empty=$this->directory.'/empty.pdf';file_put_contents($empty,'');$this->reject(fn()=>$validator->validate($empty,'empty.pdf'),'invalid_file');
    }
    public function testStorageFailureAndMetadataRollbackLeaveNoVisibleMaterial():void {
        $fault=new class extends \Illuminate\Filesystem\Filesystem {public function move($path,$target){return false;}};
        $service=new CourseMaterials($this->db,new LocalMaterialStorage($this->directory.'/fault',$fault));$before=$this->db->table('course_materials')->count();
        $this->reject(fn()=>$this->publish($service),'storage_unavailable');$this->assertSame($before,$this->db->table('course_materials')->count());
        $key=$this->ordinal();$this->migration->unprepared("CREATE TRIGGER material_insert_fail BEFORE INSERT ON course_materials FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected persistence failure'");
        try{$this->reject(fn()=>$this->publish(),'integrity_conflict');}finally{$this->migration->unprepared('DROP TRIGGER material_insert_fail');}
        $this->assertSame($before,$this->db->table('course_materials')->count());$this->assertSame($key,$this->ordinal());
        $this->assertCount(0,array_filter(glob($this->directory.'/private/*'),fn($path)=>!str_ends_with($path,'.lock')));
    }
    public function testLostCommitAcknowledgementRetainsFileAndDoesNotReplay():void {
        $connection=new class($this->db->getPdo(),$this->db->getDatabaseName(),'',$this->db->getConfig()) extends \Illuminate\Database\MySqlConnection {
            public function commit(){parent::commit();throw new \RuntimeException('Injected lost acknowledgement');}
        };
        $before=$this->db->table('course_materials')->count();$this->reject(fn()=>$this->publish(new CourseMaterials($connection,$this->storage)),'commit_outcome_unknown');
        $this->assertSame($before+1,$this->db->table('course_materials')->count());$row=(array)$this->db->table('course_materials')->orderByDesc('id')->first();
        $this->assertFileExists($this->storage->verify($row));$this->assertSame('retained',$this->storage->reconcile($row['storage_key'],$this->db));
    }
    public function testPrivateOrphanRecoveryAndTamperFailure():void {
        [$key,$lock]=$this->storage->finalize($this->pdf);$this->assertSame('busy',$this->storage->reconcile($key,$this->db));
        $this->storage->release($key,$lock);$this->assertSame('orphan_removed',$this->storage->reconcile($key,$this->db));$this->assertFileDoesNotExist($this->storage->path($key));
        $id=$this->publish();[$path]=$this->materials->file($this->studentActor,$id);file_put_contents($path,'tampered');
        $this->reject(fn()=>$this->materials->file($this->studentActor,$id),'storage_unavailable');
    }
    public function testReplacementRetainsAuthorAndSuccessorCanReadButNotPublishFromPrior():void {
        $id=$this->publish();$next=$this->actor('teacher');$result=(new ReplaceTeacher($this->db))->execute($this->actor,$this->assignmentId,['teacher_id'=>$next->identityId]);
        $this->assertSame($this->teacherActor->identityId,(string)$this->materials->material($next,$id)['author_id']);
        $this->reject(fn()=>$this->materials->publish($next,$this->assignmentId,['title'=>'Invalid'],$this->pdf,'valid.pdf'),'forbidden');
        $this->reject(fn()=>$this->publish(),'invalid_transition');$this->assertNotEmpty($this->materials->page($next,$result['successor_id'],null)['items']);
        $this->assertSame($id,(string)$this->materials->material($this->studentActor,$id)['id']);
    }
    public function testTransferHistoricalMembershipAndOldAulaNewPublicationDenial():void {
        $id=$this->publish();$otherSection=$this->catalog->create($this->actor,'section',['name'=>'B','grade_id'=>$this->grade]);
        (new TransferStudent($this->db))->execute($this->actor,$this->enrollmentId,['grade_id'=>$this->grade,'section_id'=>$otherSection]);
        $this->assertSame($id,(string)$this->materials->material($this->studentActor,$id)['id']);$later=$this->publish();
        $this->reject(fn()=>$this->materials->material($this->studentActor,$later),'not_found');
        $newStudent=$this->actor('student');$enrollment=$this->enrollment(['student_id'=>$newStudent->identityId]);
        $this->assertSame($id,(string)$this->materials->material($newStudent,$id)['id']);
        $this->commands->close($this->actor,$enrollment,$this->today());$this->reject(fn()=>$this->materials->material($newStudent,$id),'not_found');
        $courses=new \App\Application\Academic\Queries\MyCoursesQuery($this->db,new \App\Infrastructure\Persistence\Academic\LocalIdentityLabels($this->db));
        $this->assertSame($this->assignmentId,$courses->detail($this->studentActor,$this->assignmentId)['id']);
        $this->reject(fn()=>$courses->detail($newStudent,$this->assignmentId),'not_found');
    }
    public function testClosedAssignmentAndPeriodKeepHistoricalReadButDenyPublication():void {
        $id=$this->publish();$late=$this->actor('student');$this->enrollment(['student_id'=>$late->identityId]);
        $this->assertSame($id,(string)$this->materials->material($late,$id)['id']);
        $this->assignments->close($this->actor,$this->assignmentId,$this->today());$this->reject(fn()=>$this->publish(),'invalid_transition');$this->reject(fn()=>$this->materials->material($late,$id),'not_found');
        $this->assertSame($id,(string)$this->materials->material($this->studentActor,$id)['id']);$this->periods->close($this->actor,$this->period);
        $this->reject(fn()=>$this->publish(),'invalid_transition');$this->assertSame($id,(string)$this->materials->material($this->studentActor,$id)['id']);$this->reject(fn()=>$this->materials->material($late,$id),'not_found');
        foreach(['UPDATE course_materials SET title=\'Rewrite\' WHERE id='.$id,'DELETE FROM course_materials WHERE id='.$id] as $sql){try{$this->migration->unprepared($sql);$this->fail('History rewritten');}catch(\Illuminate\Database\QueryException){$this->assertTrue(true);}}
    }
    public static function races():array {return [['period_close','publish','invalid_transition'],['assignment_close','publish','invalid_transition'],['replace','publish','invalid_transition'],['publish','period_close','COMMITTED'],['transfer','publish','COMMITTED']];}
    #[\PHPUnit\Framework\Attributes\DataProvider('races')]
    public function testActualPublicationClosureReplacementTransferRaces(string $first,string $second,string $outcome):void {
        $section=$this->catalog->create($this->actor,'section',['name'=>'Race B','grade_id'=>$this->grade]);$teacher=$this->actor('teacher');
        $payload=['publisher_id'=>$this->teacherActor->identityId,'teacher_id'=>$teacher->identityId,'assignment_id'=>$this->assignmentId,'academic_period_id'=>$this->period,
            'enrollment_id'=>$this->enrollmentId,'grade_id'=>$this->grade,'section_id'=>$section,'source'=>$this->pdf,'storage'=>$this->directory.'/private'];
        $before=$this->db->table('course_materials')->count();$this->acceptanceRace($first,$second,$outcome,$payload,$payload);
        $this->assertSame($before+($outcome==='COMMITTED'?1:0),$this->db->table('course_materials')->count());
        if($first==='transfer'){$id=(string)$this->db->table('course_materials')->orderByDesc('id')->value('id');$this->reject(fn()=>$this->materials->material($this->studentActor,$id),'not_found');}
    }
}
