<?php
/* File/embedded responses must not be decorated by the site's HTML output filter. */
define('CDS_EMBEDDED_RESPONSE', true);
define('CDS_GLOBAL_UI_BUFFERED', true);
define('CDS_SKIP_DRIVE_ACTION_REGISTRATION', true);
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/learning_article.php';
require_once __DIR__ . '/includes/learning_content.php';
require_once __DIR__ . '/includes/learning_thumbnail.php';

$id = is_string($_GET['id']??null)?trim($_GET['id']):'';
$row=lc_require_item($id);
$isAdmin=(current_user()['role']??'')==='admin';
if(($_GET['download']??'')==='1'){
    if(!lc_can_download($row)){http_response_code(404);exit('Nội dung này không có tệp để tải xuống.');}
    header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
    $external=lc_drive_download_url((string)($row['url']??''));
    if($external!==''&&in_array($row['source_kind']??'',['link','gdrive','video_url','image_url'],true)){header('Location: '.$external,true,302);exit;}
    $file=cds_drive_download(substr((string)$row['file_path'],7));
    if(empty($file['ok'])){http_response_code(502);exit('Không tải được tệp. Vui lòng thử lại sau.');}
    $name=basename(str_replace('\\','/',(string)($row['file_name']??$file['name']??'hoc-lieu')));if($name==='')$name='hoc-lieu';
    $body=(string)($file['body']??'');header('Content-Type: application/octet-stream');header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));header('Content-Length: '.strlen($body));echo $body;exit;
}
if(($_GET['thumbnail']??'')==='1'){
    try{$image=lt_read_thumbnail($row);}catch(Throwable $e){http_response_code(404);exit('Không tìm thấy ảnh đại diện.');}
    header('Content-Type: '.$image['mime']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-cache');header('ETag: '.$image['etag']);
    if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$image['etag']){http_response_code(304);exit;}
    header('Content-Length: '.strlen($image['body']));echo $image['body'];exit;
}
if (($_GET['preview']??'')==='1') { require __DIR__.'/includes/learning_content_preview.php'; exit; }

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
if (($row['source_kind'] ?? '') !== 'html' && ($_GET['inline']??'')!=='1') {
    header('Cache-Control: private, no-store');
    header('Location: https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view?usp=drive_link', true, 302);
    exit;
}

$file = cds_drive_download($fileId);
if (empty($file['ok'])) { http_response_code((int)($file['status'] ?? 404)); exit('Không tải được tệp từ Google Drive.'); }

if (($_GET['inline']??'')==='1') {
    $bytes=(string)($file['body']??'');$kind=(string)($row['kind']??'');
    if(($row['source_kind']??'')!=='upload'||!in_array($kind,['Video','Hình ảnh'],true)||strlen($bytes)>50*1024*1024){http_response_code(415);exit('Tệp này không hỗ trợ trình xem trực tiếp.');}
    try{$mime=lc_upload_mime($kind,'upload',(string)($file['name']??''),$bytes,(string)($file['mime']??''));}catch(Throwable $e){http_response_code(415);exit('Định dạng nội dung không hợp lệ.');}
    header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Disposition: inline');header('Accept-Ranges: bytes');$size=strlen($bytes);try{$range=lc_byte_range((string)($_SERVER['HTTP_RANGE']??''),$size);}catch(InvalidArgumentException $e){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}if($range){[$start,$end]=$range;http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);$bytes=substr($bytes,$start,$end-$start+1);}header('Content-Length: '.strlen($bytes));echo $bytes;exit;
}

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

