<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/push_notifications.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = current_user() ?: [];
$input = json_decode((string)file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;
$action = (string)($input['action'] ?? $_GET['action'] ?? 'status');
function push_response(array $data, int $status = 200) { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
try {
    if ($action === 'status') push_response(['ok'=>true,'publicKey'=>cds_push_public_key(),'devices'=>cds_push_current_device_count($user),'unread'=>cds_push_unread_count($user),'notifications'=>cds_push_notifications_for_user($user,20)]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') push_response(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],405);
    if ($action === 'subscribe') {
        if (!cds_push_save_subscription((array)($input['subscription'] ?? []), $user)) throw new RuntimeException('Không lưu được thiết bị. Hãy kiểm tra HTTPS và quyền thông báo.');
        push_response(['ok'=>true,'message'=>'Đã bật thông báo trên thiết bị này.','devices'=>cds_push_current_device_count($user)]);
    }
    if ($action === 'unsubscribe') {
        cds_push_delete_subscription((string)($input['endpoint'] ?? ''),$user);
        push_response(['ok'=>true,'message'=>'Đã tắt thông báo trên thiết bị này.']);
    }
    if ($action === 'mark_read') {
        cds_push_mark_read($user,(string)($input['id']??''),!empty($input['all']));
        push_response(['ok'=>true,'unread'=>cds_push_unread_count($user)]);
    }
    if ($action === 'test') {
        $row=cds_push_add_notification('CDS – Đã bật thông báo','Thiết bị này đã sẵn sàng nhận thông báo mới.','admin.php',['audience'=>[cds_push_user_key($user)]]);
        $result=cds_push_send($row,[cds_push_user_key($user)]);
        push_response(['ok'=>($result['sent']??0)>0,'message'=>($result['sent']??0)>0?'Đã gửi thông báo thử.':'Chưa gửi được. Hãy kiểm tra quyền thông báo.']+$result);
    }
    push_response(['ok'=>false,'message'=>'Thao tác không hợp lệ.'],400);
} catch (Throwable $e) { push_response(['ok'=>false,'message'=>$e->getMessage()],422); }
