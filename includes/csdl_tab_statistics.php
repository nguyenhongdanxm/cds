<?php
/**
 * Thống kê toàn diện giáo viên và học sinh từ nguồn chuẩn CSDL.
 * Biến đầu vào: $teachers, $classes, $students, $stats.
 */

if (!function_exists('csdl_stat_lower')) {
    function csdl_stat_lower($value) {
        $value = trim((string)$value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
if (!function_exists('csdl_stat_upper')) {
    function csdl_stat_upper($value) {
        $value = trim((string)$value);
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }
}
if (!function_exists('csdl_stat_clean')) {
    function csdl_stat_clean($value, $empty = 'Chưa cập nhật') {
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));
        return $value !== '' ? $value : $empty;
    }
}
if (!function_exists('csdl_stat_gender')) {
    function csdl_stat_gender($value) {
        $raw = trim((string)$value);
        $key = csdl_stat_lower($raw);
        if (in_array($key, ['nam','m','male'], true)) return 'Nam';
        if (in_array($key, ['nữ','nu','f','female'], true)) return 'Nữ';
        return $raw !== '' ? 'Khác' : 'Chưa cập nhật';
    }
}
if (!function_exists('csdl_stat_percent')) {
    function csdl_stat_percent($value, $total) {
        return $total > 0 ? number_format($value * 100 / $total, 1, ',', '.') . '%' : '0%';
    }
}
if (!function_exists('csdl_stat_aggregate')) {
    function csdl_stat_aggregate(array $rows, callable $resolver) {
        $out = [];
        foreach ($rows as $row) {
            $label = trim((string)$resolver($row));
            if ($label === '') $label = 'Chưa cập nhật';
            $key = csdl_stat_lower($label);
            if (!isset($out[$key])) {
                $out[$key] = ['label'=>$label,'total'=>0,'Nam'=>0,'Nữ'=>0,'Khác'=>0,'Chưa cập nhật'=>0];
            }
            $gender = csdl_stat_gender($row['gender'] ?? '');
            $out[$key]['total']++;
            if (!isset($out[$key][$gender])) $out[$key][$gender] = 0;
            $out[$key][$gender]++;
        }
        $out = array_values($out);
        usort($out, function($a,$b){
            if ($a['label']==='Chưa cập nhật' && $b['label']==='Chưa cập nhật') return 0;
            if ($a['label']==='Chưa cập nhật') return 1;
            if ($b['label']==='Chưa cập nhật') return -1;
            return $b['total'] <=> $a['total'] ?: strnatcasecmp($a['label'],$b['label']);
        });
        return $out;
    }
}
if (!function_exists('csdl_stat_completeness')) {
    function csdl_stat_completeness(array $rows, array $fields) {
        $result = [];
        foreach ($fields as $field => $label) {
            $filled = 0;
            foreach ($rows as $row) {
                $value = $row[$field] ?? null;
                if (is_array($value)) {
                    if (!empty($value)) $filled++;
                } elseif (is_bool($value)) {
                    $filled++;
                } elseif (trim((string)$value) !== '') {
                    $filled++;
                }
            }
            $result[] = ['label'=>$label,'filled'=>$filled,'missing'=>max(0,count($rows)-$filled),'total'=>count($rows)];
        }
        return $result;
    }
}

$statStatus = (string)($_GET['stat_status'] ?? 'active');
$statScope = (string)($_GET['stat_scope'] ?? 'all');
$statGrade = trim((string)($_GET['stat_grade'] ?? ''));
$statClass = trim((string)($_GET['stat_class'] ?? ''));
$statTeam = trim((string)($_GET['stat_team'] ?? ''));
if (!in_array($statStatus,['active','inactive','all'],true)) $statStatus='active';
if (!in_array($statScope,['all','teachers','students'],true)) $statScope='all';

$filterStatus = function(array $rows) use ($statStatus) {
    if ($statStatus==='all') return array_values($rows);
    $want = $statStatus==='active';
    return array_values(array_filter($rows,function($row) use ($want){ return !empty($row['active']) === $want; }));
};

$classById=[];
foreach ($classes as $classRow) $classById[(string)($classRow['id']??'')]=$classRow;

$teamOptions=[];
foreach ($teachers as $row) {
    $team=trim((string)($row['to_chuyen_mon'] ?? ($row['pccm_group'] ?? '')));
    if ($team!=='') $teamOptions[$team]=$team;
}
natcasesort($teamOptions);

$gradeOptions=[];
foreach ($classes as $row) {
    $g=(int)($row['grade']??0);
    if ($g>0) $gradeOptions[$g]=$g;
}
ksort($gradeOptions);

$statTeachers=$filterStatus($teachers);
$statStudents=$filterStatus($students);
if ($statTeam!=='') {
    $statTeachers=array_values(array_filter($statTeachers,function($row) use ($statTeam){
        return trim((string)($row['to_chuyen_mon'] ?? ($row['pccm_group'] ?? ''))) === $statTeam;
    }));
}
if ($statGrade!=='') {
    $statStudents=array_values(array_filter($statStudents,function($row) use ($classById,$statGrade){
        $class=$classById[(string)($row['class_id']??'')]??[];
        return (string)($class['grade']??'') === $statGrade;
    }));
}
if ($statClass!=='') {
    $statStudents=array_values(array_filter($statStudents,function($row) use ($statClass){ return (string)($row['class_id']??'') === $statClass; }));
}

$genderSummary=function(array $rows){
    $out=['Nam'=>0,'Nữ'=>0,'Khác'=>0,'Chưa cập nhật'=>0];
    foreach($rows as $row){$g=csdl_stat_gender($row['gender']??''); if(!isset($out[$g]))$out[$g]=0; $out[$g]++;}
    return $out;
};
$teacherGender=$genderSummary($statTeachers);
$studentGender=$genderSummary($statStudents);

$teacherGroups=[
    'Tổ chuyên môn'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['to_chuyen_mon']??($r['pccm_group']??''),'Chưa xếp tổ');}),
    'Chuyên môn'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['specialty']??'');}),
    'Chức vụ'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['chuc_vu']??'','Không ghi chức vụ');}),
    'Cấp giảng dạy'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['teaching_level']??'');}),
    'Dân tộc'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['ethnicity']??'');}),
    'Hạng'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['hang']??'');}),
    'Bậc'=>csdl_stat_aggregate($statTeachers,function($r){return csdl_stat_clean($r['bac']??'');}),
];

