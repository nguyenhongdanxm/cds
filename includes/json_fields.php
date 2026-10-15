<?php
require_once __DIR__ . '/json_rows.php';

/** Locate top-level object fields without materializing their values. */
function cds_json_fields(string $file): array {
    if (!file_exists($file)) return [];
    $handle=@fopen($file,'rb');if(!$handle)throw new RuntimeException('Không đọc được '.basename($file));
    $phase='root';$key='';$keyRaw='';$escaped=false;$string=false;$depth=0;$start=0;$offset=0;$entries=[];
    try {
        while(!feof($handle)){
            $chunk=fread($handle,65536);if($chunk===false)throw new RuntimeException('Lỗi đọc JSON.');
            for($i=0,$n=strlen($chunk);$i<$n;$i++){
                $c=$chunk[$i];$position=$offset+$i;
                if($phase==='container'||$phase==='string'){
                    if(!$escaped){$span=strcspn($chunk,$string?"\\\"":'"{}[]',$i);if($span>0){$i+=$span-1;continue;}}
                    if($string){if($escaped)$escaped=false;elseif($c==='\\')$escaped=true;elseif($c==='"')$string=false;}
                    elseif($c==='"')$string=true;
                    elseif($c==='{'||$c==='[')$depth++;
                    elseif($c==='}'||$c===']')$depth--;
                    if($depth===0&&!$string){$entries[]=[$key,$start,$position+1-$start];$phase='comma';}
                    continue;
                }
                if($phase==='scalar'){
                    if(strpos(" \t\r\n,}",$c)===false)continue;
                    $entries[]=[$key,$start,$position-$start];$phase='comma';$i--;continue;
                }
                if($phase==='key'){
                    $keyRaw.=$c;
                    if($escaped)$escaped=false;elseif($c==='\\')$escaped=true;
                    elseif($c==='"'){
                        $key=json_decode($keyRaw,true);if(json_last_error()!==JSON_ERROR_NONE||!is_string($key))throw new RuntimeException('Khóa JSON không hợp lệ.');
                        $phase='colon';$keyRaw='';
                    }
                    continue;
                }
                if(strpos(" \t\r\n",$c)!==false)continue;
                if($phase==='root'&&$c==='{'){$phase='first';continue;}
                if(($phase==='first'||$phase==='comma')&&$c==='}'){$phase='end';continue;}
                if(($phase==='first'||$phase==='next')&&$c==='"'){$phase='key';$keyRaw='"';$escaped=false;continue;}
                if($phase==='colon'&&$c===':'){$phase='value';continue;}
                if($phase==='comma'&&$c===','){$phase='next';continue;}
                if($phase==='value'){
                    $start=$position;$escaped=false;
                    if($c==='{'||$c==='['){$phase='container';$depth=1;$string=false;continue;}
                    if($c==='"'){$phase='string';$depth=0;$string=true;continue;}
                    if(strpos('-0123456789tfn',$c)!==false){$phase='scalar';continue;}
                }
                throw new RuntimeException('Đối tượng JSON không hợp lệ; giữ nguyên nguồn.');
            }
            $offset+=strlen($chunk);
        }
        if($phase!=='end')throw new RuntimeException('Đối tượng JSON chưa hoàn chỉnh.');
    }finally{fclose($handle);}
    $fields=[];
    foreach($entries as[$name,$at,$length]){
        if(in_array($name,['reports','states'],true)&&file_get_contents($file,false,null,$at,1)==='['){
            foreach(cds_json_rows($file,$at,$length)as$row){/* validate one record at a time */}
        }else{
            $raw=file_get_contents($file,false,null,$at,$length);json_decode($raw,true);
            if(json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException('Giá trị JSON không hợp lệ.');
        }
        $fields[$name]=[$at,$length];
    }
    return $fields;
}

function cds_json_field_value(string $file,string $name,$default=[]){
    $fields=cds_json_fields($file);if(!isset($fields[$name]))return$default;
    [$at,$length]=$fields[$name];return json_decode(file_get_contents($file,false,null,$at,$length),true);
}

function cds_json_field_rows(string $file,string $name):Generator{
    $fields=cds_json_fields($file);if(!isset($fields[$name]))return;
    [$at,$length]=$fields[$name];
    if(trim((string)file_get_contents($file,false,null,$at,min($length,4)))==='null')return;
    yield from cds_json_rows($file,$at,$length);
}

/** Replace only one field; preserve all other source bytes and unknown properties. */
function cds_json_field_save(string $file,string $name,callable $build,bool $valueMode=false):bool{
    $arrayTmp=null;$objectTmp=null;$in=null;$out=null;
    try{
        $fields=cds_json_fields($file);$dir=dirname($file);
        if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))return false;
        $arrayTmp=tempnam($dir,'field-rows.');if($arrayTmp===false)return false;
        $rows=isset($fields[$name])?cds_json_rows($file,$fields[$name][0],$fields[$name][1]):[];
        if(isset($fields[$name])&&trim((string)file_get_contents($file,false,null,$fields[$name][0],min(4,$fields[$name][1])))==='null')$rows=[];
        if($valueMode){
            $value=isset($fields[$name])?json_decode(file_get_contents($file,false,null,$fields[$name][0],$fields[$name][1]),true):[];
            if(!cds_json_save($arrayTmp,$build($value)))return false;
        }elseif(!cds_json_rows_save($arrayTmp,$build($rows)))return false;
        $objectTmp=tempnam($dir,basename($file).'.tmp.');if($objectTmp===false)return false;
        $out=fopen($objectTmp,'wb');if(!$out)throw new RuntimeException('Không mở được tệp đối tượng tạm.');
        if(isset($fields[$name])){
            [$at,$length]=$fields[$name];$in=fopen($file,'rb');
            if(!$in||stream_copy_to_stream($in,$out,$at)!==$at)throw new RuntimeException('Lỗi sao chép đầu JSON.');
            $part=fopen($arrayTmp,'rb');try{if(!$part||stream_copy_to_stream($part,$out)!==filesize($arrayTmp))throw new RuntimeException('Lỗi ghi danh sách JSON.');}finally{if(is_resource($part))fclose($part);}
            if(fseek($in,$at+$length)!==0||stream_copy_to_stream($in,$out)===false)throw new RuntimeException('Lỗi sao chép cuối JSON.');
        }else{
            if(file_exists($file)){
                $in=fopen($file,'rb');$end=filesize($file)-1;
                do{if($end<0||fseek($in,$end)!==0)throw new RuntimeException('Không tìm được cuối đối tượng.');$c=fread($in,1);$end--;}while(strpos(" \t\r\n",$c)!==false);
                if($c!=='}')throw new RuntimeException('Cuối đối tượng JSON không hợp lệ.');
                rewind($in);$prefixBytes=$end+1;if(stream_copy_to_stream($in,$out,$prefixBytes)!==$prefixBytes)throw new RuntimeException('Không sao chép được nguồn JSON.');
            }elseif(fwrite($out,'{')!==1)throw new RuntimeException('Không ghi được đối tượng mới.');
            $header=($fields?',':'').json_encode($name).':';if(fwrite($out,$header)!==strlen($header))throw new RuntimeException('Không ghi được đầu đối tượng.');
            $part=fopen($arrayTmp,'rb');try{if(!$part||stream_copy_to_stream($part,$out)!==filesize($arrayTmp))throw new RuntimeException('Lỗi ghi danh sách JSON.');}finally{if(is_resource($part))fclose($part);}
            if(fwrite($out,'}')!==1)throw new RuntimeException('Không ghi được cuối đối tượng.');
        }
        if(!fflush($out))throw new RuntimeException('Không hoàn tất đối tượng JSON.');
        fclose($out);$out=null;if(is_resource($in)){fclose($in);$in=null;}
        @chmod($objectTmp,0640);if(!rename($objectTmp,$file))throw new RuntimeException('Không công bố được đối tượng JSON.');
        clearstatcache(true,$file);return true;
    }catch(Throwable$e){error_log('[CDS JSON field] '.$e->getMessage());return false;}
    finally{if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);foreach([$arrayTmp,$objectTmp]as$tmp)if(is_string($tmp)&&is_file($tmp))unlink($tmp);}
}
