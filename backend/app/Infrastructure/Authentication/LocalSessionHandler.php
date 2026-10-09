<?php
declare(strict_types=1);
namespace App\Infrastructure\Authentication;
use Illuminate\Session\DatabaseSessionHandler;
use RuntimeException;

/** Revocation tombstones stop stale concurrent requests from restoring a logged-out session. */
final class LocalSessionHandler extends DatabaseSessionHandler
{
    public function usable(string $id):bool
    {
        $row=$this->connection->table($this->table)->where('id',$id)->first();
        return $row!==null && $row->revoked_at===null && !$this->expired($row);
    }
    public function read($sessionId):string|false {return $this->usable($sessionId)?parent::read($sessionId):'';}
    public function write($sessionId,$data):bool
    {
        return $this->connection->transaction(function()use($sessionId,$data){
            $row=$this->connection->table($this->table)->where('id',$sessionId)->lockForUpdate()->first();
            if($row && ($row->revoked_at!==null || $this->expired($row))){throw new RuntimeException('Session was revoked or expired.');}
            $payload=$this->getDefaultPayload($data);
            if($row){$this->connection->table($this->table)->where('id',$sessionId)->update($payload);}
            else{$this->connection->table($this->table)->insert(['id'=>$sessionId]+$payload);}
            return true;
        });
    }
    public function destroy($sessionId):bool
    {
        $this->connection->table($this->table)->upsert([
            'id'=>$sessionId,'payload'=>'','last_activity'=>time(),'revoked_at'=>gmdate('Y-m-d H:i:s'),
        ],['id'],['payload','revoked_at']);
        return true;
    }
    public function gc($lifetime):int {return 0;} // Cleanup policy is not silently selected.
}
