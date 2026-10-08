<?php
declare(strict_types=1);
namespace Tests\Support;
abstract class ReplacementTestCase extends AssignmentTestCase
{
    protected function replacementRace(string $firstMode,string $secondMode,string $secondOutcome,array $firstInput,array $secondInput):void
    {
        $worker=function($mode,$hold,$input){
            $signals=sys_get_temp_dir().'/eval-u8-'.bin2hex(random_bytes(8));mkdir($signals);
            $process=proc_open([PHP_BINARY,'-c',dirname((string)getenv('EVAL_VENDOR_DIR')).'/php.ini',
                __DIR__.'/u8-race-worker.php',$mode,$this->actor->identityId,json_encode($input),$hold?'hold':'free',$signals],
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
            $workers[]=$a=$worker($firstMode,true,$firstInput);$output($a[1],'HELD');
            $workers[]=$b=$worker($secondMode,false,$secondInput);$output($b[1],'STARTED');
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
