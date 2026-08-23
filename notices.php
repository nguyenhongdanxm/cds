<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push_notifications.php';
require_login();

if(!function_exists('mb_strimwidth')){
    function mb_strimwidth($string,$start,$width,$trimMarker='',$encoding=null){
        $string=(string)$string;$slice=substr($string,(int)$start,(int)$width);return strlen($string)>(int)$width?$slice.$trimMarker:$slice;
    }
}

/*
 * Khắc phục lịch sử Drive có fingerprint trùng nhưng file_id cũ đã bị xóa/mất quyền.
 * Nếu để nguyên, cds_drive_upload_bytes() sẽ trả lại ID cũ và báo thành công giả.
 */
function cds_notice_repair_stale_drive_duplicate(string $field='file', string $type='plans'): void {
    $upload=$_FILES[$field]??null;
    if(!$upload || (int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return;
    $tmp=(string)($upload['tmp_name']??'');
    if($tmp==='' || !is_file($tmp)) return;
    $bytes=@file_get_contents($tmp);
    if($bytes===false) return;

    $settings=cds_drive_settings();
    $folder=cds_drive_folder($type,$settings);
    if(empty($settings['enabled']) || $folder==='') return;
    $fingerprint=hash('sha256',$folder.'|'.$bytes);
    $history=cds_drive_history();
    if(!$history) return;

    $token=cds_drive_token($settings);
    if(empty($token['ok'])) return; // để luồng upload chính trả lỗi kết nối rõ ràng
    $headers=['Authorization: Bearer '.$token['token']];
    $changed=false;
    $kept=[];

    foreach($history as $row){
        $same=(string)($row['fingerprint']??'')===$fingerprint && trim((string)($row['file_id']??''))!=='';
        if(!$same){$kept[]=$row;continue;}
        $id=trim((string)$row['file_id']);
        $check=cds_drive_http(
            'https://www.googleapis.com/drive/v3/files/'.rawurlencode($id).'?supportsAllDrives=true&fields=id,trashed',
            'GET',$headers
        );
        $meta=json_decode((string)($check['body']??''),true);
        $dead=((int)($check['status']??0)===404) || (!empty($check['ok']) && !empty($meta['trashed']));
        if($dead){$changed=true;continue;}
        $kept[]=$row;
    }

    if($changed && defined('CDS_DRIVE_HISTORY')){
        @file_put_contents(CDS_DRIVE_HISTORY,json_encode(array_values($kept),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
    }
}

$user=current_user();
$isAdmin=($user['role']??'')==='admin';
$noticeModules=['chuyenmon','vanban','noitru','thuvien','thidua','csdl'];
$canNotice=$isAdmin;
if(!$canNotice){foreach($noticeModules as $module){if(can_module($module,'edit')){$canNotice=true;break;}}}

if(isset($_GET['capability'])){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'can_manage'=>$canNotice],JSON_UNESCAPED_UNICODE);exit;
}
if(!$canNotice){http_response_code(403);echo '<!doctype html><meta charset="utf-8"><title>Không có quyền</title><div style="font:16px system-ui;padding:2rem">Bạn chưa được phân quyền đăng thông báo.</div>';exit;}

if($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['notice_action']??'')==='save'){
    cds_notice_repair_stale_drive_duplicate('file','plans');
}

require __DIR__ . '/includes/admin_notices_view.php';
