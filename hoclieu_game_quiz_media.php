<?php
require_once __DIR__ . '/includes/config.php';
$name=(string)($_GET['f']??'');
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D',$name,$matches)) { http_response_code(404); exit; }
$file=DATA_PATH.'/quiz_media/'.$name;
if (!is_file($file) || !is_readable($file)) { http_response_code(404); exit; }
$types=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
header('Content-Type: '.$types[$matches[1]]);
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400, immutable');
readfile($file);
