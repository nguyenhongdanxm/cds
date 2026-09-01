<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/lesson_book_curriculum_v2.php';
require_login();
if(!lb_can_manage_curriculum()){http_response_code(403);exit('Bạn chưa được phân quyền đưa PPCT lên.');}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
$csrf=(string)($_POST['csrf']??'');if(empty($_SESSION['lb_csrf'])||!hash_equals((string)$_SESSION['lb_csrf'],$csrf)){flash('Phiên thao tác hết hạn, vui lòng tải lại trang.','danger');header('Location: '.BASE_URL.'sodaubai.php?tab=curriculum');exit;}
$result=lb_ppct_import_v2($_FILES['curriculum_file']??[],$_POST);flash($result['message'],$result['ok']?'success':'danger');header('Location: '.BASE_URL.'sodaubai.php?tab=curriculum&week='.urlencode((string)($_POST['week']??'')));exit;
