<?php
/** Tab Danh sách nội trú: Học sinh | Lớp | Phòng | Mâm ăn */
if (!isset($nt_list_base)) $nt_list_base = BASE_URL . 'noitru_list.php';

$view = $_GET['view'] ?? 'students';
if (!in_array($view, ['students', 'classes', 'rooms', 'meals'], true)) $view = 'students';
$q = trim($_GET['q'] ?? '');
$f_class = trim($_GET['class'] ?? '');
$f_room = trim($_GET['room'] ?? '');
$f_meal = trim($_GET['meal'] ?? '');

$byClass = $byRoom = $byMeal = [];
$studentById = [];
foreach ($boarders as $s) {
    $studentById[(string)($s['id'] ?? '')] = $s;
    $cn = trim((string)($s['class_name'] ?? '')) ?: '(Chưa lớp)';
    $rm = trim((string)($s['room_ktx'] ?? '')) ?: '(Chưa phòng)';
    $mg = trim((string)($s['meal_group'] ?? '')) ?: '(Chưa mâm/nhóm ăn)';
    $byClass[$cn][] = $s; $byRoom[$rm][] = $s; $byMeal[$mg][] = $s;
}
uksort($byClass, 'csdl_compare_class_names');
ksort($byRoom, SORT_NATURAL); ksort($byMeal, SORT_NATURAL);

/* Vai trò phòng được lưu chung với dữ liệu chia phòng. */
$assignmentData = function_exists('noitru_assignments_data') ? noitru_assignments_data() : [];
$roomLeaders = (array)($assignmentData['room_leaders'] ?? []);
$roomTeachers = (array)($assignmentData['room_teachers'] ?? []);
$teacherById = [];
if (function_exists('csdl_teachers_all')) {
    foreach (csdl_teachers_all() as $teacher) {
        $id = (string)($teacher['id'] ?? '');
        if ($id !== '') $teacherById[$id] = trim((string)($teacher['name'] ?? ''));
    }
}
if (!function_exists('nt_room_role_info')) {
function nt_room_role_info(string $room, array $leaders, array $teachers, array $students, array $teacherMap): array {
    $lr = (array)($leaders[$room] ?? []);
    $tr = (array)($teachers[$room] ?? []);
    $leaderId = (string)($lr['leader_id'] ?? '');
    $deputyId = (string)($lr['deputy_id'] ?? '');
    $teacherId = (string)($tr['teacher_id'] ?? '');
    return [
        'teacher' => $teacherId !== '' ? ($teacherMap[$teacherId] ?? '') : '',
        'leader' => $leaderId !== '' ? trim((string)($students[$leaderId]['name'] ?? '')) : '',
        'deputy' => $deputyId !== '' ? trim((string)($students[$deputyId]['name'] ?? '')) : '',
    ];
}
}

if (!function_exists('nt_boarders_table')) {
function nt_boarders_table(array $list) {
    if (!$list) { echo '<div class="text-muted text-center py-4">Không có học sinh.</div>'; return; }
    csdl_sort_students($list);
    echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">';
    echo '<thead><tr><th>STT</th><th>Họ tên</th><th>Lớp</th><th>Phòng</th><th>Mâm/nhóm ăn</th><th>PH / SĐT</th><th>Ghi chú</th></tr></thead><tbody>';
    foreach ($list as $i => $s) {
        $studentJson = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '<tr><td>'.($i+1).'</td>';
        echo '<td><button type="button" class="nt-student-name" data-bs-toggle="modal" data-bs-target="#ntStudentDetailModal" data-student="'.e($studentJson).'">'.e($s['name'] ?? '').'</button>';
        if (!empty($s['code'])) echo '<div class="small text-muted">'.e($s['code']).'</div>';
        echo '</td><td>'.e(($s['class_name'] ?? '') ?: '—').'</td>';
        echo '<td>'.(!empty($s['room_ktx'])?'<span class="badge badge-room">'.e($s['room_ktx']).'</span>':'—').'</td>';
        echo '<td>'.(!empty($s['meal_group'])?'<span class="badge badge-meal">'.e($s['meal_group']).'</span>':'—').'</td>';
        echo '<td class="small">'.e($s['parent_name'] ?? ''); if (!empty($s['parent_phone'])) echo ' · '.e($s['parent_phone']); echo '</td>';
        echo '<td class="small text-muted">'.e($s['note'] ?? '').'</td></tr>';
    }
    echo '</tbody></table></div>';
}
}

