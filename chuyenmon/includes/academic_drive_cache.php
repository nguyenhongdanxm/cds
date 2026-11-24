<?php
/** Private, bounded, credential/revision-scoped cache. Never replaces live ancestry/access checks. */
function acd_cache_dir(): string {
    $dir=DATA_PATH.'/academic_drive_cache';if(!is_dir($dir)&&!@mkdir($dir,0700,true))return '';
    if(!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n",LOCK_EX);
    return $dir;
}
function acd_cache_key(array $scope): string {return hash('sha256',json_encode($scope,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
function acd_cache_read(string $key,int $ttl): ?array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))return null;$dir=acd_cache_dir();if($dir==='')return null;$path=$dir.'/'.$key.'.json';
    $size=@filesize($path);if($size===false||$size>2097152)return null;$raw=@file_get_contents($path);if($raw===false||strlen($raw)>2097152)return null;$data=json_decode($raw,true);
    if(!is_array($data)||!is_int($data['at']??null)||$data['at']>time()||$data['at']<time()-$ttl||!is_array($data['value']??null))return null;return$data['value'];
}
function acd_cache_write(string $key,array $value): void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))return;$dir=acd_cache_dir();if($dir==='')return;
    $body=json_encode(['at'=>time(),'value'=>$value],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);if($body===false||strlen($body)>2097152)return;
    $tmp=@tempnam($dir,'write_');if(!$tmp)return;if(@file_put_contents($tmp,$body)!==strlen($body)){@unlink($tmp);return;}@chmod($tmp,0600);@rename($tmp,$dir.'/'.$key.'.json');@unlink($tmp);acd_cache_prune();
}
function acd_cache_prune(): void {
    $dir=acd_cache_dir();if($dir==='')return;$files=[];$bytes=0;
    foreach(glob($dir.'/*')?:[]as$path){if(!is_file($path))continue;$name=basename($path);if(!preg_match('/^[a-f0-9]{64}\.(json|bin)$/D',$name))continue;$at=(int)@filemtime($path);$size=(int)@filesize($path);if($at<time()-600){@unlink($path);continue;}$files[]=['path'=>$path,'at'=>$at,'size'=>$size];$bytes+=$size;}
    usort($files,static fn($a,$b)=>$a['at']<=>$b['at']);$count=count($files);
    foreach($files as$file){if($bytes<=64*1024*1024&&$count<=160)break;@unlink($file['path']);$bytes-=$file['size'];$count--;}
}
function acd_cache_content_key(array $config,array $file,string $token): string {
    $version=(string)($file['version']??'');$modified=(string)($file['modifiedTime']??'');$checksum=(string)($file['md5Checksum']??'');
    if($version===''&&$modified===''&&$checksum==='')return '';
    return acd_cache_key(['content-v1',$config['id']??'',$config['revision']??'',$config['resource_key']??'',hash('sha256',$token),$file['id']??'',$version,$modified,$checksum,$file['size']??'',$file['mimeType']??'']);
}
function acd_cache_content_get(string $key,array $plan): ?array {
    if($key==='')return null;$value=acd_cache_read($key,300);if(!$value||empty($value['inline'])||($value['mime']??'')!==$plan['mime'])return null;
    $path=acd_cache_dir().'/'.$key.'.bin';$size=@filesize($path);if($size===false||$size<1||$size>$plan['limit']||$size!==($value['size']??null))return null;
    $stream=@fopen($path,'rb');if(!$stream)return null;return ['stream'=>$stream,'size'=>$size,'name'=>$plan['name'],'mime'=>$value['mime'],'inline'=>true,'etag'=>'"'.$key.'"','cache_hit'=>true];
}
function acd_cache_content_put(string $key,array $content): void {
    if($key===''||empty($content['inline']))return;$dir=acd_cache_dir();if($dir==='')return;$tmp=@tempnam($dir,'write_');if(!$tmp)return;$out=@fopen($tmp,'wb');if(!$out){@unlink($tmp);return;}
    rewind($content['stream']);$copied=stream_copy_to_stream($content['stream'],$out);fclose($out);rewind($content['stream']);
    if($copied!==$content['size']){@unlink($tmp);return;}@chmod($tmp,0600);if(!@rename($tmp,$dir.'/'.$key.'.bin')){@unlink($tmp);return;}
    acd_cache_write($key,['size'=>$content['size'],'mime'=>$content['mime'],'inline'=>true]);
}
