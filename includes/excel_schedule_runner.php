<?php
require_once __DIR__.'/excel_schedule_store.php';
function xlsched_validate_file(string $path,string $format): void {
    $size=@filesize($path);if(!$size||$size>128*1024*1024)throw new RuntimeException('File xuất rỗng hoặc vượt 128 MB.');
    $stream=fopen($path,'rb');$head=fread($stream,8192);fclose($stream);
    if(preg_match('/(?:Fatal error:|Uncaught (?:Error|Exception)|Warning:.* on line|<title>Đăng nhập)/u',$head))throw new RuntimeException('Chức năng xuất trả về lỗi thay vì bảng dữ liệu.');
    if($format==='xlsx'){$zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('File xuất không phải Excel XLSX hợp lệ.');$ok=$zip->locateName('xl/workbook.xml')!==false&&$zip->locateName('[Content_Types].xml')!==false;$zip->close();if(!$ok)throw new RuntimeException('File xuất không có workbook Excel.');}
    elseif(in_array($format,['docx','zip'],true)){$zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('Gói Word/ZIP không hợp lệ.');try{if($format==='docx'&&($zip->locateName('word/document.xml')===false||$zip->locateName('[Content_Types].xml')===false))throw new RuntimeException('Tệp không có nội dung Word.');if($zip->numFiles<1||$zip->numFiles>6000)throw new RuntimeException('Gói hồ sơ rỗng hoặc có quá nhiều mục.');$total=0;for($i=0;$i<$zip->numFiles;$i++){$entry=$zip->statIndex($i);$name=$entry['name'];if(str_starts_with($name,'/')||preg_match('/^[A-Za-z]:/',$name)||str_contains($name,'\\')||preg_match('~(?:^|/)\.\.(?:/|$)~',$name))throw new RuntimeException('Đường dẫn trong gói không an toàn.');$total+=(int)$entry['size'];if($total>256*1024*1024)throw new RuntimeException('Gói giải nén quá lớn.');}}finally{$zip->close();}}
    elseif($format==='pdf'){if(!str_starts_with($head,'%PDF-'))throw new RuntimeException('Tệp không phải PDF.');}
    elseif($format==='xls'){if(preg_match('/<(?:script|form|nav|button)\b/i',$head))throw new RuntimeException('Chức năng xuất trả về giao diện thay vì file Excel.');if(stripos($head,'<table')===false&&stripos($head,'<Workbook')===false)throw new RuntimeException('File xuất không phải bảng Excel.');}
    elseif($format==='csv'){if(stripos($head,'<html')!==false||!str_contains($head,"\n"))throw new RuntimeException('File xuất không phải CSV.');}
    else throw new RuntimeException('Định dạng xuất chưa được hỗ trợ.');
}
function xlsched_export(array $job,array $report,array $params,string $work,int $timeout): string {
    if(!function_exists('proc_open'))throw new RuntimeException('Hosting cần bật proc_open cho PHP CLI.');
    $key=bin2hex(random_bytes(12));$payload=$work.'/'.$key.'.json';$output=$work.'/'.$key.'.export';$error=$work.'/'.$key.'.stderr';$meta=$work.'/'.$key.'.meta';
    $data=['owner_id'=>$job['owner_id'],'report_id'=>$report['id'],'params'=>$params,'site_host'=>$job['site_host']??'localhost','https'=>!empty($job['https']),'meta'=>$meta];
    if(file_put_contents($payload,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Không tạo được yêu cầu xuất.');chmod($payload,0600);
    $process=null;
    try{
        $process=proc_open([PHP_BINARY,'-d','memory_limit=256M','-d','max_execution_time='.$timeout,dirname(__DIR__).'/tools/excel_export_worker.php',$payload],[0=>['file','/dev/null','r'],1=>['file',$output,'w'],2=>['file',$error,'w']],$pipes,dirname(__DIR__));
        if(!is_resource($process))throw new RuntimeException('Không khởi chạy được chức năng xuất.');$started=microtime(true);$exit=null;
        do{$status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}if(microtime(true)-$started>$timeout){proc_terminate($process,9);throw new RuntimeException('Hết thời gian xuất trong khung giờ cho phép.');}usleep(100000);}while(true);
        proc_close($process);$process=null;$result=json_decode((string)@file_get_contents($meta),true);
        if($exit!==0||(!empty($result['fatal']))||isset($result['status'])&&($result['status']<200||$result['status']>=300))throw new RuntimeException('Xuất báo cáo thất bại; kiểm tra bộ lọc, quyền và dữ liệu nguồn.');
        xlsched_validate_file($output,$report['format']);return$output;
    }catch(Throwable $e){@unlink($output);throw$e;}
    finally{if(is_resource($process)){proc_terminate($process,9);proc_close($process);}@unlink($payload);@unlink($error);@unlink($meta);foreach(glob($work.'/sess_*')?:[]as$file)@unlink($file);}
}
function xlsched_claim(array $job,string $period,DateTimeImmutable $now): ?array {
    $pdo=cds_db();$q=$pdo->prepare('SELECT * FROM cds_excel_schedule_runs WHERE job_id=? AND period_key=?');$q->execute([$job['id'],$period]);$run=$q->fetch(PDO::FETCH_ASSOC);
    if($run){if($run['status']==='success')return null;if((int)$run['attempts']>=3&&(int)$run['job_revision']===(int)$job['revision'])return null;if($run['status']==='running'&&strtotime($run['started_at'].' UTC')>time()-600)return null;if($run['retry_after']&&strtotime($run['retry_after'].' UTC')>time())return null;
        $changed=(int)$run['job_revision']!==(int)$job['revision'];if($changed)$run['remote_file_id']=null;
        $q=$pdo->prepare("UPDATE cds_excel_schedule_runs SET status='running',remote_file_id=IF(job_revision=?,remote_file_id,NULL),attempts=IF(job_revision=?,attempts+1,1),job_revision=?,started_at=UTC_TIMESTAMP(),finished_at=NULL,retry_after=NULL WHERE id=?");$q->execute([$job['revision'],$job['revision'],$job['revision'],$run['id']]);$run['status']='running';return$run;
    }
    $q=$pdo->prepare("INSERT INTO cds_excel_schedule_runs(job_id,period_key,job_revision,status,started_at) VALUES(?,?,?,'running',UTC_TIMESTAMP())");$q->execute([$job['id'],$period,$job['revision']]);return['id'=>(int)$pdo->lastInsertId(),'job_id'=>$job['id'],'period_key'=>$period,'remote_file_id'=>null];
}
function xlsched_live(array $job): void {$current=xlsched_job($job['id']);if(empty(xlsched_state()['enabled'])||!$current||!$current['enabled']||(int)$current['revision']!==(int)$job['revision']||!xlsched_admin_record($job['owner_id']))throw new RuntimeException('Lịch hoặc quyền quản trị đã thay đổi; dừng lượt chạy.');}
function xlsched_tick(?DateTimeImmutable $now=null,int $maxJobs=10): array {
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh'));$pdo=cds_db();$lock='cds_excel_'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if(!(int)$q->fetchColumn())return['busy'=>true,'ran'=>0];
    $ran=0;$messages=[];$work=cds_instance_private_path('excel_exports').'/'.substr(hash('sha256',dirname(__DIR__)),0,16);$tickStart=time();
    try{
        $pdo->exec("UPDATE cds_excel_schedule_state SET last_tick_at=UTC_TIMESTAMP(),last_message='Cron đã kết nối' WHERE id=1");if(empty(xlsched_state()['enabled']))return['disabled'=>true,'ran'=>0];
        if(!is_dir($work)&&!mkdir($work,0700,true))throw new RuntimeException('Không tạo được kho tạm ngoài web root.');chmod($work,0700);
        foreach(glob($work.'/*')?:[]as$old){if(is_file($old)&&!is_link($old)&&filemtime($old)<time()-86400&&preg_match('/^(?:[a-f0-9]{24}\.(?:json|export|stderr|meta)|sess_[a-f0-9]+)$/D',basename($old)))@unlink($old);}
        foreach(xlsched_jobs()as$job){if($ran>=max(1,min(20,$maxJobs))||time()-$tickStart>240)break;if(!$job['enabled'])continue;
            $manual=!empty($job['manual_requested_at']);$window=xlsched_window($job,$now);if(!$manual&&!$window['due']){
                if($window['eligible']&&$now>=$window['end']&&strtotime($job['created_at'].' UTC')<=$window['start']->getTimestamp()){
                    $missed=$pdo->prepare("INSERT IGNORE INTO cds_excel_schedule_runs(job_id,period_key,job_revision,status,attempts,message,started_at,finished_at) VALUES(?,?,?,'missed',0,'Hết khung giờ; không chạy bù ban ngày.',UTC_TIMESTAMP(),UTC_TIMESTAMP())");$missed->execute([$job['id'],$window['period'],$job['revision']]);
                }
                continue;
            }
            $period=$manual?'manual:'.$job['manual_requested_at']:$window['period'];$run=xlsched_claim($job,$period,$now);if(!$run){if($manual){$clear=$pdo->prepare('UPDATE cds_excel_schedules SET manual_requested_at=NULL WHERE id=? AND manual_requested_at=?');$clear->execute([$job['id'],$job['manual_requested_at']]);}continue;}$ran++;$file=null;
            try{
                xlsched_live($job);$report=xlsched_report($job['report_id']);$params=json_decode($job['params_json'],true,512,JSON_THROW_ON_ERROR);$weeks=[];
                if(array_key_exists('week',$report['date'])){require_once __DIR__.'/school_week_calendar.php';$weeks=cds_school_week_calendar();}
                if(array_key_exists('timetable_week',$report['date'])){$raw=json_decode((string)@file_get_contents(dirname(__DIR__).'/chuyenmon/data/timetable_weeks.json'),true);foreach(is_array($raw)?$raw:[]as$w){$start=$w['start_date']??'';if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$start))$weeks[]=['key'=>$w['id']??'','label'=>(string)($w['label']??$w['name']??$w['id']??''),'start'=>$start,'end'=>date('Y-m-d',strtotime($start.' +6 days'))];}}
                $params=xlsched_resolve_dates($report,$params,$job['date_policy'],$now,$weeks);
                $remaining=$manual?300:$window['end']->getTimestamp()-time();if($remaining<40)throw new RuntimeException('Không đủ thời gian hoàn tất trước cuối khung giờ.');$deadline=time()+min(300,$remaining);
                $file=xlsched_export($job,$report,$params,$work,max(10,min(240,$remaining-30)));xlsched_live($job);
                $job['_resolved_params']=$params;foreach($weeks as$w){$wk=(string)($params['week']??$params['template_week_id']??'');if($wk!==''&&(string)($w['key']??$w['id']??'')===$wk){$job['_week_label']=(string)($w['label']??('Tuần '.$wk));break;}}
                $remote=xlsched_drive_sync($job,$run,$file,$report,$deadline);
                xlsched_mark_run((int)$run['id'],'success','Đã đồng bộ '.number_format(filesize($file)).' byte lên Drive.',$remote);
                $messages[]=$job['name'].': thành công';
            }catch(Throwable $e){xlsched_mark_run((int)$run['id'],'failed',$e->getMessage());$q=$pdo->prepare('UPDATE cds_excel_schedule_runs SET retry_after=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) WHERE id=?');$q->execute([$run['id']]);$messages[]=$job['name'].': '.$e->getMessage();}
            finally{if($file)@unlink($file);if($manual){$q=$pdo->prepare('UPDATE cds_excel_schedules SET manual_requested_at=NULL WHERE id=? AND manual_requested_at=?');$q->execute([$job['id'],$job['manual_requested_at']]);}}
        }
        $q=$pdo->prepare('UPDATE cds_excel_schedule_state SET last_message=? WHERE id=1');$q->execute([mb_substr('Đã xử lý '.$ran.' lịch. '.implode(' · ',$messages),0,500)]);
        $pdo->exec("DELETE FROM cds_excel_schedule_runs WHERE finished_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)");
        return compact('ran','messages');
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
