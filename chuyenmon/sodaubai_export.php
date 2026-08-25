<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lesson_book_store.php';
require_once __DIR__ . '/includes/lesson_book_excel.php';
require_login();
$week=lb_week((string)($_GET['week']??''));if(!$week){http_response_code(404);exit('Không tìm thấy tuần học.');}
$class=trim((string)($_GET['class']??''));
if($class===''){http_response_code(422);exit('Hãy chọn một lớp trước khi xuất Sổ đầu bài.');}
$allSlots=lb_slots($week);$canSeeClass=false;foreach($allSlots as$r)if(($r['class']??'')===$class&&lb_can_view($r)){$canSeeClass=true;break;}
if(!$canSeeClass){http_response_code(403);exit('Tài khoản không được xem hoặc xuất Sổ đầu bài của lớp này.');}
$rows=array_values(array_filter($allSlots,fn($r)=>lb_can_view($r)&&($r['class']??'')===$class));$filename='So-dau-bai-Lop-'.preg_replace('/[^A-Za-z0-9_-]+/','-',$class.'-'.($week['label']??'tuan')).'.xlsx';$tmp=tempnam(sys_get_temp_dir(),'lb_export_');if($tmp===false||!lb_lesson_book_xlsx($rows,$week,$class,$tmp)){http_response_code(500);exit('Không tạo được Excel. Hosting cần bật ZipArchive.');}
if(!empty($_GET['save_drive'])){$content=(string)file_get_contents($tmp);@unlink($tmp);$res=cds_drive_save_generated($content,$filename,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','education_plans');flash(!empty($res['ok'])?'Đã lưu bản Excel lên Google Drive.':('Không lưu được Drive: '.($res['message']??'Lỗi không xác định')),!empty($res['ok'])?'success':'danger');header('Location: '.BASE_URL.'sodaubai.php?tab=week&week='.urlencode((string)$week['key']).'&class='.urlencode($class));exit;}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');readfile($tmp);@unlink($tmp);