$subTabs = [
    'students'=>['Học sinh','bi-person-lines-fill'],
    'classes'=>['Lớp','bi-building'],
    'rooms'=>['Phòng','bi-door-closed'],
    'meals'=>['Mâm ăn','bi-egg-fried'],
];
?>
<style>
.nt-room-role-box{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem;margin:.65rem 0 1rem}.nt-room-role{padding:.65rem .75rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.nt-room-role small{display:block;color:#64748b;font-size:.72rem}.nt-room-role strong{display:block;margin-top:.15rem;font-size:.9rem;overflow-wrap:anywhere}.nt-room-card-meta{margin-top:.5rem;padding-top:.45rem;border-top:1px dashed #e5e7eb;font-size:.72rem;line-height:1.45;color:#64748b}.nt-room-card-meta b{color:#334155;font-weight:600}@media(max-width:700px){.nt-room-role-box{grid-template-columns:1fr}.nt-room-card-meta{font-size:.68rem}}
</style>
<ul class="nav nav-tabs mb-3 flex-wrap">
<?php foreach ($subTabs as $vk=>$vi): ?>
<li class="nav-item"><a class="nav-link <?= $view===$vk?'active':'' ?>" href="<?= e(nt_list_url(['view'=>$vk])) ?>"><i class="bi <?=e($vi[1])?>"></i> <?=e($vi[0])?>
<?php if($vk==='students'):?><span class="badge bg-secondary ms-1"><?=count($boarders)?></span><?php endif;?>
<?php if($vk==='classes'):?><span class="badge bg-secondary ms-1"><?=count($byClass)?></span><?php endif;?>
<?php if($vk==='rooms'):?><span class="badge bg-secondary ms-1"><?=count($byRoom)?></span><?php endif;?>
<?php if($vk==='meals'):?><span class="badge bg-secondary ms-1"><?=count($byMeal)?></span><?php endif;?>
</a></li>
<?php endforeach; ?>
</ul>

<?php if ($view === 'students'): ?>
<?php
$list=$boarders;
if($q!==''){$lower=fn($v)=>function_exists('mb_strtolower')?mb_strtolower((string)$v,'UTF-8'):strtolower((string)$v);$qq=$lower($q);$list=array_values(array_filter($list,function($s)use($qq,$lower){$blob=$lower(implode(' ',[$s['name']??'',$s['code']??'',$s['class_name']??'',$s['room_ktx']??'',$s['meal_group']??'',$s['parent_name']??'',$s['parent_phone']??'']));return function_exists('mb_strpos')?mb_strpos($blob,$qq)!==false:strpos($blob,$qq)!==false;}));}
if($f_class!=='')$list=array_values(array_filter($list,fn($s)=>(trim((string)($s['class_name']??''))?:'(Chưa lớp)')===$f_class));
if($f_room!=='')$list=array_values(array_filter($list,fn($s)=>(trim((string)($s['room_ktx']??''))?:'(Chưa phòng)')===$f_room));
if($f_meal!=='')$list=array_values(array_filter($list,fn($s)=>(trim((string)($s['meal_group']??''))?:'(Chưa mâm/nhóm ăn)')===$f_meal));
$classKeys=array_keys($byClass);
?>
<form method="get" class="card card-soft mb-3" action="<?=e($nt_list_base)?>"><div class="card-body py-2"><input type="hidden" name="view" value="students"><div class="row g-2 align-items-end">
<div class="col-md-4"><label class="form-label small mb-0">Tìm kiếm</label><input type="search" name="q" class="form-control form-control-sm" value="<?=e($q)?>" placeholder="Tên, mã, lớp, phòng, mâm, PH…"></div>
<div class="col-md-2"><label class="form-label small mb-0">Lớp</label><select name="class" class="form-select form-select-sm"><option value="">Tất cả</option><?php foreach($classKeys as $ck):?><option value="<?=e($ck)?>" <?=$f_class===$ck?'selected':''?>><?=e($ck)?> (<?=count($byClass[$ck])?>)</option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label small mb-0">Phòng</label><select name="room" class="form-select form-select-sm"><option value="">Tất cả</option><?php foreach($byRoom as $rk=>$arr):?><option value="<?=e($rk)?>" <?=$f_room===$rk?'selected':''?>><?=e($rk)?> (<?=count($arr)?>)</option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label small mb-0">Mâm / nhóm ăn</label><select name="meal" class="form-select form-select-sm"><option value="">Tất cả</option><?php foreach($byMeal as $mk=>$arr):?><option value="<?=e($mk)?>" <?=$f_meal===$mk?'selected':''?>><?=e($mk)?> (<?=count($arr)?>)</option><?php endforeach;?></select></div>
<div class="col-md-2 d-flex gap-1"><button class="btn btn-nt btn-sm flex-grow-1">Lọc</button><a href="<?=e(nt_list_url(['view'=>'students']))?>" class="btn btn-outline-secondary btn-sm">Xóa</a></div>
</div></div></form>
<div class="card card-soft"><div class="card-body py-2 border-bottom small text-muted">Hiển thị <strong><?=count($list)?></strong> / <?=count($boarders)?> HS nội trú</div><?php nt_boarders_table($list);?></div>

<?php elseif ($view === 'classes'): ?>
<?php if($f_class!==''&&isset($byClass[$f_class])): ?>
<div class="mb-2"><a href="<?=e(nt_list_url(['view'=>'classes']))?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Tất cả lớp</a><span class="ms-2 fw-semibold">Lớp <?=e($f_class)?></span><span class="badge bg-secondary ms-1"><?=count($byClass[$f_class])?> HS</span></div>
<div class="card card-soft"><?php nt_boarders_table($byClass[$f_class]);?></div>
<?php else: ?><div class="row g-2"><?php foreach($byClass as $ck=>$arr):?><div class="col-6 col-md-4 col-lg-3"><a href="<?=e(nt_list_url(['view'=>'classes','class'=>$ck]))?>" class="text-decoration-none"><div class="card card-soft h-100"><div class="card-body text-center py-3"><div class="fw-bold" style="color:var(--primary);font-size:1.15rem"><?=e($ck)?></div><div class="text-muted small"><?=count($arr)?> học sinh</div></div></div></a></div><?php endforeach;?></div><?php endif; ?>

<?php elseif ($view === 'rooms'): ?>
<?php if($f_room!==''&&isset($byRoom[$f_room])):$role=nt_room_role_info($f_room,$roomLeaders,$roomTeachers,$studentById,$teacherById); ?>
<div class="mb-2"><a href="<?=e(nt_list_url(['view'=>'rooms']))?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Tất cả phòng</a><span class="ms-2 fw-semibold">Phòng <?=e($f_room)?></span><span class="badge badge-room ms-1"><?=count($byRoom[$f_room])?> HS</span></div>
<div class="nt-room-role-box">
<div class="nt-room-role"><small><i class="bi bi-person-badge"></i> Giáo viên phụ trách</small><strong><?=e($role['teacher']?:'Chưa gắn')?></strong></div>
<div class="nt-room-role"><small><i class="bi bi-star"></i> Trưởng phòng</small><strong><?=e($role['leader']?:'Chưa gắn')?></strong></div>
<div class="nt-room-role"><small><i class="bi bi-person-check"></i> Phó phòng</small><strong><?=e($role['deputy']?:'Chưa gắn')?></strong></div>
</div>
<div class="card card-soft"><?php nt_boarders_table($byRoom[$f_room]);?></div>
<?php else: ?><div class="row g-2"><?php foreach($byRoom as $rk=>$arr):$role=nt_room_role_info($rk,$roomLeaders,$roomTeachers,$studentById,$teacherById);?><div class="col-6 col-md-4 col-lg-3"><a href="<?=e(nt_list_url(['view'=>'rooms','room'=>$rk]))?>" class="text-decoration-none"><div class="card card-soft h-100"><div class="card-body text-center py-3"><i class="bi bi-door-closed fs-4" style="color:var(--primary)"></i><div class="fw-bold mt-1"><?=e($rk)?></div><div class="text-muted small"><?=count($arr)?> học sinh</div><div class="nt-room-card-meta text-start"><div><b>GV:</b> <?=e($role['teacher']?:'—')?></div><div><b>Trưởng:</b> <?=e($role['leader']?:'—')?></div><div><b>Phó:</b> <?=e($role['deputy']?:'—')?></div></div></div></div></a></div><?php endforeach;?></div><?php endif; ?>

<?php elseif ($view === 'meals'): ?>
<?php if($f_meal!==''&&isset($byMeal[$f_meal])): ?>
<div class="mb-2"><a href="<?=e(nt_list_url(['view'=>'meals']))?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Tất cả mâm</a><span class="ms-2 fw-semibold"><?=e($f_meal)?></span><span class="badge badge-meal ms-1"><?=count($byMeal[$f_meal])?> HS</span></div>
<div class="card card-soft"><?php nt_boarders_table($byMeal[$f_meal]);?></div>
<?php else: ?><div class="row g-2"><?php foreach($byMeal as $mk=>$arr):?><div class="col-6 col-md-4 col-lg-3"><a href="<?=e(nt_list_url(['view'=>'meals','meal'=>$mk]))?>" class="text-decoration-none"><div class="card card-soft h-100"><div class="card-body text-center py-3"><i class="bi bi-egg-fried fs-4 text-success"></i><div class="fw-bold mt-1"><?=e($mk)?></div><div class="text-muted small"><?=count($arr)?> học sinh</div></div></div></a></div><?php endforeach;?></div><?php endif; ?>
<?php endif; ?>

<div class="modal fade nt-profile-modal" id="ntStudentDetailModal" tabindex="-1" aria-labelledby="ntStudentDetailTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content"><div class="nt-profile-head"><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button><div class="nt-profile-person"><div class="nt-profile-avatar"><i class="bi bi-person-fill"></i></div><div><h5 id="ntStudentDetailTitle" data-student-field="name">Thông tin học sinh</h5><p><span data-student-field="code">—</span> · Lớp <span data-student-field="class_name">—</span></p></div></div></div><div class="nt-profile-body">
<section class="nt-profile-section"><div class="nt-profile-section-title"><i class="bi bi-person-vcard"></i> Thông tin cá nhân</div><div class="nt-profile-grid"><div class="nt-profile-field"><small>Ngày sinh</small><strong data-student-field="dob">—</strong></div><div class="nt-profile-field"><small>Giới tính</small><strong data-student-field="gender">—</strong></div><div class="nt-profile-field"><small>Dân tộc</small><strong data-student-field="ethnicity">—</strong></div><div class="nt-profile-field"><small>CCCD/Mã định danh</small><strong data-student-field="cccd">—</strong></div><div class="nt-profile-field"><small>Số điện thoại</small><strong data-student-field="phone">—</strong></div><div class="nt-profile-field"><small>Lớp</small><strong data-student-field="class_name">—</strong></div><div class="nt-profile-field full"><small>Quê quán</small><strong data-student-field="hometown">—</strong></div><div class="nt-profile-field full"><small>Địa chỉ</small><strong data-student-field="address">—</strong></div></div></section>
<section class="nt-profile-section"><div class="nt-profile-section-title"><i class="bi bi-people"></i> Gia đình và liên hệ</div><div class="nt-profile-grid"><div class="nt-profile-field"><small>Phụ huynh</small><strong data-student-field="parent_name">—</strong></div><div class="nt-profile-field"><small>Điện thoại phụ huynh</small><strong data-student-field="parent_phone">—</strong></div></div></section>
<section class="nt-profile-section"><div class="nt-profile-section-title"><i class="bi bi-building"></i> Thông tin nội trú</div><div class="nt-profile-grid"><div class="nt-profile-field"><small>Phòng KTX</small><strong data-student-field="room_ktx">—</strong></div><div class="nt-profile-field"><small>Mâm/nhóm ăn</small><strong data-student-field="meal_group">—</strong></div><div class="nt-profile-field full"><small>Ghi chú</small><strong class="nt-profile-note" data-student-field="note">—</strong></div></div></section>
</div></div></div></div>
<script>document.getElementById('ntStudentDetailModal')?.addEventListener('show.bs.modal',function(event){var trigger=event.relatedTarget,student={};try{student=JSON.parse(trigger?.dataset.student||'{}')}catch(e){}this.querySelectorAll('[data-student-field]').forEach(function(field){var key=field.dataset.studentField,value=String(student[key]??'').trim();if(key==='dob'&&/^\d{4}-\d{2}-\d{2}$/.test(value)){var p=value.split('-');value=p[2]+'/'+p[1]+'/'+p[0]}field.textContent=value||'—'})});</script>
