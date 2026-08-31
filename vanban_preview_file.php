<?php
/** Luồng đọc tạm thời dành riêng cho Microsoft Office Viewer; token tự hết hạn sau 30 phút. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/vanban_store.php';

$kind = trim((string)($_GET['kind'] ?? ''));
$id = trim((string)($_GET['id'] ?? ''));
$index = max(0, (int)($_GET['file'] ?? 0));
$expires = (int)($_GET['expires'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));
$payload = $kind . '|' . $id . '|' . $index . '|' . $expires;
if ($expires < time() || $expires > time() + 3600 || !hash_equals(hash_hmac('sha256', $payload, vb_preview_secret()), $token)) {
    http_response_code(403); exit('Liên kết xem trước đã hết hạn.');
}

$path = ''; $name = 'van-ban';
if ($kind === 'document') {
    foreach (vb_rows(VANBAN_DOCUMENTS_FILE) as $row) if ((string)($row['id'] ?? '') === $id) {
        $files = vb_document_attachments($row);
        if (isset($files[$index])) { $path=(string)$files[$index]['path']; $name=(string)$files[$index]['name']; }
        break;
    }
} elseif ($kind === 'number') {
    foreach (vb_rows(VANBAN_NUMBERS_FILE) as $row) if ((string)($row['id'] ?? '') === $id) {
        $path=(string)($row['file_path'] ?? ''); $name=(string)($row['file_name'] ?? basename($path)); break;
    }
} elseif ($kind === 'archive') {
    foreach (vb_rows(VANBAN_ARCHIVES_FILE) as $row) if ((string)($row['id'] ?? '') === $id) {
        $path=(string)($row['file_path'] ?? ''); $name=basename($path); break;
    }
}
if ($path === '') { http_response_code(404); exit('Không tìm thấy tệp.'); }

if (str_starts_with($path, 'gdrive:')) {
    $file = cds_drive_download(substr($path, 7));
    if (empty($file['ok'])) { http_response_code((int)($file['status'] ?? 404)); exit; }
    $bytes=(string)$file['body']; $name=(string)($file['name'] ?? $name); $mime=(string)($file['mime'] ?? 'application/octet-stream');
} else {
    $absolute = realpath(BASE_PATH . '/' . ltrim($path, '/'));
    $dataRoot = realpath(DATA_PATH);
    if (!$absolute || !$dataRoot || !str_starts_with($absolute, $dataRoot . DIRECTORY_SEPARATOR) || !is_file($absolute)) { http_response_code(404); exit; }
    $bytes=(string)file_get_contents($absolute);
    $mime=function_exists('mime_content_type') ? (mime_content_type($absolute) ?: 'application/octet-stream') : 'application/octet-stream';
}
header('Content-Type: '.$mime);
header('Content-Length: '.strlen($bytes));
header("Content-Disposition: inline; filename*=UTF-8''".rawurlencode($name));
header('Cache-Control: private, max-age=900');
header('X-Content-Type-Options: nosniff');
echo $bytes;
