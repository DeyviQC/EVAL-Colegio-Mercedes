<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Application\Academic\AcademicCommandFailure;
use App\Application\Academic\Commands\AcademicCatalogCommands;
use App\Application\Academic\Commands\AcademicPeriodCommands;
use App\Application\Academic\Commands\EnrollmentCommands;
use App\Infrastructure\Authentication\AuthenticatedActor;
use App\Infrastructure\Authentication\LocalUserProvider;
use App\Infrastructure\Persistence\Academic\AcademicTransactionFailure;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;

abstract class EnrollmentTestCase extends TestCase
{
    protected const DATABASE='eval_u5_test';
    protected Connection $db;
    protected Connection $migration;
    protected EnrollmentCommands $commands;
    protected AcademicPeriodCommands $periods;
    protected AcademicCatalogCommands $catalog;
    protected AuthenticatedActor $actor;
    protected string $suffix;
    protected string $period;
    protected string $grade;
    protected string $section;
    protected string $student;
    protected function setUp():void
    {
        $this->db=DB::connection();$this->migration=DB::connection('migration');
        $this->assertSame(static::DATABASE,$this->db->getDatabaseName());
        $this->suffix=bin2hex(random_bytes(6));$this->actor=$this->actor('director_admin');
        $this->commands=new EnrollmentCommands($this->db);
        $this->periods=new AcademicPeriodCommands($this->db);$this->catalog=new AcademicCatalogCommands($this->db);
        $this->period=$this->periods->create($this->actor,['name'=>'Period '.$this->suffix,'start_on'=>'2026-03-01','end_on'=>'2026-12-20']);
        $this->periods->activate($this->actor,$this->period);
        $this->grade=$this->catalog->create($this->actor,'grade',['name'=>'Grade '.$this->suffix]);
        $this->section=$this->catalog->create($this->actor,'section',['name'=>'A','grade_id'=>$this->grade]);
        $this->student=(string)$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
    }
    protected function tearDown():void
    {
        foreach([$this->db,$this->migration] as $db){if($db->transactionLevel()>0){$db->rollBack(0);}}
        $this->migration->table('academic_periods')->where('state','active')->update(['state'=>'closed']);
    }
    protected function actor(string $role):AuthenticatedActor
    {
        $id=$this->migration->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $hasher=new BcryptHasher(['rounds'=>4]);$password=bin2hex(random_bytes(16));$login='fixture-'.$id;
        $this->migration->table('local_credentials')->insert(['id'=>$id,'login'=>$login,'password'=>$hasher->make($password)]);
        $this->migration->table('local_role_grants')->insert(['identity_id'=>$id,'role'=>$role]);
        $provider=new LocalUserProvider($this->db,$hasher,'local_credentials');$user=$provider->retrieveByCredentials(['login'=>$login,'password'=>$password]);
        $this->assertNotNull($user);$this->assertTrue($provider->validateCredentials($user,['password'=>$password]));return $provider->actor($user);
    }
    protected function input(array $overrides=[]):array
    {
        return array_replace(['student_id'=>$this->student,'academic_period_id'=>$this->period,'grade_id'=>$this->grade,
            'section_id'=>$this->section,'effective_from'=>'2026-01-01'],$overrides);
    }
    protected function enrollment(array $overrides=[]):string {return $this->commands->create($this->actor,$this->input($overrides));}
    protected function ordinal():int {return (int)$this->db->table('academic_write_guard')->where('id',1)->value('last_ordinal');}
    protected function today():string {return (new \DateTimeImmutable('now',new \DateTimeZone('America/Lima')))->format('Y-m-d');}
    protected function denied(string $category,callable $operation):void
    {
        $before=$this->ordinal();$events=$this->db->table('academic_lifecycle_events')->count();$rows=$this->db->table('student_enrollments')->count();
        try{$operation();$this->fail('Invalid academic operation succeeded.');}
        catch(AcademicCommandFailure|AcademicTransactionFailure $error){$this->assertSame($category,$error->category);}
        $this->assertSame($before,$this->ordinal());$this->assertSame($events,$this->db->table('academic_lifecycle_events')->count());
        $this->assertSame($rows,$this->db->table('student_enrollments')->count());$this->assertFalse($this->db->getPdo()->inTransaction());
    }
    protected function race(string $firstMode,string $secondMode,string $secondOutcome):void
    {
        $worker=function($mode,$hold){
            $signals=sys_get_temp_dir().'/eval-u5-'.bin2hex(random_bytes(8));mkdir($signals);
            $process=proc_open([PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
                __DIR__.'/u5-race-worker.php',$mode,$this->actor->identityId,json_encode($this->input()),$hold?'hold':'free',$signals],
                [0=>['file','NUL','r'],1=>['file',$signals.'/out','w'],2=>['file',$signals.'/err','w']],$pipes);
            $this->assertIsResource($process);return [$process,$signals];
        };
        $output=function($signals,$needle){
            $deadline=microtime(true)+10;
            do{$text=is_file($signals.'/out')?(string)file_get_contents($signals.'/out'):'';
                if(str_contains($text,$needle)){return $text;}usleep(10000);
            }while(microtime(true)<$deadline);
            $this->fail('Worker barrier timed out: '.$text.(string)file_get_contents($signals.'/err'));
        };
        $workers=[];
        try{
            $workers[]=$a=$worker($firstMode,true);$output($a[1],'HELD');
            $workers[]=$b=$worker($secondMode,false);$output($b[1],'STARTED');
            $waiting=false;$deadline=microtime(true)+5;
            do{
                foreach($this->db->select('SHOW PROCESSLIST') as $row){
                    if(str_contains(strtolower((string)$row->Info),'academic_write_guard') && str_contains(strtolower((string)$row->Info),'for update')){$waiting=true;break;}
                }
                if(!$waiting){usleep(10000);}
            }while(!$waiting && microtime(true)<$deadline);
            $this->assertTrue($waiting,'Second real command must wait on the guard.');
            file_put_contents($a[1].'/go','go');
            $this->assertStringContainsString('COMMITTED',$output($a[1],'COMMITTED'));
            $this->assertStringContainsString($secondOutcome,$output($b[1],$secondOutcome));
        }finally{
            foreach($workers as [$process,$signals]){
                if(proc_get_status($process)['running']){proc_terminate($process);}proc_close($process);
                foreach(glob($signals.'/*') as $file){unlink($file);}rmdir($signals);
            }
        }
    }
}
