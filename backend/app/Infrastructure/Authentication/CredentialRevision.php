<?php
declare(strict_types=1);
namespace App\Infrastructure\Authentication;
use Illuminate\Database\Connection;
final class CredentialRevision {
    public static function current(Connection $db,string $id,string $passwordHash):string {
        // Older isolated prerequisite schemas retain their verified password-only revision.
        $epoch=$db->getSchemaBuilder()->hasTable('local_account_events')?$db->table('local_account_events')->where('target_id',$id)->where('operation','deactivated')->max('operation_key'):null;
        return hash('sha256',$passwordHash.($epoch===null?'':'|deactivated:'.(string)$epoch));
    }
}
