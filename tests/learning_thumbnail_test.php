<?php
$dir=sys_get_temp_dir().'/learning-thumbnail-'.bin2hex(random_bytes(5));mkdir($dir);define('DATA_PATH',$dir);define('BASE_URL','/cds/');require __DIR__.'/../includes/learning_thumbnail.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}function rejects($f){try{$f();}catch(Throwable $e){return;}throw new RuntimeException('Expected rejection');}
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ZkAAAAASUVORK5CYII=');
try{
 check(lt_image_info('cover.png',$png)['mime']==='image/png','Actual image detected');rejects(fn()=>lt_image_info('cover.svg','<svg onload="alert(1)"></svg>'));rejects(fn()=>lt_image_info('cover.png','<?php echo 1;'));rejects(fn()=>lt_image_info('cover.php',$png));rejects(fn()=>lt_image_info('large.png',str_repeat('x',LT_MAX_BYTES+1)));
 $big=substr_replace($png,pack('N',4097),16,4);rejects(fn()=>lt_image_info('big.png',$big));
 check(lt_select_upload('existing',null,false)==='existing'&&lt_select_upload('existing',null,true)==='','Keep/remove optional upload');rejects(fn()=>lt_select_upload('', ['error'=>0,'name'=>'a.png','tmp_name'=>__FILE__],false));
 mkdir($dir.'/learning_thumbnails');$name=str_repeat('a',32).'.png';file_put_contents($dir.'/learning_thumbnails/'.$name,$png);$row=['id'=>'abc&thumbnail=0','kind'=>'Video','title'=>'<img onerror=1>','thumbnail_file'=>$name];
 check(str_contains(lt_thumbnail_url($row),'id=abc%26thumbnail%3D0&thumbnail=1'),'Opaque authenticated thumbnail URL');$image=lt_read_thumbnail($row);check($image['mime']==='image/png'&&$image['body']===$png&&str_starts_with($image['etag'],'"'),'Safe image response metadata');
 $html=lt_cover($row);check(str_contains($html,'&lt;img onerror=1&gt;')&&!str_contains($html,'alt="<img'),'Escaped image title');$default=lt_cover(['kind'=>'Video']);check(str_contains($default,'learning-cover--video')&&!str_contains($default,'<img'),'Default type cover for old rows');
 rejects(fn()=>lt_read_thumbnail(['thumbnail_file'=>'../secret.png']));$outside=$dir.'/secret.png';file_put_contents($outside,$png);$link=str_repeat('b',32).'.png';symlink($outside,$dir.'/learning_thumbnails/'.$link);rejects(fn()=>lt_read_thumbnail(['thumbnail_file'=>$link]));
 lt_delete_file('../secret.png');check(is_file($outside),'Delete never follows caller paths');lt_delete_file($name);check(!is_file($dir.'/learning_thumbnails/'.$name)&&lt_thumbnail_url($row)==='','Removed/missing thumbnail falls back');
 echo "PASS learning thumbnails: real image formats, size/dimensions, forged uploads, private paths/symlinks, escaped covers and old-row fallback\n";
}finally{foreach(glob($dir.'/learning_thumbnails/*')?:[]as$f)unlink($f);if(is_dir($dir.'/learning_thumbnails'))rmdir($dir.'/learning_thumbnails');foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