$teacherKiemNhiem=[];
foreach($statTeachers as $row){
    foreach((array)($row['kiem_nhiem']??[]) as $item){
        $role=csdl_stat_clean(is_array($item)?($item['role']??''):$item,'Chưa cập nhật');
        $key=csdl_stat_lower($role);
        if(!isset($teacherKiemNhiem[$key]))$teacherKiemNhiem[$key]=['label'=>$role,'total'=>0];
        $teacherKiemNhiem[$key]['total']++;
    }
}
$teacherKiemNhiem=array_values($teacherKiemNhiem);
usort($teacherKiemNhiem,function($a,$b){return $b['total']<=>$a['total'] ?: strnatcasecmp($a['label'],$b['label']);});

$studentGroups=[
    'Khối'=>csdl_stat_aggregate($statStudents,function($r) use($classById){$c=$classById[(string)($r['class_id']??'')]??[];$g=(int)($c['grade']??0);return $g>0?'Khối '.$g:'Chưa xếp khối';}),
    'Lớp'=>csdl_stat_aggregate($statStudents,function($r) use($classById){return csdl_stat_clean($classById[(string)($r['class_id']??'')]['name']??'','Chưa xếp lớp');}),
    'Dân tộc'=>csdl_stat_aggregate($statStudents,function($r){return csdl_stat_clean($r['ethnicity']??'');}),
    'Nội trú'=>csdl_stat_aggregate($statStudents,function($r){return !empty($r['boarder'])?'Nội trú':'Không nội trú';}),
    'Phòng KTX'=>csdl_stat_aggregate($statStudents,function($r){return csdl_stat_clean($r['room_ktx']??'','Chưa xếp phòng');}),
    'Nhóm ăn'=>csdl_stat_aggregate($statStudents,function($r){return csdl_stat_clean($r['meal_group']??'','Chưa xếp nhóm ăn');}),
    'Quê quán'=>csdl_stat_aggregate($statStudents,function($r){return csdl_stat_clean($r['hometown']??'');}),
];

