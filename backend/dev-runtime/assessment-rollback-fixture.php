<?php
declare(strict_types=1);
require __DIR__.'/config.php';if(!evalDevBinding(getenv())||getenv('EVAL_DB_ROLE')!=='migration'||!in_array($argv[1]??null,['prepare','remove'],true))exit(2);
require dirname(__DIR__).'/bootstrap.php';$db=Illuminate\Database\Capsule\Manager::connection();
$db->unprepared('DROP TRIGGER IF EXISTS eval_assessment_rollback_probe');
if($argv[1]==='prepare')$db->unprepared("CREATE TRIGGER eval_assessment_rollback_probe BEFORE INSERT ON delivery_assessments FOR EACH ROW BEGIN IF NEW.feedback='EVAL atomic assessment rollback verification' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Isolated assessment failure probe'; END IF; END");
echo 'Scoped assessment fixture '.($argv[1]==='prepare'?'prepared':'removed').PHP_EOL;
