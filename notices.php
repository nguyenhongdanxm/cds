<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push_notifications.php';

/* Link cũ từ chuông/push vẫn hoạt động nhưng chuyển sang trang xem công khai. */
$publicNoticeId = trim((string)($_GET['notice'] ?? ''));
if ($publicNoticeId !== '') {
    header('Location: ' . BASE_URL . 'public_notice.php?id=' . rawurlencode($publicNoticeId));
    exit;
}

/* Chỉ màn hình quản lý mới yêu cầu đăng nhập. */
require_login();

function cds_notice_public_drive_url(string $path, string $title='', string $back='/'): string {
    if ($path === '' || !str_starts_with($path, 'gdrive:')) return '';
    $id = trim(substr($path, 7));
    if ($id === '') return '';
    return BASE_URL . 'public_drive_viewer.php?' . http_build_query(['id'=>$id,'title'=>$title,'back'=>$back]);
}

/*
 * Mọi file đính kèm của Thông báo được đặt quyền Anyone with the link / Reader.
 * Dùng registry cục bộ để không gọi Google API lặp lại với file đã chia sẻ.
 */
function cds_notice_share_all_files_public(): void {
    $source = DATA_PATH . '/cm_docs.json';
    $rows = is_file($source) ? json_decode((string)@file_get_contents($source), true) : [];
    if (!is_array($rows)) return;

    $registryFile = DATA_PATH . '/public_notice_drive_shared.json';
    $registry = is_file($registryFile) ? json_decode((string)@file_get_contents($registryFile), true) : [];
    if (!is_array($registry)) $registry = [];

    $ids = [];
    foreach ($rows as $row) {
        if (!is_array($row) || ($row['section'] ?? '') !== 'kh_thongbao') continue;
        $path = trim((string)($row['file_path'] ?? ''));
        if (!str_starts_with($path, 'gdrive:')) continue;
        $id = trim(substr($path, 7));
        if ($id !== '' && preg_match('/^[A-Za-z0-9_-]{10,}$/', $id)) $ids[$id] = true;
    }
    if (!$ids) return;

    $token = cds_drive_token();
    if (empty($token['ok'])) return;
    $headers = ['Authorization: Bearer ' . $token['token'], 'Content-Type: application/json; charset=UTF-8'];
    $changed = false;

    foreach (array_keys($ids) as $id) {
        if (!empty($registry[$id])) continue;
        $body = json_encode(['type'=>'anyone','role'=>'reader','allowFileDiscovery'=>false], JSON_UNESCAPED_UNICODE);
        $res = cds_drive_http(
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '/permissions?supportsAllDrives=true&sendNotificationEmail=false&fields=id',
            'POST',
            $headers,
            $body
        );
        if (!empty($res['ok'])) {
            $registry[$id] = ['shared_at'=>date('c')];
            $changed = true;
        }
    }

    if ($changed) @file_put_contents($registryFile, json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/* Đồng bộ Thông báo sang “Công việc sắp diễn ra” bằng URL công khai. */
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
        $content = trim(strip_tags((string)($row['content'] ?? '')));
        $filePath = trim((string)($row['file_path'] ?? ''));
        $link = trim((string)($row['link'] ?? ''));

        $item = $row;
        $item['kind'] = 'notice';
        $item['section'] = 'central_notice';
        if ($content === '' && $filePath !== '' && str_starts_with($filePath, 'gdrive:')) {
            $item['url'] = cds_notice_public_drive_url($filePath, (string)($row['title'] ?? 'Tài liệu'), '/');
        } elseif ($content === '' && $link !== '') {
            $item['url'] = $link;
        } else {
            $item['url'] = BASE_URL . 'public_notice.php?id=' . rawurlencode($id);
        }
        $item['dashboard_detail'] = $fieldLabels[$field] ?? 'Thông báo';
        $feed[] = $item;
    }

    $dir = defined('PCCM_DATA_PATH') && PCCM_DATA_PATH !== ''
        ? rtrim(PCCM_DATA_PATH, '/\\')
        : (__DIR__ . '/chuyenmon/data');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/dashboard_notices.json', json_encode($feed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/* Sau thao tác lưu/xóa của admin_notices_v2.php vẫn chạy nhờ shutdown. */
register_shutdown_function('cds_notice_share_all_files_public');
register_shutdown_function('cds_notice_sync_dashboard_feed');
cds_notice_share_all_files_public();
cds_notice_sync_dashboard_feed();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$noticeModules = ['chuyenmon','vanban','noitru','thuvien','thidua','csdl'];
$canNotice = $isAdmin;
if (!$canNotice) foreach ($noticeModules as $module) if (can_module($module, 'edit')) { $canNotice = true; break; }

if (isset($_GET['capability'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'can_manage'=>$canNotice], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$canNotice) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Không có quyền</title><div style="font:16px system-ui;padding:2rem">Bạn chưa được phân quyền đăng thông báo.</div>';
    exit;
}

require __DIR__ . '/includes/admin_notices_v2.php';
