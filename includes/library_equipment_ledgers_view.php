<?php
$equipmentRows=$data['equipment_loans'];
$equipmentToday=date('Y-m-d');
$equipmentStats=lib_eq_loan_stats($equipmentRows,$equipmentToday);
$equipmentTeachers=lib_eq_teacher_report($equipmentRows,$equipmentToday);
$equipmentTeacher=is_string($_GET['eq_teacher']??null)?$_GET['eq_teacher']:'';
// Use a hash of the normalized name as an opaque filter value; an unknown value matches no rows.
$equipmentVisibleRows=$equipmentTeacher===''?$equipmentRows:array_values(array_filter($equipmentRows,static fn($row)=>is_array($row)&&hash('sha256',lib_eq_norm((string)($row['borrower']??'')))===$equipmentTeacher));
$equipmentBooks=lib_eq_ledgers($equipmentVisibleRows);
$equipmentSignActor=function_exists('current_user')?(current_user()??[]):[];
$equipmentSignatureTeachers=function_exists('cds_ts_teachers')?cds_ts_teachers():[];
$equipmentSignTeacher=function_exists('cds_ts_actor_teacher')?cds_ts_actor_teacher($equipmentSignActor):null;
$equipmentSignMeta=$equipmentSignTeacher?cds_ts_meta((string)$equipmentSignTeacher['id']):null;
$equipmentSignViewer=($equipmentSignActor['role']??'')==='viewer';
?>
<section id="equipment-loan-ledgers" class="mb-4" aria-label="Thống kê mượn trả thiết bị">
<h3 class="h5">Thống kê mượn–trả toàn trường</h3>
<p class="text-muted small">Tổng hợp toàn bộ lịch sử, không thay đổi khi lọc giáo viên. Mỗi dòng thiết bị được tính là một lượt; quá hạn là chưa trả và đã qua ngày hẹn trả.</p>
<div class="row g-3 mb-3">
<?php foreach(['total'=>['Tổng lượt mượn','primary'],'returned'=>['Đã trả','success'],'open'=>['Đang mượn','warning'],'overdue'=>['Quá hạn','danger']] as $key=>$metric):?>
<div class="col-6 col-lg-3"><div class="card h-100 p-3"><span><?=e($metric[0])?></span><strong class="fs-3 text-<?=e($metric[1])?>" data-equipment-stat="<?=e($key)?>"><?=$equipmentStats[$key]?></strong></div></div>
<?php endforeach;?>
</div>
<details class="card mb-3"><summary class="p-3">Thống kê theo giáo viên (<?=count($equipmentTeachers)?>)</summary>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Giáo viên</th><th>Tổng lượt mượn</th><th>Đã trả</th><th>Đang mượn</th><th>Quá hạn</th></tr></thead><tbody>
<?php foreach($equipmentTeachers as $teacherKey=>$teacher):?><tr><td><a href="?<?=e(http_build_query(['tab'=>'equipment_loans','eq_teacher'=>$teacherKey]))?>#equipment-loan-ledgers"><?=e($teacher['name'])?></a></td><?php foreach(['total','returned','open','overdue'] as $metric):?><td><?=$teacher['stats'][$metric]?></td><?php endforeach;?></tr><?php endforeach;?>
<?php if(!$equipmentTeachers):?><tr><td colspan="5" class="text-muted">Chưa có lượt mượn thiết bị.</td></tr><?php endif;?>
</tbody></table></div></details>
<form method="get" action="<?=BASE_URL?>thuvien.php#equipment-loan-ledgers" class="card p-3 mb-3"><input type="hidden" name="tab" value="equipment_loans">
<div class="row g-2 align-items-end"><div class="col-md-8"><label for="equipment-teacher-filter" class="form-label">Lọc theo giáo viên</label><select id="equipment-teacher-filter" name="eq_teacher" class="form-select"><option value="">Tất cả giáo viên</option>
<?php foreach($equipmentTeachers as $teacherKey=>$teacher):?><option value="<?=e($teacherKey)?>" <?=$equipmentTeacher===$teacherKey?'selected':''?>><?=e($teacher['name'])?></option><?php endforeach;?>
</select></div><div class="col-md-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Lọc</button><a class="btn btn-outline-secondary" href="?tab=equipment_loans#equipment-loan-ledgers">Bỏ lọc</a></div></div>
</form>
</section>
<div class="alert alert-info" data-equipment-signature-guide><strong>Ký mượn / Ký trả:</strong> Mở sổ bên dưới và dùng cột <strong>Chữ ký giáo viên</strong> ở từng dòng thiết bị. Ký trả thực hiện sau khi xác nhận trả. Mỗi giáo viên đăng nhập tài khoản của mình để ký; tài khoản quản trị không ký thay giáo viên khác.
<?php if(!$equipmentSignTeacher):?><div class="mt-1">Tài khoản đang đăng nhập chưa liên kết với hồ sơ giáo viên để ký. Nếu đang quản lý kho, hãy để giáo viên đứng tên mượn đăng nhập xác nhận.</div><?php elseif(!$equipmentSignMeta):?><div class="mt-1">Chưa có ảnh chữ ký dùng chung cho tài khoản này. <a href="<?=e(BASE_URL)?>admin.php?view=profile">Mở thông tin cá nhân để bổ sung chữ ký</a>.</div><?php endif;?></div>
<div class="d-flex justify-content-between mb-3"><strong><?=count($equipmentBooks)?> sổ giáo viên–môn học</strong><span class="text-muted"><?=count($equipmentVisibleRows)?> dòng mượn–trả</span></div>
<?php foreach($equipmentBooks as$book):$equipmentBookTeacher=function_exists('cds_ts_teacher')?cds_ts_teacher(['name'=>$book['borrower']],$equipmentSignatureTeachers):null;$equipmentIsOwner=$equipmentSignTeacher&&$equipmentBookTeacher&&(string)$equipmentSignTeacher['id']===(string)$equipmentBookTeacher['id'];$equipmentSignReason=$equipmentSignViewer?'Tài khoản chỉ xem dữ liệu':(!$equipmentSignTeacher?'Tài khoản chưa liên kết hồ sơ giáo viên':(!$equipmentIsOwner?'Chỉ giáo viên đứng tên mượn được ký':(!$equipmentSignMeta?'Chưa có ảnh chữ ký trong CSDL':'')));?>
<details <?=$equipmentIsOwner||count($equipmentBooks)===1?'open':''?> class="card mb-3 equipment-ledger" data-equipment-ledger="<?=e($book['id'])?>">
<summary class="p-3"><strong><?=e($book['borrower']?:'Chưa xác định giáo viên')?></strong> · <?=e($book['subject']?:'Chưa xác định môn')?> · <?=e($book['school_year'])?> <span class="badge text-bg-secondary ms-2"><?=count($book['rows'])?> dòng</span> <span class="badge <?=$book['open']?'text-bg-warning':'text-bg-success'?>"><?=$book['open']?> dòng chưa trả</span><a class="btn btn-sm btn-outline-primary ms-2" target="_blank" rel="noopener" href="<?=BASE_URL?>thietbi_phieu.php?ledger=<?=urlencode($book['id'])?>"><i class="bi bi-printer"></i> In / Xem sổ</a></summary>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>STT</th><th>Lớp / Tiết PPCT</th><th>Thiết bị</th><th>Chữ ký giáo viên</th><th>SL</th><th>Ngày mượn</th><th>Hẹn trả</th><th>Mục đích</th><th>Ngày trả</th><th>Tình trạng</th><th>Thao tác dòng</th></tr></thead><tbody>
<?php foreach($book['rows']as$i=>$loan):$late=empty($loan['returned_at'])&&!empty($loan['due_at'])&&$loan['due_at']<date('Y-m-d');?><tr class="<?=$late?'overdue':''?>" data-equipment-loan="<?=e($loan['id']??'')?>"><td><?=$i+1?></td><td><?=e($loan['teaching_grade']??'')?> / <?=e($loan['lesson_period']??'')?></td><td><?=e($loan['equipment_name']??'')?><br><small><?=e($loan['equipment_code']??'')?></small></td><td class="equipment-signature-actions"><?php require __DIR__.'/library_equipment_signature_cell.php';?></td><td><?=(int)($loan['quantity']??0)?></td><td><?=lib_date((string)($loan['borrowed_at']??''))?></td><td><?=lib_date((string)($loan['due_at']??''))?><?=$late?'<br><strong>QUÁ HẠN</strong>':''?></td><td><?=e($loan['purpose']??'')?></td><td><?=lib_date((string)($loan['returned_at']??''))?></td><td><?=e($loan['return_condition']??'')?></td><td class="text-nowrap"><?php if(empty($loan['returned_at'])&&($canEquipmentLoanEdit||($loan['created_by']??'')===$userName)):?><button class="btn btn-sm btn-success" data-eqloan="<?=e(base64_encode(json_encode($loan,JSON_UNESCAPED_UNICODE)))?>" onclick="openEquipmentReturn(this)"><i class="bi bi-box-arrow-in-left"></i> Trả dòng này</button><?php endif;?><?php if($canEquipmentLoanDelete):?><form class="d-inline" method="post" onsubmit="return confirm('Xóa dòng mượn thiết bị này? Các dòng khác trong sổ được giữ nguyên.')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_equipment_loan"><input type="hidden" name="id" value="<?=e($loan['id']??'')?>"><button class="btn btn-sm btn-outline-danger" title="Xóa dòng"><i class="bi bi-trash"></i></button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></details>
<?php endforeach;if(!$equipmentBooks):?><div class="card p-4 text-center text-muted">Không có lượt mượn thiết bị phù hợp.</div><?php endif;?>
