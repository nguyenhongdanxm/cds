<?php
require_once __DIR__.'/includes/config.php';require_once __DIR__.'/includes/auth.php';require_once __DIR__.'/includes/google_drive_storage.php';require_login();
$file=cds_drive_download((string)($_GET['id']??''));if(empty($file['ok'])){http_response_code((int)($file['status']??404));exit;}
header('Content-Type: '.$file['mime']);header('Content-Length: '.strlen($file['body']));$disposition=!empty($_GET['download'])?'attachment':'inline';header("Content-Disposition: $disposition; filename*=UTF-8''".rawurlencode($file['name']));header('Cache-Control: private, max-age=3600');echo $file['body'];
