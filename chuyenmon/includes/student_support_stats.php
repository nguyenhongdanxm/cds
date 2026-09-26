<?php
/** Thống kê bồi dưỡng theo danh sách học sinh đang hoạt động và đăng ký từng môn. */
$statGrade = static function (string $class): string {
    return preg_match('/^(?:Lớp\s*)?(12|11|10|9|8|7|6)(?=\D|$)/iu', trim($class), $m) ? $m[1] : '';
};
$grades = []; $classesByGrade = []; $studentGrades = []; $denominators = [];
foreach ($students as $id => $student) {
    $grade = $statGrade($student['class']);
    $studentGrades[$id] = $grade;
    if ($grade !== '') $grades[$grade] = true;
    $classesByGrade[$grade][$student['class']] = true;
    $denominators[$grade][$student['class']][$id] = true;
}
ksort($grades, SORT_NUMERIC);
$categoryFilter = (string)($_GET['stats_category'] ?? 'tn');
if (!in_array($categoryFilter, ['tn','ts','muinhon','chuadat'], true)) $categoryFilter = 'tn';
$gradeFilter = (string)($_GET['stats_grade'] ?? '');
if ($gradeFilter !== '' && !isset($grades[$gradeFilter])) $gradeFilter = '';
$classFilter = (string)($_GET['stats_class'] ?? '');
if ($classFilter !== '' && !isset($classesByGrade[$statGrade($classFilter)][$classFilter])) $classFilter = '';
if ($gradeFilter !== '' && $classFilter !== '' && $statGrade($classFilter) !== $gradeFilter) $classFilter = '';
$subjectFilter = (string)($_GET['stats_subject'] ?? '');
$statSubjects = array_values(array_unique(array_merge(
    in_array($categoryFilter,['tn','ts'],true) ? $settings[$categoryFilter] : $available,
    array_keys((array)($members[$categoryFilter] ?? []))
)));
sort($statSubjects, SORT_NATURAL | SORT_FLAG_CASE);
if ($subjectFilter !== '' && !in_array($subjectFilter, $statSubjects, true)) $subjectFilter = '';
$statsView=(string)($_GET['stats_view']??'groups'); if(!in_array($statsView,['groups','overlap'],true)) $statsView='groups';
$statParams = ['stats_view'=>$statsView,'tab'=>'thongke','year'=>$year,'stats_category'=>$categoryFilter,'stats_grade'=>$gradeFilter,'stats_class'=>$classFilter,'stats_subject'=>$subjectFilter];
$statUrl = static function (array $extra = []) use ($statParams): string {
    return BASE_URL . 'boiduong.php?' . http_build_query(array_merge($statParams, $extra));
};
$inScope = static function (string $id) use ($students, $studentGrades, $gradeFilter, $classFilter): bool {
    return isset($students[$id]) && ($gradeFilter === '' || $studentGrades[$id] === $gradeFilter)
        && ($classFilter === '' || $students[$id]['class'] === $classFilter);
};
$baseCount = static function (string $grade, string $class = '') use ($denominators): int {
    if ($class !== '') return count($denominators[$grade][$class] ?? []);
    $ids = []; foreach (($denominators[$grade] ?? []) as $group) foreach ($group as $id => $_) $ids[$id] = true;
    return count($ids);
};
$summary = []; $examByStudent = ['tn'=>[], 'ts'=>[]];
foreach ($members as $category => $bySubject) {
    if (!isset($tabs[$category]) || !in_array($category, ['tn','ts','muinhon','chuadat'], true)) continue;
    foreach ($bySubject as $subject => $registered) foreach ($registered as $id => $entry) {
        if (!$inScope((string)$id)) continue;
        $student = $students[$id]; $grade = $studentGrades[$id];
        if ($categoryFilter === $category && in_array($category, ['tn','ts'], true)) {
            $examByStudent[$category][$id][$subject] = (string)($entry['group'] ?? '');
        }
        if ($categoryFilter !== $category) continue;
        if ($subjectFilter !== '' && $subjectFilter !== $subject) continue;
        // Một học sinh chỉ được đếm một lần cho từng môn và phạm vi.
        $summary[$category][$subject][$grade][$student['class']][$id] = $entry;
    }
}
// Hiện cả 0% khi chọn môn/phạm vi nhưng chưa có học sinh mũi nhọn hoặc chưa đạt.
$analysisSubjects = $subjectFilter !== '' ? [$subjectFilter] : array_keys((array)($members[$categoryFilter] ?? []));
foreach (['muinhon','chuadat'] as $category) {
    if ($categoryFilter !== $category) continue;
    foreach ($analysisSubjects as $subject) foreach ($denominators as $grade => $byClass) {
        if ($gradeFilter !== '' && $gradeFilter !== (string)$grade) continue;
        foreach ($byClass as $class => $_) {
            if ($classFilter !== '' && $classFilter !== $class) continue;
            if (!isset($summary[$category][$subject][$grade][$class])) $summary[$category][$subject][$grade][$class] = [];
        }
    }
}
$summaryRows = [];
foreach ($summary as $category => $bySubject) foreach ($bySubject as $subject => $byGrade) foreach ($byGrade as $grade => $byClass) {
    $gradeIds = [];
    foreach ($byClass as $class => $ids) {
        foreach ($ids as $id => $_) $gradeIds[$id] = true;
        $summaryRows[] = ['category'=>$category,'subject'=>$subject,'grade'=>$grade,'class'=>$class,'ids'=>$ids];
    }
    if ($classFilter === '') $summaryRows[] = ['category'=>$category,'subject'=>$subject,'grade'=>$grade,'class'=>'','ids'=>$gradeIds];
}
usort($summaryRows, static function ($a,$b) {
    return strnatcasecmp($a['category'].'|'.$a['subject'].'|'.$a['grade'].'|'.$a['class'], $b['category'].'|'.$b['subject'].'|'.$b['grade'].'|'.$b['class']);
});
$examGroups = [];
foreach ($examByStudent as $category => $byStudent) {
    if ($categoryFilter !== $category) continue;
    foreach ($byStudent as $id => $subjectGroups) foreach ($subjectGroups as $subject => $group) {
        if ($subjectFilter !== '' && $subjectFilter !== $subject) continue;
        $group = $group ?: 'Chưa xếp';
        $key = json_encode([$category,$subject,$group],JSON_UNESCAPED_UNICODE);
        $examGroups[$key][$id] = true;
    }
}
ksort($examGroups);
// Mỗi dòng biểu thị cặp môn và nhóm cụ thể cùng chứa học sinh; đó là ràng buộc giờ học.
$overlaps = [];
foreach ($examByStudent as $category => $byStudent) {
    if ($categoryFilter !== $category) continue;
    foreach ($byStudent as $id => $subjectGroups) {
        ksort($subjectGroups, SORT_NATURAL | SORT_FLAG_CASE);
        $subjects = array_keys($subjectGroups);
        for ($i=0; $i<count($subjects); $i++) for ($j=$i+1; $j<count($subjects); $j++) {
            $a=$subjects[$i]; $b=$subjects[$j];
            if ($subjectFilter !== '' && $subjectFilter !== $a && $subjectFilter !== $b) continue;
            $ga=$subjectGroups[$a] ?: 'Chưa xếp'; $gb=$subjectGroups[$b] ?: 'Chưa xếp';
            $key=json_encode([$category,$a,$ga,$b,$gb], JSON_UNESCAPED_UNICODE);
            $overlaps[$key][$id] = true;
        }
    }
}
uasort($overlaps, static fn($a,$b) => count($b) <=> count($a));
$detailMode = (string)($_GET['detail_mode'] ?? '');
$targetSubject='';
$detailIds = []; $detailTitle = '';
if ($detailMode === 'summary') {
    $targetCat=(string)($_GET['detail_category'] ?? ''); $targetSubject=(string)($_GET['detail_subject'] ?? '');
    $targetGrade=(string)($_GET['detail_grade'] ?? ''); $targetClass=(string)($_GET['detail_class'] ?? '');
    foreach ($summaryRows as $row) if ($row['category']===$targetCat && $row['subject']===$targetSubject && $row['grade']===$targetGrade && $row['class']===$targetClass) {
        $detailIds=array_keys($row['ids']); $detailTitle=($tabs[$targetCat] ?? '').' · '.$targetSubject.' · Khối '.$targetGrade.($targetClass!==''?' · '.$targetClass:' · Toàn khối'); break;
    }
} elseif ($detailMode === 'exam_group') {
    $key=(string)($_GET['group_key'] ?? '');
    if (isset($examGroups[$key])) {
        $detailIds=array_keys($examGroups[$key]); $parts=json_decode($key,true);
        $detailTitle=($tabs[$parts[0]] ?? '').' · '.$parts[1].' · '.$parts[2];
    }
} elseif ($detailMode === 'overlap') {
    $key=(string)($_GET['overlap_key'] ?? '');
    if (isset($overlaps[$key])) {
        $detailIds=array_keys($overlaps[$key]); $parts=json_decode($key,true);
        $detailTitle=($tabs[$parts[0]] ?? '').' · '.$parts[1].' ('.$parts[2].') ∩ '.$parts[3].' ('.$parts[4].')';
    }
}
usort($detailIds, static fn($a,$b)=>strnatcasecmp($students[$a]['class'].'|'.$students[$a]['name'], $students[$b]['class'].'|'.$students[$b]['name']));
?>
<form method="get" action="<?=BASE_URL?>boiduong.php" class="card mb-3"><div class="card-body row g-2 align-items-end">
<input type="hidden" name="tab" value="thongke">
<div class="col-md-2"><label class="form-label">Năm học</label><input class="form-control" name="year" value="<?=e($year)?>" pattern="[0-9]{4}-[0-9]{4}"></div>
<div class="col-md-2"><label class="form-label">Loại nội dung</label><select class="form-select" name="stats_category" onchange="this.form.stats_subject.value='';this.form.submit()"><?php foreach(['tn','ts','muinhon','chuadat'] as $key):?><option value="<?=$key?>" <?=$categoryFilter===$key?'selected':''?>><?=e($tabs[$key])?></option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label">Khối</label><select class="form-select" name="stats_grade"><option value="">Tất cả khối</option><?php foreach(array_keys($grades) as $grade):?><option value="<?=e($grade)?>" <?=$gradeFilter===$grade?'selected':''?>>Khối <?=e($grade)?></option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label">Lớp</label><select class="form-select" name="stats_class"><option value="">Tất cả lớp</option><?php foreach($classesByGrade as $grade=>$names):$list=array_keys($names);sort($list,SORT_NATURAL);foreach($list as $class):?><option value="<?=e($class)?>" data-grade="<?=e($grade)?>" <?=$classFilter===$class?'selected':''?>><?=e($class)?></option><?php endforeach;endforeach;?></select></div>
<div class="col-md-2"><label class="form-label">Môn</label><select class="form-select" name="stats_subject"><option value="">Tất cả môn</option><?php foreach($statSubjects as $subject):?><option value="<?=e($subject)?>" <?=$subjectFilter===$subject?'selected':''?>><?=e($subject)?></option><?php endforeach;?></select></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Lọc thống kê</button></div>
</div></form>
<?php if(in_array($categoryFilter,['muinhon','chuadat'],true)):?><div class="card mb-3"><div class="card-body"><h5>Tỷ lệ <?=e(mb_strtolower($tabs[$categoryFilter],'UTF-8'))?> theo môn, khối, lớp</h5><p class="small text-muted">Tỷ lệ = số học sinh được chọn ở môn và phạm vi tương ứng ÷ số học sinh đang hoạt động trong lớp hoặc khối. Mỗi học sinh được đếm một lần cho mỗi môn.</p>
<div class="table-responsive"><table class="table table-striped table-sm align-middle"><thead><tr><th>Nội dung</th><th>Môn</th><th>Khối</th><th>Lớp</th><th>Học sinh</th><th>Tổng HS</th><th>Tỷ lệ</th></tr></thead><tbody>
<?php foreach($summaryRows as $row):$count=count($row['ids']);$base=$baseCount($row['grade'],$row['class']);$isAnalysis=in_array($row['category'],['muinhon','chuadat'],true);?><tr><td><?=e($tabs[$row['category']])?></td><td><?=e($row['subject'])?></td><td><?=e($row['grade'])?></td><td><?=e($row['class']?:'Toàn khối')?></td><td><a href="<?=e($statUrl(['detail_mode'=>'summary','detail_category'=>$row['category'],'detail_subject'=>$row['subject'],'detail_grade'=>$row['grade'],'detail_class'=>$row['class']]))?>"><?=$count?> · Xem DS</a></td><td><?=$isAnalysis?$base:'—'?></td><td><?=$isAnalysis&&$base?number_format(100*$count/$base,1,',','.').'%':'—'?></td></tr><?php endforeach;if(!$summaryRows):?><tr><td colspan="7" class="text-muted text-center">Chưa có dữ liệu phù hợp.</td></tr><?php endif;?></tbody></table></div></div></div><?php endif;?>
<?php if(in_array($categoryFilter,['tn','ts'],true)):?>
<div class="nav nav-pills gap-2 mb-3"><a class="nav-link <?=$statsView==='groups'?'active':''?>" href="<?=e($statUrl(['stats_view'=>'groups','detail_mode'=>'']))?>">Theo nhóm TBK/TBY</a><a class="nav-link <?=$statsView==='overlap'?'active':''?>" href="<?=e($statUrl(['stats_view'=>'overlap','detail_mode'=>'']))?>">Giao thoa giữa các môn</a></div>
<?php if($statsView==='groups'):?>
<div class="card mb-3"><div class="card-body"><h5>Học sinh TBK/TBY theo từng môn</h5><div class="table-responsive"><table class="table table-striped table-sm"><thead><tr><th>Kỳ thi</th><th>Môn</th><th>Nhóm</th><th>Số học sinh</th></tr></thead><tbody>
<?php foreach($examGroups as $key=>$ids):[$cat,$sub,$group]=json_decode($key,true);?><tr><td><?=e($tabs[$cat])?></td><td><?=e($sub)?></td><td><?=e($group)?></td><td><a href="<?=e($statUrl(['detail_mode'=>'exam_group','group_key'=>$key]))?>"><?=count($ids)?> · Xem DS</a></td></tr><?php endforeach;if(!$examGroups):?><tr><td colspan="4" class="text-muted text-center">Chưa có học sinh ôn thi phù hợp.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php else:?><div class="card mb-3"><div class="card-body"><h5>Giao thoa lớp ôn thi TBK/TBY giữa các môn</h5><p class="small text-muted">Mỗi dòng là hai lớp môn cùng có học sinh. Bấm số học sinh để xem ai bị trùng; không xếp hai lớp môn đó cùng tiết. Nhóm “Chưa xếp” cần được phân nhóm trước khi lập TKB.</p>
<div class="table-responsive"><table class="table table-striped table-sm align-middle"><thead><tr><th>Kỳ thi</th><th>Môn 1</th><th>Nhóm 1</th><th>Môn 2</th><th>Nhóm 2</th><th>HS chung</th><th>Lưu ý TKB</th></tr></thead><tbody>
<?php foreach($overlaps as $key=>$ids):[$cat,$a,$ga,$b,$gb]=json_decode($key,true);?><tr><td><?=e($tabs[$cat])?></td><td><?=e($a)?></td><td><?=e($ga)?></td><td><?=e($b)?></td><td><?=e($gb)?></td><td><a href="<?=e($statUrl(['detail_mode'=>'overlap','overlap_key'=>$key]))?>"><?=count($ids)?> · Xem DS</a></td><td class="text-danger fw-semibold">Không xếp cùng tiết</td></tr><?php endforeach;if(!$overlaps):?><tr><td colspan="7" class="text-muted text-center">Không có học sinh chung giữa hai môn trong phạm vi lọc.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php endif;?><?php endif;?>
<?php if($detailTitle!==''):?><div class="card mb-3" id="supportStatDetail"><div class="card-body"><h5><?=e($detailTitle)?> (<?=count($detailIds)?> học sinh)</h5><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>STT</th><th>Họ và tên</th><th>Lớp</th><th><?=in_array($categoryFilter,['tn','ts'],true)?'TBK/TBY theo môn':'Đề xuất'?></th></tr></thead><tbody><?php foreach($detailIds as $i=>$id):?><tr><td><?=$i+1?></td><td><?=e($students[$id]['name'])?></td><td><?=e($students[$id]['class'])?></td><td><?php if(in_array($categoryFilter,['tn','ts'],true)):foreach(($examByStudent[$categoryFilter][$id]??[]) as $sub=>$gr):?><span class="badge text-bg-light border me-1"><?=e($sub.' · '.($gr?:'Chưa xếp'))?></span><?php endforeach;else:?><?=e($members[$categoryFilter][$targetSubject][$id]['recommendation']??'—')?><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endif;?>
