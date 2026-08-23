<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push_notifications.php';
require_login();

function cds_notice_drive_view_url(string $path, string $title='', string $back='admin.php'): string {
    if ($path === '' || !str_starts_with($path, 'gdrive:')) return '';
    $id = trim(substr($path, 7));
    if ($id === '') return '';
    return BASE_URL . 'drive_viewer.php?' . http_build_query(['id'=>$id,'title'=>$title,'back'=>$back]);
}

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
        $content = trim(strip_tags((string)($row['content'] ?? '')));
        $filePath = trim((string)($row['file_path'] ?? ''));
        $link = trim((string)($row['link'] ?? ''));

        $item = $row;
        $item['kind'] = 'notice';
        $item['section'] = 'central_notice';
        if ($content === '' && $filePath !== '' && str_starts_with($filePath, 'gdrive:')) {
            $item['url'] = cds_notice_drive_view_url($filePath, (string)($row['title'] ?? 'Tài liệu'), 'admin.php');
        } elseif ($content === '' && $link !== '') {
            $item['url'] = $link;
        } else {
            $item['url'] = BASE_URL . 'notices.php' . ($id !== '' ? '?notice=' . rawurlencode($id) : '');
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
register_shutdown_function('cds_notice_sync_dashboard_feed');
cds_notice_sync_dashboard_feed();

/* Chế độ xem thông báo dành cho mọi tài khoản đã đăng nhập. */
$viewNoticeId = trim((string)($_GET['notice'] ?? ''));
if ($viewNoticeId !== '') {
    $rows = is_file(DATA_PATH . '/cm_docs.json') ? json_decode((string)@file_get_contents(DATA_PATH . '/cm_docs.json'), true) : [];
    $notice = null;
    foreach (is_array($rows) ? $rows : [] as $row) {
        if (($row['section'] ?? '') === 'kh_thongbao' && (string)($row['id'] ?? '') === $viewNoticeId) { $notice = $row; break; }
    }
    if (!$notice) { http_response_code(404); exit('Không tìm thấy thông báo.'); }

    $title = trim((string)($notice['title'] ?? 'Thông báo')) ?: 'Thông báo';
    $content = trim((string)($notice['content'] ?? ''));
    $plainContent = trim(strip_tags($content));
    $filePath = trim((string)($notice['file_path'] ?? ''));
    $link = trim((string)($notice['link'] ?? ''));

    if ($plainContent === '' && $filePath !== '' && str_starts_with($filePath, 'gdrive:')) {
        header('Location: ' . cds_notice_drive_view_url($filePath, $title, 'admin.php'));
        exit;
    }
    if ($plainContent === '' && $link !== '') {
        header('Location: ' . $link);
        exit;
    }

    $fileUrl = cds_notice_drive_view_url($filePath, $title, 'notices.php?notice=' . rawurlencode($viewNoticeId));
    ?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=e($title)?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#172033;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.notice-view{min-height:100vh;display:grid;grid-template-rows:auto 1fr}.notice-top{position:sticky;top:0;z-index:5;display:flex;align-items:center;gap:.7rem;padding:.7rem .9rem;background:#fff;border-bottom:1px solid #dbe5ee;box-shadow:0 2px 10px #0001}.notice-top strong{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.notice-top a,.notice-top button{border:1px solid #cbd5e1;background:#fff;color:#1e293b;border-radius:10px;min-height:40px;padding:.55rem .8rem;font:inherit;font-weight:750;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer}.notice-top .close{background:#0f4c81;color:#fff;border-color:#0f4c81}.notice-body{width:min(920px,calc(100% - 2rem));margin:1.2rem auto;background:#fff;border:1px solid #dfe8f0;border-radius:18px;box-shadow:0 10px 32px #173b5d12;padding:1.2rem 1.3rem}.meta{color:#64748b;font-size:.9rem;margin:.35rem 0 1rem}.content{font-size:1rem;line-height:1.65;white-space:pre-wrap}.actions{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.2rem;padding-top:1rem;border-top:1px solid #e5edf4}.actions a{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem .9rem;border-radius:10px;text-decoration:none;font-weight:750}.file{background:#0f4c81;color:#fff}.link{border:1px solid #cbd5e1;color:#1e293b}@media(max-width:600px){.notice-top{padding:.55rem}.notice-top .label{display:none}.notice-body{width:calc(100% - 1rem);margin:.5rem auto;border-radius:12px;padding:1rem}}</style></head><body><div class="notice-view"><header class="notice-top"><button class="close" type="button" onclick="closeNotice()"><i class="bi bi-x-lg"></i><span class="label">Đóng</span></button><strong><?=e($title)?></strong><a href="admin.php"><i class="bi bi-house"></i><span class="label">Tổng quan</span></a></header><main class="notice-body"><h2><?=e($title)?></h2><div class="meta"><?=e((string)($notice['date'] ?? ''))?><?=!empty($notice['by'])?' · '.e((string)$notice['by']):''?></div><div class="content"><?=nl2br(e(strip_tags($content)))?></div><?php if($fileUrl!==''||$link!==''):?><div class="actions"><?php if($fileUrl!==''):?><a class="file" href="<?=e($fileUrl)?>"><i class="bi bi-file-earmark-text"></i> Xem file đính kèm</a><?php endif;?><?php if($link!==''):?><a class="link" href="<?=e($link)?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> Mở liên kết</a><?php endif;?></div><?php endif;?></main></div><script>function closeNotice(){if(history.length>1){history.back();return;}location.href='admin.php';}</script></body></html><?php
    exit;
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
require __DIR__ . '/includes/admin_notices_v2.php';
