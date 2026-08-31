<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/vanban_store.php';
require_once __DIR__ . '/includes/vanban_views.php';
require_login();

$user = current_user();
$id = trim((string)($_GET['id'] ?? ''));
$index = max(0, (int)($_GET['file'] ?? 0));
$document = null;
foreach (vb_rows(VANBAN_DOCUMENTS_FILE) as $row) {
    if ((string)($row['id'] ?? '') === $id) { $document = $row; break; }
}
if (!$document) { http_response_code(404); exit('Không tìm thấy văn bản.'); }
$attachments = vb_document_attachments($document);
if (!$attachments || !isset($attachments[$index])) { http_response_code(404); exit('Văn bản chưa có tệp đính kèm.'); }

vb_record_document_view($id, $user);
$item=$attachments[$index];$file=vb_read_stored_file((string)($item['path']??''),(string)($item['name']??'van-ban'));
if(empty($file['ok'])){http_response_code((int)($file['status']??404));exit('Không tìm thấy tệp văn bản.');}
$name=(string)($item['name']??($file['name']??'van-ban'));$disposition=!empty($_GET['download'])?'attachment':'inline';
header('Content-Type: '.(string)($file['mime']??'application/octet-stream'));
header('Content-Length: '.strlen((string)$file['body']));
header("Content-Disposition: $disposition; filename*=UTF-8''".rawurlencode($name));
header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');echo $file['body'];exit;
