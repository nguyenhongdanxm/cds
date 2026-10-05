<?php
$healthView = in_array($_GET['health_view'] ?? 'record', ['record','history','inventory'], true) ? ($_GET['health_view'] ?? 'record') : 'record';
$medicines = $healthView === 'history' ? [] : noitru_medicines_all();
if ($medicines) usort($medicines, fn($a,$b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
$healthEdit = null;
if ($healthView === 'record' && $canEditCurrent && !empty($_GET['edit'])) {
    foreach (noitru_health_all() as $record) if ((string)($record['id']??'') === (string)$_GET['edit'] && can_class($record['class_name']??'')) { $healthEdit=$record; break; }
}
if($healthEdit) {
    $boarders=array_values(array_filter(noitru_assignment_apply(noitru_boarders_on_date($healthEdit['date'])),fn($student)=>can_class($student['class_name']??'')));
    $known=array_column($medicines,'id');
    foreach(($healthEdit['medicines']??[]) as $item) if(!in_array($item['id'],$known,true)) {$item['quantity']=0;$medicines[]=$item;$known[]=$item['id'];}
}
$oldMedicineQty=[];
foreach (($healthEdit['medicines']??[]) as $item) { $key=(string)($item['medicine_id']??$item['id']??''); $oldMedicineQty[$key]=($oldMedicineQty[$key]??0)+(int)($item['quantity']??0); }
$classGroups = [];
if ($healthView === 'record') {
    foreach ($boarders as $student) $classGroups[trim($student['class_name'] ?? '') ?: '(Chưa lớp)'][] = $student;
    uksort($classGroups, 'csdl_compare_class_names');
    foreach ($classGroups as &$healthStudents) csdl_sort_students($healthStudents);
    unset($healthStudents);
}
$healthLabels = ['medicine'=>'Phát thuốc','first_aid'=>'Theo dõi tại phòng y tế','hospital'=>'Vào viện','family_pickup'=>'Gia đình đón về','thuoc'=>'Phát thuốc','kham'=>'Theo dõi tại phòng y tế','theo_doi'=>'Theo dõi'];
require __DIR__.'/noitru_health_history_filter.php';
$showMedicineStats = $healthView === 'inventory' && ($_GET['medicine_stats']??'') === '1';
$transactionTotals = $showMedicineStats ? noitru_medicine_totals() : [];
$periodIssued=[];
if ($showMedicineStats) {
    foreach (noitru_health_for_range($historyFrom,$historyTo) as $record) foreach (($record['medicines']??[]) as $item) {
        $key=(string)($item['medicine_id']??$item['id']??''); $periodIssued[$key]=($periodIssued[$key]??0)+(int)($item['quantity']??0);
    }

}
$historyStats = ['medicine'=>0,'first_aid'=>0,'hospital'=>0];
foreach ($filteredHealth as $row) if (isset($historyStats[$row['type'] ?? ''])) $historyStats[$row['type']]++;
$today = date('Y-m-d');
$threeMonths = date('Y-m-d', strtotime('+3 months'));
$inventoryStats = ['all'=>count($medicines),'low'=>0,'expiry'=>0];
foreach ($medicines as $medicine) {
    if ((int)($medicine['quantity'] ?? 0) <= (int)($medicine['low_stock'] ?? 10)) $inventoryStats['low']++;
    $expiry = $medicine['expiry_date'] ?? '';
    if ($expiry !== '' && $expiry <= $threeMonths) $inventoryStats['expiry']++;
}
?>
<div class="health-page">
  <div class="nt-page-head health-heading">
    <div><h4><i class="bi bi-heart text-danger"></i> Quản lý sức khỏe</h4><div class="subtitle">Theo dõi và chăm sóc sức khỏe học sinh</div></div>
    <?php if($healthView==='history'): ?><a class="btn btn-success health-export-excel" href="<?=e(BASE_URL.'noitru_health_excel.php?'.http_build_query(['range'=>$historyRange,'date'=>$historyDate,'q'=>(string)($_GET['q']??''),'type'=>$historyType,'student_id'=>$historyStudentId]))?>"><i class="bi bi-file-earmark-excel"></i> Xuất Excel A4 ngang</a> <button type="button" class="btn health-export-image" id="healthExportImage">Ảnh tổng hợp tuần</button><?php else: ?><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-download"></i> Xuất báo cáo</button><?php endif; ?>
  </div>

  <button type="button" class="btn btn-outline-primary mb-3" id="healthQrOpen"><i class="bi bi-qr-code-scan"></i> Quét thẻ học sinh</button>
  <nav class="health-tabs" aria-label="Chức năng quản lý sức khỏe">
    <a class="<?= $healthView==='record'?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=record') ?>"><i class="bi bi-stethoscope"></i> Ghi nhận</a>
    <a class="<?= $healthView==='history'?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=history') ?>"><i class="bi bi-calendar3"></i> Lịch sử</a>
    <a class="<?= $healthView==='inventory'?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=inventory') ?>"><i class="bi bi-box-seam"></i> Kho thuốc</a>
  </nav>

<?php if ($healthView === 'record'): ?>
  <form method="post" class="health-record-layout" id="healthRecordForm">
    <input type="hidden" name="action" value="health_save"><input type="hidden" name="id" value="<?= e($healthEdit['id']??'') ?>">
    <section class="health-panel health-students">
      <h5><i class="bi bi-person"></i> Chọn học sinh</h5>
      <div class="health-class-chips" id="healthClassChips">
        <button class="active" type="button" data-class="all">Tất cả (<?= count($boarders) ?>)</button>
        <?php foreach ($classGroups as $class=>$students): ?><button type="button" data-class="<?= e($class) ?>"><?= e($class) ?> (<?= count($students) ?>)</button><?php endforeach; ?>
      </div>
      <div class="health-search"><i class="bi bi-search"></i><input class="form-control" id="healthStudentSearch" placeholder="Tìm học sinh..."></div>
      <div class="health-student-list" id="healthStudentList">
        <?php foreach ($boarders as $student): ?>
          <label class="health-student-item" data-class="<?= e($student['class_name'] ?? '') ?>" data-search="<?= e(mb_strtolower(($student['name'] ?? '').' '.($student['code'] ?? ''),'UTF-8')) ?>">
            <input type="radio" name="student_id" value="<?= e($student['id']) ?>" <?= (string)($healthEdit['student_id']??($_GET['student_id']??''))===(string)$student['id']?'checked':'' ?> <?= $healthEdit && (string)$healthEdit['student_id']!==(string)$student['id']?'disabled':'' ?> required>
            <span><strong><?= e($student['name']) ?></strong><small><?= e($student['class_name'] ?? '') ?> · <?= e($student['code'] ?? '') ?></small></span><i class="bi bi-check-circle-fill"></i>
          </label>
        <?php endforeach; ?>
        <?php if (!$boarders): ?><div class="health-empty">Chưa có học sinh nội trú trong phạm vi quản lý.</div><?php endif; ?>
      </div>
    </section>

    <section class="health-panel health-form-panel">
      <h5><i class="bi bi-stethoscope"></i> <?= $healthEdit?'Chỉnh sửa hồ sơ đã lưu':'Thông tin sức khỏe' ?></h5>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Ngày ghi nhận</label><input class="form-control" type="date" name="date" value="<?= e($healthEdit['date']??date('Y-m-d')) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Hình thức xử lý</label><select class="form-select" name="type" id="healthTreatmentType"><option value="medicine" <?= ($healthEdit['type']??'medicine')==='medicine'?'selected':'' ?>>Phát thuốc</option><option value="first_aid" <?= ($healthEdit['type']??'medicine')==='first_aid'?'selected':'' ?>>Theo dõi tại phòng y tế</option><option value="hospital" <?= ($healthEdit['type']??'medicine')==='hospital'?'selected':'' ?>>Vào viện</option><option value="family_pickup" <?= ($healthEdit['type']??'medicine')==='family_pickup'?'selected':'' ?>>Gia đình đón về</option></select></div>
        <div class="col-12"><label class="form-label">Chẩn đoán / Triệu chứng <span class="text-danger">*</span></label><textarea class="form-control" name="diagnosis" rows="3" placeholder="Nhập chẩn đoán bệnh hoặc triệu chứng..." required><?= e($healthEdit['diagnosis']??'') ?></textarea></div>
        <div class="col-12"><label class="form-label">Xử trí</label><input class="form-control" name="treatment" value="<?= e($healthEdit['treatment']??'') ?>" placeholder="Mô tả theo dõi, chuyển viện hoặc bàn giao..."></div>
        <div class="col-12" id="healthMedicineArea">
          <label class="form-label">Thuốc phát cho học sinh</label>
          <div id="healthMedicineRows"></div>
          <button class="btn btn-outline-success btn-sm" type="button" id="addHealthMedicine" <?= !$medicines?'disabled':'' ?>><i class="bi bi-capsule"></i> Chọn thuốc từ kho</button>
          <?php if (!$medicines): ?><div class="form-text">Kho chưa có thuốc. Hãy thêm thuốc ở tab Kho thuốc.</div><?php endif; ?>
        </div>
        <div class="col-12"><label class="health-contact"><input class="form-check-input" type="checkbox" name="parent_contacted" value="1" <?= !empty($healthEdit['parent_contacted'])?'checked':'' ?>><i class="bi bi-telephone"></i><strong>Đã liên hệ phụ huynh</strong></label></div>
        <div class="col-12"><label class="form-label">Ghi chú thêm</label><textarea class="form-control" name="note" rows="3" placeholder="Ghi chú thêm (nếu có)..."><?= e($healthEdit['note']??'') ?></textarea></div>
      </div>
      <div class="health-form-actions"><a class="btn btn-outline-secondary" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=record') ?>">Tạo hồ sơ mới</a><button class="btn btn-info text-white flex-grow-1" type="submit" <?= !$canEditCurrent?'disabled':'' ?>><i class="bi bi-floppy"></i> <?= $healthEdit?'Lưu chỉnh sửa':'Lưu thông tin' ?></button></div>
    </section>
  </form>

  <template id="healthMedicineTemplate"><div class="health-medicine-row"><select class="form-select" name="medicine_id[]" required><option value="">— Chọn thuốc —</option><?php foreach ($medicines as $medicine): $available=(int)($medicine['quantity']??0)+(int)($oldMedicineQty[(string)$medicine['id']]??0); ?><option value="<?= e($medicine['id']) ?>" data-stock="<?= $available ?>" <?= $available<=0?'disabled':'' ?>><?= e($medicine['name']) ?> · còn <?= (int)($medicine['quantity']??0) ?> <?= e($medicine['unit']??'') ?></option><?php endforeach; ?></select><input class="form-control" type="number" name="medicine_qty[]" min="1" value="1" required aria-label="Số lượng"><button class="btn btn-outline-danger" type="button" data-remove-medicine><i class="bi bi-x-lg"></i></button></div></template>

<?php elseif ($healthView === 'history'): ?>
  <section class="health-panel">
    <div class="health-history-head"><h5>Lịch sử chăm sóc sức khỏe<?= $historyStudentId!=='' ? ' · '.e($historyStudentName ?: 'Học sinh không thuộc phạm vi quản lý') : '' ?></h5><?php if($historyStudentId!==''): ?><a class="btn btn-sm btn-outline-secondary" href="<?=e(BASE_URL.'noitru.php?tab=health&health_view=history')?>">Xem tất cả học sinh</a><?php endif; ?><div class="health-range-actions"><a class="btn btn-outline-secondary btn-sm" href="#"><i class="bi bi-archive"></i> Thùng rác</a><?php foreach (array_merge(['day'=>'Ngày','week'=>'Tuần','month'=>'Tháng'], $historyStudentId!=='' ? ['all'=>'Toàn bộ'] : []) as $range=>$label): ?><a class="btn btn-sm <?= $historyRange===$range?'btn-info text-white':'btn-outline-secondary' ?>" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'health','health_view'=>'history','range'=>$range,'date'=>$historyDate,'student_id'=>$historyStudentId])) ?>"><?= e($label) ?></a><?php endforeach; ?><form method="get"><input type="hidden" name="tab" value="health"><input type="hidden" name="health_view" value="history"><input type="hidden" name="student_id" value="<?=e($historyStudentId)?>"><input type="hidden" name="range" value="<?= e($historyRange) ?>"><input class="form-control form-control-sm" type="date" name="date" value="<?= e($historyDate) ?>" onchange="this.form.submit()"></form></div></div>
    <form method="get" class="health-history-filter"><input type="hidden" name="tab" value="health"><input type="hidden" name="health_view" value="history"><input type="hidden" name="student_id" value="<?=e($historyStudentId)?>"><input type="hidden" name="range" value="<?= e($historyRange) ?>"><input type="hidden" name="date" value="<?= e($historyDate) ?>"><div class="health-search"><i class="bi bi-search"></i><input class="form-control" name="q" value="<?= e($_GET['q']??'') ?>" placeholder="Tìm học sinh, chẩn đoán..."></div><select class="form-select" name="type"><option value="all">Tất cả</option><?php foreach (['medicine','first_aid','hospital','family_pickup'] as $type): ?><option value="<?= e($type) ?>" <?= $historyType===$type?'selected':'' ?>><?= e($healthLabels[$type]) ?></option><?php endforeach; ?></select><button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Lọc</button></form>
    <div class="health-summary-cards"><div class="green"><strong><?= $historyStats['medicine'] ?></strong><span>Phát thuốc</span></div><div class="yellow"><strong><?= $historyStats['first_aid'] ?></strong><span>Theo dõi tại phòng y tế</span></div><div class="red"><strong><?= $historyStats['hospital'] ?></strong><span>Vào viện</span></div></div>
    <div class="table-responsive"><table class="table health-table align-middle"><thead><tr><th>STT</th><th>Ngày</th><th>Học sinh</th><th>Chẩn đoán</th><th>Xử lý</th><th class="text-end">Thao tác</th></tr></thead><tbody>
      <?php foreach ($filteredHealth as $index=>$row): ?><tr><td><?= $index+1 ?></td><td><?= e(date('d/m/Y',strtotime($row['date']??'now'))) ?></td><td><strong><?= e($row['student_name']??'') ?></strong><small><?= e($row['class_name']??'') ?></small></td><td><?= e($row['diagnosis']??'') ?></td><td><span class="health-type type-<?= e($row['type']??'') ?>"><?= e($healthLabels[$row['type']??'']??($row['type']??'')) ?></span><?php if (!empty($row['medicines'])): ?><small><?= e(implode(', ',array_map(fn($item)=>($item['name']??'').' x'.($item['quantity']??0),$row['medicines']))) ?></small><?php endif; ?></td><td class="text-end"><?php if ($canEditCurrent): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'health','health_view'=>'record','edit'=>$row['id'],'date'=>$row['date']])) ?>" title="Sửa hồ sơ"><i class="bi bi-pencil-square"></i> Sửa</a> <?php endif; ?><?php if ($canDeleteCurrent): ?><form method="post" class="d-inline" onsubmit="return confirm('Xóa bản ghi y tế này?')"><input type="hidden" name="action" value="health_delete"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-outline-danger btn-sm" title="Xóa"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$filteredHealth): ?><tr><td colspan="6"><div class="health-empty">Không có bản ghi trong khoảng thời gian này</div></td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

