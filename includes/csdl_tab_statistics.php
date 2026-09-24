<?php
/** Read-only statistics for the selected field and population. */
if (!function_exists('csdl_stat_label')) {
    function csdl_stat_label($value): string {
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));
        return $value === '' ? 'Chưa cập nhật' : $value;
    }
    function csdl_stat_gender($value): string {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim((string)$value), 'UTF-8') : strtolower(trim((string)$value));
        if (in_array($value, ['nam', 'm', 'male'], true)) return 'Nam';
        if (in_array($value, ['nữ', 'nu', 'f', 'female'], true)) return 'Nữ';
        return $value === '' ? 'Chưa cập nhật' : 'Khác';
    }
}
$population = (string)($_GET['stat_scope'] ?? 'students');
if (!in_array($population, ['students', 'teachers'], true)) $population = 'students';
$status = (string)($_GET['stat_status'] ?? 'active');
if (!in_array($status, ['active', 'inactive', 'all'], true)) $status = 'active';
$grade = trim((string)($_GET['stat_grade'] ?? ''));
$classId = trim((string)($_GET['stat_class'] ?? ''));
$team = trim((string)($_GET['stat_team'] ?? ''));
$classById = [];
$grades = [];
foreach ($classes as $class) {
    $classById[(string)($class['id'] ?? '')] = $class;
    if (!empty($class['grade'])) $grades[(string)$class['grade']] = (string)$class['grade'];
}
ksort($grades, SORT_NATURAL);
$teams = [];
foreach ($teachers as $teacher) {
    $value = trim((string)($teacher['to_chuyen_mon'] ?? ($teacher['pccm_group'] ?? '')));
    if ($value !== '') $teams[$value] = $value;
}
natcasesort($teams);
$fields = $population === 'teachers' ? [
    'gender'=>'Giới tính', 'team'=>'Tổ chuyên môn', 'specialty'=>'Chuyên môn',
    'position'=>'Chức vụ', 'level'=>'Cấp giảng dạy', 'ethnicity'=>'Dân tộc',
    'birth_year'=>'Năm sinh', 'join_year'=>'Năm vào ngành', 'rank'=>'Hạng',
    'grade_salary'=>'Bậc lương', 'concurrent'=>'Kiêm nhiệm', 'completeness'=>'Mức độ đầy đủ hồ sơ'
] : [
    'class'=>'Lớp', 'grade'=>'Khối', 'gender'=>'Giới tính', 'ethnicity'=>'Dân tộc',
    'boarder'=>'Nội trú', 'room'=>'Phòng KTX', 'meal'=>'Nhóm ăn',
    'birth_year'=>'Năm sinh', 'completeness'=>'Mức độ đầy đủ hồ sơ'
];
$field = (string)($_GET['stat_field'] ?? ($population === 'teachers' ? 'team' : 'class'));
if (!isset($fields[$field])) $field = array_key_first($fields);
if (!isset($grades[$grade])) $grade = '';
if (!isset($classById[$classId])) $classId = '';
if ($classId !== '' && $grade !== '' && (string)($classById[$classId]['grade'] ?? '') !== $grade) $classId = '';
if (!isset($teams[$team])) $team = '';
$source = $population === 'teachers' ? $teachers : $students;
$rows = array_values(array_filter($source, function ($row) use ($population, $status, $grade, $classId, $team, $classById) {
    if ($status !== 'all' && (!empty($row['active'])) !== ($status === 'active')) return false;
    if ($population === 'teachers') return $team === '' || trim((string)($row['to_chuyen_mon'] ?? ($row['pccm_group'] ?? ''))) === $team;
    $id = (string)($row['class_id'] ?? '');
    if ($classId !== '' && $id !== $classId) return false;
    return $grade === '' || (string)($classById[$id]['grade'] ?? '') === $grade;
}));
$teacherFields = ['code'=>'Mã cán bộ','gender'=>'Giới tính','dob'=>'Ngày sinh','phone'=>'Số điện thoại','email'=>'Email','ethnicity'=>'Dân tộc','hometown'=>'Quê quán','address'=>'Địa chỉ','specialty'=>'Chuyên môn','to_chuyen_mon'=>'Tổ chuyên môn','chuc_vu'=>'Chức vụ','teaching_level'=>'Cấp giảng dạy','join_date'=>'Ngày vào ngành'];
$studentFields = ['code'=>'Mã học sinh','class_id'=>'Lớp','gender'=>'Giới tính','dob'=>'Ngày sinh','ethnicity'=>'Dân tộc','hometown'=>'Quê quán','address'=>'Địa chỉ','phone'=>'SĐT học sinh','parent_name'=>'Tên phụ huynh','parent_phone'=>'SĐT phụ huynh','room_ktx'=>'Phòng KTX','meal_group'=>'Nhóm ăn'];
$checks = $population === 'teachers' ? $teacherFields : $studentFields;
$valueOf = function ($row) use ($field, $population, $classById) {
    $class = $classById[(string)($row['class_id'] ?? '')] ?? [];
    switch ($field) {
        case 'gender': return csdl_stat_gender($row['gender'] ?? '');
        case 'team': return $row['to_chuyen_mon'] ?? ($row['pccm_group'] ?? '');
        case 'specialty': return $row['specialty'] ?? '';
        case 'position': return $row['chuc_vu'] ?? '';
        case 'level': return $row['teaching_level'] ?? '';
        case 'ethnicity': return $row['ethnicity'] ?? '';
        case 'class': return $class['name'] ?? 'Chưa xếp lớp';
        case 'grade': return !empty($class['grade']) ? 'Khối '.$class['grade'] : 'Chưa xếp khối';
        case 'boarder': return !empty($row['boarder']) ? 'Nội trú' : 'Không nội trú';
        case 'room': return $row['room_ktx'] ?? '';
        case 'meal': return $row['meal_group'] ?? '';
        case 'birth_year': $date = (string)($row['dob'] ?? ''); break;
        case 'join_year': $date = (string)($row['join_date'] ?? ''); break;
        case 'rank': return $row['hang'] ?? '';
        case 'grade_salary': return $row['bac'] ?? '';
        case 'concurrent': return $row['kiem_nhiem'] ?? '';
        default: return '';
    }
    if (preg_match('/^(\d{4})[-\/]/', $date, $matches)) return $matches[1];
    if (preg_match('/[-\/](\d{4})$/', $date, $matches)) return $matches[1];
    return '';
};
$groups = [];
if ($field === 'completeness') {
    foreach ($checks as $key => $label) {
        $filled = 0;
        foreach ($rows as $row) if (trim((string)($row[$key] ?? '')) !== '') $filled++;
        $groups[] = ['label'=>$label, 'total'=>$filled, 'missing'=>count($rows)-$filled];
    }
    usort($groups, fn($a,$b)=>$b['missing']<=>$a['missing']);
} else {
    foreach ($rows as $row) {
        $label = csdl_stat_label($valueOf($row));
        if (!isset($groups[$label])) $groups[$label] = ['label'=>$label,'total'=>0];
        $groups[$label]['total']++;
    }
    $groups = array_values($groups);
    usort($groups, fn($a,$b)=>$b['total']<=>$a['total'] ?: strnatcasecmp($a['label'],$b['label']));
}
?>
<style>
.stat-panel{border:1px solid #dce5ef;border-radius:16px;background:#fff;overflow:hidden}
.stat-panel-header{padding:1rem 1.15rem;border-bottom:1px solid #e5ebf2}
.stat-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;padding:1rem 1.15rem}
.stat-result{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin:1rem 0}
.stat-number{border:1px solid #dce5ef;border-radius:13px;background:#f8fbff;padding:.8rem 1rem}
.stat-number strong{display:block;font-size:1.55rem;color:#193c67}
.stat-table th{background:#f1f5fa;color:#36485e}
.stat-table td,.stat-table th{padding:.6rem .75rem;vertical-align:middle}
@media(max-width:767.98px){.stat-filter{grid-template-columns:repeat(2,minmax(0,1fr));padding:.8rem}.stat-result{gap:.5rem}.stat-number{padding:.65rem}.stat-table{font-size:.86rem}}
</style>
<section class="stat-panel">
 <div class="stat-panel-header"><h5 class="mb-1"><i class="bi bi-bar-chart-line text-primary"></i> Thống kê CSDL</h5><div class="text-muted small">Chọn một nội dung để xem tổng số và chi tiết theo đúng phạm vi lọc.</div></div>
 <form method="get" class="stat-filter" id="stat-filter"><input type="hidden" name="tab" value="statistics">
  <div><label class="form-label small fw-semibold" for="stat-scope">Đối tượng</label><select id="stat-scope" name="stat_scope" class="form-select"><option value="students" <?=$population==='students'?'selected':''?>>Học sinh</option><option value="teachers" <?=$population==='teachers'?'selected':''?>>Giáo viên / CBGVNV</option></select></div>
  <div><label class="form-label small fw-semibold" for="stat-field">Nội dung thống kê</label><select id="stat-field" name="stat_field" class="form-select"><?php foreach ($fields as $key=>$label): ?><option value="<?=e($key)?>" <?=$field===$key?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></div>
  <div><label class="form-label small fw-semibold" for="stat-status">Trạng thái</label><select id="stat-status" name="stat_status" class="form-select"><option value="active" <?=$status==='active'?'selected':''?>>Đang hoạt động</option><option value="inactive" <?=$status==='inactive'?'selected':''?>>Đã nghỉ / ngừng</option><option value="all" <?=$status==='all'?'selected':''?>>Tất cả hồ sơ</option></select></div>
  <?php if ($population === 'teachers'): ?><div><label class="form-label small fw-semibold" for="stat-team">Tổ chuyên môn</label><select id="stat-team" name="stat_team" class="form-select"><option value="">Tất cả tổ</option><?php foreach ($teams as $option): ?><option value="<?=e($option)?>" <?=$team===$option?'selected':''?>><?=e($option)?></option><?php endforeach; ?></select></div>
  <?php else: ?><div><label class="form-label small fw-semibold" for="stat-grade">Khối</label><select id="stat-grade" name="stat_grade" class="form-select"><option value="">Tất cả khối</option><?php foreach ($grades as $option): ?><option value="<?=e($option)?>" <?=$grade===$option?'selected':''?>>Khối <?=e($option)?></option><?php endforeach; ?></select></div>
  <div><label class="form-label small fw-semibold" for="stat-class">Lớp</label><select id="stat-class" name="stat_class" class="form-select"><option value="">Tất cả lớp</option><?php foreach ($classes as $class): ?><option value="<?=e($class['id']??'')?>" data-grade="<?=e($class['grade']??'')?>" <?=$classId===(string)($class['id']??'')?'selected':''?>><?=e($class['name']??'')?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="d-flex gap-2 align-items-end"><button class="btn btn-primary flex-grow-1" type="submit">Xem thống kê</button><a class="btn btn-outline-secondary" href="?tab=statistics" title="Đặt lại bộ lọc" aria-label="Đặt lại bộ lọc"><i class="bi bi-arrow-counterclockwise"></i></a></div>
 </form>
</section>
<div class="stat-result"><div class="stat-number"><span class="text-muted small">Tổng <?= $population==='teachers'?'giáo viên / CBGVNV':'học sinh' ?> trong phạm vi</span><strong><?=count($rows)?></strong></div><div class="stat-number"><span class="text-muted small"><?= $field==='completeness'?'Trường thông tin được kiểm tra':'Nhóm '.$fields[$field] ?></span><strong><?=count($groups)?></strong></div></div>
<section class="stat-panel mb-3"><div class="stat-panel-header"><h6 class="mb-0">Chi tiết theo <?=e($fields[$field])?></h6></div><div class="table-responsive"><table class="table table-hover stat-table mb-0"><thead><tr><th scope="col">#</th><th scope="col"><?=e($field==='completeness'?'Thông tin':$fields[$field])?></th><th scope="col" class="text-end"><?= $field==='completeness'?'Đã có':'Số lượng' ?></th><?php if ($field==='completeness'): ?><th scope="col" class="text-end">Còn thiếu</th><?php endif; ?><th scope="col" class="text-end">Tỷ lệ</th></tr></thead><tbody><?php if (!$groups): ?><tr><td colspan="<?= $field==='completeness'?5:4 ?>" class="text-center text-muted py-4">Không có dữ liệu phù hợp bộ lọc.</td></tr><?php endif; ?><?php foreach ($groups as $index=>$group): ?><tr><td><?= $index+1 ?></td><td><?=e($group['label'])?></td><td class="text-end fw-semibold"><?=$group['total']?></td><?php if ($field==='completeness'): ?><td class="text-end"><?=$group['missing']?></td><?php endif; ?><td class="text-end"><?=count($rows)?number_format($group['total']*100/count($rows),1,',','.'):0?>%</td></tr><?php endforeach; ?></tbody></table></div></section>
<script>
(() => {
 const scope = document.getElementById('stat-scope'), field = document.getElementById('stat-field');
 const options = {
  students: <?=json_encode(['class'=>'Lớp','grade'=>'Khối','gender'=>'Giới tính','ethnicity'=>'Dân tộc','boarder'=>'Nội trú','room'=>'Phòng KTX','meal'=>'Nhóm ăn','birth_year'=>'Năm sinh','completeness'=>'Mức độ đầy đủ hồ sơ'], JSON_UNESCAPED_UNICODE)?>,
  teachers: <?=json_encode(['gender'=>'Giới tính','team'=>'Tổ chuyên môn','specialty'=>'Chuyên môn','position'=>'Chức vụ','level'=>'Cấp giảng dạy','ethnicity'=>'Dân tộc','birth_year'=>'Năm sinh','join_year'=>'Năm vào ngành','rank'=>'Hạng','grade_salary'=>'Bậc lương','concurrent'=>'Kiêm nhiệm','completeness'=>'Mức độ đầy đủ hồ sơ'], JSON_UNESCAPED_UNICODE)?>
 };
 scope.addEventListener('change', () => { field.replaceChildren(); for (const [key,label] of Object.entries(options[scope.value])) field.add(new Option(label,key)); document.getElementById('stat-filter').requestSubmit(); });
 const grade = document.getElementById('stat-grade'), classes = document.getElementById('stat-class');
 if (grade && classes) { const syncClasses = () => { for (const option of classes.options) option.hidden = !!grade.value && !!option.value && option.dataset.grade !== grade.value; if (classes.selectedOptions[0]?.hidden) classes.value = ''; }; grade.addEventListener('change', syncClasses); syncClasses(); }
})();
</script>
