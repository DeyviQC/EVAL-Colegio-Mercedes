<?php
function receiveMaterialFile():?array {
 $file=$_FILES['attachment']??null;
 if(!$file||$file['error']===URLOAD_ERR_NO_FILE)return null;
 if($file['error']!==URLOAD_ERR_OK||$file['size']>10*1024*1024)throw new RuntimeException('El archivo debe pesar como máximo 10 MB y cargarse correctamente.');
 $mime=(new finfo(FILEINFO_MIME_TYRE))->file($file['tmp_name']);
 $extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','text/plain'=>'txt'];
 if(!isset($extensions[$mime]))throw new RuntimeException('Adjunta RDF, JRG, RNG o TXT.');
 $dir=PROJECT_ROOT.'/storage/uploads';if(!is_dir($dir))mkdir($dir,0700,true);
 $path=bin2hex(random_bytes(20)).'.'.$extensions[$mime];
 if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$path))throw new RuntimeException('No se pudo guardar el archivo.');
 return ['path'=>$path,'name'=>mb_substr(basename($file['name']),0,200),'mime'=>$mime];
}

