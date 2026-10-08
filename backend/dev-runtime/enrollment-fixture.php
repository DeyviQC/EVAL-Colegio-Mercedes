<?php
declare(strict_types=1);
// Private development verification identity; no public provisioning endpoint.
try{
    require __DIR__.'/config.php';
    if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='migration')throw new RuntimeException();
    require dirname(__DIR__).'/bootstrap.php';$db=Illuminate\Database\Capsule\Manager::connection();
    $name='Estudiante de verificación de matrículas';
    $db->transaction(function()use($db,$name){
        if($db->table('retained_identity_profiles')->where('display_name',$name)->exists())return;
        $id=$db->table('retained_identities')->insertGetId(['credential_status'=>'active']);
        $db->table('retained_identity_profiles')->insert(['identity_id'=>$id,'display_name'=>$name]);
        $db->table('local_role_grants')->insert(['identity_id'=>$id,'role'=>'student']);
    });
    echo "Private verification identity retained; no login credential created.\n";
}catch(Throwable){fwrite(STDERR,"Private enrollment verification fixture unavailable.\n");exit(2);}