$teacherFields=[
    'code'=>'Mã cán bộ','cccd'=>'CCCD','gender'=>'Giới tính','dob'=>'Ngày sinh','phone'=>'Số điện thoại','email'=>'Email',
    'ethnicity'=>'Dân tộc','hometown'=>'Quê quán','address'=>'Địa chỉ','specialty'=>'Chuyên môn','to_chuyen_mon'=>'Tổ chuyên môn',
    'chuc_vu'=>'Chức vụ','teaching_level'=>'Cấp giảng dạy','join_date'=>'Ngày vào ngành','hang'=>'Hạng','bac'=>'Bậc','cap_luong'=>'Cấp lương',
    'he_so'=>'Hệ số lương','he_so_from'=>'Ngày hưởng hệ số','kiem_nhiem'=>'Kiêm nhiệm','note'=>'Ghi chú'
];
$studentFields=[
    'code'=>'Mã học sinh','cccd'=>'CCCD','class_id'=>'Lớp','gender'=>'Giới tính','dob'=>'Ngày sinh','ethnicity'=>'Dân tộc','hometown'=>'Quê quán',
    'address'=>'Địa chỉ','phone'=>'SĐT học sinh','parent_name'=>'Tên phụ huynh','parent_phone'=>'SĐT phụ huynh','room_ktx'=>'Phòng KTX',
    'meal_group'=>'Nhóm ăn','note'=>'Ghi chú'
];
$teacherCompleteness=csdl_stat_completeness($statTeachers,$teacherFields);
$studentCompleteness=csdl_stat_completeness($statStudents,$studentFields);

$classRows=[];
foreach($classes as $class){
    $classId=(string)($class['id']??'');
    if($statGrade!=='' && (string)($class['grade']??'')!==$statGrade)continue;
    if($statClass!=='' && $classId!==$statClass)continue;
    $rows=array_values(array_filter($statStudents,function($s) use($classId){return (string)($s['class_id']??'')===$classId;}));
    $g=$genderSummary($rows);
    $boarders=0;$withParent=0;$withParentPhone=0;$withStudentPhone=0;$eth=[];
    foreach($rows as $s){
        if(!empty($s['boarder']))$boarders++;
        if(trim((string)($s['parent_name']??''))!=='')$withParent++;
        if(trim((string)($s['parent_phone']??''))!=='')$withParentPhone++;
        if(trim((string)($s['phone']??''))!=='')$withStudentPhone++;
        $e=csdl_stat_clean($s['ethnicity']??'');$eth[csdl_stat_lower($e)]=$e;
    }
    $classRows[]=[
        'name'=>(string)($class['name']??''),'grade'=>(int)($class['grade']??0),'total'=>count($rows),
        'male'=>$g['Nam'],'female'=>$g['Nữ'],'other'=>$g['Khác']+$g['Chưa cập nhật'],'boarder'=>$boarders,
        'ethnicity'=>count($eth),'parent'=>$withParent,'parent_phone'=>$withParentPhone,'student_phone'=>$withStudentPhone,
        'homeroom'=>teacher_name_by_id($class['homeroom_teacher_id']??'',$teachers),
    ];
}
usort($classRows,function($a,$b){return $a['grade']<=>$b['grade'] ?: strnatcasecmp($a['name'],$b['name']);});

