<?php
/** Run the actual deployment script against a temporary hosting directory only. */
$repo=dirname(__DIR__);$tmp=sys_get_temp_dir().'/staff-deploy-'.bin2hex(random_bytes(8));$target=$tmp.'/public';
mkdir($target.'/data/staff_evaluation',0750,true);mkdir($target.'/uploads',0750,true);mkdir($target.'/includes',0750,true);
$sentinels=['data/staff_evaluation/month-2026-11.json'=>'{"records":{"synthetic":"keep"}}','data/users.json'=>'private fixture users','uploads/evidence.txt'=>'existing evidence','includes/database.conf'=>'private fixture connection'];
foreach($sentinels as$path=>$bytes)file_put_contents($target.'/'.$path,$bytes);
try{
 $env=getenv();$env['CDS_DEPLOY_PATH']=$target;$env['CDS_INSTANCE_CONFIG']=$tmp.'/no-instance.json';$env['CDS_DEPLOY_CONFIG']=$tmp.'/no-deploy.json';
 $process=proc_open([PHP_BINARY,$repo.'/tools/deploy_cds.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$repo,$env);
 if(!is_resource($process))throw new RuntimeException('Cannot start isolated deployment');
 $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit!==0||!str_contains($stdout,'CDS_DEPLOY_OK'))throw new RuntimeException('Fixture deployment failed: '.$stderr);
 foreach(['hoclieu_game_muc.php','assets/cds-muc-motion.js','assets/cds-muc-audio.js','assets/muc/actors-v2.webp','assets/muc/actors-v2.json','assets/muc/dog-bark.mp3','assets/cds-muc-engine.js','assets/cds-muc-scene.js','assets/cds-muc.js','assets/cds-muc.css','assets/muc/school.webp','assets/muc/street.webp','assets/muc/sprites.webp','chuyenmon/index.php','chuyenmon/includes/cm_overview.php','assets/cm-overview.css','ioffice_settings.php','bin/ioffice-vault.php','includes/ioffice_vault.php','thidua_danhgia.php','includes/staff_evaluation.php','includes/staff_identity.php','includes/staff_evaluation_month_print.php','assets/staff-evaluation.css','assets/staff-evaluation-print.css','chuyenmon/danhgia.php']as$path){
  if(!is_file($target.'/'.$path)||hash_file('sha256',$repo.'/'.$path)!==hash_file('sha256',$target.'/'.$path))throw new RuntimeException('Missing/stale deployed file: '.$path);
 }
 foreach($sentinels as$path=>$bytes)if(file_get_contents($target.'/'.$path)!==$bytes)throw new RuntimeException('Existing hosting data changed: '.$path);
 echo "PASS: actual deploy copies Muc game page/JS/CSS/artwork, iOffice endpoint/CLI/helper and evaluation endpoint, service, CSS and redirect; existing records, users, uploads and connection unchanged\n";
}finally{
 $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($items as$item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($tmp);
}
