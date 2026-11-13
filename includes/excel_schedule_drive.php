<?php
require_once __DIR__.'/excel_schedule_naming.php';
/** Drive binary uploads. No conversion/recalculation of the existing Excel export. */
if(!function_exists('xlsched_http')){
function xlsched_http(string $url,string $method,array $headers=[],string $body='',int $timeout=40): array {
    if(!function_exists('curl_init'))throw new RuntimeException('Hosting cần bật PHP cURL.');
    $u=parse_url($url);if(($u['scheme']??'')!=='https'||($u['host']??'')!=='www.googleapis.com'||isset($u['user'])||isset($u['port']))throw new RuntimeException('Địa chỉ Drive không hợp lệ.');
    $responseHeaders=[];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>max(1,$timeout),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$responseHeaders){$pair=explode(':',$line,2);if(count($pair)===2)$responseHeaders[strtolower(trim($pair[0]))]=trim($pair[1]);return strlen($line);}]);
    if($body!==''||in_array($method,['POST','PATCH','PUT'],true))curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($response===false)throw new RuntimeException('Mất kết nối Drive; sẽ thử lại trong khung giờ.');
    return['status'=>$status,'body'=>(string)$response,'headers'=>$responseHeaders];
}}
function xlsched_drive_json(string $url,string $token): array {$r=xlsched_http($url,'GET',['Authorization: Bearer '.$token]);if($r['status']!==200)throw new RuntimeException('Drive trả HTTP '.$r['status'].'. Kiểm tra kết nối và quyền thư mục.');$data=json_decode($r['body'],true);if(!is_array($data))throw new RuntimeException('Phản hồi Drive không hợp lệ.');return$data;}
function xlsched_drive_folder(string $folder,string $token): void {
    $info=xlsched_drive_json('https://www.googleapis.com/drive/v3/files/'.rawurlencode($folder).'?supportsAllDrives=true&fields=id,mimeType,trashed,capabilities(canAddChildren)',$token);
    if(($info['mimeType']??'')!=='application/vnd.google-apps.folder'||!empty($info['trashed'])||empty($info['capabilities']['canAddChildren']))throw new RuntimeException('Thư mục Drive không tồn tại hoặc tài khoản chưa có quyền thêm tệp.');
}
/** Resolve only CDS-owned archive folders under the configured root. The cron DB lock serializes creation. */
function xlsched_drive_archive_folder(string $parent,string $name,string $token,array $job,int $deadline): string {
    xlsched_live($job);if(time()>=$deadline)throw new RuntimeException('Đã hết khung giờ đồng bộ.');
    $identity=substr(hash('sha256',cds_instance_config_path()),0,24);$key=hash('sha256',$parent.'|'.$name);
    $escape=static fn($s)=>str_replace(["\\","'"],["\\\\","\\'"],$s);
    $q="'".$escape($parent)."' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder' and appProperties has { key='cdsInstance' and value='".$identity."' } and appProperties has { key='cdsArchive' and value='".$key."' }";
    $found=xlsched_drive_json('https://www.googleapis.com/drive/v3/files?'.http_build_query(['q'=>$q,'fields'=>'files(id),nextPageToken','pageSize'=>2,'supportsAllDrives'=>'true','includeItemsFromAllDrives'=>'true']),$token);
    if(count($found['files']??[])>1||!empty($found['nextPageToken']))throw new RuntimeException('Có nhiều thư mục lưu sổ trùng định danh; cần kiểm tra Drive.');
    $id=(string)($found['files'][0]['id']??'');
    if($id===''){
        $response=xlsched_http('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id','POST',['Authorization: Bearer '.$token,'Content-Type: application/json; charset=UTF-8'],json_encode(['name'=>$name,'mimeType'=>'application/vnd.google-apps.folder','parents'=>[$parent],'appProperties'=>['cdsInstance'=>$identity,'cdsArchive'=>$key]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),max(1,min(40,$deadline-time())));
        $id=(string)(json_decode($response['body'],true)['id']??'');if(!in_array($response['status'],[200,201],true)||!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$id))throw new RuntimeException('Không tạo được thư mục lưu sổ trên Drive.');
    }
    xlsched_drive_folder($id,$token);return$id;
}
function xlsched_drive_sync(array $job,array $run,string $path,array $report,int $deadline): string {
    $settings=cds_drive_settings();if(empty($settings['enabled']))throw new RuntimeException('Google Drive đang tắt.');$access=cds_drive_token($settings);if(empty($access['ok']))throw new RuntimeException('Chưa kết nối được tài khoản Google Drive.');$token=$access['token'];
    xlsched_drive_folder($job['folder_id'],$token);xlsched_live($job);
    $archive=($report['id']??'')==='lb_weekly';$period=$archive?(string)($job['_archive_period']??''):(string)$run['period_key'];
    if($archive){
        if($period===''||empty($job['_archive_year'])||empty($job['_archive_week']))throw new RuntimeException('Chưa xác định năm học/tuần để lưu sổ.');
        $job['folder_id']=xlsched_drive_archive_folder($job['folder_id'],$job['_archive_year'],$token,$job,$deadline);
        $job['folder_id']=xlsched_drive_archive_folder($job['folder_id'],$job['_archive_week'],$token,$job,$deadline);
    }
    $fileId=$job['file_mode']==='replace'?($job['remote_file_id']??''):($run['remote_file_id']??'');
    if(!$fileId&&$archive){
        $identity=substr(hash('sha256',cds_instance_config_path()),0,24);$escape=static fn($s)=>str_replace(["\\","'"],["\\\\","\\'"],$s);
        $query="'".$escape($job['folder_id'])."' in parents and trashed = false and appProperties has { key='cdsJob' and value='".$escape($job['id'])."' } and appProperties has { key='cdsInstance' and value='".$identity."' } and appProperties has { key='cdsPeriod' and value='".$escape($period)."' }";
        $found=xlsched_drive_json('https://www.googleapis.com/drive/v3/files?'.http_build_query(['q'=>$query,'fields'=>'files(id),nextPageToken','pageSize'=>2,'supportsAllDrives'=>'true','includeItemsFromAllDrives'=>'true']),$token);
        if(count($found['files']??[])>1||!empty($found['nextPageToken']))throw new RuntimeException('Có nhiều tệp sổ trùng lớp/tuần; cần kiểm tra Drive.');
        $fileId=(string)($found['files'][0]['id']??'');
        if($fileId!==''){$q=cds_db()->prepare('UPDATE cds_excel_schedule_runs SET remote_file_id=? WHERE id=?');$q->execute([$fileId,$run['id']]);}
    }
    if(!$fileId){$ids=xlsched_drive_json('https://www.googleapis.com/drive/v3/files/generateIds?count=1&space=drive',$token);$fileId=(string)($ids['ids'][0]??'');if(!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$fileId))throw new RuntimeException('Drive không cấp được mã tệp.');
        if($job['file_mode']==='replace'){$q=cds_db()->prepare('UPDATE cds_excel_schedules SET remote_file_id=? WHERE id=? AND revision=? AND deleted_at IS NULL');$q->execute([$fileId,$job['id'],$job['revision']]);if($q->rowCount()!==1)throw new RuntimeException('Lịch đã thay đổi trong lúc đồng bộ.');}
        $q=cds_db()->prepare('UPDATE cds_excel_schedule_runs SET remote_file_id=? WHERE id=?');$q->execute([$fileId,$run['id']]);
    }
    $identity=substr(hash('sha256',cds_instance_config_path()),0,24);
    $info=xlsched_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true&fields=id,parents,appProperties,trashed','GET',['Authorization: Bearer '.$token]);$exists=$info['status']===200;
    if(!$exists&&$info['status']!==404)throw new RuntimeException('Không kiểm tra được tệp Drive (HTTP '.$info['status'].').');
    if($exists){$data=json_decode($info['body'],true);if(!is_array($data)||!empty($data['trashed'])||($data['appProperties']['cdsJob']??'')!==$job['id']||($data['appProperties']['cdsInstance']??'')!==$identity||!in_array($job['folder_id'],$data['parents']??[],true)||($job['file_mode']==='snapshot'&&($data['appProperties']['cdsPeriod']??'')!==$period))throw new RuntimeException('Tệp Drive không còn thuộc lịch/thư mục này; không ghi đè.');}
    $name=xlsched_filename($job,$run,$report);
    $mime=['xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','xls'=>'application/vnd.ms-excel','csv'=>'text/csv','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','pdf'=>'application/pdf','zip'=>'application/zip'][$report['format']];
    $meta=['name'=>$name,'appProperties'=>['cdsJob'=>$job['id'],'cdsInstance'=>$identity,'cdsPeriod'=>$period]];
    if(!$exists){$meta['id']=$fileId;$meta['parents']=[$job['folder_id']];}
    $url='https://www.googleapis.com/upload/drive/v3/files'.($exists?'/'.rawurlencode($fileId):'').'?uploadType=resumable&supportsAllDrives=true&fields=id';
    $start=xlsched_http($url,$exists?'PATCH':'POST',['Authorization: Bearer '.$token,'Content-Type: application/json; charset=UTF-8','X-Upload-Content-Type: '.$mime,'X-Upload-Content-Length: '.filesize($path)],json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),max(1,min(40,$deadline-time())));
    $session=(string)($start['headers']['location']??'');$sessionUrl=parse_url($session);if(!in_array($start['status'],[200,201],true)||($sessionUrl['scheme']??'')!=='https'||($sessionUrl['host']??'')!=='www.googleapis.com'||isset($sessionUrl['user'])||isset($sessionUrl['port']))throw new RuntimeException('Không khởi tạo được phiên tải Drive.');
    $stream=fopen($path,'rb');$size=filesize($path);$offset=0;$last=null;
    try{while($offset<$size){xlsched_live($job);if(time()>=$deadline)throw new RuntimeException('Đã hết khung giờ đồng bộ.');$chunk=fread($stream,4*1024*1024);if($chunk===false||$chunk==='')throw new RuntimeException('Không đọc được file xuất.');$length=strlen($chunk);
        $last=xlsched_http($session,'PUT',['Authorization: Bearer '.$token,'Content-Type: '.$mime,'Content-Length: '.$length,'Content-Range: bytes '.$offset.'-'.($offset+$length-1).'/'.$size],$chunk,max(1,min(40,$deadline-time())));
        if(!in_array($last['status'],[200,201,308],true))throw new RuntimeException('Tải lên Drive chưa hoàn tất (HTTP '.$last['status'].').');
        if($last['status']===308){$range=$last['headers']['range']??'';if(!preg_match('/^bytes=0-(\d+)$/D',$range,$m)||(int)$m[1]!==$offset+$length-1)throw new RuntimeException('Drive chưa xác nhận đầy đủ khối dữ liệu.');}
        $offset+=$length;
    }}finally{fclose($stream);}
    $done=json_decode($last['body']??'',true);if(!in_array($last['status']??0,[200,201],true)||($done['id']??'')!==$fileId)throw new RuntimeException('Drive chưa xác nhận tệp cuối cùng.');return$fileId;
}
