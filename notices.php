<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push_notifications.php';
require_login();

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
require __DIR__ . '/includes/admin_notices_v2.php';
