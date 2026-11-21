<?php
$dir=sys_get_temp_dir().'/academic-drive-'.bin2hex(random_bytes(6));mkdir($dir);define('DATA_PATH',$dir);
require __DIR__.'/../includes/json_store.php';
function acd_transport(string $url,array $headers):array{
    $GLOBALS['requests'][]=[$url,$headers];$p=parse_url($url);parse_str($p['query']??'',$q);
    if(str_ends_with($p['path'],'/files'))return ['status'=>200,'body'=>json_encode($GLOBALS['listing'])];
    $id=basename($p['path']);$item=$GLOBALS['metadata'][$id]??null;
    return ['status'=>$item?200:404,'body'=>json_encode($item)];
}
require __DIR__.'/../chuyenmon/includes/academic_drive.php';
require __DIR__.'/../includes/chuyenmon_permission_runtime.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function rejects(callable $f,$message){try{$f();}catch(Throwable $e){return;}throw new RuntimeException('Expected rejection: '.$message);}
try{
$root='rootfolder123';$child='childfolder123';$other='otherfolder123';$file='document12345';$mime='application/vnd.google-apps.folder';
$config=['id'=>$root,'resource_key'=>'rootkey','name'=>'Hồ sơ'];
check(acd_folder_link('https://drive.google.com/drive/u/0/folders/'.$root.'?resourcekey=abc')===['id'=>$root,'resource_key'=>'abc'],'Folder and resource key');
foreach(['https://evil.test/drive/folders/'.$root,'http://drive.google.com/drive/folders/'.$root,'https://user@drive.google.com/drive/folders/'.$root,'https://drive.google.com/open?id[]=abc','https://drive.google.com/drive/folders/'.$root.'?resourcekey[]=abc']as$url)rejects(fn()=>acd_folder_link($url),'unsafe input');
check(acd_config()['id']==='','Initial configuration');acd_config_save($config,'');$saved=acd_config();check($saved['id']===$root&&$saved['revision']!=='','Persist configuration');rejects(fn()=>acd_config_save($config,''),'stale revision');check(acd_config()===$saved,'Stale write unchanged');
$GLOBALS['metadata']=[$root=>['id'=>$root,'name'=>'Root','mimeType'=>$mime,'driveId'=>'shared123'], $child=>['id'=>$child,'name'=>'<img src=x onerror=alert(1)>','mimeType'=>$mime,'parents'=>[$root],'resourceKey'=>'childkey','driveId'=>'shared123'], $other=>['id'=>$other,'name'=>'Outside','mimeType'=>$mime]];
$GLOBALS['listing']=['files'=>[['id'=>$file,'name'=>'<script>alert(1)</script>','parents'=>[$child],'mimeType'=>'application/pdf','resourceKey'=>'filekey','webViewLink'=>'javascript:alert(1)'],['id'=>'shortcut12345','name'=>'Shortcut','parents'=>[$child],'mimeType'=>'application/vnd.google-apps.shortcut','webViewLink'=>'https://drive.google.com/file/d/shortcut12345/view'],['id'=>'notchild12345','parents'=>[$other]]],'nextPageToken'=>'next-page'];
$keys=[];$data=acd_browse($saved,$child,'updated',"O'Brien\\notes",'page2','test-token',$keys);
check(count($data['items'])===2&&count($data['breadcrumbs'])===2,'Children and breadcrumbs');check($data['items'][0]['preview_url']==='https://drive.google.com/file/d/'.$file.'/preview?resourcekey=filekey','Safe keyed preview');check(str_starts_with($data['items'][0]['open_url'],'https://drive.google.com/'),'Unsafe external link replaced');check($data['items'][1]['preview_url']==='','Shortcut does not enter outside tree');
[$url,$headers]=end($GLOBALS['requests']);parse_str(parse_url($url,PHP_URL_QUERY),$params);
check($params['corpora']==='drive'&&$params['driveId']==='shared123'&&$params['pageToken']==='page2','Shared Drive pagination');check(str_contains($params['q'],"O\\'Brien\\\\notes"),'Escaped Drive query');check(count(array_filter($headers,fn($h)=>str_starts_with($h,'X-Goog-Drive-Resource-Keys: ')&&str_contains($h,$root.'/rootkey')&&str_contains($h,$child.'/childkey')))===1,'Resource keys propagated');
rejects(function()use($saved,$other,&$keys){acd_browse($saved,$other,'name','','','token',$keys);},'Outside root');
$GLOBALS['metadata'][$child]['parents']=[$other];rejects(function()use($saved,$child,&$keys){acd_browse($saved,$child,'name','','','token',$keys);},'Moved folder revalidated');
$GLOBALS['metadata'][$root]['trashed']=true;rejects(function()use($saved,$root,&$keys){acd_browse($saved,$root,'name','','','token',$keys);},'Trashed root');
foreach([['unknown','',''],['name','',str_repeat('a',4097)],['name',str_repeat('a',151),'']]as[$sort,$q,$cursor])rejects(function()use($saved,$root,$sort,$q,$cursor,&$keys){acd_browse($saved,$root,$sort,$q,$cursor,'token',$keys);},'Invalid filters');
check(cds_cm_feature_access_for_user(['role'=>'gv','permission_model_version'=>2,'groups'=>['gv']],'cm.hoso',$dir)==='none','No new staff grant');check(cds_cm_feature_access_for_user(['role'=>'gv','permission_model_version'=>2,'permission_overrides'=>['cm.hoso'=>'view']],'cm.hoso',$dir)==='view','Explicit view grant');check(cds_cm_feature_access_for_user(['role'=>'admin'],'cm.hoso',$dir)==='delete','Admin access');
acd_config_save(['id'=>'','resource_key'=>'','name'=>''],$saved['revision']);check(acd_config()['id']==='','Unlink');
file_put_contents(acd_config_path(),'{broken');rejects(fn()=>acd_config(),'Corrupt config fails closed');
echo "PASS academic Drive: root confinement, moved/trashed folders, shared Drive, resource keys, pagination, escaped queries, safe URLs, revisions and permissions\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
