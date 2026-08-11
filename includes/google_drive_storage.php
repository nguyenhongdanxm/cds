<?php
/** Lưu trữ Google Drive bằng Service Account, không cần Composer. */
if (function_exists('cds_drive_settings')) return;
if (!defined('CDS_DRIVE_SETTINGS')) define('CDS_DRIVE_SETTINGS', DATA_PATH . '/google_drive_settings.json');

function cds_drive_settings(): array {
    $raw = is_file(CDS_DRIVE_SETTINGS) ? json_decode((string)file_get_contents(CDS_DRIVE_SETTINGS), true) : [];
    return array_merge(['enabled'=>false,'client_email'=>'','private_key'=>'','folders'=>['documents'=>'','plans'=>'','education_plans'=>'','photos'=>'']], is_array($raw)?$raw:[]);
}
function cds_drive_save_settings(array $settings): bool {
    if (!is_dir(DATA_PATH)) @mkdir(DATA_PATH,0755,true);
    return false !== file_put_contents(CDS_DRIVE_SETTINGS,json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function cds_drive_b64url(string $value): string { return rtrim(strtr(base64_encode($value),'+/','-_'),'='); }
function cds_drive_http(string $url,string $method='GET',array $headers=[],string $body=''): array {
    if (!function_exists('curl_init')) return ['ok'=>false,'status'=>0,'body'=>'','error'=>'Hosting chưa bật PHP cURL.'];
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>15]);
    if($body!=='')curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$response=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['ok'=>$response!==false&&$status>=200&&$status<300,'status'=>$status,'body'=>(string)$response,'error'=>$error];
}
function cds_drive_token(array $settings=[]): array {
    $settings=$settings?:cds_drive_settings();$email=trim((string)($settings['client_email']??''));$key=(string)($settings['private_key']??'');
    if($email===''||$key==='')return ['ok'=>false,'message'=>'Chưa có tài khoản dịch vụ Google.'];
    $now=time();$header=cds_drive_b64url(json_encode(['alg'=>'RS256','typ'=>'JWT']));$claim=cds_drive_b64url(json_encode(['iss'=>$email,'scope'=>'https://www.googleapis.com/auth/drive','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now-30,'exp'=>$now+3500]));$input=$header.'.'.$claim;
    if(!function_exists('openssl_sign')||!openssl_sign($input,$signature,$key,OPENSSL_ALGO_SHA256))return ['ok'=>false,'message'=>'Khóa riêng không hợp lệ hoặc hosting chưa bật OpenSSL.'];
    $body=http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$input.'.'.cds_drive_b64url($signature)]);$res=cds_drive_http('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],$body);$json=json_decode($res['body'],true);
    if(!$res['ok']||empty($json['access_token']))return ['ok'=>false,'message'=>'Không lấy được quyền truy cập Google Drive: '.($json['error_description']??$res['error']??('HTTP '.$res['status']))];
    return ['ok'=>true,'token'=>$json['access_token']];
}
function cds_drive_upload_bytes(string $bytes,string $name,string $mime,string $category): array {
    $settings=cds_drive_settings();if(empty($settings['enabled']))return ['ok'=>false,'disabled'=>true,'message'=>'Google Drive chưa bật.'];
    $folder=trim((string)($settings['folders'][$category]??''));if($folder==='')return ['ok'=>false,'disabled'=>true,'message'=>'Chưa chỉ định thư mục cho loại dữ liệu này.'];
    $token=cds_drive_token($settings);if(empty($token['ok']))return $token;$boundary='cds-'.bin2hex(random_bytes(12));
    $meta=json_encode(['name'=>$name,'parents'=>[$folder]],JSON_UNESCAPED_UNICODE);$body="--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$boundary\r\nContent-Type: $mime\r\n\r\n$bytes\r\n--$boundary--";
    $res=cds_drive_http('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,mimeType,size','POST',['Authorization: Bearer '.$token['token'],'Content-Type: multipart/related; boundary='.$boundary],$body);$json=json_decode($res['body'],true);
    if(!$res['ok']||empty($json['id']))return ['ok'=>false,'message'=>'Không tải được file lên Drive: '.($json['error']['message']??$res['error']??('HTTP '.$res['status']))];
    return ['ok'=>true,'id'=>$json['id'],'path'=>'gdrive:'.$json['id'],'name'=>$json['name']??$name];
}
function cds_storage_handle_upload(string $field,string $category): string {
    $upload=$_FILES[$field]??null;if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
    $settings=cds_drive_settings();$folder=trim((string)($settings['folders'][$category]??''));
    if(!empty($settings['enabled'])&&$folder!==''){
        if(($upload['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Tải file lên không thành công.');
        $tmp=(string)($upload['tmp_name']??'');$bytes=$tmp!==''?file_get_contents($tmp):false;if($bytes===false)throw new RuntimeException('Không đọc được file tải lên.');
        $mime=function_exists('mime_content_type')?(mime_content_type($tmp)?:'application/octet-stream'):'application/octet-stream';$result=cds_drive_upload_bytes($bytes,basename((string)($upload['name']??'file')),$mime,$category);
        if(empty($result['ok']))throw new RuntimeException($result['message']??'Không lưu được file lên Google Drive.');return $result['path'];
    }
    return function_exists('cm_handle_upload')?cm_handle_upload($field):'';
}
function cds_storage_file_url(string $path): string {
    if(str_starts_with($path,'gdrive:'))return BASE_URL.'drive_file.php?id='.rawurlencode(substr($path,7));
    return function_exists('cm_file_url')?cm_file_url($path):$path;
}
function cds_drive_download(string $id): array {
    if(!preg_match('/^[A-Za-z0-9_-]{10,}$/',$id))return ['ok'=>false,'status'=>404];$token=cds_drive_token();if(empty($token['ok']))return ['ok'=>false,'status'=>503,'message'=>$token['message']??''];
    $meta=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?supportsAllDrives=true&fields=name,mimeType,size','GET',['Authorization: Bearer '.$token['token']]);$info=json_decode($meta['body'],true);if(!$meta['ok'])return ['ok'=>false,'status'=>$meta['status']?:404];
    $file=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?supportsAllDrives=true&alt=media','GET',['Authorization: Bearer '.$token['token']]);if(!$file['ok'])return ['ok'=>false,'status'=>$file['status']?:404];return ['ok'=>true,'body'=>$file['body'],'name'=>$info['name']??'file','mime'=>$info['mimeType']??'application/octet-stream'];
}
