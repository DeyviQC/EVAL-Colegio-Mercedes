<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use Tests\Support\SubmissionReferenceTestCase;
use App\Infrastructure\Authentication\LocalSessionAuthentication;
use App\Infrastructure\Persistence\Academic\PersistedAcademicReferences;
use App\Http\Controllers\Academic\FoundationController;
use Illuminate\Encryption\Encrypter;
use Illuminate\Hashing\BcryptHasher;
use Symfony\Component\HttpFoundation\Request;
final class PersistedAcademicPolicyHttpTest extends SubmissionReferenceTestCase
{
    public function testActualSessionReadsPersistedOriginalHistoryWithoutCrossStudentLeaks():void
    {
        $id=$this->accept();$otherStudent=$this->actor('student');
        $this->enrollment(['student_id'=>$otherStudent->identityId]);
        $other=(new \App\Application\Academic\Commands\AcceptSubmissionReference($this->db))->execute($otherStudent,['activity_id'=>$this->activityId]);
        $password=bin2hex(random_bytes(12));$hasher=new BcryptHasher(['rounds'=>4]);
        $this->migration->table('local_credentials')->where('id',$this->studentActor->identityId)->update(['password'=>$hasher->make($password)]);
        $section=$this->catalog->create($this->actor,'section',['name'=>'Destination','grade_id'=>$this->grade]);
        (new \App\Application\Academic\Commands\TransferStudent($this->db))->execute($this->actor,$this->enrollmentId,['grade_id'=>$this->grade,'section_id'=>$section]);
        (new \App\Application\Academic\Commands\ReplaceTeacher($this->db))->execute($this->actor,$this->assignmentId,['teacher_id'=>$this->teacher]);
        $this->periods->close($this->actor,$this->period);$this->catalog->update($this->actor,'entry',$this->entry,['name'=>'Corrected '.$this->suffix,'is_active'=>false]);
        $labels=new class implements \App\Application\Academic\Queries\AcademicIdentityLabels {public function displayName(string $id):?string{return 'Synthetic corrected teacher';}};
        $auth=new LocalSessionAuthentication($this->db,new Encrypter(random_bytes(32),'AES-256-CBC'),'https://eval.test',5,5,60,$hasher);
        $compose=require dirname(__DIR__,3).'/routes/academic.php';$app=$compose($auth,new FoundationController($this->db,new PersistedAcademicReferences($this->db),$labels));
        $cookie=null;$csrf=null;$request=function($path,$method='GET',$body=[])use($app,&$cookie,&$csrf){
            $response=$app(Request::create('https://eval.test'.$path,$method,[],$cookie===null?[]:[LocalSessionAuthentication::COOKIE=>$cookie],[],
                ['HTTP_ORIGIN'=>'https://eval.test','HTTP_X_CSRF_TOKEN'=>$csrf??'','CONTENT_TYPE'=>'application/json','REMOTE_ADDR'=>'127.3.0.1'],json_encode((object)$body)));
            foreach($response->headers->getCookies() as $item){$cookie=$item->getValue();}$data=json_decode($response->getContent(),true);$csrf=$data['csrf_token']??$csrf;return $response;};
        $this->assertSame(200,$request('/auth/session')->getStatusCode());
        $this->assertSame(200,$request('/auth/login','POST',['login'=>'fixture-'.$this->studentActor->identityId,'password'=>$password])->getStatusCode());
        $response=$request('/academic/submissions/'.$id);$this->assertSame(200,$response->getStatusCode());$view=json_decode($response->getContent(),true)['data'];
        $this->assertSame($this->assignmentId,$view['originalTeachingAssignment']['id']);$this->assertSame($this->enrollmentId,$view['acceptedUnderEnrollment']['id']);
        $this->assertSame('Corrected '.$this->suffix,$view['originalTeachingAssignment']['instructionalEntry']['displayName']);
        $this->assertSame(403,$request('/academic/submissions','POST',['activity_id'=>$this->activityId])->getStatusCode());
        $this->assertSame(403,$request('/academic/submissions/late','POST',['activity_id'=>$this->activityId])->getStatusCode());
        $this->assertSame(404,$request('/academic/submissions/'.$other)->getStatusCode());
        $this->assertStringNotContainsString('name_key',$response->getContent());$this->assertArrayNotHasKey('grade',$view['submission']);
    }
}
