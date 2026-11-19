<?php
/** Exercise the real cPanel PHP copier against a temporary destination, never live hosting. */
$repo=dirname(__DIR__);$tmp=sys_get_temp_dir().'/login-notice-deploy-'.bin2hex(random_bytes(8));$target=$tmp.'/public';
mkdir($target.'/data/login_notice_receipts/existing',0750,true);mkdir($target.'/uploads',0750,true);mkdir($target.'/includes',0750,true);
$sentinels=['data/login_notice.json'=>'{"id":"existing-notice","enabled":true}', 'data/login_notice_receipts/existing/account.json'=>'{"closed_at":"keep"}', 'data/users.json'=>'existing accounts', 'uploads/evidence.txt'=>'existing upload', 'includes/database.conf'=>'existing connection'];
foreach($sentinels as $path=>$bytes)file_put_contents($target.'/'.$path,$bytes);
try{
 $env=getenv();$env['CDS_DEPLOY_PATH']=$target;$env['CDS_INSTANCE_CONFIG']=$tmp.'/no-instance.json';$env['CDS_DEPLOY_CONFIG']=$tmp.'/no-deploy.json';
 $p=proc_open([PHP_BINARY,$repo.'/tools/deploy_cds.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$repo,$env);
 if(!is_resource($p))throw new RuntimeException('Cannot start isolated deploy');
 $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0||!str_contains($out,'CDS_DEPLOY_OK'))throw new RuntimeException('Deploy failed: '.$out.$err);
 foreach(['login.php','login_notice.php','admin.php','includes/login_notice_store.php','includes/login_notice_popup.php','includes/login_notice_settings.php','assets/login-notice.js','assets/login-notice.css','assets/login-notice-settings.js','assets/viewer-read-only.js'] as $path){
  if(!is_file($target.'/'.$path)||hash_file('sha256',$repo.'/'.$path)!==hash_file('sha256',$target.'/'.$path))throw new RuntimeException('Missing/stale deployed file: '.$path);
 }
 foreach($sentinels as $path=>$bytes)if(file_get_contents($target.'/'.$path)!==$bytes)throw new RuntimeException('Runtime data overwritten: '.$path);
 if(!preg_match('/\/bin\/cp[^\n]*\blogin_notice\.php\b/',file_get_contents($repo.'/tools/deploy_cpanel.sh')))throw new RuntimeException('Legacy shell deploy omits endpoint');
 if(!str_contains(file_get_contents($repo.'/.cpanel.yml'),'-l login_notice.php'))throw new RuntimeException('Missing cPanel endpoint syntax check');
 echo "PASS: actual hosting deploy copies notice endpoint, settings, popup and assets; accounts, announcement config, closed receipts, uploads and connection retained\n";
}finally{
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $f)$f->isDir()&&!$f->isLink()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
}
