<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use Illuminate\Database\Connection;
use App\Application\Academic\Queries\AcademicIdentityLabels;
final class LocalIdentityLabels implements AcademicIdentityLabels {
    public function __construct(private Connection $db){}
    public function displayName(string $identityId):?string {
        $name=$this->db->table('retained_identity_profiles')->where('identity_id',$identityId)->value('display_name');
        return is_string($name)&&trim($name)!==''?$name:null;
    }
}
