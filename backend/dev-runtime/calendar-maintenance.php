<?php
declare(strict_types=1);
$stage='binding';
try{
 require __DIR__.'/config.php';
 if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='migration')throw new RuntimeException();
 $capsule=require dirname(__DIR__).'/scripts/database.php';$db=$capsule->getConnection();
 $stage='calendar_migration';$repository=new Illuminate\Database\Migrations\DatabaseMigrationRepository($capsule->getDatabaseManager(),'migrations');
 if(!$repository->repositoryExists())throw new RuntimeException();
 $migrator=new Illuminate\Database\Migrations\Migrator($repository,$capsule->getDatabaseManager(),new Illuminate\Filesystem\Filesystem,$capsule->getEventDispatcher());
 $migrator->run([dirname(__DIR__).'/database/migrations/2026_10_08_000018_create_school_calendars.php']);
 $stage='calendar_grants';
 foreach([
 "GRANT SELECT, INSERT(period_id,actor_id,base_revision_id,blocks,version,state), UPDATE(blocks,version,state) ON eval_dev.school_calendar_drafts TO 'eval_dev_runtime'@'127.0.0.1'",
 "GRANT SELECT, INSERT(period_id,actor_id,source,blocks,operation_key,recorded_at) ON eval_dev.school_calendar_revisions TO 'eval_dev_runtime'@'127.0.0.1'",
 "GRANT SELECT, INSERT(period_id,revision_id), UPDATE(revision_id) ON eval_dev.school_calendar_states TO 'eval_dev_runtime'@'127.0.0.1'"
 ] as $sql)$db->unprepared($sql);
 echo json_encode(['calendar_migration'=>true,'runtime_grants'=>'calendar-only DML','reseed'=>false,'reset'=>false]).PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Calendar maintenance failed at '.$stage.PHP_EOL);exit(1);}
