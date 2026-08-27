<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lesson_book_store.php';
require_once __DIR__ . '/includes/lesson_book_ppct_template_v2.php';
require_login();
if(!lb_is_admin()){http_response_code(403);exit('Chỉ quản trị được tải mẫu PPCT.');}
$tmp=tempnam(sys_get_temp_dir(),'lb_ppct_');if($tmp===false||!lb_ppct_template_v2_xlsx($tmp)){http_response_code(500);exit('Không tạo được mẫu Excel. Hosting cần bật ZipArchive.');}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="mau-nhap-PPCT-so-dau-bai.xlsx"');
header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');readfile($tmp);@unlink($tmp);
