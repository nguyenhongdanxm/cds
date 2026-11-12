<?php
/** Synthetic calendar and Drive only: no real school files, account or network. */
require __DIR__.'/../includes/instance_config.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function fails(callable $f){try{$f();}catch(Throwable $e){return;}throw new RuntimeException('Invalid weekly archive accepted');}
function csdl_years_all(){return [['id'=>'year-fixture','label'=>'2026–2027','is_current'=>true,'start'=>'2026-09-07','end'=>'2027-05-30','weeks'=>[['number'=>10,'start'=>'2026-11-09']]]];}
require __DIR__.'/../includes/excel_schedule_catalog.php';
require __DIR__.'/../includes/excel_schedule_lesson_book.php';
require __DIR__.'/../includes/excel_schedule_naming.php';
$now=new DateTimeImmutable('2026-11-15 16:20:00',new DateTimeZone('UTC'));
$r=xlsched_report('lb_weekly');$p=xlsched_resolve_dates($r,['class'=>'6A','school_year'=>'year-fixture'],'week',$now,cds_school_week_calendar());
check($p['week']==='10','Sunday exports current school week, not previous week or ISO week');$context=xlsched_lb_context($p,$now);
check($context['_archive_year']==='Năm học 2026–2027'&&$context['_archive_week']==='Tuần 10 (2026-11-09 - 2026-11-15)','Year and actual school-week folders');
check($context['_archive_period']==='year-fixture|2026-11-09','Archive identity independent of manual run timestamp');
fails(fn()=>xlsched_lb_context(array_replace($p,['school_year'=>'old-year']),$now));
fails(fn()=>xlsched_lb_context(array_replace($p,['week'=>'9']),$now));
fails(fn()=>xlsched_lb_context($p,new DateTimeImmutable('2027-08-01')));
$job=array_replace(['id'=>str_repeat('a',32),'folder_id'=>'fixture_root_123','file_mode'=>'snapshot','name'=>'Sổ đầu bài','params_json'=>json_encode($p),'_resolved_params'=>$p],$context);
check(xlsched_filename($job,['period_key'=>'manual:test'],$r)==='Sổ đầu bài_Lớp 6A_Tuần 10.xlsx','Readable, stable class/week filename');
check(xlsched_window(['id'=>$job['id'],'frequency'=>'weekly','day'=>7,'start_time'=>'23:00','end_time'=>'23:30'],$now)['due'],'Sunday 23:20 VN is in window');
check(!xlsched_window(['id'=>$job['id'],'frequency'=>'weekly','day'=>7,'start_time'=>'23:00','end_time'=>'23:30'],new DateTimeImmutable('2026-11-16 23:20:00+07:00'))['due'],'Monday cannot trigger weekly archive');
function cds_drive_settings(){return ['enabled'=>true];}function cds_drive_token($s){return ['ok'=>true,'token'=>'fixture-only'];}
function xlsched_live($job){if(!empty($GLOBALS['cancel']))throw new RuntimeException('Cancelled');}
function cds_db(){return new class{function prepare($sql){return new class{function execute($values){$GLOBALS['reserved'][]=$values;}};}};}
$GLOBALS['files']=[];$GLOBALS['calls']=[];$GLOBALS['reserved']=[];$GLOBALS['fixtureIdCounter']=0;$GLOBALS['badIdentity']=false;
function xlsched_http($url,$method,$headers=[],$body='',$timeout=40){
    check(str_starts_with($url,'https://www.googleapis.com/'),'Only Drive API allowed');$GLOBALS['calls'][]=[$method,$url];$data=json_decode($body,true);$u=parse_url($url);parse_str($u['query']??'',$q);$path=$u['path'];$files=&$GLOBALS['files'];
    if($path==='/drive/v3/files/generateIds')return ['status'=>200,'body'=>json_encode(['ids'=>['fixture_file_'.(++$GLOBALS['fixtureIdCounter'])]]),'headers'=>[]];
    if($path==='/drive/v3/files'&&$method==='GET'){
        $found=[];foreach($files as$id=>$f){$query=$q['q']??'';$matches=true;foreach(['cdsArchive','cdsJob','cdsInstance','cdsPeriod']as$key)if(preg_match("/key='".$key."' and value='([^']+)'/",$query,$m)&&($f['appProperties'][$key]??'')!==$m[1])$matches=false;if(!str_contains($query,"'".($f['parents'][0]??'')."' in parents"))$matches=false;if(str_contains($query,"mimeType = 'application/vnd.google-apps.folder'")&&($f['mimeType']??'')!=='application/vnd.google-apps.folder')$matches=false;if($matches)$found[]=['id'=>$id];}
        if(!empty($GLOBALS['duplicate'])&&$found)$found[]=$found[0];return ['status'=>200,'body'=>json_encode(['files'=>$found]),'headers'=>[]];
    }
    if($path==='/drive/v3/files'&&$method==='POST'){$id='fixture_folder_'.(++$GLOBALS['fixtureIdCounter']);$files[$id]=$data;return ['status'=>200,'body'=>json_encode(['id'=>$id]),'headers'=>[]];}
    if(str_starts_with($path,'/drive/v3/files/')&&$method==='GET'){$id=basename($path);if($id==='fixture_root_123')$data=['mimeType'=>'application/vnd.google-apps.folder','capabilities'=>['canAddChildren'=>true]];elseif(isset($files[$id])){$data=$files[$id];$data['capabilities']=['canAddChildren'=>true];if($GLOBALS['badIdentity'])$data['appProperties']['cdsInstance']='other';}else return ['status'=>404,'body'=>'{}','headers'=>[]];return ['status'=>200,'body'=>json_encode($data),'headers'=>[]];}
    if(str_starts_with($path,'/upload/')&&in_array($method,['POST','PATCH'],true)){$id=$method==='POST'?$data['id']:basename($path);$GLOBALS['pending']=[$id,$data,$method];return ['status'=>200,'body'=>'{}','headers'=>['location'=>'https://www.googleapis.com/upload/fixture?upload_id=fixture']];}
    if($method==='PUT'){[$id,$meta,$operation]=$GLOBALS['pending'];$files[$id]=array_replace($files[$id]??[],$meta);return ['status'=>200,'body'=>json_encode(['id'=>$id]),'headers'=>[]];}
    throw new RuntimeException('Unexpected Drive operation '.$method.' '.$url);
}
require __DIR__.'/../includes/excel_schedule_drive.php';
$tmp=tempnam(sys_get_temp_dir(),'weekly-sdb-');file_put_contents($tmp,'fixture XLSX bytes');
try{
    $run=['id'=>1,'period_key'=>'2026-11-15'];$first=xlsched_drive_sync($job,$run,$tmp,$r,time()+60);check(count($GLOBALS['files'])===3,'Creates year folder, week folder and one class workbook');
    $again=xlsched_drive_sync($job,['id'=>2,'period_key'=>'manual:2026-11-15'],$tmp,$r,time()+60);check($again===$first&&count($GLOBALS['files'])===3,'Manual retry updates the same class/week without duplicate folders or files');
    check($GLOBALS['files'][$first]['name']==='Sổ đầu bài_Lớp 6A_Tuần 10.xlsx','Upload uses stable workbook name');
    $GLOBALS['badIdentity']=true;fails(fn()=>xlsched_drive_sync($job,['id'=>3,'period_key'=>'retry','remote_file_id'=>$first],$tmp,$r,time()+60));$GLOBALS['badIdentity']=false;
    $GLOBALS['duplicate']=true;fails(fn()=>xlsched_drive_sync($job,$run,$tmp,$r,time()+60));$GLOBALS['duplicate']=false;
    $next=array_replace($job,['_archive_week'=>'Tuần 11 (2026-11-16 - 2026-11-22)','_archive_period'=>'year-fixture|2026-11-16','_week_label'=>'Tuần 11']);$next['_resolved_params']['week']='11';
    $second=xlsched_drive_sync($next,['id'=>4,'period_key'=>'2026-11-22'],$tmp,$r,time()+60);check($second!==$first&&count($GLOBALS['files'])===5,'New week keeps old workbook and creates only one new week folder');
    $GLOBALS['cancel']=true;fails(fn()=>xlsched_drive_sync($job,$run,$tmp,$r,time()+60));
    echo "PASS weekly lesson books: VN Sunday/current school week, year rollover guard, stable names, managed folders, retry/manual deduplication, retained previous weeks, ownership and cancellation; no network\n";
}finally{unlink($tmp);}
