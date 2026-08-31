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
$url = vb_file_url((string)($attachments[$index]['path'] ?? ''));
if ($url === '') { http_response_code(404); exit('Không tìm thấy đường dẫn tệp.'); }
if (!empty($_GET['download'])) $url .= (str_contains($url, '?') ? '&' : '?') . 'download=1';
header('Location: ' . $url, true, 302);
exit;
