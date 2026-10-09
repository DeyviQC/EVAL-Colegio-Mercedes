<?php
declare(strict_types=1);
require __DIR__.'/config.php';
if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='runtime')throw new RuntimeException('Invalid verification binding');
require dirname(__DIR__).'/bootstrap.php';
$db=Illuminate\Database\Capsule\Manager::connection();$service=new App\Application\Accounts\LocalAccountService($db);$checks=0;
$assert=function(bool $condition)use(&$checks){if(!$condition)throw new RuntimeException('Account verification failed');$checks++;};
$actor=function(string $login)use($db){$row=$db->table('local_credentials')->where('login',$login)->first();return new App\Infrastructure\Authentication\AuthenticatedActor((string)$row->id,$db->table('local_role_grants')->where('identity_id',$row->id)->pluck('role')->all(),App\Infrastructure\Authentication\CredentialRevision::current($db,(string)$row->id,$row->password));};
$director=$actor('director');$username='docente.verificacion.'.bin2hex(random_bytes(5));
$deny=function(string $category,callable $operation)use($db,$assert){$before=[$db->table('local_account_events')->count(),$db->table('local_credentials')->count(),$db->table('academic_write_guard')->value('last_ordinal')];try{$operation();throw new RuntimeException('Expected account denial');}catch(App\Application\Academic\AcademicCommandFailure $e){$assert($e->category===$category);}$assert($before===[$db->table('local_account_events')->count(),$db->table('local_credentials')->count(),$db->table('academic_write_guard')->value('last_ordinal')]);};
foreach(['docente','estudiante','subdirector'] as $login){$unprivileged=$actor($login);$deny('forbidden',fn()=>$service->execute($unprivileged,'created',null,['name'=>'Unauthorized','username'=>$username,'role'=>'teacher']));}
$deny('invalid_input',fn()=>$service->execute($director,'created',null,['name'=>'Invalid','username'=>'correo@ejemplo.test','role'=>'teacher']));
$deny('invalid_input',fn()=>$service->execute($director,'created',null,['name'=>'Invalid','username'=>$username,'role'=>'director_admin']));
$deny('forbidden',fn()=>$service->execute($director,'password_reset',$director->identityId,[]));
$result=$service->execute($director,'created',null,['name'=>'Docente de verificación de cuentas','username'=>strtoupper($username),'role'=>'teacher']);
$assert(strlen($result['password'])===20);$assert(password_verify($result['password'],$db->table('local_credentials')->where('id',$result['id'])->value('password')));
$assert(!$db->table('teaching_assignments')->where('teacher_id',$result['id'])->exists());
$deny('username_unavailable',fn()=>$service->execute($director,'created',null,['name'=>'Duplicate','username'=>$username,'role'=>'student']));
$teacher=$actor($username);$deny('invalid_current_password',fn()=>$service->execute($teacher,'password_changed',null,['current_password'=>'wrong','password'=>'New-valid-password-2026']));
$service->execute($director,'deactivated',$teacher->identityId,[]);$service->execute($director,'reactivated',$teacher->identityId,[]);
$deny('forbidden',fn()=>$service->execute($teacher,'password_changed',null,['current_password'=>$result['password'],'password'=>'New-valid-password-2026']));
$assert($db->table('local_credentials')->where('id',$result['id'])->value('login')===$username);
foreach([fn()=>$db->table('local_account_events')->where('target_id',$result['id'])->delete(),fn()=>$db->table('local_credentials')->where('id',$result['id'])->update(['login'=>'forged']),fn()=>$db->table('local_role_grants')->where('identity_id',$result['id'])->update(['role'=>'director_admin'])] as $operation){try{$operation();throw new RuntimeException('Forbidden runtime privilege');}catch(Illuminate\Database\QueryException){$assert(true);}}
$assert($db->getSchemaBuilder()->getColumnListing('local_account_events')===['id','actor_id','target_id','operation','operation_key','recorded_at']);
echo json_encode(['checks'=>$checks,'authorization'=>true,'retention'=>true,'restrictedPrivileges'=>true]).PHP_EOL;
