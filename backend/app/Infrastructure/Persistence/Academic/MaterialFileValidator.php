<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use App\Application\Academic\AcademicCommandFailure;
final class MaterialFileValidator {
    public const LIMIT=25*1024*1024;
    private int $limit;
    public function __construct(){ $policy=require dirname(__DIR__,4).'/config/course-materials.php';$this->limit=$policy['max_bytes'];
        if($this->limit<1||$this->limit>self::LIMIT)throw new \LogicException('Approved material bounds required.');}
    public function validate(string $path,string $name):array {
        $size=filesize($path);if($size===false||$size<1||$size>$this->limit)throw new AcademicCommandFailure('invalid_file');
        if(!mb_check_encoding($name,'UTF-8')||mb_strlen($name)>255||preg_match('/[\x00-\x1f\x7f\\\\\/]/u',$name))throw new AcademicCommandFailure('invalid_file');
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $types=['pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        if(!isset($types[$extension]))throw new AcademicCommandFailure('invalid_file');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$prefix=file_get_contents($path,false,null,0,16);
        if($extension==='pdf'){
            $tail=file_get_contents($path,false,null,max(0,$size-2048));
            if($mime!=='application/pdf'||!str_starts_with($prefix,'%PDF-')||!str_contains($tail,'%%EOF'))throw new AcademicCommandFailure('invalid_file');
        }elseif(in_array($extension,['jpg','jpeg','png'],true)){
            $image=@getimagesize($path);if(!$image||($image['mime']??null)!==$types[$extension]||$mime!==$types[$extension])throw new AcademicCommandFailure('invalid_file');
        }elseif(str_ends_with($extension,'x'))$this->openXml($path,$extension);
        else $this->compound($path,$extension);
        return ['bytes'=>$size,'mime'=>$types[$extension],'sha256'=>hash_file('sha256',$path)];
    }
    private function openXml(string $path,string $extension):void {
        $zip=new \ZipArchive;if($zip->open($path)!==true)throw new AcademicCommandFailure('invalid_file');
        try{
            if($zip->numFiles>4096)throw new AcademicCommandFailure('invalid_file');$total=0;$names=[];
            for($i=0;$i<$zip->numFiles;$i++){
                $item=$zip->statIndex($i);$name=strtolower($item['name']);$total+=$item['size'];
                if(isset($names[$name])||$total>128*1024*1024||str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\')
                    ||str_contains($name,'vba')||str_contains($name,'macro')||str_contains($name,'activex')||str_contains($name,'embeddings/')
                    ||($item['encryption_method']??0)!==0)throw new AcademicCommandFailure('invalid_file');$names[$name]=true;
            }
            $types=$zip->statName('[Content_Types].xml');if(!$types||$types['size']>1024*1024)throw new AcademicCommandFailure('invalid_file');
            $xml=$zip->getFromName('[Content_Types].xml');if($xml===false||preg_match('/<!DOCTYPE|<!ENTITY|macroEnabled|vbaProject|activeX/i',$xml))throw new AcademicCommandFailure('invalid_file');
            $doc=new \DOMDocument;$previous=libxml_use_internal_errors(true);
            try{$valid=$doc->loadXML($xml,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
            if(!$valid||$doc->documentElement?->namespaceURI!=='http://schemas.openxmlformats.org/package/2006/content-types')throw new AcademicCommandFailure('invalid_file');
            [$part,$type]=match($extension){'docx'=>['/word/document.xml','wordprocessingml.document'],'pptx'=>['/ppt/presentation.xml','presentationml.presentation'],'xlsx'=>['/xl/workbook.xml','spreadsheetml.sheet']};
            $found=false;foreach($doc->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types','Override') as $node){
                if($node->getAttribute('PartName')===$part&&$node->getAttribute('ContentType')==='application/vnd.openxmlformats-officedocument.'.$type.'.main+xml')$found=true;
            }
            if(!$found||$zip->locateName(substr($part,1))===false||$zip->locateName('_rels/.rels')===false)throw new AcademicCommandFailure('invalid_file');
        }finally{$zip->close();}
    }
    // Parse the compound-file directory through FAT chains; scanning raw bytes misses hidden VBA storages.
    private function compound(string $path,string $extension):void {
        $data=file_get_contents($path);$size=strlen($data);$u32=static fn($s,$offset)=>unpack('V',substr($s,$offset,4))[1];
        if($size<512||substr($data,0,8)!==hex2bin('d0cf11e0a1b11ae1')||substr($data,28,2)!==hex2bin('feff'))throw new AcademicCommandFailure('invalid_file');
        $shift=unpack('v',substr($data,30,2))[1];if(!in_array($shift,[9,12],true))throw new AcademicCommandFailure('invalid_file');$sector=1<<$shift;
        $read=function($id)use($data,$sector,$size){$offset=($id+1)*$sector;if($id>=0xfffffffa||$offset+$sector>$size)throw new AcademicCommandFailure('invalid_file');return substr($data,$offset,$sector);};
        $fatIds=[];for($i=0;$i<109;$i++){$id=$u32($data,76+$i*4);if($id!==0xffffffff)$fatIds[]=$id;}
        $next=$u32($data,68);$difCount=$u32($data,72);if($difCount>4096)throw new AcademicCommandFailure('invalid_file');$seen=[];
        for($i=0;$i<$difCount;$i++){if(isset($seen[$next]))throw new AcademicCommandFailure('invalid_file');$seen[$next]=true;$block=$read($next);
            for($j=0;$j<$sector/4-1;$j++){$id=$u32($block,$j*4);if($id!==0xffffffff)$fatIds[]=$id;}$next=$u32($block,$sector-4);}
        if(count($fatIds)!==$u32($data,44)||!$fatIds||count(array_unique($fatIds))!==count($fatIds)||count($fatIds)>ceil((intdiv($size,$sector)-1)/($sector/4))+1)throw new AcademicCommandFailure('invalid_file');$fat=[];
        foreach($fatIds as $id)$fat=array_merge($fat,array_values(unpack('V*',$read($id))));
        $directory='';$id=$u32($data,48);$seen=[];
        while($id!==0xfffffffe){if(isset($seen[$id])||!isset($fat[$id])||strlen($directory)>4*1024*1024)throw new AcademicCommandFailure('invalid_file');$seen[$id]=true;$directory.=$read($id);$id=$fat[$id];}
        $names=[];$streams=[];$root=null;for($offset=0;$offset+128<=strlen($directory);$offset+=128){$entry=substr($directory,$offset,128);$type=ord($entry[66]);if($type===0)continue;
            $length=unpack('v',substr($entry,64,2))[1];if($length<2||$length>64||$length%2)throw new AcademicCommandFailure('invalid_file');
            $name=mb_convert_encoding(substr($entry,0,$length-2),'UTF-8','UTF-16LE');
            if(preg_match('/vba|macro|project|objectpool|activex|encrypted|encryption/i',$name))throw new AcademicCommandFailure('invalid_file');$names[]=[$name,$type];
            if($u32($entry,124)!==0)throw new AcademicCommandFailure('invalid_file');$streams[$name]=[$u32($entry,116),$u32($entry,120)];if($type===5)$root=$streams[$name];}
        $required=match($extension){'doc'=>['WordDocument'],'xls'=>['Workbook','Book'],'ppt'=>['PowerPoint Document']};$found=false;
        foreach($names as [$name,$type])if($type===2&&in_array($name,$required,true))$found=true;
        if(!$found)throw new AcademicCommandFailure('invalid_file');
        {
            $chain=function($start,$map,$reader,$limit)use($size){$out='';$seen=[];$id=$start;
                while($id!==0xfffffffe){if(isset($seen[$id])||!isset($map[$id])||strlen($out)>$limit)throw new AcademicCommandFailure('invalid_file');$seen[$id]=true;$out.=$reader($id);$id=$map[$id];}return $out;};
            $stream=match($extension){'doc'=>'WordDocument','ppt'=>'PowerPoint Document','xls'=>isset($streams['Workbook'])?'Workbook':'Book'};
            [$start,$length]=$streams[$stream];if($length>self::LIMIT||$length<8)throw new AcademicCommandFailure('invalid_file');
            if($length<4096){if(!$root)throw new AcademicCommandFailure('invalid_file');$mini=$chain($root[0],$fat,$read,self::LIMIT);
                $miniFatBytes=$chain($u32($data,60),$fat,$read,self::LIMIT);$miniFat=array_values(unpack('V*',$miniFatBytes));
                $body=$chain($start,$miniFat,function($id)use($mini){if(($id+1)*64>strlen($mini))throw new AcademicCommandFailure('invalid_file');return substr($mini,$id*64,64);},self::LIMIT);
            }else $body=$chain($start,$fat,$read,self::LIMIT);
            $body=substr($body,0,$length);
            if($extension==='doc'){if(strlen($body)<32||substr($body,0,2)!==hex2bin('eca5')||unpack('v',substr($body,2,2))[1]<0x00c1||(unpack('v',substr($body,10,2))[1]&0x8100))throw new AcademicCommandFailure('invalid_file');return;}
            if($extension==='ppt'){if(!in_array(unpack('v',substr($body,2,2))[1],[1000,4085],true)||$u32($body,4)>strlen($body)-8)throw new AcademicCommandFailure('invalid_file');return;}
            $offset=0;$bof=false;
            while($offset+4<=strlen($body)){[$tag,$count]=array_values(unpack('v2',substr($body,$offset,4)));$offset+=4;if($offset+$count>strlen($body))throw new AcademicCommandFailure('invalid_file');$record=substr($body,$offset,$count);$offset+=$count;
                if($tag===0x002f)throw new AcademicCommandFailure('invalid_file');
                if($tag===0x0809){if($count<4)throw new AcademicCommandFailure('invalid_file');$type=unpack('v',substr($record,2,2))[1];if($type===0x0040)throw new AcademicCommandFailure('invalid_file');$bof=true;}
                if($tag===0x0085&&($count<6||in_array(ord($record[5]),[1,6],true)))throw new AcademicCommandFailure('invalid_file');
                if($tag===0x0018&&($count<2||(unpack('v',substr($record,0,2))[1]&0x000e)))throw new AcademicCommandFailure('invalid_file');
            }if(!$bof||$offset!==strlen($body))throw new AcademicCommandFailure('invalid_file');
        }
    }
}
