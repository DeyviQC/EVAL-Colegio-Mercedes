<?php
declare(strict_types=1);
$stage='binding';
try{
 require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='migration')throw new RuntimeException();
 $capsule=require dirname(__DIR__).'/scripts/database.php';$db=$capsule->getConnection();$stage='resource_week_migration';
 if(!$db->getSchemaBuilder()->hasTable('school_calendar_revisions'))throw new RuntimeException();
 $repository=new Illuminate\Database\Migrations\DatabaseMigrationRepository($capsule->getDatabaseManager(),'migrations');if(!$repository->repositoryExists())throw new RuntimeException();
 $migrator=new Illuminate\Database\Migrations\Migrator($repository,$capsule->getDatabaseManager(),new Illuminate\Filesystem\Filesystem,$capsule->getEventDispatcher());
 $migrator->run([dirname(__DIR__).'/database/migrations/2026_10_08_000019_create_resource_week_associations.php']);
 $stage='resource_week_grants';foreach(['material','activity'] as $kind){
  $db->unprepared("GRANT SELECT, INSERT(resource_id,period_id,calendar_revision_id,week_id,actor_id,version,operation_key,recorded_at) ON eval_dev.{$kind}_week_changes TO 'eval_dev_runtime'@'127.0.0.1'");
  $db->unprepared("GRANT SELECT, INSERT(resource_id,change_id), UPDATE(change_id) ON eval_dev.{$kind}_week_states TO 'eval_dev_runtime'@'127.0.0.1'");
 }
 echo json_encode(['resource_week_migration'=>true,'runtime_grants'=>'association-only DML','calendar_changes'=>false,'reseed'=>false,'reset'=>false]).PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Resource week maintenance failed at '.$stage.PHP_EOL);exit(1);}
