<?php
/** Tương thích liên kết xem trước đã được trình duyệt lưu từ phiên bản trước. */
require_once __DIR__ . '/includes/auth.php';
require_login();
$kind=trim((string)($_GET['kind']??'document'));$id=trim((string)($_GET['id']??''));$index=max(0,(int)($_GET['file']??0));
if($kind!=='document'||$id===''){http_response_code(404);exit('Không tìm thấy văn bản.');}
header('Location: '.BASE_URL.'vanban_preview.php?'.http_build_query(['id'=>$id,'file'=>$index]),true,302);exit;