<?php else: ?>
  <section class="health-panel mb-3">
    <form method="get" class="d-flex flex-wrap gap-2"><input type="hidden" name="tab" value="health"><input type="hidden" name="health_view" value="inventory"><input type="hidden" name="medicine_stats" value="1"><select class="form-select w-auto" name="range"><?php foreach (['week'=>'Tuần','month'=>'Tháng'] as $key=>$label): ?><option value="<?= $key ?>" <?= $historyRange===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select><input class="form-control w-auto" type="date" name="date" value="<?= e($historyDate) ?>"><button class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> Thống kê</button></form>
    <?php if($showMedicineStats): ?>
    <div class="health-medicine-statistics mt-3"><h5>Thống kê cấp thuốc <?= $historyRange==='week'?'tuần':'tháng' ?></h5><p>Từ <?= e(date('d/m/Y',strtotime($historyFrom))) ?> đến <?= e(date('d/m/Y',strtotime($historyTo))) ?> · Còn lại là tồn kho hiện tại</p>
    <div class="table-responsive"><table class="table health-table align-middle"><thead><tr><th>Tên thuốc</th><th>Đơn vị</th><th>Tổng nhập</th><th>Đã cấp trong kỳ</th><th>Còn lại</th></tr></thead><tbody>
    <?php foreach($medicines as $medicine): $key=(string)$medicine['id']; ?><tr><td><strong><?= e($medicine['name']??'') ?></strong></td><td><?= e($medicine['unit']??'') ?></td><td><?= (int)($transactionTotals[$key]['imported']??0) ?></td><td class="text-primary fw-bold"><?= (int)($periodIssued[$key]??0) ?></td><td class="text-success fw-bold"><?= (int)($medicine['quantity']??0) ?></td></tr><?php endforeach; ?>
    <?php if(!$medicines): ?><tr><td colspan="5">Kho thuốc chưa có dữ liệu.</td></tr><?php endif; ?>
    </tbody></table></div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=inventory') ?>">Đóng thống kê</a></div>
    <?php endif; ?>
  </section>
  <div class="health-inventory-stats">
    <a class="active" href="#medicineList"><i class="bi bi-box-seam"></i><strong><?= $inventoryStats['all'] ?></strong><span>Tất cả</span></a>
    <a href="#medicineList"><i class="bi bi-capsule"></i><strong><?= $inventoryStats['low'] ?></strong><span>Sắp hết kho</span></a>
    <a href="#medicineList"><i class="bi bi-exclamation-triangle"></i><strong><?= $inventoryStats['expiry'] ?></strong><span>Sắp/Hết HSD</span></a>
  </div>
  <section class="health-panel" id="medicineList">
    <div class="health-inventory-head"><h5>Danh sách thuốc</h5><div><button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#medicineRestockPicker"><i class="bi bi-arrow-up-circle"></i> Bổ sung</button><button class="btn btn-info text-white" type="button" data-bs-toggle="modal" data-bs-target="#medicineFormModal" onclick="resetMedicineForm()"><i class="bi bi-plus-lg"></i> Thêm mới</button></div></div>
    <div class="health-search mb-3"><i class="bi bi-search"></i><input class="form-control" id="medicineSearch" placeholder="Tìm thuốc..."></div>
    <div class="table-responsive"><table class="table health-table align-middle" id="medicineTable"><thead><tr><th>STT</th><th>Tên thuốc</th><th>Đơn vị</th><th>Hạn SD</th><th>Còn hiện tại</th><th class="text-end">Thao tác</th></tr></thead><tbody>
      <?php foreach ($medicines as $index=>$medicine): $qty=(int)($medicine['quantity']??0); $low=$qty<=(int)($medicine['low_stock']??10); ?>
        <tr data-medicine-name="<?= e(mb_strtolower($medicine['name']??'','UTF-8')) ?>"><td><?= $index+1 ?></td><td><strong><?= e($medicine['name']??'') ?></strong><?php if(!empty($medicine['note'])):?><small><?= e($medicine['note']) ?></small><?php endif;?></td><td><?= e($medicine['unit']??'') ?></td><td><?= !empty($medicine['expiry_date'])?e(date('d/m/Y',strtotime($medicine['expiry_date']))):'—' ?></td><td><span class="health-stock <?= $low?'low':'' ?>"><?= $qty ?></span></td><td class="text-end text-nowrap"><button class="btn btn-outline-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#medicineRestockModal" onclick='openMedicineRestock(<?= json_encode($medicine,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' title="Bổ sung"><i class="bi bi-arrow-up-circle"></i></button> <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#medicineFormModal" onclick='editMedicine(<?= json_encode($medicine,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' title="Sửa"><i class="bi bi-pencil-square"></i></button><?php if($canDeleteCurrent): ?> <form method="post" class="d-inline" onsubmit="return confirm('Xóa thuốc này khỏi danh sách?')"><input type="hidden" name="action" value="medicine_delete"><input type="hidden" name="id" value="<?= e($medicine['id']) ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
      <?php endforeach; ?><?php if(!$medicines): ?><tr><td colspan="6"><div class="health-empty">Kho thuốc chưa có dữ liệu.</div></td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

  <div class="modal fade" id="medicineFormModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title" id="medicineFormTitle">Thêm thuốc mới</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="medicine_save"><input type="hidden" name="id" id="medicineId"><label class="form-label">Tên thuốc *</label><input class="form-control mb-3" name="name" id="medicineName" required><div class="row g-2"><div class="col-6"><label class="form-label">Đơn vị</label><select class="form-select" name="unit" id="medicineUnit"><?php foreach(['viên','gói','lọ','ống','chai','hộp','vỉ','tuýp','cuộn'] as $unit): ?><option><?= e($unit) ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label">Số lượng ban đầu</label><input class="form-control" type="number" min="0" name="quantity" id="medicineQuantity" value="0"></div><div class="col-6"><label class="form-label">Hạn sử dụng</label><input class="form-control" type="date" name="expiry_date" id="medicineExpiry"></div><div class="col-6"><label class="form-label">Ngưỡng sắp hết</label><input class="form-control" type="number" min="0" name="low_stock" id="medicineLowStock" value="10"></div></div><label class="form-label mt-3">Ghi chú</label><textarea class="form-control" name="note" id="medicineNote"></textarea></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-info text-white">Lưu thuốc</button></div></form></div></div>
  <div class="modal fade" id="medicineRestockModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Bổ sung kho thuốc</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="medicine_restock"><input type="hidden" name="id" id="restockMedicineId"><div class="alert alert-light border" id="restockMedicineName"></div><label class="form-label">Số lượng bổ sung *</label><input class="form-control mb-3" type="number" min="1" name="quantity" required><label class="form-label">Ghi chú</label><input class="form-control" name="note" placeholder="Nguồn nhập, số lô..."></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-success">Bổ sung</button></div></form></div></div>
  <div class="modal fade" id="medicineRestockPicker" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chọn thuốc cần bổ sung</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body d-grid gap-2"><?php foreach($medicines as $medicine): ?><button class="btn btn-outline-secondary text-start" type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#medicineRestockModal" onclick='openMedicineRestock(<?= json_encode($medicine,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><strong><?= e($medicine['name']) ?></strong> · còn <?= (int)($medicine['quantity']??0) ?> <?= e($medicine['unit']??'') ?></button><?php endforeach; ?><?php if(!$medicines): ?><div class="health-empty">Chưa có thuốc để bổ sung.</div><?php endif; ?></div></div></div></div>
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const list=document.getElementById('healthStudentList'), search=document.getElementById('healthStudentSearch'), chips=document.getElementById('healthClassChips'); let activeClass='all';
  function filterStudents(){ if(!list)return; const q=(search?.value||'').toLocaleLowerCase('vi'); list.querySelectorAll('.health-student-item').forEach(row=>{row.hidden=!((activeClass==='all'||row.dataset.class===activeClass)&&(!q||row.dataset.search.includes(q)));}); }
  search?.addEventListener('input',filterStudents); chips?.addEventListener('click',e=>{const button=e.target.closest('button[data-class]');if(!button)return;activeClass=button.dataset.class;chips.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b===button));filterStudents();});
  const type=document.getElementById('healthTreatmentType'), medicineArea=document.getElementById('healthMedicineArea'), rows=document.getElementById('healthMedicineRows'), template=document.getElementById('healthMedicineTemplate');
  function toggleMedicine(){if(medicineArea){medicineArea.hidden=type?.value!=='medicine';medicineArea.querySelectorAll('select,input').forEach(el=>el.disabled=medicineArea.hidden);}} type?.addEventListener('change',toggleMedicine);toggleMedicine();
  function addMedicine(item){if(!rows||!template)return;const fragment=template.content.cloneNode(true),row=fragment.querySelector('.health-medicine-row'),select=row.querySelector('select'),qty=row.querySelector('input'); if(item){select.value=item.medicine_id||item.id||'';qty.value=item.quantity||1;} function stock(){const max=Number(select.selectedOptions[0]?.dataset.stock||0);qty.max=String(max);qty.setCustomValidity(select.value&&Number(qty.value)>max?'Số lượng vượt tồn kho có thể cấp':'');} select.addEventListener('change',stock);qty.addEventListener('input',stock);rows.appendChild(fragment);stock();toggleMedicine();}
  const savedMedicines=<?= json_encode($healthEdit['medicines']??[],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;savedMedicines.forEach(addMedicine);
  document.getElementById('addHealthMedicine')?.addEventListener('click',()=>addMedicine()); rows?.addEventListener('click',e=>{if(e.target.closest('[data-remove-medicine]'))e.target.closest('.health-medicine-row').remove();});
  document.getElementById('medicineSearch')?.addEventListener('input',function(){const q=this.value.toLocaleLowerCase('vi');document.querySelectorAll('#medicineTable tbody tr[data-medicine-name]').forEach(row=>row.hidden=!row.dataset.medicineName.includes(q));});
});
function resetMedicineForm(){document.getElementById('medicineFormTitle').textContent='Thêm thuốc mới';document.getElementById('medicineId').value='';document.getElementById('medicineName').value='';document.getElementById('medicineUnit').value='viên';document.getElementById('medicineQuantity').value='0';document.getElementById('medicineQuantity').disabled=false;document.getElementById('medicineExpiry').value='';document.getElementById('medicineLowStock').value='10';document.getElementById('medicineNote').value='';}
function editMedicine(m){document.getElementById('medicineFormTitle').textContent='Chỉnh sửa thuốc';document.getElementById('medicineId').value=m.id||'';document.getElementById('medicineName').value=m.name||'';document.getElementById('medicineUnit').value=m.unit||'viên';document.getElementById('medicineQuantity').value=m.quantity||0;document.getElementById('medicineQuantity').disabled=true;document.getElementById('medicineExpiry').value=m.expiry_date||'';document.getElementById('medicineLowStock').value=m.low_stock??10;document.getElementById('medicineNote').value=m.note||'';}
function openMedicineRestock(m){document.getElementById('restockMedicineId').value=m.id||'';document.getElementById('restockMedicineName').textContent=(m.name||'')+' · Tồn hiện tại: '+(m.quantity||0)+' '+(m.unit||'');}
</script>


