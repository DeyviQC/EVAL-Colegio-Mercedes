<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use App\Application\Academic\AcademicCommandFailure;
final class DeliveryFileValidator {
 public function validate(string $path,string $name):array {
  $config=require dirname(__DIR__,4).'/config/activity-delivery.php';$limit=$config['max_bytes'];if(!is_int($limit)||$limit<1||$limit>10485760)throw new \LogicException('Invalid evidence bounds');
  $size=filesize($path);if($size===false||$size<1||$size>$limit)throw new AcademicCommandFailure('invalid_file');
  $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($extension,['pdf','jpg','jpeg','png','txt','docx'],true))throw new AcademicCommandFailure('invalid_file');
  if($extension!=='txt')return (new MaterialFileValidator)->validate($path,$name);
  if(!mb_check_encoding($name,'UTF-8')||mb_strlen($name)>255||preg_match('/[\x00-\x1f\x7f\\\\\/]/u',$name))throw new AcademicCommandFailure('invalid_file');
  $text=file_get_contents($path);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
  if(!mb_check_encoding($text,'UTF-8')||str_contains($text,"\0")||preg_match('/[\x01-\x08\x0b\x0c\x0e-\x1f]/',$text)||!in_array($mime,['text/plain','application/octet-stream'],true)||preg_match('/<\s*(?:html|script|svg|\?php)|^\s*(?:MZ|#!)/i',$text))throw new AcademicCommandFailure('invalid_file');
  return ['bytes'=>$size,'mime'=>'text/plain','sha256'=>hash_file('sha256',$path)];
 }
}