$statusLabel=['active'=>'Đang học / đang công tác','inactive'=>'Đã nghỉ / ngừng hoạt động','all'=>'Tất cả hồ sơ'][$statStatus];
$showTeachers=$statScope!=='students';
$showStudents=$statScope!=='teachers';
?>

<div class="card card-soft mb-3">
  <div class="card-body py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h5 class="mb-1"><i class="bi bi-bar-chart-line text-primary"></i> Thống kê dữ liệu giáo viên & học sinh</h5>
        <div class="stat-note">Tra cứu linh hoạt từ toàn trường đến tổ, khối và từng lớp. Các nhóm chi tiết được thu gọn để không dàn trải.</div>
      </div>
      <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= e($statusLabel) ?></span>
    </div>
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="statistics">
      <div class="col-6 col-lg-2"><label class="form-label small mb-1">Đối tượng</label><select class="form-select form-select-sm" name="stat_scope"><option value="all" <?=$statScope==='all'?'selected':''?>>GV & HS</option><option value="teachers" <?=$statScope==='teachers'?'selected':''?>>Giáo viên</option><option value="students" <?=$statScope==='students'?'selected':''?>>Học sinh</option></select></div>
      <div class="col-6 col-lg-2"><label class="form-label small mb-1">Trạng thái</label><select class="form-select form-select-sm" name="stat_status"><option value="active" <?=$statStatus==='active'?'selected':''?>>Đang hoạt động</option><option value="inactive" <?=$statStatus==='inactive'?'selected':''?>>Đã nghỉ</option><option value="all" <?=$statStatus==='all'?'selected':''?>>Tất cả</option></select></div>
      <div class="col-6 col-lg-2"><label class="form-label small mb-1">Tổ GV</label><select class="form-select form-select-sm" name="stat_team"><option value="">Tất cả tổ</option><?php foreach($teamOptions as $team):?><option value="<?=e($team)?>" <?=$statTeam===$team?'selected':''?>><?=e($team)?></option><?php endforeach;?></select></div>
      <div class="col-6 col-lg-2"><label class="form-label small mb-1">Khối HS</label><select class="form-select form-select-sm" name="stat_grade"><option value="">Tất cả khối</option><?php foreach($gradeOptions as $grade):?><option value="<?=$grade?>" <?=$statGrade===(string)$grade?'selected':''?>>Khối <?=$grade?></option><?php endforeach;?></select></div>
      <div class="col-8 col-lg-2"><label class="form-label small mb-1">Lớp HS</label><select class="form-select form-select-sm" name="stat_class"><option value="">Tất cả lớp</option><?php foreach($classes as $c):?><option value="<?=e($c['id']??'')?>" <?=$statClass===(string)($c['id']??'')?'selected':''?>><?=e($c['name']??'')?></option><?php endforeach;?></select></div>
      <div class="col-4 col-lg-2 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Lọc</button><a class="btn btn-outline-secondary btn-sm" href="?tab=statistics" title="Đặt lại"><i class="bi bi-arrow-counterclockwise"></i></a></div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <?php if($showTeachers):?>
  <div class="col-6 col-xl-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-person-badge"></i></span><strong><?=number_format(count($statTeachers),0,',','.')?></strong><small>Giáo viên / CBGVNV</small></div></div>
  <div class="col-6 col-xl-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-gender-ambiguous"></i></span><strong><?=number_format($teacherGender['Nam'],0,',','.')?> / <?=number_format($teacherGender['Nữ'],0,',','.')?></strong><small>Nam / Nữ giáo viên</small></div></div>
  <?php endif;?>
  <?php if($showStudents):?>
  <div class="col-6 col-xl-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-mortarboard"></i></span><strong><?=number_format(count($statStudents),0,',','.')?></strong><small>Học sinh</small></div></div>
  <div class="col-6 col-xl-3"><div class="stat-kpi"><span class="stat-kpi-icon"><i class="bi bi-gender-ambiguous"></i></span><strong><?=number_format($studentGender['Nam'],0,',','.')?> / <?=number_format($studentGender['Nữ'],0,',','.')?></strong><small>Nam / Nữ học sinh</small></div></div>
  <?php endif;?>
