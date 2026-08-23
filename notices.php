<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push_notifications.php';
require_login();

/* Đồng bộ Thông báo tập trung sang nguồn mà admin.php dùng cho “Công việc sắp diễn ra”. */
function cds_notice_sync_dashboard_feed(): void {
    $source = DATA_PATH . '/cm_docs.json';
    $rows = is_file($source) ? json_decode((string)@file_get_contents($source), true) : [];
    if (!is_array($rows)) $rows = [];

    $fieldLabels = [
        'chung'=>'Thông báo chung',
        'chuyenmon'=>'Chuyên môn',
        'vanban'=>'Văn bản · Hành chính',
        'noitru'=>'Nội trú',
        'thuvien'=>'Thư viện · Thiết bị',
        'thidua'=>'Thi đua',
        'csdl'=>'Cơ sở dữ liệu',
    ];
    $feed = [];
    foreach ($rows as $row) {
        if (!is_array($row) || ($row['section'] ?? '') !== 'kh_thongbao') continue;
        $id = trim((string)($row['id'] ?? ''));
        $field = trim((string)($row['field'] ?? 'chuyenmon')) ?: 'chuyenmon';
        $item = $row;
        $item['kind'] = 'notice';
        /* Không dùng section kh_thongbao ở bản mirror để dashboard không kéo link về Chuyên môn cũ. */
        $item['section'] = 'central_notice';
        $item['url'] = '/notices.php' . ($id !== '' ? '?notice=' . rawurlencode($id) : '');
        $item['dashboard_detail'] = $fieldLabels[$field] ?? 'Thông báo';
        $feed[] = $item;
    }

    $dir = defined('PCCM_DATA_PATH') && PCCM_DATA_PATH !== ''
        ? rtrim(PCCM_DATA_PATH, '/\\')
        : (__DIR__ . '/chuyenmon/data');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(
        $dir . '/dashboard_notices.json',
        json_encode($feed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}
/* admin_notices_v2.php có redirect/exit sau lưu/xóa; shutdown vẫn chạy nên dữ liệu dashboard luôn được cập nhật. */
register_shutdown_function('cds_notice_sync_dashboard_feed');
/* Đồng bộ ngay cả khi chỉ mở trang, giúp các thông báo đã đăng trước bản sửa này xuất hiện trên Tổng quan. */
cds_notice_sync_dashboard_feed();

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
