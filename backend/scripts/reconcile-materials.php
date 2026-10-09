<?php
declare(strict_types=1);
$database=require __DIR__.'/database.php';
if(getenv('EVAL_UNIT')!=='U11'||getenv('EVAL_DB_ROLE')!=='migration')throw new RuntimeException('Owned isolated material maintenance only.');
$root=getenv('EVAL_MATERIAL_ROOT');if(!$root||count($argv)!==2)throw new RuntimeException('Configured private root and one storage key required.');
$storage=new \App\Infrastructure\Persistence\Academic\LocalMaterialStorage($root);
echo $storage->reconcile($argv[1],$database->getConnection()).PHP_EOL;
