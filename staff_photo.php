<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/staff_card_store.php';
require_once __DIR__.'/includes/account_profile.php';
require_login();
$id=trim((string)($_GET['id']??''));$teacher=csdl_teacher_find($id);$user=current_user()??[];$own=cds_account_teacher_for_user($user);$isOwn=$own&&hash_equals((string)($own['id']??''),$id);
if(!$isOwn&&!can_perm('csdl.teachers')){http_response_code(403);exit;}
if(!$teacher){http_response_code(404);exit;}
$photo=staff_card_photo_record($id,true);
if($photo&&!empty($photo['image_data'])){
    $bytes=(string)$photo['image_data'];$etag='"'.(string)($photo['checksum_sha256']??hash('sha256',$bytes)).'"';
    if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}
    header('Content-Type: '.(string)($photo['mime_type']??'image/jpeg'));header('Cache-Control: private, max-age=300');header('ETag: '.$etag);header('Content-Length: '.strlen($bytes));echo $bytes;exit;
}
$file=staff_card_photo_file($id);if($file===''){http_response_code(404);exit;}$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));$type=$ext==='png'?'image/png':($ext==='webp'?'image/webp':'image/jpeg');header('Content-Type: '.$type);header('Cache-Control: private, max-age=300');header('Content-Length: '.filesize($file));readfile($file);
