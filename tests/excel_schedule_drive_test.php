<?php
require __DIR__.'/../includes/instance_config.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function fails(callable$fn){try{$fn();}catch(Throwable$e){return;}throw new RuntimeException('Unsafe upload accepted');}
function cds_drive_settings(){return['enabled'=>true];}function cds_drive_token($settings){return['ok'=>true,'token'=>'fixture-token-never-network'];}
function xlsched_live($job){if(!empty($GLOBALS['cancel']))throw new RuntimeException('Cancelled');}
$GLOBALS['calls']=[];$GLOBALS['offset']=0;$GLOBALS['badLocation']=false;$GLOBALS['badIdentity']=false;
function xlsched_http($url,$method,$headers=[],$body='',$timeout=40){
 check(str_starts_with($url,'https://www.googleapis.com/'),'Only Google endpoints');$GLOBALS['calls'][]=['url'=>$url,'method'=>$method,'bytes'=>strlen($body)];
 if(str_contains($url,'fixture_folder'))return['status'=>200,'body'=>json_encode(['mimeType'=>'application/vnd.google-apps.folder','capabilities'=>['canAddChildren'=>true]]),'headers'=>[]];
 if($method==='GET')return['status'=>200,'body'=>json_encode(['id'=>'fixture_remote_id','parents'=>['fixture_folder_123'],'appProperties'=>['cdsJob'=>$GLOBALS['badIdentity']?'other':str_repeat('a',32),'cdsInstance'=>substr(hash('sha256',cds_instance_config_path()),0,24)]]),'headers'=>[]];
 if($method==='PATCH'){check(!isset(json_decode($body,true)['parents']),'Never move unrelated Drive files');return['status'=>200,'body'=>'{}','headers'=>['location'=>$GLOBALS['badLocation']?'https://evil.example/upload':'https://www.googleapis.com/upload/drive/v3/files?upload_id=fixture']];}
 if($method==='PUT'){$GLOBALS['offset']+=strlen($body);$last=$GLOBALS['offset']>=9*1024*1024;return['status'=>$last?200:308,'body'=>$last?' {"id":"fixture_remote_id"}':'','headers'=>$last?[]:['range'=>'bytes=0-'.($GLOBALS['offset']-1)]];}
 throw new RuntimeException('Unexpected API request');
}
require __DIR__.'/../includes/excel_schedule_drive.php';
$file=tempnam(sys_get_temp_dir(),'drive-chunks-');$stream=fopen($file,'wb');for($i=0;$i<9;$i++)fwrite($stream,str_repeat('x',1024*1024));fclose($stream);
$job=['id'=>str_repeat('a',32),'folder_id'=>'fixture_folder_123','file_mode'=>'replace','remote_file_id'=>'fixture_remote_id','name'=>'Báo cáo'];$run=['id'=>1,'period_key'=>'2026-11-10'];
try{
 check(xlsched_drive_sync($job,$run,$file,['format'=>'xlsx'],time()+60)==='fixture_remote_id','Update own managed file');
 $chunks=array_values(array_filter($GLOBALS['calls'],fn($r)=>$r['method']==='PUT'));check(array_column($chunks,'bytes')===[4194304,4194304,1048576],'4 MiB bounded chunks');
 check(count(array_filter($GLOBALS['calls'],fn($r)=>$r['method']==='POST'))===0,'Existing file is updated, not duplicated');
 $GLOBALS['badIdentity']=true;fails(fn()=>xlsched_drive_sync($job,$run,$file,['format'=>'xlsx'],time()+60));$GLOBALS['badIdentity']=false;
 $GLOBALS['badLocation']=true;fails(fn()=>xlsched_drive_sync($job,$run,$file,['format'=>'xlsx'],time()+60));$GLOBALS['badLocation']=false;
 $GLOBALS['cancel']=true;fails(fn()=>xlsched_drive_sync($job,$run,$file,['format'=>'xlsx'],time()+60));
 echo "PASS Drive mock: existing file ownership, no duplicate/move, chunking, untrusted upload URL and cancellation; no network used\n";
}finally{unlink($file);}
