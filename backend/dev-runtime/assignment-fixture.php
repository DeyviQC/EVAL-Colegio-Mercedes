<?php
declare(strict_types=1);
try{
    require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='migration')throw new RuntimeException();
    require dirname(__DIR__).'/bootstrap.php';$db=Illuminate\Database\Capsule\Manager::connection();
    $db->transaction(function()use($db){
        foreach(['Docente de verificación de organización','Docente sucesor de verificación'] as $name){
            if($db->table('retained_identity_profiles')->where('display_name',$name)->exists())continue;
            $id=$db->table('retained_identities')->insertGetId(['credential_status'=>'active']);
            $db->table('retained_identity_profiles')->insert(['identity_id'=>$id,'display_name'=>$name]);$db->table('local_role_grants')->insert(['identity_id'=>$id,'role'=>'teacher']);
        }
    });echo "Private assignment verification identities retained; no login credentials created.\n";
}catch(Throwable){fwrite(STDERR,"Private assignment fixture unavailable.\n");exit(2);}
