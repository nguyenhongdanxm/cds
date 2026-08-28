<?php
require_once __DIR__ . '/includes/functions.php';
if (!cds_user() || !cds_can_feature('cm.kehoach', 'view')) { header('Content-Type: application/json; charset=utf-8'); http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Tài khoản chưa có quyền Kế hoạch giáo dục.'],JSON_UNESCAPED_UNICODE); exit; }
header('Content-Type: application/json; charset=utf-8');

function edu_json(bool $ok,string $message='',array $extra=[]): void {echo json_encode(array_merge(['ok'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_UNICODE);exit;}
function edu_norm($value): string {$value=preg_replace('/\s+/u',' ',trim((string)$value));return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);}
function edu_grade($class): string {$class=trim((string)$class);if(preg_match('/(?:^|\D)(1[0-2]|[6-9])(?:\D|$)/u',$class,$m)||preg_match('/^(1[0-2]|[6-9])/u',$class,$m))return $m[1];return $class;}
function edu_save(string $file,array $rows): bool {return cds_json_save($file,array_values($rows));}
function edu_category(array $settings): string {$key='education_plans';if(!empty($settings['types'][$key]['folder_id']))return $key;foreach((array)($settings['types']??[]) as $candidate=>$type)if(edu_norm($type['label']??'')===edu_norm('Kế hoạch giáo dục')&&!empty($type['folder_id']))return (string)$candidate;return $key;}
function edu_context(): array {
    $user=cds_user()??[];$teacher=trim((string)($user['teacher_name']??$user['name']??''));$group=$teacher!==''?trim((string)get_teacher_group($teacher)):'';
    $allowed=[];foreach(get_assignments() as $a){if(edu_norm($a['teacher']??'')!==edu_norm($teacher))continue;$subject=trim((string)($a['subject']??''));$grade=edu_grade($a['class']??'');if($subject!==''&&$grade!=='')$allowed[$subject.'|'.$grade]=true;}
    return [$user,$teacher,$group,$allowed];
}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')edu_json(false,'Phương thức không hợp lệ.');
if(empty($_SESSION['cm_education_csrf'])||!hash_equals((string)$_SESSION['cm_education_csrf'],(string)($_POST['csrf']??'')))edu_json(false,'Phiên làm việc không hợp lệ.');
[$user,$teacher,$group,$allowed]=edu_context();if($teacher===''||$group==='')edu_json(false,'Tài khoản chưa liên kết đầy đủ với giáo viên và tổ chuyên môn.');
$action=(string)($_POST['action']??'');$subject=trim((string)($_POST['subject']??''));$grade=trim((string)($_POST['grade']??''));$appendix=in_array($_POST['appendix']??'', ['I','II','III'],true)?(string)$_POST['appendix']:'I';
if(in_array($action,['init','finalize'],true)&&!isset($allowed[$subject.'|'.$grade]))edu_json(false,'Môn hoặc khối không thuộc phân công chuyên môn của tài khoản.');
$settings=cds_drive_settings();$category=edu_category($settings);$folder=trim((string)($settings['types'][$category]['folder_id']??''));
if(empty($settings['enabled'])||$folder==='')edu_json(false,'Chưa cấu hình thư mục Kế hoạch giáo dục trên Google Drive.');

if($action==='init'){
    $name=basename((string)($_POST['file_name']??'ke-hoach.pdf'));$size=(int)($_POST['file_size']??0);
    if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf'||$size<=0||$size>25*1024*1024)edu_json(false,'Tệp PDF không hợp lệ hoặc vượt quá 25 MB.');
    $_POST['name']=$name;$name=function_exists('cds_drive_descriptive_name')?cds_drive_descriptive_name($name,$category):$name;
    $token=cds_drive_token($settings);if(empty($token['ok']))edu_json(false,(string)($token['message']??'Không kết nối được Google Drive.'));
    $metadata=json_encode(['name'=>$name,'parents'=>[$folder],'appProperties'=>['cdsType'=>$category]],JSON_UNESCAPED_UNICODE);
    $location='';$ch=curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true&fields=id,name,mimeType,size,parents');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$metadata,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token['token'],'Content-Type: application/json; charset=UTF-8','X-Upload-Content-Type: application/pdf','X-Upload-Content-Length: '.$size],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);},CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
    if($status<200||$status>=300||$location==='')edu_json(false,'Không tạo được phiên upload Google Drive'.($error!==''?': '.$error:''));
    $nonce=bin2hex(random_bytes(20));$_SESSION['cm_education_pending'][$nonce]=['expires'=>time()+900,'teacher'=>$teacher,'group'=>$group,'subject'=>$subject,'grade'=>$grade,'appendix'=>$appendix,'category'=>$category,'folder'=>$folder,'name'=>$name,'size'=>$size,'record_id'=>trim((string)($_POST['id']??'')),'upload_url'=>$location];
    edu_json(true,'Đã sẵn sàng tải trực tiếp lên Drive.',['upload_url'=>$location,'upload_token'=>$nonce]);
}
if($action==='recover'){
    $nonce=(string)($_POST['upload_token']??'');$pending=$_SESSION['cm_education_pending'][$nonce]??null;
    if(!is_array($pending)||(int)($pending['expires']??0)<time()||empty($pending['upload_url']))edu_json(false,'Phiên upload đã hết hạn.');
    $ch=curl_init((string)$pending['upload_url']);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_POSTFIELDS=>'',CURLOPT_HTTPHEADER=>['Content-Length: 0','Content-Range: bytes */'.(int)$pending['size']],CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10]);
    $responseHeaders=[];curl_setopt($ch,CURLOPT_HEADERFUNCTION,function($ch,$line)use(&$responseHeaders){$parts=explode(':',$line,2);if(count($parts)===2)$responseHeaders[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);});
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$info=json_decode((string)$body,true);
    if(in_array($status,[200,201],true)&&!empty($info['id']))edu_json(true,'Google Drive đã nhận đủ tệp.',['drive_file_id'=>$info['id']]);
    $next=0;if($status===308&&!empty($responseHeaders['range'])&&preg_match('/bytes=0-(\d+)/i',$responseHeaders['range'],$m))$next=(int)$m[1]+1;
    edu_json(true,'Tiếp tục tải qua máy chủ.',['fallback'=>true,'drive_status'=>$status,'next_offset'=>$next]);
}
if($action==='chunk'){
    $nonce=(string)($_POST['upload_token']??'');$pending=$_SESSION['cm_education_pending'][$nonce]??null;
    if(!is_array($pending)||(int)($pending['expires']??0)<time()||empty($pending['upload_url']))edu_json(false,'Phiên upload đã hết hạn. Vui lòng tải lại.');
    $chunk=$_FILES['chunk']??null;$offset=max(0,(int)($_POST['offset']??0));$total=(int)($pending['size']??0);
    if(!$chunk||($chunk['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($chunk['tmp_name']??'')))edu_json(false,'Máy chủ không nhận được phần dữ liệu PDF.');
    $length=(int)($chunk['size']??0);if($length<=0||$length>1024*1024||$offset+$length>$total)edu_json(false,'Phần dữ liệu tải lên không hợp lệ.');
    $bytes=file_get_contents((string)$chunk['tmp_name']);if($bytes===false||strlen($bytes)!==$length)edu_json(false,'Không đọc được phần dữ liệu PDF.');
    $headers=[];$ch=curl_init((string)$pending['upload_url']);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_HTTPHEADER=>['Content-Type: application/pdf','Content-Length: '.$length,'Content-Range: bytes '.$offset.'-'.($offset+$length-1).'/'.$total],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);},CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>15]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);$info=json_decode((string)$body,true);
    if(in_array($status,[200,201],true)&&!empty($info['id']))edu_json(true,'Google Drive đã nhận đủ tệp.',['complete'=>true,'drive_file_id'=>$info['id'],'next_offset'=>$total]);
    if($status===308){$next=$offset+$length;if(!empty($headers['range'])&&preg_match('/bytes=0-(\d+)/i',$headers['range'],$m))$next=(int)$m[1]+1;edu_json(true,'Đã chuyển một phần dữ liệu.',['complete'=>false,'next_offset'=>$next]);}
    edu_json(false,'Google Drive không nhận được phần dữ liệu'.($error!==''?': '.$error:'').' (mã '.$status.').');
}
if($action==='finalize'){
    $nonce=(string)($_POST['upload_token']??'');$pending=$_SESSION['cm_education_pending'][$nonce]??null;unset($_SESSION['cm_education_pending'][$nonce]);
    if(!is_array($pending)||(int)($pending['expires']??0)<time()||edu_norm($pending['teacher']??'')!==edu_norm($teacher))edu_json(false,'Phiên upload đã hết hạn.');
    $fileId=trim((string)($_POST['drive_file_id']??''));if(!preg_match('/^[A-Za-z0-9_-]{10,}$/',$fileId))edu_json(false,'Google Drive không trả về ID tệp hợp lệ.');
    $token=cds_drive_token($settings);if(empty($token['ok']))edu_json(false,(string)($token['message']??'Không xác minh được tệp.'));
    $check=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true&fields=id,name,mimeType,size,parents','GET',['Authorization: Bearer '.$token['token']]);$info=json_decode($check['body'],true);
    if(empty($check['ok'])||($info['mimeType']??'')!=='application/pdf'||!in_array($pending['folder'],(array)($info['parents']??[]),true))edu_json(false,'Tệp chưa được lưu đúng vào thư mục Kế hoạch giáo dục.');
    $dataFile=DATA_PATH.'/education_plans.json';$rows=load_json($dataFile,[]);$rows=is_array($rows)?$rows:[];$id=(string)($pending['record_id']??'');$found=false;
    foreach($rows as &$row){if(($row['id']??'')!==$id)continue;if(edu_norm($row['teacher']??'')!==edu_norm($teacher)||!empty($row['approved_at']))edu_json(false,'Kế hoạch không còn quyền chỉnh sửa.');$row['appendix']=$pending['appendix'];$row['subject']=$pending['subject'];$row['grade']=$pending['grade'];$row['file_path']='gdrive:'.$fileId;$row['submitted_at']=date('c');$row['rejected_at']='';$row['rejected_by']='';$row['rejection_reason']='';$row['updated_at']=date('c');$found=true;break;}unset($row);
    if(!$found)$rows[]=['id'=>'khdg_'.bin2hex(random_bytes(8)),'teacher_id'=>(string)($user['id']??''),'teacher'=>$teacher,'teacher_group'=>$group,'appendix'=>$pending['appendix'],'subject'=>$pending['subject'],'grade'=>$pending['grade'],'file_path'=>'gdrive:'.$fileId,'submitted_at'=>date('c'),'created_at'=>date('c'),'updated_at'=>date('c'),'approved_at'=>'','approved_by'=>''];
    if(!edu_save($dataFile,$rows))edu_json(false,'Không lưu được dữ liệu kế hoạch.');
    edu_json(true,$found?'Đã cập nhật kế hoạch giáo dục.':'Đã nộp kế hoạch giáo dục.',['redirect'=>BASE_URL.'kehoach.php?tab=vanban&appendix='.urlencode($pending['appendix'])]);
}
edu_json(false,'Thao tác không hợp lệ.');
