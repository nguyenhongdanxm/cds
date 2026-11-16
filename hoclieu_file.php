<?php
/* HOC_LIEU_FILE_BUILD: 2026-08-11.1 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/learning_article.php';

$id = trim((string)($_GET['id'] ?? ''));
$data = load_json(DATA_PATH . '/learning_hub.json', ['items' => []]);
$row = null;
foreach ((array)($data['items'] ?? []) as $candidate) {
    if (($candidate['id'] ?? '') === $id) { $row = $candidate; break; }
}
if (!$row) { http_response_code(404); exit('Không tìm thấy học liệu.'); }

$user = current_user() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';
$userId = (string)($user['id'] ?? $user['username'] ?? '');
if (empty($row['approved']) && !$isAdmin && !cds_is_viewer($user) && ($row['author_id'] ?? '') !== $userId) {
    http_response_code(403); exit('Học liệu chưa được duyệt.');
}

if (($row['source_kind'] ?? '') === 'article') {
    if (!$isAdmin && !can_perm_level('hl.xem', 'view')) { http_response_code(403); exit('Bạn chưa có quyền xem học liệu.'); }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    // Isolate authored HTML: no scripts, forms, frames, navigation or same-origin access.
    header("Content-Security-Policy: sandbox; default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'");
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($row['title']) . '</title><style>body{max-width:960px;margin:24px auto;padding:0 18px;font:18px/1.65 Arial,sans-serif;color:#243247;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{border-collapse:collapse;max-width:100%}td,th{border:1px solid #aaa;padding:8px}h1{line-height:1.3}article{overflow-x:auto}</style><h1>' . e($row['title']) . '</h1><p>' . e($row['author'] ?? '') . ' · Khối ' . e($row['grade'] ?? '') . '</p><article>' . lh_article_clean((string)($row['article_body'] ?? '')) . '</article></html>';
    exit;
}

$path = (string)($row['file_path'] ?? '');
if (!str_starts_with($path, 'gdrive:')) { http_response_code(404); exit('Không tìm thấy tệp.'); }
$fileId = trim(substr($path, 7));
if ($fileId === '') { http_response_code(404); exit('Không tìm thấy tệp.'); }

// Tài liệu thông thường mở thẳng bằng trình xem gốc của Google Drive để cPanel
// không phải tải toàn bộ tệp rồi truyền lại. HTML tương tác vẫn cần proxy an toàn.
if (($row['source_kind'] ?? '') !== 'html') {
    header('Cache-Control: private, no-store');
    header('Location: https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view?usp=drive_link', true, 302);
    exit;
}

$file = cds_drive_download($fileId);
if (empty($file['ok'])) { http_response_code((int)($file['status'] ?? 404)); exit('Không tải được tệp từ Google Drive.'); }

$name = (string)($file['name'] ?? $row['title'] ?? 'hoc-lieu');
$extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
$mime = (string)($file['mime'] ?? 'application/octet-stream');
if (in_array($extension, ['html', 'htm'], true) || ($row['source_kind'] ?? '') === 'html') {
    $mime = 'text/html; charset=utf-8';
    header("Content-Security-Policy: sandbox allow-scripts allow-forms allow-modals; default-src 'none'; img-src https: data: blob:; media-src https: data: blob:; style-src 'unsafe-inline' https:; script-src 'unsafe-inline' https:; font-src https: data:; connect-src https:");
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, max-age=900');
header('Content-Type: ' . $mime);
header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($name));
header('Content-Length: ' . strlen((string)$file['body']));
echo $file['body'];
exit;