<style>
.health-page .health-export-excel{background:#15803d!important;border:1px solid #15803d!important;color:#fff!important;font-size:14px!important;opacity:1!important;padding:10px 16px!important}
.health-page .health-export-excel i{color:inherit!important}.health-page .health-export-image{background:#0e7490!important;color:#fff!important;border:1px solid #0e7490!important;padding:10px 16px!important}
.health-report-dialog{width:min(620px,calc(100% - 1.2rem));max-height:90vh;overflow:auto;border:0;border-radius:20px;padding:0;box-shadow:0 25px 70px #0f172a47}.health-report-dialog::backdrop{background:#0f172aae}.health-report-dialog-body{padding:1.35rem}.health-report-dialog-head{display:flex;justify-content:space-between;gap:1rem;align-items:start}.health-report-close{border:0;background:transparent;font-size:1.2rem}.health-report-preview{margin-top:1rem;padding:.6rem;background:#eef3f8;border-radius:14px}.health-report-preview img{border-radius:10px;box-shadow:0 8px 28px #0f172a1f}.health-report-preview .health-report-actions{display:flex;justify-content:flex-end;gap:.7rem;margin-top:1.2rem}.health-report-preview .health-report-actions .btn{margin:0}@media(max-width:767.98px){.health-report-dialog-body{padding:1rem}.health-report-actions .btn{flex:1}}.health-report-preview img{display:block;max-width:100%;height:auto;margin:12px 0}.health-report-preview button{margin-right:10px;margin-bottom:16px}.health-report-preview a{display:inline-block;text-decoration:none}.health-report-actions .btn-info{background:#089dd8!important;color:#fff!important;border-color:#089dd8!important}.health-report-actions .btn-outline-secondary{background:#fff!important;color:#475569!important;border:1px solid #cbd5e1!important}
</style>
<?php if($healthView==='history'): ?>
<dialog class="health-report-dialog" id="healthReportDialog" aria-labelledby="healthReportTitle"><div class="health-report-dialog-body"><div class="health-report-dialog-head"><div><h3 class="h5 mb-1" id="healthReportTitle"><i class="bi bi-image"></i> Ảnh báo cáo sức khỏe tuần</h3><p class="text-muted small mb-0">Ảnh được dàn trang tối ưu để lưu và chia sẻ.</p></div><button class="health-report-close" type="button" id="healthReportClose" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></div><div id="healthReportPreview" class="health-report-preview" hidden></div></div></dialog>
<?php
$reportFrom=date('Y-m-d',strtotime('monday this week',strtotime($historyDate)));
$reportTo=date('Y-m-d',strtotime('sunday this week',strtotime($historyDate)));
$report=['school'=>defined('SCHOOL_NAME')?SCHOOL_NAME:'Trường','reporter'=>$user['name']??'','from'=>$reportFrom,'to'=>$reportTo,'total'=>0,'students'=>0,'types'=>['medicine'=>0,'first_aid'=>0,'hospital'=>0,'family_pickup'=>0],'classes'=>[]];
$reportStudentIds=[];$reportAllowed=[];
foreach(noitru_boarders_on_date($historyDate) as $student) if(can_class($student['class_name']??'')) $reportAllowed[(string)$student['id']]=true;
foreach(noitru_health_for_range($reportFrom,$reportTo) as $record) {
    $sid=(string)($record['student_id']??'');
    if(!isset($reportAllowed[$sid]) || !can_class($record['class_name']??''))continue;
    $type=['thuoc'=>'medicine','kham'=>'first_aid','theo_doi'=>'first_aid'][$record['type']??'']??($record['type']??'');
    $class=trim($record['class_name']??'')?:'Chưa lớp';
    if(!isset($report['classes'][$class]))$report['classes'][$class]=['class'=>$class,'visits'=>0,'students'=>[],'hospital'=>0];
    $report['total']++;$reportStudentIds[$sid]=true;
    if(isset($report['types'][$type]))$report['types'][$type]++;
    $report['classes'][$class]['visits']++;$report['classes'][$class]['students'][$sid]=true;
    if($type==='hospital')$report['classes'][$class]['hospital']++;
}
$report['students']=count($reportStudentIds);
uksort($report['classes'],'csdl_compare_class_names');
foreach($report['classes'] as &$classSummary)$classSummary['students']=count($classSummary['students']);
unset($classSummary);$report['classes']=array_values($report['classes']);
?>
<script type="application/json" id="healthReportData"><?= json_encode($report,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(BASE_URL.'assets/noitru_health_report.js?v=20261005-attendance3') ?>"></script>
<?php endif; ?>



<dialog id="healthQrDialog" style="width:min(94vw,520px);border:0;border-radius:16px;padding:20px">
  <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Quét thẻ học sinh</h5><button class="btn-close" id="healthQrClose" type="button" aria-label="Đóng"></button></div>
  <div id="healthQrReader" style="width:100%;overflow:hidden;border-radius:12px"></div>
  <div id="healthQrCameraArea" hidden class="mt-2"><label class="form-label small" for="healthQrCamera">Chọn camera (ưu tiên camera sau chính)</label><select id="healthQrCamera" class="form-select form-select-sm"></select></div>
  <div id="healthQrZoomArea" hidden class="mt-2"><label class="form-label small" for="healthQrZoom">Zoom <strong id="healthQrZoomValue">1×</strong></label><input id="healthQrZoom" class="form-range" type="range" min="1" max="6" step="0.1" value="1"></div>
  <button id="healthQrTorch" hidden type="button" class="btn btn-outline-secondary btn-sm mt-2" aria-pressed="false">Bật đèn</button>
  <p id="healthQrStatus" class="small text-muted mt-2" role="status" aria-live="polite">Đưa mã QR trên thẻ học sinh vào khung camera.</p>
  <button type="button" class="btn btn-outline-primary mb-3" id="healthQrStart">Mở camera / Quét lại</button>
  <label class="form-label small" for="healthQrFile">Hoặc chọn ảnh mã QR</label><input class="form-control mb-3" type="file" id="healthQrFile" accept="image/*">
  <div id="healthQrResult" hidden class="border rounded p-3 bg-light"><strong id="healthQrName"></strong><div id="healthQrClass" class="small text-muted mb-3"></div><div class="d-flex flex-wrap gap-2"><a id="healthQrRecord" class="btn btn-primary">Ghi nhận sức khỏe</a><a id="healthQrHistory" class="btn btn-outline-primary">Lịch sử sức khỏe</a></div></div>
</dialog>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="<?=e(BASE_URL)?>assets/noitru_health_qr.js?v=20261005-camera2" defer></script>
