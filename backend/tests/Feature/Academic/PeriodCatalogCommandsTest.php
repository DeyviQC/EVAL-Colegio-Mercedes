<?php
declare(strict_types=1);
namespace Tests\Feature\Academic;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\AcademicPeriodCommands;
use App\Application\Academic\Commands\AcademicCatalogCommands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Authentication\LocalUserProvider;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;

final class PeriodCatalogCommandsTest extends TestCase
{
    private Connection $db;
    private Connection $migration;
    private AcademicPeriodCommands $periods;
    private AcademicCatalogCommands $catalog;
    private AuthenticatedActor $actor;
    private string $suffix;
    private array $fixtureSecrets=[];
    protected function setUp():void
    {
        $this->db=DB::connection();$this->migration=DB::connection('migration');
        $this->assertSame('eval_u4_test',$this->db->getDatabaseName());
        $this->periods=new AcademicPeriodCommands($this->db);$this->catalog=new AcademicCatalogCommands($this->db);
        $this->suffix=bin2hex(random_bytes(6));$this->actor=$this->actor('director_admin');
    }
    protected function tearDown():void
    {
        foreach([$this->db,$this->migration] as $db){if($db->transactionLevel()>0){$db->rollBack(0);}}
        $this->migration->table('academic_periods')->where('state','active')->update(['state'=>'closed']);
    }
    private function actor(string $role):AuthenticatedActor
    {
        $id=$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $hasher=new BcryptHasher(['rounds'=>4]);$secret=bin2hex(random_bytes(16));$login='fixture-'.$id;
        $this->fixtureSecrets[(string)$id]=$secret;
        $this->migration->table('local_credentials')->insert(['id'=>$id,'login'=>$login,'password'=>$hasher->make($secret)]);
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$id,'role'=>$role]);
        $provider=new LocalUserProvider($this->db,$hasher,'local_credentials');
        $user=$provider->retrieveByCredentials(['login'=>$login,'password'=>$secret]);
        $this->assertNotNull($user);$this->assertTrue($provider->validateCredentials($user,['password'=>$secret]));
        return $provider->actor($user);
    }
    private function period(string $label='Period'):string
    {return $this->periods->create($this->actor,['name'=>$label.' '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);}
    private function grade(string $label='Grade',bool $active=true):string
    {return $this->catalog->create($this->actor,'grade',['name'=>$label.' '.$this->suffix,'is_active'=>$active]);}
    private function denied(string $category,callable $operation):void
    {
        $before=(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal');
        $events=$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count();
        try{$operation();$this->fail('Invalid command succeeded.');}
        catch(AcademicCommandFailure $error){$this->assertSame($category,$error->category);}
        $this->assertSame($before,(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal'));
        $this->assertSame($events,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count());
    }
    public function testPeriodCreationAndExplicitNormalLifecycle():void
    {
        $id=$this->period();
        $this->assertSame('planned',$this->db->table('academic_periods')->where('id',$id)->value('state'));
        $this->periods->activate($this->actor,$id);$this->assertSame('active',$this->db->table('academic_periods')->where('id',$id)->value('state'));
        $this->periods->close($this->actor,$id);$this->assertSame('closed',$this->db->table('academic_periods')->where('id',$id)->value('state'));
        $this->assertSame(3,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count());
    }
    public function testInvalidDeclaredDatesDoNotAllocateOrCreatePeriod():void
    {
        $this->denied('invalid_interval',fn()=>$this->periods->create($this->actor,['name'=>'Invalid '.$this->suffix,'start_on'=>'2026-12-01','end_on'=>'2026-03-01']));
    }
    public function testCatalogCreateRetainsClassificationVisibleLabelAndSeparateKey():void
    {
        $label=" a\u{0301}LGEBRA ".$this->suffix;
        $id=$this->catalog->create($this->actor,'entry',['name'=>$label,'kind'=>'subject']);
        $row=$this->db->table('instructional_entries')->where('id',$id)->first();
        $this->assertNotNull($row);$this->assertSame($label,$row->name);$this->assertSame('subject',$row->kind);
        $this->assertNotSame($label,$row->name_key);
    }
    public function testActiveCatalogDuplicateUsesApprovedUnicodeEquality():void
    {
        $this->catalog->create($this->actor,'entry',['name'=>'Álgebra '.$this->suffix,'kind'=>'subject']);
        $this->denied('duplicate_name',fn()=>$this->catalog->create($this->actor,'entry',['name'=>" a\u{0301}LGEBRA ".$this->suffix.' ','kind'=>'subject']));
    }
    public function testPeriodSkippedReversedAndRepeatedTransitionsFail():void
    {
        $id=$this->period();$this->denied('invalid_transition',fn()=>$this->periods->close($this->actor,$id));
        $this->periods->activate($this->actor,$id);
        $this->denied('invalid_transition',fn()=>$this->periods->activate($this->actor,$id));
        $this->periods->close($this->actor,$id);
        $this->denied('invalid_transition',fn()=>$this->periods->activate($this->actor,$id));
        $this->denied('invalid_transition',fn()=>$this->periods->close($this->actor,$id));
    }
    public function testOnlyOneActivePeriodAndNoCalendarDrivenCurrentPeriod():void
    {
        $this->assertNull($this->periods->current($this->actor));
        $past=$this->periods->create($this->actor,['name'=>'Past '.$this->suffix,'start_on'=>'2020-01-01','end_on'=>'2020-12-31']);
        $this->assertNull($this->periods->current($this->actor));
        $this->assertSame('planned',$this->periods->historical($this->actor,$past)['state']);
        $this->periods->activate($this->actor,$past);
        $other=$this->period('Other');
        $this->denied('active_period_conflict',fn()=>$this->periods->activate($this->actor,$other));
        $this->assertSame($past,$this->periods->current($this->actor)['id']);
        $this->periods->close($this->actor,$past);$this->assertNull($this->periods->current($this->actor));
        $this->assertSame('closed',$this->periods->historical($this->actor,$past)['state']);
    }
    public function testPeriodNamesRemainUniqueAfterClosureWithAccentsDistinguished():void
    {
        $id=$this->period('Álgebra');$this->periods->activate($this->actor,$id);$this->periods->close($this->actor,$id);
        $this->denied('duplicate_name',fn()=>$this->periods->create($this->actor,[
            'name'=>"\u{00A0}a\u{0301}LGEBRA ".$this->suffix."\u{2003}",'start_on'=>'2026-03-01','end_on'=>'2026-12-20',
        ]));
        $this->assertNotSame($id,$this->period('Algebra'));
    }
    public function testMalformedDatesAndClientStateKeysAreRejected():void
    {
        $this->denied('invalid_interval',fn()=>$this->periods->create($this->actor,['name'=>'Invalid '.$this->suffix,'start_on'=>'2026-02-30','end_on'=>'2026-12-20']));
        $this->denied('invalid_input',fn()=>$this->periods->create($this->actor,['name'=>'Tampered '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20','state'=>'active']));
        $this->denied('invalid_name',fn()=>$this->periods->create($this->actor,['name'=>'  ','start_on'=>'2026-03-01','end_on'=>'2026-12-20']));
    }
    public function testOtherRolesAndForgedRoleSnapshotsCannotManageFoundation():void
    {
        foreach(['vice_principal','teacher','student'] as $role){
            $actor=$this->actor($role);$forged=new AuthenticatedActor($actor->identityId,['director_admin'],$actor->credentialRevision());
            $this->denied('forbidden',fn()=>$this->periods->create($forged,['name'=>$role.' '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']));
            $this->denied('forbidden',fn()=>$this->catalog->create($forged,'grade',['name'=>$role.' '.$this->suffix]));
            $this->denied('forbidden',fn()=>$this->periods->current($forged));
        }
    }
    public function testSubjectAreaAndSectionParentUniquenessStaySeparate():void
    {
        $subject=$this->catalog->create($this->actor,'entry',['name'=>'Álgebra '.$this->suffix,'kind'=>'subject']);
        $this->assertNotSame($subject,$this->catalog->create($this->actor,'entry',['name'=>'Álgebra '.$this->suffix,'kind'=>'area']));
        $this->assertNotSame($subject,$this->catalog->create($this->actor,'entry',['name'=>'Algebra '.$this->suffix,'kind'=>'subject']));
        $g1=$this->grade('First');$g2=$this->grade('Second');
        $s1=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$g1]);
        $this->denied('duplicate_name',fn()=>$this->catalog->create($this->actor,'section',['name'=>' a ','grade_id'=>$g1]));
        $this->assertNotSame($s1,$this->catalog->create($this->actor,'section',['name'=>' a ','grade_id'=>$g2]));
        $this->denied('duplicate_name',fn()=>$this->catalog->create($this->actor,'grade',['name'=>' first '.$this->suffix.' ']));
    }
    public function testRenameAndReactivationConflictsKeepSourceAndReferences():void
    {
        foreach(['grade','entry','section'] as $type){
            $scope=match($type){'entry'=>['kind'=>'subject'],'section'=>['grade_id'=>$this->grade('Parent')],default=>[]};
            $first=$this->catalog->create($this->actor,$type,$scope+['name'=>'First '.$this->suffix]);
            $second=$this->catalog->create($this->actor,$type,$scope+['name'=>'Second '.$this->suffix]);
            $table=match($type){'entry'=>'instructional_entries','section'=>'sections',default=>'grades'};
            $this->denied('duplicate_name',fn()=>$this->catalog->update($this->actor,$type,$second,['name'=>' first '.$this->suffix.' ']));
            $this->assertSame('Second '.$this->suffix,$this->db->table($table)->where('id',$second)->value('name'));
            $this->catalog->update($this->actor,$type,$second,['is_active'=>false]);
            $this->catalog->update($this->actor,$type,$second,['name'=>' first '.$this->suffix.' ']);
            $this->denied('duplicate_name',fn()=>$this->catalog->update($this->actor,$type,$second,['is_active'=>true]));
            $this->assertSame(0,(int)$this->db->table($table)->where('id',$second)->value('is_active'));
            $this->catalog->update($this->actor,$type,$first,['is_active'=>false]);
            $this->catalog->update($this->actor,$type,$second,['is_active'=>true]);
            $this->assertSame(1,(int)$this->db->table($table)->where('id',$second)->value('is_active'));
        }
    }
    public function testInvalidKindsNamesAndScopeRewritesAreRejected():void
    {
        foreach(['both','neither','unknown'] as $kind){$this->denied('invalid_kind',fn()=>$this->catalog->create($this->actor,'entry',['name'=>'Entry '.$this->suffix,'kind'=>$kind]));}
        $this->denied('invalid_name',fn()=>$this->catalog->create($this->actor,'grade',['name'=>"\u{00A0}\u{2003}"]));
        $grade=$this->grade();$section=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$grade]);
        $entry=$this->catalog->create($this->actor,'entry',['name'=>'Entry '.$this->suffix,'kind'=>'subject']);
        $this->denied('invalid_input',fn()=>$this->catalog->update($this->actor,'entry',$entry,['kind'=>'area']));
        $this->denied('invalid_input',fn()=>$this->catalog->update($this->actor,'section',$section,['grade_id'=>$this->actor->identityId]));
        $this->denied('invalid_input',fn()=>$this->catalog->update($this->actor,'grade',$grade,['id'=>PHP_INT_MAX]));
    }
    private function children():array
    {
        $period=$this->period();$this->periods->activate($this->actor,$period);$grade=$this->grade();
        $section=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$grade]);
        $entry=$this->catalog->create($this->actor,'entry',['name'=>'Subject '.$this->suffix,'kind'=>'subject']);
        $scope=['academic_period_id'=>$period,'grade_id'=>$grade,'section_id'=>$section];
        $key=(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal');
        $student=$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $teacher=$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $enrollment=$this->migration->table('student_enrollments')->insertGetId($scope+[
            'student_id'=>$student,'state'=>'active','effective_from'=>'2026-01-01','operational_start_key'=>$key,
        ]);
        $assignment=$this->migration->table('teaching_assignments')->insertGetId($scope+[
            'teacher_id'=>$teacher,'instructional_entry_id'=>$entry,'state'=>'active','effective_from'=>'2026-03-01','operational_start_key'=>$key,
        ]);
        return compact('period','grade','section','entry','enrollment','assignment');
    }
    public function testClosingPeriodRetainsActiveChildrenAndUnrelatedCatalogAndPeriods():void
    {
        $ids=$this->children();$other=$this->period('Other');
        $enrollment=(array)$this->db->table('student_enrollments')->where('id',$ids['enrollment'])->first();
        $assignment=(array)$this->db->table('teaching_assignments')->where('id',$ids['assignment'])->first();
        $this->periods->close($this->actor,$ids['period']);
        $this->assertSame($enrollment,(array)$this->db->table('student_enrollments')->where('id',$ids['enrollment'])->first());
        $this->assertSame($assignment,(array)$this->db->table('teaching_assignments')->where('id',$ids['assignment'])->first());
        $this->assertSame(1,(int)$this->db->table('grades')->where('id',$ids['grade'])->value('is_active'));
        $this->assertSame('planned',$this->periods->historical($this->actor,$other)['state']);
        $this->assertSame('closed',$this->periods->historical($this->actor,$ids['period'])['state']);
        $this->assertNull($this->periods->current($this->actor));
        $this->assertSame('closed',$this->db->table('academic_lifecycle_events')->where('period_id',$ids['period'])->orderByDesc('id')->value('event_type'));
    }
    public function testReferencedCatalogCorrectionRetainsScopeOwnerAndDisplayEvidence():void
    {
        $ids=$this->children();$before=(array)$this->db->table('teaching_assignments')->where('id',$ids['assignment'])->first();
        $this->catalog->update($this->actor,'entry',$ids['entry'],['name'=>'Corrected '.$this->suffix,'is_active'=>false]);
        $this->assertSame($before,(array)$this->db->table('teaching_assignments')->where('id',$ids['assignment'])->first());
        $row=$this->db->table('instructional_entries')->where('id',$ids['entry'])->first();
        $this->assertSame('subject',$row->kind);$this->assertSame(0,(int)$row->is_active);
        $event=$this->db->table('academic_lifecycle_events')->where('entry_id',$ids['entry'])->where('event_type','renamed')->first();
        $metadata=json_decode($event->metadata,true);
        $this->assertSame('Subject '.$this->suffix,$metadata['previous_name']);$this->assertSame('Corrected '.$this->suffix,$metadata['new_name']);
        $this->denied('invalid_input',fn()=>$this->catalog->update($this->actor,'entry',$ids['entry'],['kind'=>'area']));
        $otherGrade=$this->grade('Other grade');
        $this->denied('invalid_input',fn()=>$this->catalog->update($this->actor,'section',$ids['section'],['grade_id'=>$otherGrade]));
    }
    public function testLockedActiveReferenceValidationDeniesInactiveAndMismatchedScope():void
    {
        $ids=$this->children();
        $validate=function($section)use($ids){
            (new \App\Infrastructure\Persistence\Academic\AcademicWriteTransaction($this->db))->execute($this->actor->identityId,
                fn()=>new \App\Infrastructure\Persistence\Academic\AcademicLockSet([
                    'instructional_entries'=>[$ids['entry']],'grades'=>[$ids['grade']],'sections'=>[$section],
                ]),function($context)use($ids,$section){
                    \App\Application\Academic\CatalogReferenceValidation::assertActive($context,$ids['grade'],$section,$ids['entry']);return true;
                },fn()=>new \App\Infrastructure\Persistence\Academic\AcademicMutationResult('validation fixture'));
        };
        $validate($ids['section']);
        foreach(['entry'=>'instructional_entries','grade'=>'grades','section'=>'sections'] as $type=>$table){
            $this->catalog->update($this->actor,$type,$ids[$type],['is_active'=>false]);
            $this->denied('inactive_catalog_reference',fn()=>$validate($ids['section']));
            $this->catalog->update($this->actor,$type,$ids[$type],['is_active'=>true]);
        }
        $other=$this->catalog->create($this->actor,'section',['name'=>'B','grade_id'=>$this->grade('Other')]);
        $this->denied('scope_mismatch',fn()=>$validate($other));
    }
    public function testCatalogManagementDoesNotInventActiveGradeRequirement():void
    {
        $grade=$this->grade('Inactive parent',false);
        $section=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$grade]);
        $this->assertSame($grade,(string)$this->db->table('sections')->where('id',$section)->value('grade_id'));
        $this->assertSame(0,(int)$this->db->table('grades')->where('id',$grade)->value('is_active'));
    }
    public function testLedgerFailureRollsBackCommandStateKeyAndEvents():void
    {
        $id=$this->period();$before=(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal');
        $events=$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count();
        $trigger='u4_event_fault_'.(int)$this->actor->identityId;
        $this->migration->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON academic_lifecycle_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected U4 ledger failure'");
        try{$this->periods->activate($this->actor,$id);$this->fail('Failed event committed source.');}
        catch(\App\Infrastructure\Persistence\Academic\AcademicTransactionFailure $error){$this->assertSame('integrity_conflict',$error->category);}
        finally{$this->migration->unprepared("DROP TRIGGER $trigger");}
        $this->assertSame('planned',$this->db->table('academic_periods')->where('id',$id)->value('state'));
        $this->assertSame($before,(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal'));
        $this->assertSame($events,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count());
    }
    public function testSessionAuthenticatedActorExecutesCommandAndOwnsLedgerEvidence():void
    {
        $app=new \App\Infrastructure\Authentication\LocalSessionAuthentication($this->db,
            new \Illuminate\Encryption\Encrypter(random_bytes(32),'AES-256-CBC'),'https://eval.test',5,3,60,new BcryptHasher(['rounds'=>4]));
        $cookie=null;$csrf=null;
        $send=function($path,$method='GET',$body=[])use($app,&$cookie,&$csrf){
            $request=\Symfony\Component\HttpFoundation\Request::create('https://eval.test'.$path,$method,[],
                $cookie?[\App\Infrastructure\Authentication\LocalSessionAuthentication::COOKIE=>$cookie]:[],[],
                ['HTTP_ORIGIN'=>'https://eval.test','HTTP_X_CSRF_TOKEN'=>$csrf??'','REMOTE_ADDR'=>'127.2.0.'.((int)$this->actor->identityId%255)],json_encode($body));
            $response=$app->handle($request,function($authenticated){
                $id=$this->periods->create($authenticated,['name'=>'Session '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
                return new \Symfony\Component\HttpFoundation\JsonResponse(['id'=>$id]);
            });
            foreach($response->headers->getCookies() as $c){$cookie=$c->getValue();}
            $data=json_decode($response->getContent(),true);$csrf=$data['csrf_token']??$csrf;return $response;
        };
        $this->assertSame(200,$send('/auth/session')->getStatusCode());
        $this->assertSame(200,$send('/auth/login','POST',['login'=>'fixture-'.$this->actor->identityId,'password'=>$this->fixtureSecrets[$this->actor->identityId]])->getStatusCode());
        $response=$send('/period-fixture','POST');$this->assertSame(200,$response->getStatusCode());
        $id=json_decode($response->getContent(),true)['id'];
        $this->assertSame($this->actor->identityId,(string)$this->db->table('academic_lifecycle_events')->where('period_id',$id)->value('actor_id'));
    }
    private function worker(string $mode,string $id,string $label,bool $hold):array
    {
        $signals=sys_get_temp_dir().'/eval-u4-'.bin2hex(random_bytes(8));mkdir($signals);
        $process=proc_open([PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
            dirname(__DIR__,2).'/Support/u4-race-worker.php',$mode,$this->actor->identityId,$id,$label,$hold?'hold':'free',$signals],
            [0=>['file','NUL','r'],1=>['file',$signals.'/out','w'],2=>['file',$signals.'/err','w']],$pipes);
        $this->assertIsResource($process);return [$process,$signals];
    }
    private function awaitOutput(string $signals,string $needle):string
    {
        $output='';$deadline=microtime(true)+10;
        do{$output=is_file($signals.'/out')?(string)file_get_contents($signals.'/out'):'';
            if(str_contains($output,$needle)){return $output;}usleep(10000);
        }while(microtime(true)<$deadline);
        $this->fail('Worker barrier timed out: '.$output.(string)file_get_contents($signals.'/err'));
    }
    private function race(string $mode,string $first,string $second,string $label,string $denial):void
    {
        $before=(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal');
        $events=$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count();$workers=[];
        try {
            $workers[]=$a=$this->worker($mode,$first,$label,true);$this->awaitOutput($a[1],'HELD');
            $workers[]=$b=$this->worker($mode,$second,$label,false);$this->awaitOutput($b[1],'STARTED');
            $waiting=false;$deadline=microtime(true)+5;
            do{
                foreach($this->db->select('SHOW PROCESSLIST') as $row){
                    if(str_contains(strtolower((string)$row->Info),'academic_write_guard') && str_contains(strtolower((string)$row->Info),'for update')){$waiting=true;break;}
                }
                if(!$waiting){usleep(10000);}
            }while(!$waiting && microtime(true)<$deadline);
            $this->assertTrue($waiting,'Second command must wait on the common guard.');
            file_put_contents($a[1].'/go','go');
            $this->assertStringContainsString('COMMITTED',$this->awaitOutput($a[1],'COMMITTED'));
            $this->assertStringContainsString($denial,$this->awaitOutput($b[1],$denial));
            $this->assertSame($before+1,(int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal'));
            $this->assertSame($events+1,$this->db->table('academic_lifecycle_events')->where('actor_id',$this->actor->identityId)->count());
        }finally{
            foreach($workers as [$process,$signals]){
                if(proc_get_status($process)['running']){proc_terminate($process);}proc_close($process);
                foreach(glob($signals.'/*') as $file){unlink($file);}rmdir($signals);
            }
        }
    }
    public function testActualCommandsSerializeEmptySetPeriodActivationRace():void
    {
        $this->race('activate',$this->period('First'),$this->period('Second'),'unused','active_period_conflict');
        $this->assertSame(1,$this->db->table('academic_periods')->where('state','active')->count());
    }
    public function testActualCommandsSerializeCatalogCreationRace():void
    {
        $label='Race '.$this->suffix;$this->race('create','0','0',$label,'duplicate_name');
        $this->assertSame(1,$this->db->table('grades')->where('name',$label)->where('is_active',true)->count());
    }
    public function testActualCommandsSerializeActiveRenameRace():void
    {
        $first=$this->grade('First');$second=$this->grade('Second');$label='Renamed '.$this->suffix;
        $this->race('rename',$first,$second,$label,'duplicate_name');
        $this->assertSame('Second '.$this->suffix,$this->db->table('grades')->where('id',$second)->value('name'));
    }
    public function testActualCommandsSerializeReactivationRace():void
    {
        $first=$this->grade('Same',false);$second=$this->grade('Same',false);
        $this->race('reactivate',$first,$second,'unused','duplicate_name');
        $this->assertSame(0,(int)$this->db->table('grades')->where('id',$second)->value('is_active'));
    }
    public function testCredentialRemovalAfterAuthenticationDeniesCommandsUnderGuard():void
    {
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update(['password'=>null]);
        $this->denied('forbidden',fn()=>$this->grade());
        $this->denied('forbidden',fn()=>$this->period());
    }
    public function testCredentialRevisionChangeAfterAuthenticationDeniesCommands():void
    {
        $this->migration->table('local_credentials')->where('id',$this->actor->identityId)->update([
            'password'=>(new BcryptHasher(['rounds'=>4]))->make('changed fixture'),
        ]);
        $this->denied('forbidden',fn()=>$this->grade());
    }
    public function testUnicodePolicyAppliesToRenameAndReactivationInEveryCatalogScope():void
    {
        foreach(['entry','grade','section'] as $type){
            $scope=match($type){'entry'=>['kind'=>'area'],'section'=>['grade_id'=>$this->grade('Unicode parent')],default=>[]};
            $first=$this->catalog->create($this->actor,$type,$scope+['name'=>'Álgebra '.$this->suffix]);
            $second=$this->catalog->create($this->actor,$type,$scope+['name'=>'Different '.$this->suffix]);
            $equivalent="\u{2003}a\u{0301}LGEBRA ".$this->suffix."\u{00A0}";
            $this->denied('duplicate_name',fn()=>$this->catalog->update($this->actor,$type,$second,['name'=>$equivalent]));
            $this->catalog->update($this->actor,$type,$second,['is_active'=>false]);
            $this->catalog->update($this->actor,$type,$second,['name'=>$equivalent]);
            $this->denied('duplicate_name',fn()=>$this->catalog->update($this->actor,$type,$second,['is_active'=>true]));
            $table=match($type){'entry'=>'instructional_entries','section'=>'sections',default=>'grades'};
            $this->assertSame($equivalent,$this->db->table($table)->where('id',$second)->value('name'));
            $this->catalog->update($this->actor,$type,$first,['is_active'=>false]);
            $this->catalog->update($this->actor,$type,$second,['is_active'=>true]);
            $this->assertNotSame($second,$this->catalog->create($this->actor,$type,$scope+['name'=>'Algebra '.$this->suffix]));
        }
    }
    public function testCatalogDeactivationKeepsAllExistingRelationshipRows():void
    {
        $ids=$this->children();
        $beforeEnrollment=(array)$this->db->table('student_enrollments')->where('id',$ids['enrollment'])->first();
        $beforeAssignment=(array)$this->db->table('teaching_assignments')->where('id',$ids['assignment'])->first();
        foreach(['grade','section','entry'] as $type){$this->catalog->update($this->actor,$type,$ids[$type],['is_active'=>false]);}
        $this->assertSame($beforeEnrollment,(array)$this->db->table('student_enrollments')->where('id',$ids['enrollment'])->first());
        $this->assertSame($beforeAssignment,(array)$this->db->table('teaching_assignments')->where('id',$ids['assignment'])->first());
    }
}
