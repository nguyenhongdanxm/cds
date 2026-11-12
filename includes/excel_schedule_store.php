<?php
require_once __DIR__.'/database.php';
require_once __DIR__.'/excel_schedule_catalog.php';
function xlsched_ready(): bool {try{cds_db()->query('SELECT id FROM cds_excel_schedule_state WHERE id=1');return true;}catch(Throwable $e){return false;}}
function xlsched_state(): array {return cds_db()->query('SELECT * FROM cds_excel_schedule_state WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:['enabled'=>0];}
function xlsched_jobs(): array {return cds_db()->query('SELECT j.*,(SELECT r.status FROM cds_excel_schedule_runs r WHERE r.job_id=j.id ORDER BY r.id DESC LIMIT 1) AS recent_status,(SELECT r.started_at FROM cds_excel_schedule_runs r WHERE r.job_id=j.id ORDER BY r.id DESC LIMIT 1) AS recent_started FROM cds_excel_schedules j WHERE j.deleted_at IS NULL ORDER BY j.created_at ASC')->fetchAll(PDO::FETCH_ASSOC);}
function xlsched_job(string $id): ?array {$q=cds_db()->prepare('SELECT * FROM cds_excel_schedules WHERE id=? AND deleted_at IS NULL');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);return$r?:null;}
function xlsched_admin_record(string $id): ?array {
    $file=dirname(__DIR__).'/data/users.json';$rows=json_decode((string)@file_get_contents($file),true);
    if(!is_array($rows))return null;foreach($rows as$row)if(is_array($row)&&($row['id']??'')===$id&&!empty($row['active'])&&($row['role']??'')==='admin')return$row;return null;
}
function xlsched_validate(array $input,string $actor): array {
    $report=xlsched_report(trim((string)($input['report_id']??'')));$name=trim((string)($input['name']??''));if($name===''||mb_strlen($name)>150)throw new InvalidArgumentException('Tên lịch cần có từ 1–150 ký tự.');
    $params=$input['params']??[];if(!is_array($params))throw new InvalidArgumentException('Bộ lọc không hợp lệ.');$params=xlsched_params($report,$params);
    $folder=trim((string)($input['folder_id']??''));if(str_starts_with($folder,'https://drive.google.com/')){preg_match('~/folders/([A-Za-z0-9_-]+)~',$folder,$m);$folder=$m[1]??'';}
    if(!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$folder))throw new InvalidArgumentException('Nhập ID hoặc URL thư mục Google Drive hợp lệ.');
    $frequency=(string)($input['frequency']??'daily');if(!in_array($frequency,['daily','weekly','monthly'],true))throw new InvalidArgumentException('Chu kỳ không hợp lệ.');$day=(int)($input['day']??1);
    if($day<1||$day>($frequency==='weekly'?7:31))throw new InvalidArgumentException('Ngày lặp không hợp lệ.');
    $start=(string)($input['start_time']??'00:00');$end=(string)($input['end_time']??'05:00');
    xlsched_window(['id'=>'validate','start_time'=>$start,'end_time'=>$end,'frequency'=>$frequency,'day'=>$day],new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')));
    $policy=(string)($input['date_policy']??'fixed');if(!in_array($policy,['fixed','yesterday','today','month','previous_month','week','previous_week'],true))throw new InvalidArgumentException('Khoảng dữ liệu không hợp lệ.');
    if(isset($report['policies'])&&!in_array($policy,$report['policies'],true))throw new InvalidArgumentException('Chu kỳ dữ liệu không phù hợp với báo cáo.');
    if(!$report['date']&&$policy!=='fixed')throw new InvalidArgumentException('Báo cáo này dùng bộ lọc đã chọn, không có bộ lọc ngày động.');
    foreach($report['required']??[]as$key){if($policy!=='fixed'&&in_array($key,$report['date'],true))continue;if(!isset($params[$key])||$params[$key]===''||$params[$key]===[])throw new InvalidArgumentException('Cần chọn '.xlsched_field_label($key).'.');}
    foreach(['from','to','date','stats_from','stats_to','health_from','health_to']as$key){$v=$params[$key]??'';if($v!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$v,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])))throw new InvalidArgumentException('Ngày lọc không hợp lệ.');}
    $mode=(string)($input['file_mode']??'replace');if(!in_array($mode,['replace','snapshot'],true))throw new InvalidArgumentException('Kiểu lưu không hợp lệ.');
    if($report['id']==='lb_weekly'&&($frequency!=='weekly'||$day!==7||$policy!=='week'||$mode!=='snapshot'))throw new InvalidArgumentException('Sổ đầu bài tự động dùng Chủ nhật hằng tuần, tuần hiện tại và bản lưu theo tuần.');
    $host=(string)($_SERVER['HTTP_HOST']??'localhost');if(!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]+)?$/D',$host))throw new InvalidArgumentException('Tên miền không hợp lệ.');
    return['site_host'=>$host,'https'=>(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?1:0,'name'=>$name,'report_id'=>$report['id'],'params_json'=>json_encode($params,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'folder_id'=>$folder,'frequency'=>$frequency,'day'=>$day,'start_time'=>$start,'end_time'=>$end,'date_policy'=>$policy,'file_mode'=>$mode,'owner_id'=>$actor,'enabled'=>!empty($input['enabled'])?1:0];
}
function xlsched_save(array $data,string $id='',int $revision=0): string {
    $pdo=cds_db();$columns=array_keys($data);
    if($id===''){if((int)$pdo->query('SELECT COUNT(*) FROM cds_excel_schedules WHERE deleted_at IS NULL')->fetchColumn()>=500)throw new RuntimeException('Tối đa 500 lịch đang quản lý.');$id=bin2hex(random_bytes(16));$q=$pdo->prepare('INSERT INTO cds_excel_schedules(id,'.'`'.implode('`,`',$columns).'`'.',created_at,updated_at) VALUES('.implode(',',array_fill(0,count($columns)+1,'?')).',UTC_TIMESTAMP(),UTC_TIMESTAMP())');$q->execute(array_merge([$id],array_values($data)));}
    else{$old=xlsched_job($id);if(!$old)throw new RuntimeException('Không tìm thấy lịch.');$reset=$old['folder_id']!==$data['folder_id']||$old['file_mode']!==$data['file_mode']||$old['report_id']!==$data['report_id'];$q=$pdo->prepare('UPDATE cds_excel_schedules SET '.implode(',',array_map(fn($key)=>'`'.$key.'`=?',$columns)).',revision=revision+1,updated_at=UTC_TIMESTAMP()'.($reset?',remote_file_id=NULL':'').' WHERE id=? AND revision=? AND deleted_at IS NULL');$q->execute(array_merge(array_values($data),[$id,$revision]));if($q->rowCount()!==1)throw new RuntimeException('Lịch đã được thay đổi; hãy tải lại trang.');}
    return$id;
}
function xlsched_history(int $limit=80): array {$limit=max(1,min(200,$limit));return cds_db()->query('SELECT r.*,j.name FROM cds_excel_schedule_runs r LEFT JOIN cds_excel_schedules j ON j.id=r.job_id ORDER BY r.id DESC LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);}
function xlsched_mark_run(int $id,string $status,string $message='',?string $fileId=null): void {
    $q=cds_db()->prepare('UPDATE cds_excel_schedule_runs SET status=?,message=?,remote_file_id=COALESCE(?,remote_file_id),finished_at=UTC_TIMESTAMP() WHERE id=?');$q->execute([$status,mb_substr($message,0,1000),$fileId,$id]);
}
