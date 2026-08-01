<?php
$healthView = in_array($_GET['health_view'] ?? 'record', ['record','history','inventory'], true) ? ($_GET['health_view'] ?? 'record') : 'record';
$healthRows = array_values(array_filter(noitru_health_all(), fn($row) => noitru_student_in_scope($row['student_id'] ?? '')));
usort($healthRows, fn($a,$b) => strcmp(($b['date'] ?? '') . ($b['created_at'] ?? ''), ($a['date'] ?? '') . ($a['created_at'] ?? '')));
$medicines = noitru_medicines_all();
usort($medicines, fn($a,$b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
$classGroups = [];
foreach ($boarders as $student) $classGroups[trim($student['class_name'] ?? '') ?: '(Chưa lớp)'][] = $student;
ksort($classGroups, SORT_NATURAL);
$healthLabels = ['medicine'=>'Phát thuốc','first_aid'=>'Sơ cứu','hospital'=>'Vào viện','family_pickup'=>'Gia đình đón về','thuoc'=>'Phát thuốc','kham'=>'Sơ cứu','theo_doi'=>'Theo dõi'];
$historyRange = in_array($_GET['range'] ?? 'month', ['day','week','month'], true) ? ($_GET['range'] ?? 'month') : 'month';
$historyDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$historyTs = strtotime($historyDate);
if ($historyRange === 'day') { $historyFrom = $historyTo = $historyDate; }
elseif ($historyRange === 'week') { $historyFrom = date('Y-m-d', strtotime('monday this week', $historyTs)); $historyTo = date('Y-m-d', strtotime('sunday this week', $historyTs)); }
else { $historyFrom = date('Y-m-01', $historyTs); $historyTo = date('Y-m-t', $historyTs); }
$historySearch = mb_strtolower(trim($_GET['q'] ?? ''), 'UTF-8');
$historyType = trim($_GET['type'] ?? 'all');
$filteredHealth = array_values(array_filter($healthRows, function($row) use ($historyFrom,$historyTo,$historySearch,$historyType) {
    $date = $row['date'] ?? '';
    if ($date < $historyFrom || $date > $historyTo) return false;
    if ($historyType !== 'all' && ($row['type'] ?? '') !== $historyType) return false;
    if ($historySearch !== '') {
        $haystack = mb_strtolower(($row['student_name'] ?? '') . ' ' . ($row['diagnosis'] ?? '') . ' ' . ($row['class_name'] ?? ''), 'UTF-8');
        if (mb_strpos($haystack, $historySearch) === false) return false;
    }
    return true;
}));
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
    <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-download"></i> Xuất báo cáo</button>
  </div>

  <nav class="health-tabs" aria-label="Chức năng quản lý sức khỏe">
    <a class="<?= $healthView==='record'?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=record') ?>"><i class="bi bi-stethoscope"></i> Ghi nhận</a>
    <a class="<?= $healthView==='history'?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=history') ?>"><i class="bi bi-calendar3"></i> Lịch sử</a>
    <a class="<?= $healthView==='inventory'?'active':'' ?>" href="<?= e(BASE_URL.'noitru.php?tab=health&health_view=inventory') ?>"><i class="bi bi-box-seam"></i> Kho thuốc</a>
  </nav>

<?php if ($healthView === 'record'): ?>
  <form method="post" class="health-record-layout" id="healthRecordForm">
    <input type="hidden" name="action" value="health_save">
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
            <input type="radio" name="student_id" value="<?= e($student['id']) ?>" required>
            <span><strong><?= e($student['name']) ?></strong><small><?= e($student['class_name'] ?? '') ?> · <?= e($student['code'] ?? '') ?></small></span><i class="bi bi-check-circle-fill"></i>
          </label>
        <?php endforeach; ?>
        <?php if (!$boarders): ?><div class="health-empty">Chưa có học sinh nội trú trong phạm vi quản lý.</div><?php endif; ?>
      </div>
    </section>

    <section class="health-panel health-form-panel">
      <h5><i class="bi bi-stethoscope"></i> Thông tin sức khỏe</h5>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Ngày ghi nhận</label><input class="form-control" type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Hình thức xử lý</label><select class="form-select" name="type" id="healthTreatmentType"><option value="medicine">Phát thuốc</option><option value="first_aid">Sơ cứu</option><option value="hospital">Vào viện</option><option value="family_pickup">Gia đình đón về</option></select></div>
        <div class="col-12"><label class="form-label">Chẩn đoán / Triệu chứng <span class="text-danger">*</span></label><textarea class="form-control" name="diagnosis" rows="3" placeholder="Nhập chẩn đoán bệnh hoặc triệu chứng..." required></textarea></div>
        <div class="col-12"><label class="form-label">Xử trí</label><input class="form-control" name="treatment" placeholder="Mô tả cách sơ cứu, chuyển viện hoặc bàn giao..."></div>
        <div class="col-12" id="healthMedicineArea">
          <label class="form-label">Thuốc phát cho học sinh</label>
          <div id="healthMedicineRows"></div>
          <button class="btn btn-outline-success btn-sm" type="button" id="addHealthMedicine" <?= !$medicines?'disabled':'' ?>><i class="bi bi-capsule"></i> Chọn thuốc từ kho</button>
          <?php if (!$medicines): ?><div class="form-text">Kho chưa có thuốc. Hãy thêm thuốc ở tab Kho thuốc.</div><?php endif; ?>
        </div>
        <div class="col-12"><label class="health-contact"><input class="form-check-input" type="checkbox" name="parent_contacted" value="1"><i class="bi bi-telephone"></i><strong>Đã liên hệ phụ huynh</strong></label></div>
        <div class="col-12"><label class="form-label">Ghi chú thêm</label><textarea class="form-control" name="note" rows="3" placeholder="Ghi chú thêm (nếu có)..."></textarea></div>
      </div>
      <div class="health-form-actions"><button class="btn btn-outline-secondary" type="reset">Làm mới</button><button class="btn btn-info text-white flex-grow-1" type="submit" <?= !$canEditCurrent?'disabled':'' ?>><i class="bi bi-floppy"></i> Lưu thông tin</button></div>
    </section>
  </form>

  <template id="healthMedicineTemplate"><div class="health-medicine-row"><select class="form-select" name="medicine_id[]" required><option value="">— Chọn thuốc —</option><?php foreach ($medicines as $medicine): ?><option value="<?= e($medicine['id']) ?>" data-stock="<?= (int)($medicine['quantity']??0) ?>"><?= e($medicine['name']) ?> · còn <?= (int)($medicine['quantity']??0) ?> <?= e($medicine['unit']??'') ?></option><?php endforeach; ?></select><input class="form-control" type="number" name="medicine_qty[]" min="1" value="1" required aria-label="Số lượng"><button class="btn btn-outline-danger" type="button" data-remove-medicine><i class="bi bi-x-lg"></i></button></div></template>

<?php elseif ($healthView === 'history'): ?>
  <section class="health-panel">
    <div class="health-history-head"><h5>Lịch sử chăm sóc sức khỏe</h5><div class="health-range-actions"><a class="btn btn-outline-secondary btn-sm" href="#"><i class="bi bi-archive"></i> Thùng rác</a><?php foreach (['day'=>'Ngày','week'=>'Tuần','month'=>'Tháng'] as $range=>$label): ?><a class="btn btn-sm <?= $historyRange===$range?'btn-info text-white':'btn-outline-secondary' ?>" href="<?= e(BASE_URL.'noitru.php?'.http_build_query(['tab'=>'health','health_view'=>'history','range'=>$range,'date'=>$historyDate])) ?>"><?= e($label) ?></a><?php endforeach; ?><form method="get"><input type="hidden" name="tab" value="health"><input type="hidden" name="health_view" value="history"><input type="hidden" name="range" value="<?= e($historyRange) ?>"><input class="form-control form-control-sm" type="date" name="date" value="<?= e($historyDate) ?>" onchange="this.form.submit()"></form></div></div>
    <form method="get" class="health-history-filter"><input type="hidden" name="tab" value="health"><input type="hidden" name="health_view" value="history"><input type="hidden" name="range" value="<?= e($historyRange) ?>"><input type="hidden" name="date" value="<?= e($historyDate) ?>"><div class="health-search"><i class="bi bi-search"></i><input class="form-control" name="q" value="<?= e($_GET['q']??'') ?>" placeholder="Tìm học sinh, chẩn đoán..."></div><select class="form-select" name="type"><option value="all">Tất cả</option><?php foreach (['medicine','first_aid','hospital','family_pickup'] as $type): ?><option value="<?= e($type) ?>" <?= $historyType===$type?'selected':'' ?>><?= e($healthLabels[$type]) ?></option><?php endforeach; ?></select><button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Lọc</button></form>
    <div class="health-summary-cards"><div class="green"><strong><?= $historyStats['medicine'] ?></strong><span>Phát thuốc</span></div><div class="yellow"><strong><?= $historyStats['first_aid'] ?></strong><span>Sơ cứu</span></div><div class="red"><strong><?= $historyStats['hospital'] ?></strong><span>Vào viện</span></div></div>
    <div class="table-responsive"><table class="table health-table align-middle"><thead><tr><th>STT</th><th>Ngày</th><th>Học sinh</th><th>Chẩn đoán</th><th>Xử lý</th><th class="text-end">Thao tác</th></tr></thead><tbody>
      <?php foreach ($filteredHealth as $index=>$row): ?><tr><td><?= $index+1 ?></td><td><?= e(date('d/m/Y',strtotime($row['date']??'now'))) ?></td><td><strong><?= e($row['student_name']??'') ?></strong><small><?= e($row['class_name']??'') ?></small></td><td><?= e($row['diagnosis']??'') ?></td><td><span class="health-type type-<?= e($row['type']??'') ?>"><?= e($healthLabels[$row['type']??'']??($row['type']??'')) ?></span><?php if (!empty($row['medicines'])): ?><small><?= e(implode(', ',array_map(fn($item)=>($item['name']??'').' x'.($item['quantity']??0),$row['medicines']))) ?></small><?php endif; ?></td><td class="text-end"><?php if ($canDeleteCurrent): ?><form method="post" class="d-inline" onsubmit="return confirm('Xóa bản ghi y tế này?')"><input type="hidden" name="action" value="health_delete"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-outline-danger btn-sm" title="Xóa"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$filteredHealth): ?><tr><td colspan="6"><div class="health-empty">Không có bản ghi trong khoảng thời gian này</div></td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

<?php else: ?>
  <div class="health-inventory-stats">
    <a class="active" href="#medicineList"><i class="bi bi-box-seam"></i><strong><?= $inventoryStats['all'] ?></strong><span>Tất cả</span></a>
    <a href="#medicineList"><i class="bi bi-capsule"></i><strong><?= $inventoryStats['low'] ?></strong><span>Sắp hết kho</span></a>
    <a href="#medicineList"><i class="bi bi-exclamation-triangle"></i><strong><?= $inventoryStats['expiry'] ?></strong><span>Sắp/Hết HSD</span></a>
  </div>
  <section class="health-panel" id="medicineList">
    <div class="health-inventory-head"><h5>Danh sách thuốc</h5><div><button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#medicineRestockPicker"><i class="bi bi-arrow-up-circle"></i> Bổ sung</button><button class="btn btn-info text-white" type="button" data-bs-toggle="modal" data-bs-target="#medicineFormModal" onclick="resetMedicineForm()"><i class="bi bi-plus-lg"></i> Thêm mới</button></div></div>
    <div class="health-search mb-3"><i class="bi bi-search"></i><input class="form-control" id="medicineSearch" placeholder="Tìm thuốc..."></div>
    <div class="table-responsive"><table class="table health-table align-middle" id="medicineTable"><thead><tr><th>STT</th><th>Tên thuốc</th><th>Đơn vị</th><th>Hạn SD</th><th>Nhập</th><th>Đã phát</th><th>Còn</th><th class="text-end">Thao tác</th></tr></thead><tbody>
      <?php $transactions=noitru_medicine_transactions(); foreach ($medicines as $index=>$medicine): $imported=$issued=0; foreach($transactions as $tx) if(($tx['medicine_id']??'')===($medicine['id']??'')){if(($tx['type']??'')==='issue')$issued+=(int)($tx['quantity']??0);else $imported+=(int)($tx['quantity']??0);} $qty=(int)($medicine['quantity']??0); $low=$qty<=(int)($medicine['low_stock']??10); ?>
        <tr data-medicine-name="<?= e(mb_strtolower($medicine['name']??'','UTF-8')) ?>"><td><?= $index+1 ?></td><td><strong><?= e($medicine['name']??'') ?></strong><?php if(!empty($medicine['note'])):?><small><?= e($medicine['note']) ?></small><?php endif;?></td><td><?= e($medicine['unit']??'') ?></td><td><?= !empty($medicine['expiry_date'])?e(date('d/m/Y',strtotime($medicine['expiry_date']))):'—' ?></td><td class="text-success"><?= $imported ?></td><td class="text-warning"><?= $issued ?></td><td><span class="health-stock <?= $low?'low':'' ?>"><?= $qty ?></span></td><td class="text-end text-nowrap"><button class="btn btn-outline-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#medicineRestockModal" onclick='openMedicineRestock(<?= json_encode($medicine,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' title="Bổ sung"><i class="bi bi-arrow-up-circle"></i></button> <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#medicineFormModal" onclick='editMedicine(<?= json_encode($medicine,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' title="Sửa"><i class="bi bi-pencil-square"></i></button><?php if($canDeleteCurrent): ?> <form method="post" class="d-inline" onsubmit="return confirm('Xóa thuốc này khỏi danh sách?')"><input type="hidden" name="action" value="medicine_delete"><input type="hidden" name="id" value="<?= e($medicine['id']) ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
      <?php endforeach; ?><?php if(!$medicines): ?><tr><td colspan="8"><div class="health-empty">Kho thuốc chưa có dữ liệu.</div></td></tr><?php endif; ?>
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
  function toggleMedicine(){if(medicineArea)medicineArea.hidden=type?.value!=='medicine';} type?.addEventListener('change',toggleMedicine);toggleMedicine();
  document.getElementById('addHealthMedicine')?.addEventListener('click',()=>{rows.appendChild(template.content.cloneNode(true));}); rows?.addEventListener('click',e=>{if(e.target.closest('[data-remove-medicine]'))e.target.closest('.health-medicine-row').remove();});
  document.getElementById('medicineSearch')?.addEventListener('input',function(){const q=this.value.toLocaleLowerCase('vi');document.querySelectorAll('#medicineTable tbody tr[data-medicine-name]').forEach(row=>row.hidden=!row.dataset.medicineName.includes(q));});
});
function resetMedicineForm(){document.getElementById('medicineFormTitle').textContent='Thêm thuốc mới';document.getElementById('medicineId').value='';document.getElementById('medicineName').value='';document.getElementById('medicineUnit').value='viên';document.getElementById('medicineQuantity').value='0';document.getElementById('medicineQuantity').disabled=false;document.getElementById('medicineExpiry').value='';document.getElementById('medicineLowStock').value='10';document.getElementById('medicineNote').value='';}
function editMedicine(m){document.getElementById('medicineFormTitle').textContent='Chỉnh sửa thuốc';document.getElementById('medicineId').value=m.id||'';document.getElementById('medicineName').value=m.name||'';document.getElementById('medicineUnit').value=m.unit||'viên';document.getElementById('medicineQuantity').value=m.quantity||0;document.getElementById('medicineQuantity').disabled=true;document.getElementById('medicineExpiry').value=m.expiry_date||'';document.getElementById('medicineLowStock').value=m.low_stock??10;document.getElementById('medicineNote').value=m.note||'';}
function openMedicineRestock(m){document.getElementById('restockMedicineId').value=m.id||'';document.getElementById('restockMedicineName').textContent=(m.name||'')+' · Tồn hiện tại: '+(m.quantity||0)+' '+(m.unit||'');}
</script>
