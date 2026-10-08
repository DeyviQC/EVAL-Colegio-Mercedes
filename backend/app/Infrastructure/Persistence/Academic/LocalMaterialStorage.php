<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Filesystem\Filesystem;
use App\Application\Academic\AcademicCommandFailure;
final class LocalMaterialStorage {
    private Filesystem $files;
    public function __construct(private string $root,?Filesystem $files=null){
        $this->files=$files??new Filesystem;if(!$this->files->isDirectory($root))$this->files->makeDirectory($root,0700,true);
        if(!is_dir($root)||is_link($root))throw new AcademicCommandFailure('storage_unavailable');
    }
    public function path(string $key):string {if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new AcademicCommandFailure('invalid_input');return $this->root.DIRECTORY_SEPARATOR.$key;}
    public function finalize(string $source):array {
        $key=bin2hex(random_bytes(32));$lock=fopen($this->path($key).'.lock','x+b');
        if(!$lock||!flock($lock,LOCK_EX))throw new AcademicCommandFailure('storage_unavailable');
        try{if(!$this->files->copy($source,$this->path($key).'.part')||!$this->files->move($this->path($key).'.part',$this->path($key)))throw new AcademicCommandFailure('storage_unavailable');
            return [$key,$lock];
        }catch(\Throwable $error){@unlink($this->path($key).'.part');@unlink($this->path($key));$this->release($key,$lock);throw new AcademicCommandFailure('storage_unavailable');}
    }
    public function release(string $key,$lock):void {flock($lock,LOCK_UN);fclose($lock);/* Persistent lock inode prevents unlink/reopen races. */}
    public function remove(string $key):void {if(is_file($this->path($key))&&!unlink($this->path($key)))throw new AcademicCommandFailure('storage_unavailable');}
    public function verify(array $material):string {
        $path=$this->path($material['storage_key']);if(!is_file($path)||is_link($path)||filesize($path)!==(int)$material['bytes']||!hash_equals($material['sha256'],hash_file('sha256',$path)))throw new AcademicCommandFailure('storage_unavailable');return $path;
    }
    public function reconcile(string $key,\Illuminate\Database\Connection $db):string {
        $lock=fopen($this->path($key).'.lock','c+b');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return 'busy';}
        try{return $db->transaction(function()use($key,$db){$db->table('academic_write_guard')->where('id',1)->lockForUpdate()->first();
            $row=$db->table('course_materials')->where('storage_key',$key)->first();if($row){$this->verify((array)$row);return 'retained';}
            $this->remove($key);return 'orphan_removed';});}finally{$this->release($key,$lock);}
    }
}
