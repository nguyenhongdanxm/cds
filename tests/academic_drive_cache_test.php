<?php
$dir=sys_get_temp_dir().'/academic-cache-'.bin2hex(random_bytes(5));mkdir($dir);define('DATA_PATH',$dir);
require __DIR__.'/../chuyenmon/includes/academic_drive.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}function rejects($f){try{$f();}catch(Throwable$e){return;}throw new RuntimeException('Expected rejection');}
function acd_transport(string $url,array $headers): array {
    $GLOBALS['calls']++;$id=basename(parse_url($url,PHP_URL_PATH));$body=$id==='files'?$GLOBALS['listing']:($GLOBALS['metadata'][$id]??null);return ['status'=>$body?200:404,'body'=>json_encode($body)];
}
function acd_binary_transport(string $url,array $headers,$stream,int $limit): int {$GLOBALS['binary_calls']++;fwrite($stream,'%PDF-1.4 test document');return 200;}
$root='rootfolder123';$child='childfolder123';$file='document12345';$outside='outside12345';$folderMime='application/vnd.google-apps.folder';$config=['id'=>$root,'resource_key'=>'','revision'=>'one'];$keys=[];
$GLOBALS['calls']=0;$GLOBALS['binary_calls']=0;
$GLOBALS['metadata']=[$root=>['id'=>$root,'mimeType'=>$folderMime,'name'=>'Root'],$child=>['id'=>$child,'mimeType'=>$folderMime,'name'=>'Child','parents'=>[$root]],$outside=>['id'=>$outside,'mimeType'=>$folderMime,'name'=>'Outside'],$file=>['id'=>$file,'mimeType'=>'application/pdf','name'=>'Test.pdf','parents'=>[$child],'version'=>'1','capabilities'=>['canDownload'=>true]]];
$GLOBALS['listing']=['files'=>[['id'=>$file,'mimeType'=>'application/pdf','name'=>'First.pdf','parents'=>[$child]]]];
try{
 $first=acd_browse($config,$child,'name','','','token',$keys);$cold=$GLOBALS['calls'];$GLOBALS['calls']=0;
 $second=acd_browse($config,$child,'name','','','token',$keys);check($second===$first&&$GLOBALS['calls']===$cold-1,'Warm folder saves a listing request, but still validates both ancestors');
 $GLOBALS['listing']['files'][0]['name']='Changed.pdf';check(acd_browse($config,$child,'name','','','token',$keys)['items'][0]['name']==='First.pdf','Bounded short listing cache');
 check(acd_browse($config,$child,'name','','','token',$keys,true)['items'][0]['name']==='Changed.pdf','Explicit refresh bypasses cache');
 $GLOBALS['metadata'][$child]['parents']=[$outside];rejects(function()use($config,$child,&$keys){acd_browse($config,$child,'name','','','token',$keys);});$GLOBALS['metadata'][$child]['parents']=[$root];
 $c=acd_content($config,$file,'token',$keys);check(!$c['cache_hit']&&$GLOBALS['binary_calls']===1,'Cold content reads upstream');$etag=$c['etag'];fclose($c['stream']);
 $c=acd_content($config,$file,'token',$keys);check($c['cache_hit']&&$GLOBALS['binary_calls']===1&&$c['etag']===$etag&&stream_get_contents($c['stream'])==='%PDF-1.4 test document','Warm content reuses bytes with stable ETag');fclose($c['stream']);
 $GLOBALS['metadata'][$file]['capabilities']['canDownload']=false;rejects(function()use($config,$file,&$keys){acd_content($config,$file,'token',$keys);});$GLOBALS['metadata'][$file]['capabilities']['canDownload']=true;
 $GLOBALS['metadata'][$file]['parents']=[$outside];rejects(function()use($config,$file,&$keys){acd_content($config,$file,'token',$keys);});$GLOBALS['metadata'][$file]['parents']=[$child];
 $GLOBALS['metadata'][$file]['version']='2';$c=acd_content($config,$file,'token',$keys);check(!$c['cache_hit']&&$c['etag']!==$etag&&$GLOBALS['binary_calls']===2,'Source revision invalidates bytes');fclose($c['stream']);
 $c=acd_content($config,$file,'other-token',$keys);check(!$c['cache_hit']&&$GLOBALS['binary_calls']===3,'Credential boundary');fclose($c['stream']);
 $newConfig=$config;$newConfig['revision']='two';$c=acd_content($newConfig,$file,'token',$keys);check(!$c['cache_hit']&&$GLOBALS['binary_calls']===4,'Configuration boundary');fclose($c['stream']);
 $cacheKey=acd_cache_content_key($config,$GLOBALS['metadata'][$file],'token');file_put_contents(acd_cache_dir().'/'.$cacheKey.'.json',json_encode(['at'=>time()-301,'value'=>['size'=>22,'mime'=>'application/pdf','inline'=>true]]));$c=acd_content($config,$file,'token',$keys);check(!$c['cache_hit'],'Expired bytes are fetched again');fclose($c['stream']);
 $key=str_repeat('a',64);file_put_contents(acd_cache_dir().'/'.$key.'.bin',str_repeat('x',1024));touch(acd_cache_dir().'/'.$key.'.bin',time()-601);acd_cache_prune();check(!is_file(acd_cache_dir().'/'.$key.'.bin'),'Expired cache files pruned');
 $large=acd_cache_dir().'/'.str_repeat('b',64).'.bin';$handle=fopen($large,'wb');ftruncate($handle,65*1024*1024);fclose($handle);touch($large,time()-20);acd_cache_prune();check(!is_file($large),'Private byte cache stays within disk budget');
 rejects(function(){acd_cache_key(['bad'=>"\xff"]);});
 rejects(function()use($config,$child,&$keys){acd_browse($config,$child,'name',"\xff",'','token',$keys);});
 check(acd_cache_read('../secrets',20)===null,'Invalid cache keys rejected');
 echo "PASS academic cache: fewer remote requests, forced refresh, live confinement/revocation checks, byte reuse, source/config/credential invalidation, expiry and cleanup\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$p){if($p->isDir())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($dir);}
