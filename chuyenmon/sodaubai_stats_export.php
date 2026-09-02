<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/lesson_book_store.php';
require_once __DIR__.'/includes/lesson_book_excel.php';
require_login();
$from=(string)($_GET['stats_from']??'');$to=(string)($_GET['stats_to']??'');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){http_response_code(422);exit('Khoảng ngày không hợp lệ.');}
$completion=in_array((string)($_GET['stats_completion']??'all'),['all','completed','incomplete','saved_unsigned','not_saved'],true)?(string)$_GET['stats_completion']:'all';
$rows=lb_stat_rows($from,$to,trim((string)($_GET['stats_teacher']??'')),trim((string)($_GET['stats_subject']??'')),trim((string)($_GET['stats_class']??'')),$completion);
$tmp=tempnam(sys_get_temp_dir(),'lb_stats_');if($tmp===false||!lb_statistics_xlsx($rows,$from,$to,$tmp)){http_response_code(500);exit('Không tạo được tệp thống kê. Hosting cần bật ZipArchive.');}
$filename='Thong-ke-So-dau-bai-'.$from.'-'.$to.'.xlsx';header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