</div>

<div class="accordion" id="statisticsAccordion">
<?php if($showTeachers):?>
  <div class="accordion-item border-0 card-soft mb-3 overflow-hidden">
    <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#statTeacherStructure"><strong><i class="bi bi-person-lines-fill me-2 text-primary"></i>Cơ cấu giáo viên / CBGVNV</strong></button></h2>
    <div id="statTeacherStructure" class="accordion-collapse collapse show" data-bs-parent="#statisticsAccordion"><div class="accordion-body">
      <div class="row g-3">
      <?php foreach($teacherGroups as $title=>$rows):?>
        <div class="col-12 col-xl-6"><div class="border rounded-3 p-2 h-100"><div class="fw-semibold small mb-2"><?=e($title)?> <span class="badge text-bg-light border"><?=count($rows)?> nhóm</span></div><div class="table-responsive"><table class="table table-sm stat-table mb-0"><thead><tr><th>Nhóm</th><th>Tổng</th><th>Nam</th><th>Nữ</th><th>Tỷ lệ</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td class="stat-label"><?=e($r['label'])?></td><td><strong><?=$r['total']?></strong></td><td><?=$r['Nam']?></td><td><?=$r['Nữ']?></td><td><?=csdl_stat_percent($r['total'],count($statTeachers))?></td></tr><?php endforeach;?></tbody></table></div></div></div>
      <?php endforeach;?>
      <?php if($teacherKiemNhiem):?><div class="col-12"><div class="border rounded-3 p-2"><div class="fw-semibold small mb-2">Kiêm nhiệm</div><div class="d-flex flex-wrap gap-2"><?php foreach($teacherKiemNhiem as $r):?><span class="badge rounded-pill text-bg-light border px-3 py-2"><?=e($r['label'])?> <strong><?=$r['total']?></strong></span><?php endforeach;?></div></div></div><?php endif;?>
      </div>
    </div></div>
  </div>

  <div class="accordion-item border-0 card-soft mb-3 overflow-hidden">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#statTeacherData"><strong><i class="bi bi-clipboard-data me-2 text-primary"></i>Mức độ đầy đủ dữ liệu giáo viên</strong></button></h2>
    <div id="statTeacherData" class="accordion-collapse collapse" data-bs-parent="#statisticsAccordion"><div class="accordion-body"><div class="table-responsive"><table class="table table-sm stat-table mb-0"><thead><tr><th>Trường dữ liệu</th><th>Đã có</th><th>Còn thiếu</th><th>Hoàn thiện</th></tr></thead><tbody><?php foreach($teacherCompleteness as $r):?><tr><td class="stat-label"><?=e($r['label'])?></td><td><?=$r['filled']?></td><td class="<?=$r['missing']?'text-danger fw-semibold':''?>"><?=$r['missing']?></td><td style="min-width:150px"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:7px"><div class="progress-bar" style="width:<?=csdl_stat_percent($r['filled'],$r['total'])?>"></div></div><small><?=csdl_stat_percent($r['filled'],$r['total'])?></small></div></td></tr><?php endforeach;?></tbody></table></div></div></div>
  </div>
<?php endif;?>

