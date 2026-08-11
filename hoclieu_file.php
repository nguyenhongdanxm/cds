<?php
/* HOC_LIEU_FILE_BUILD: 2026-08-11.1 */
require_once __DIR__ . '/includes/auth.php';
require_login();

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
if (empty($row['approved']) && !$isAdmin && ($row['author_id'] ?? '') !== $userId) {
    http_response_code(403); exit('Học liệu chưa được duyệt.');
}

$path = (string)($row['file_path'] ?? '');
if (!str_starts_with($path, 'gdrive:')) { http_response_code(404); exit('Không tìm thấy tệp.'); }
$fileId = substr($path, 7);
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
