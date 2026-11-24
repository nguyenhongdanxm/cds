<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/csdl_store.php';
require_once __DIR__.'/includes/teacher_signatures.php';
require_login();
$id=is_string($_GET['id']??null)?$_GET['id']:'';$user=current_user()??[];$own=cds_ts_actor_teacher($user);
if(!($own&&(string)$own['id']===$id)&&!can_perm('csdl.teachers')){http_response_code(403);exit;}
$sig=cds_ts_meta($id);$path=cds_ts_path((string)($sig['path']??''));if($path===''){http_response_code(404);exit;}
$info=@getimagesize($path);if(!in_array($info['mime']??'',['image/png','image/jpeg','image/webp'],true)){http_response_code(404);exit;}
header('Content-Type: '.$info['mime']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($path));readfile($path);
