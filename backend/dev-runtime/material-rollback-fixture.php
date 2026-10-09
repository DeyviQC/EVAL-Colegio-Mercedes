<?php
declare(strict_types=1);
require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='migration'||!in_array($argv[1]??null,['prepare','remove'],true))exit(2);require dirname(__DIR__).'/bootstrap.php';$db=Illuminate\Database\Capsule\Manager::connection();
foreach(['INSERT','UPDATE'] as $op){$name='eval_material_'.strtolower($op).'_rollback_probe';$db->unprepared('DROP TRIGGER IF EXISTS '.$name);if($argv[1]==='prepare')$db->unprepared("CREATE TRIGGER $name BEFORE $op ON material_states FOR EACH ROW BEGIN DECLARE probe_title VARCHAR(255); SELECT title INTO probe_title FROM material_revisions WHERE id=NEW.current_revision_id; IF probe_title='EVAL atomic material rollback verification' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Isolated material pointer failure probe'; END IF; END");}
echo 'Scoped material rollback fixture '.($argv[1]==='prepare'?'prepared':'removed').PHP_EOL;
