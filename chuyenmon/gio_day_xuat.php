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
$page_title='Xuất dữ liệu giờ dạy';require __DIR__.'/includes/header.php';
?>
<section class="card p-3 mb-3"><?php require __DIR__.'/includes/teaching_hours_filter.php'; ?>
<p class="small">Bố cục theo bảng kê đã cung cấp: tổng hợp tổ/nhóm, chi tiết giáo viên, kiêm nhiệm và bảng kê cá nhân. Ôn TN, vào 10, HSG được tổng hợp riêng và có chi tiết đối chiếu từ sổ ký.</p>
<div class="alert alert-info py-2 small">Đây là bảng kê khối lượng thực dạy + kiêm nhiệm. Chưa tính định mức, bù trừ, tiền lương hoặc số tiết được thanh toán; các cột tương ứng trong mẫu để trống. Không xuất sheet thanh toán khi chưa có dữ liệu này.</div>
<?php if($thCategory!==''): ?><div class="alert alert-warning py-2">Để xuất bảng kê đầy đủ, chọn “Tất cả, chia cột riêng” trong Nhóm giờ dạy.</div><?php else: ?><div class="d-flex flex-wrap gap-2"><?php foreach(['all'=>'Xuất toàn bộ bảng kê','summary'=>'Xuất bảng tổng hợp và đối chiếu','personal'=>'Xuất bảng kê cá nhân']as$key=>$label): ?><a class="btn btn-sm btn-outline-primary" href="<?=e(BASE_URL.'gio_day_xuat.php?'.http_build_query($thQuery+['download'=>1,'format'=>$key]))?>"><?=e($label)?></a><?php endforeach; ?></div><?php endif; ?>
<p class="small text-muted mt-2 mb-0"><?=count($thTotals)?> giáo viên · <?=count($thActual)?> dòng thực dạy đã ký · <?=count($thRoles)?> nhiệm vụ kiêm nhiệm. Ngày lọc là ngày dạy, không phải ngày bấm ký. Bảng cá nhân gộp các tiết PPCT liên tiếp cùng môn/lớp/nhóm; dải có khoảng trống được tách riêng và giữ tổng số tiết thực tế.</p>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
