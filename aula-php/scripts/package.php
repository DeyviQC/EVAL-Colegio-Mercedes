<?php
declare(strict_types=1);
// Exporta el proyecto y una instantánea consistente de la base local.
$root=dirname(__DIR__);
$output=$root.'/dist';
if(!is_dir($output))mkdir($output,0700,true);
$zip=new ZipArchive();
$archive=$output.'/EVAL-NSM-laptop.zip';
if($zip->open($archive,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo crear el ZIP.');
$prefix='EVAL-NSM/';
$snapshot=null;
try {
 foreach(['app','config','database','public','resources','scripts','tests','docs'] as $folder) {
  $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));
  foreach($iterator as $file) {
   if(!$file->isFile())continue;
   $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($root)+1));
   if($relative==='config/local.php')continue;
   $zip->addFile($file->getPathname(),$prefix.$relative);
  }
 }
 foreach(['README.md','.gitignore','Abrir EVAL-NSM.cmd'] as $file)$zip->addFile($root.'/'.$file,$prefix.$file);
 $zip->addEmptyDir($prefix.'storage/uploads');
 $zip->addEmptyDir($prefix.'storage/logs');
 if(is_file($root.'/storage/eval.sqlite')) {
  $snapshot=tempnam(sys_get_temp_dir(),'eval-export-');
  $source=new SQLite3($root.'/storage/eval.sqlite',SQLITE3_OPEN_READONLY);
  $target=new SQLite3($snapshot);
  if(!$source->backup($target)||$target->querySingle('PRAGMA integrity_check')!=='ok')throw new RuntimeException('No se pudo verificar la base exportada.');
  $target->close();$source->close();
  $zip->addFile($snapshot,$prefix.'storage/eval.sqlite');
 }
 if(is_dir($root.'/storage/uploads'))foreach(new DirectoryIterator($root.'/storage/uploads') as $file)if($file->isFile())$zip->addFile($file->getPathname(),$prefix.'storage/uploads/'.$file->getFilename());
 if(!$zip->close())throw new RuntimeException('No se pudo finalizar el ZIP.');
 echo "ZIP preparado: $archive\n";
} finally {if($snapshot&&is_file($snapshot))unlink($snapshot);}
