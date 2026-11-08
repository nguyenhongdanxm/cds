<?php
try{require __DIR__.'/includes/teaching_hours_bootstrap.php';}catch(Throwable $e){http_response_code(400);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
if(isset($_GET['download'])){
 $file=null;try{
  if($thCategory!=='')throw new RuntimeException('Xuất bảng kê đầy đủ cần chọn tất cả nhóm giờ dạy. Các nhóm ôn thi vẫn được chia cột riêng.');
  $format=(string)($_GET['format']??'all');if(!in_array($format,['all','summary','personal'],true))throw new RuntimeException('Chọn loại bảng kê.');
  require_once __DIR__.'/includes/teaching_hours_excel.php';$file=tempnam(sys_get_temp_dir(),'cds-hours-');if(!$file||!th_export_xlsx($thActual,$thRoles,$thTotals,$thFrom,$thTo,SCHOOL_NAME,$format,$file,$thWarnings))throw new RuntimeException('Không tạo được Excel. Kiểm tra PHP ZipArchive.');
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="bang-ke-gio-day-'.$thFrom.'-'.$thTo.'.xlsx"');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($file));readfile($file);
 }catch(Throwable $e){http_response_code(400);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}finally{if(is_string($file)&&is_file($file))unlink($file);}exit;
}
header('Location: '.BASE_URL.'gio_day.php?'.http_build_query($thQuery+['tab'=>'export']));exit;
