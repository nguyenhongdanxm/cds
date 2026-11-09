<?php
require __DIR__.'/../includes/excel_schedule_catalog.php';
require __DIR__.'/../includes/excel_schedule_store.php';
require __DIR__.'/../includes/excel_schedule_runner.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function fails(callable $fn){try{$fn();}catch(Throwable $e){return;}throw new RuntimeException('Unsafe/invalid input accepted');}
$_SERVER['HTTP_HOST']='school.example';$_SERVER['HTTPS']='on';
$catalog=xlsched_catalog();check(count($catalog)>40,'All existing export families and templates registered');
foreach($catalog as$r){check(is_file(dirname(__DIR__).'/'.$r['path']),'Registered source exists: '.$r['id']);check(!str_contains($r['path'],'..'),'Local reviewed path');check(!array_intersect(['delete','reset','save_drive','remove','api','csrf'],$r['fields']),'No mutation/delegation fields');}
$r=xlsched_report('lb_stats');$params=xlsched_params($r,['stats_teacher'=>'Tên <script>','stats_from'=>'2026-11-01','save_drive'=>'1','action'=>'delete']);check(!isset($params['action'],$params['save_drive']),'Unknown actions cannot be persisted');
fails(fn()=>xlsched_report('../../users.php'));fails(fn()=>xlsched_params($r,['stats_teacher'=>['bad']]));fails(fn()=>xlsched_params($r,['stats_teacher'=>str_repeat('x',1001)]));
check(xlsched_match('noitru.php','GET',['tab'=>'meals','export'=>'month_breakfast'])['id']==='meal_month_breakfast','Legacy source tab alias');
check(xlsched_match('chuyenmon/sodaubai_stats_export.php','GET',['stats_from'=>'2026-01-01'])['id']==='lb_stats','Default xlsx format accepted');
check(xlsched_match('noitru.php','POST',['tab'=>'meal_summary','action'=>'delete'])===null,'Mutation requests never schedule');
$job=['id'=>str_repeat('a',32),'start_time'=>'00:00','end_time'=>'05:00','frequency'=>'daily','day'=>1];
$at=fn($v)=>new DateTimeImmutable($v,new DateTimeZone('Asia/Ho_Chi_Minh'));
check(!xlsched_window($job,$at('2026-11-10 05:00:00'))['due'],'End boundary is exclusive');check(!xlsched_window($job,$at('2026-11-10 23:59:00'))['due'],'Never daytime catch-up');
$w=xlsched_window($job,$at('2026-11-10 04:59:00'));check($w['due']&&$w['period']==='2026-11-10','Daily window');check($w['planned']<=$at('2026-11-10 04:45:00'),'Room left for cron and bounded export');
check(xlsched_window($job,new DateTimeImmutable('2026-11-09 21:59:00',new DateTimeZone('UTC')))['period']==='2026-11-10','Independent of server timezone');
$cross=array_replace($job,['start_time'=>'23:00','end_time'=>'02:00']);check(xlsched_window($cross,$at('2026-11-11 01:59:00'))['period']==='2026-11-10','Cross-midnight belongs to starting day');
$monthly=array_replace($job,['frequency'=>'monthly','day'=>31]);check(xlsched_window($monthly,$at('2027-02-28 04:59:00'))['due'],'Short month clamps day');
$weekly=array_replace($job,['frequency'=>'weekly','day'=>1]);check(!xlsched_window($weekly,$at('2026-11-10 04:59:00'))['due'],'Weekly only correct weekday');
fails(fn()=>xlsched_window(array_replace($job,['end_time'=>'00:10']),$at('2026-11-10')));fails(fn()=>xlsched_hhmm('24:00'));
$day=$at('2026-03-01 00:00:00');$resolved=xlsched_resolve_dates($r,[],'yesterday',$day);check($resolved['stats_from']==='2026-02-28'&&$resolved['stats_to']==='2026-02-28','Month/year boundaries');
$resolved=xlsched_resolve_dates($r,[],'previous_month',$at('2028-03-01'));check($resolved['stats_from']==='2028-02-01'&&$resolved['stats_to']==='2028-02-29','Leap month');
$resolved=xlsched_resolve_dates(xlsched_report('attendance'),[],'previous_week',$at('2026-11-10'));check($resolved['period_type']==='week'&&$resolved['period_value']==='2026-W45','Actual attendance period contract');
$resolved=xlsched_resolve_dates(xlsched_report('health'),[],'yesterday',$at('2026-11-10'));check($resolved['range']==='day'&&$resolved['date']==='2026-11-09','Actual health date contract');
$resolved=xlsched_resolve_dates(xlsched_report('exit'),[],'yesterday',$at('2026-11-10'));check($resolved['period']==='custom','Exit date filters are honored');
$week=xlsched_resolve_dates(xlsched_report('lb_class'),['class'=>'6A'],'today',$at('2026-11-10'),[['key'=>'w10','start'=>'2026-11-09','end'=>'2026-11-15']]);check($week['week']==='w10'&&$week['class']==='6A','Week resolved by date, without changing class');
fails(fn()=>xlsched_resolve_dates(xlsched_report('lb_class'),[],'today',$at('2026-11-10')));
$input=['report_id'=>'lb_stats','name'=>'Ký sổ','params'=>[],'folder_id'=>'https://drive.google.com/drive/folders/fixture_folder_123','frequency'=>'daily','start_time'=>'00:00','end_time'=>'05:00','date_policy'=>'yesterday','file_mode'=>'replace','enabled'=>'1'];
$valid=xlsched_validate($input,'admin-id');check($valid['folder_id']==='fixture_folder_123'&&$valid['owner_id']==='admin-id','Folder URL, actor and safe data');
fails(fn()=>xlsched_validate(array_replace($input,['folder_id'=>'https://evil.example/folders/123']),'x'));fails(fn()=>xlsched_validate(array_replace($input,['date_policy'=>'sql']),'x'));fails(fn()=>xlsched_validate(array_replace($input,['report_id'=>'attendance','date_policy'=>'today']),'x'));
$tmp=tempnam(sys_get_temp_dir(),'excel-validation-');try{file_put_contents($tmp,'<html><form>login</form><table><tr><td>Wrong page</td></tr></table></html>');fails(fn()=>xlsched_validate_file($tmp,'xls'));file_put_contents($tmp,"\xEF\xBB\xBFcol1,col2\na,b\n");xlsched_validate_file($tmp,'csv');file_put_contents($tmp,'not an xlsx');fails(fn()=>xlsched_validate_file($tmp,'xlsx'));}finally{unlink($tmp);}
echo "PASS schedules: registry, mutation rejection, VN/UTC windows, midnight, weekly/monthly/leap dates, real report date contracts, filters and file validation\n";