<?php if($showStudents):?>
  <div class="accordion-item border-0 card-soft mb-3 overflow-hidden">
    <h2 class="accordion-header"><button class="accordion-button <?=$showTeachers?'collapsed':''?>" type="button" data-bs-toggle="collapse" data-bs-target="#statStudentStructure"><strong><i class="bi bi-people-fill me-2 text-primary"></i>Cơ cấu học sinh</strong></button></h2>
    <div id="statStudentStructure" class="accordion-collapse collapse <?=$showTeachers?'':'show'?>" data-bs-parent="#statisticsAccordion"><div class="accordion-body"><div class="row g-3">
      <?php foreach($studentGroups as $title=>$rows):?><div class="col-12 col-xl-6"><div class="border rounded-3 p-2 h-100"><div class="fw-semibold small mb-2"><?=e($title)?> <span class="badge text-bg-light border"><?=count($rows)?> nhóm</span></div><div class="table-responsive"><table class="table table-sm stat-table mb-0"><thead><tr><th>Nhóm</th><th>Tổng</th><th>Nam</th><th>Nữ</th><th>Tỷ lệ</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td class="stat-label"><?=e($r['label'])?></td><td><strong><?=$r['total']?></strong></td><td><?=$r['Nam']?></td><td><?=$r['Nữ']?></td><td><?=csdl_stat_percent($r['total'],count($statStudents))?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endforeach;?>
    </div></div></div>
  </div>

  <div class="accordion-item border-0 card-soft mb-3 overflow-hidden">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#statClassDetail"><strong><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Chi tiết học sinh đến từng lớp</strong><span class="badge text-bg-primary ms-2"><?=count($classRows)?> lớp</span></button></h2>
    <div id="statClassDetail" class="accordion-collapse collapse" data-bs-parent="#statisticsAccordion"><div class="accordion-body p-0"><div class="table-responsive"><table class="table table-hover table-sm stat-table mb-0"><thead><tr><th>Khối</th><th>Lớp</th><th>GVCN</th><th>Tổng</th><th>Nam</th><th>Nữ</th><th>Nội trú</th><th>Số dân tộc</th><th>Có PH</th><th>SĐT PH</th><th>SĐT HS</th></tr></thead><tbody><?php foreach($classRows as $r):?><tr><td><?=$r['grade']?></td><td class="fw-bold"><?=e($r['name'])?></td><td class="small"><?=e($r['homeroom'])?></td><td><strong><?=$r['total']?></strong></td><td><?=$r['male']?></td><td><?=$r['female']?></td><td><?=$r['boarder']?></td><td><?=$r['ethnicity']?></td><td><?=$r['parent']?></td><td><?=$r['parent_phone']?></td><td><?=$r['student_phone']?></td></tr><?php endforeach;?></tbody></table></div></div></div>
  </div>

  <div class="accordion-item border-0 card-soft mb-3 overflow-hidden">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#statStudentData"><strong><i class="bi bi-clipboard-data me-2 text-primary"></i>Mức độ đầy đủ dữ liệu học sinh</strong></button></h2>
    <div id="statStudentData" class="accordion-collapse collapse" data-bs-parent="#statisticsAccordion"><div class="accordion-body"><div class="table-responsive"><table class="table table-sm stat-table mb-0"><thead><tr><th>Trường dữ liệu</th><th>Đã có</th><th>Còn thiếu</th><th>Hoàn thiện</th></tr></thead><tbody><?php foreach($studentCompleteness as $r):?><tr><td class="stat-label"><?=e($r['label'])?></td><td><?=$r['filled']?></td><td class="<?=$r['missing']?'text-danger fw-semibold':''?>"><?=$r['missing']?></td><td style="min-width:150px"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:7px"><div class="progress-bar" style="width:<?=csdl_stat_percent($r['filled'],$r['total'])?>"></div></div><small><?=csdl_stat_percent($r['filled'],$r['total'])?></small></div></td></tr><?php endforeach;?></tbody></table></div></div></div>
  </div>
<?php endif;?>
</div>

<div class="alert alert-light border mt-3 mb-0 small text-muted"><i class="bi bi-info-circle"></i> Các số liệu được tính trực tiếp từ hồ sơ hiện có trong CSDL. Mục <strong>Còn thiếu</strong> giúp rà nhanh trường dữ liệu cần bổ sung; bộ lọc phía trên cho phép thu hẹp đến tổ, khối hoặc từng lớp.</div>
