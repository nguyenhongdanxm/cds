<?php
/**
 * Nộp, duyệt và thống kê Kế hoạch giáo dục theo Phụ lục I, II, III.
 */
$page_title = 'Kế hoạch giáo dục';
$educationUser = cds_user() ?? [];
$educationRole = (string)($educationUser['role'] ?? '');
$educationGroups = (array)($educationUser['groups'] ?? []);
$educationIsAdmin = $educationRole === 'admin';
$educationIsLeader = $educationRole === 'totruong' || in_array('totruong', $educationGroups, true);
$educationTeacher = trim((string)($educationUser['teacher_name'] ?? $educationUser['name'] ?? ''));
$educationGroup = $educationTeacher !== '' ? trim((string)get_teacher_group($educationTeacher)) : '';
$educationDataFile = DATA_PATH . '/education_plans.json';

function cm_education_norm($value): string {
    $value = preg_replace('/\s+/u', ' ', trim((string)$value));
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
function cm_education_grade($class): string {
    $class = trim((string)$class);
    if (preg_match('/(?:^|\D)(1[0-2]|[6-9])(?:\D|$)/u', $class, $m)) return $m[1];
    if (preg_match('/^(1[0-2]|[6-9])/u', $class, $m)) return $m[1];
    return $class;
}
function cm_education_load(string $file): array {
    $rows = load_json($file, []);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}
function cm_education_save(string $file, array $rows): bool {
    return cds_json_save($file, array_values($rows));
}
function cm_education_upload_pdf(string $field): string {
    $upload = $_FILES[$field] ?? null;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Tải tệp không thành công.');
    $name = basename((string)($upload['name'] ?? 'ke-hoach.pdf'));
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $signature = $tmp !== '' ? file_get_contents($tmp, false, null, 0, 5) : false;
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || $signature !== '%PDF-') throw new RuntimeException('Chỉ chấp nhận văn bản PDF đã ký số.');
    if ($size <= 0 || $size > 25 * 1024 * 1024) throw new RuntimeException('Tệp PDF phải có dung lượng không quá 25 MB.');

    $settings = function_exists('cds_drive_settings') ? cds_drive_settings() : [];
    $category = 'education_plans';
    if (empty($settings['types'][$category]['folder_id'])) {
        foreach ((array)($settings['types'] ?? []) as $key => $type) {
            if (cm_education_norm($type['label'] ?? '') === cm_education_norm('Kế hoạch giáo dục') && !empty($type['folder_id'])) {
                $category = (string)$key;
                break;
            }
        }
    }
    if (empty($settings['enabled'])) throw new RuntimeException('Kho Google Drive chưa được bật.');
    if (empty($settings['types'][$category]['folder_id'])) throw new RuntimeException('Chưa chọn thư mục “Kế hoạch giáo dục” trong Kho Google Drive.');
    $bytes = file_get_contents($tmp);
    if ($bytes === false) throw new RuntimeException('Không đọc được tệp PDF.');
    $result = cds_drive_upload_bytes($bytes, $name, 'application/pdf', $category);
    if (empty($result['ok'])) throw new RuntimeException($result['message'] ?? 'Không lưu được tệp vào thư mục Kế hoạch giáo dục.');

    $storedPath = (string)$result['path'];
    if (str_starts_with($storedPath, 'gdrive:')) {
        $driveId = substr($storedPath, 7);
        $cacheDir = DATA_PATH . '/cache/education_pdf';
        if ((is_dir($cacheDir) || @mkdir($cacheDir, 0755, true)) && $driveId !== '') {
            if (!is_file($cacheDir . '/.htaccess')) @file_put_contents($cacheDir . '/.htaccess', "Require all denied\nDeny from all\n");
            @copy($tmp, $cacheDir . '/' . hash('sha256', $driveId) . '.pdf');
        }
    }
    return $storedPath;
}
function cm_education_is_visible(array $row, bool $isAdmin, bool $isLeader, string $teacher, string $group): bool {
    if ($isAdmin) return true;
    if ($isLeader && $group !== '' && cm_education_norm($row['teacher_group'] ?? '') === cm_education_norm($group)) return true;
    return cm_education_norm($row['teacher'] ?? '') === cm_education_norm($teacher);
}
function cm_education_redirect(string $appendix = 'I'): void {
    $redirect = BASE_URL . 'kehoach.php?tab=vanban&appendix=' . urlencode($appendix);
    if (strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0) {
        $flash = is_array($_SESSION['flash'] ?? null) ? $_SESSION['flash'] : [];
        unset($_SESSION['flash']);
        $type = (string)($flash['type'] ?? 'success');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>$type === 'success','message'=>(string)($flash['message'] ?? ''),'redirect'=>$redirect], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . $redirect);
    exit;
}
function cm_education_requirement_key($teacher, $subject, $grade, $appendix): string {
    return cm_education_norm($teacher) . '|' . cm_education_norm($subject) . '|' . cm_education_norm($grade) . '|' . strtoupper(trim((string)$appendix));
}
function cm_education_stat_add(array &$buckets, string $name, bool $submitted, bool $approved): void {
    $name = trim($name) !== '' ? trim($name) : 'Chưa xác định';
    if (!isset($buckets[$name])) $buckets[$name] = ['name'=>$name,'required'=>0,'submitted'=>0,'approved'=>0,'missing'=>0];
    $buckets[$name]['required']++;
    if ($submitted) $buckets[$name]['submitted']++;
    if ($approved) $buckets[$name]['approved']++;
    if (!$submitted) $buckets[$name]['missing']++;
}

$educationAssignments = [];
foreach (get_assignments() as $assignment) {
    if (cm_education_norm($assignment['teacher'] ?? '') !== cm_education_norm($educationTeacher)) continue;
    $subject = trim((string)($assignment['subject'] ?? ''));
    $grade = cm_education_grade($assignment['class'] ?? '');
    if ($subject === '' || $grade === '') continue;
    $educationAssignments[$subject . '|' . $grade] = ['subject'=>$subject,'grade'=>$grade];
}
$educationAssignments = array_values($educationAssignments);
usort($educationAssignments, fn($a,$b)=>strnatcasecmp($a['subject'].'|'.$a['grade'],$b['subject'].'|'.$b['grade']));
$educationSubjects = array_values(array_unique(array_column($educationAssignments, 'subject')));
$educationGrades = array_values(array_unique(array_column($educationAssignments, 'grade')));
sort($educationSubjects, SORT_NATURAL | SORT_FLAG_CASE);
sort($educationGrades, SORT_NATURAL);

$educationRows = cm_education_load($educationDataFile);
if (empty($_SESSION['cm_education_csrf'])) $_SESSION['cm_education_csrf'] = bin2hex(random_bytes(24));
$educationCsrf = (string)$_SESSION['cm_education_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($educationCsrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $action = (string)($_POST['action'] ?? '');
    $appendix = in_array($_POST['appendix'] ?? '', ['I','II','III'], true) ? (string)$_POST['appendix'] : 'I';

    if ($action === 'save_plan') {
        if ($educationTeacher === '' || $educationGroup === '') { flash('Tài khoản chưa liên kết đầy đủ với giáo viên và tổ chuyên môn.', 'danger'); cm_education_redirect($appendix); }
        $subject = trim((string)($_POST['subject'] ?? ''));
        $grade = trim((string)($_POST['grade'] ?? ''));
        $allowed = false;
        foreach ($educationAssignments as $assignment) if ($assignment['subject'] === $subject && $assignment['grade'] === $grade) { $allowed = true; break; }
        if (!$allowed) { flash('Môn hoặc khối không thuộc phân công chuyên môn của tài khoản.', 'danger'); cm_education_redirect($appendix); }

        $id = trim((string)($_POST['id'] ?? ''));
        $found = false;
        try { $newFile = cm_education_upload_pdf('file'); }
        catch (RuntimeException $error) { flash($error->getMessage(), 'danger'); cm_education_redirect($appendix); }

        foreach ($educationRows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            if (!cm_education_is_visible($row, $educationIsAdmin, false, $educationTeacher, $educationGroup) || cm_education_norm($row['teacher'] ?? '') !== cm_education_norm($educationTeacher)) { http_response_code(403); exit('Không được sửa kế hoạch của giáo viên khác.'); }
            if (!empty($row['approved_at'])) { flash('Kế hoạch đã được duyệt nên không thể chỉnh sửa.', 'warning'); cm_education_redirect((string)($row['appendix'] ?? $appendix)); }
            $row['appendix'] = $appendix;
            $row['subject'] = $subject;
            $row['grade'] = $grade;
            if ($newFile !== '') { $row['file_path'] = $newFile; $row['submitted_at'] = date('c'); }
            if (empty($row['file_path'])) { flash('Vui lòng tải lên văn bản PDF đã ký số.', 'danger'); cm_education_redirect($appendix); }
            $row['updated_at'] = date('c');
            $found = true;
            break;
        }
        unset($row);

        if (!$found) {
            if ($newFile === '') { flash('Vui lòng tải lên văn bản PDF đã ký số.', 'danger'); cm_education_redirect($appendix); }
            $educationRows[] = [
                'id'=>'khdg_'.bin2hex(random_bytes(8)), 'teacher_id'=>(string)($educationUser['id'] ?? ''),
                'teacher'=>$educationTeacher, 'teacher_group'=>$educationGroup, 'appendix'=>$appendix,
                'subject'=>$subject, 'grade'=>$grade, 'file_path'=>$newFile,
                'submitted_at'=>date('c'), 'created_at'=>date('c'), 'updated_at'=>date('c'),
                'approved_at'=>'', 'approved_by'=>'',
            ];
        }
        if (!cm_education_save($educationDataFile, $educationRows)) { flash('Không lưu được dữ liệu kế hoạch.', 'danger'); cm_education_redirect($appendix); }
        flash($found ? 'Đã cập nhật kế hoạch giáo dục.' : 'Đã nộp kế hoạch giáo dục.');
        cm_education_redirect($appendix);
    }

    if ($action === 'delete_plan') {
        $id = trim((string)($_POST['id'] ?? ''));
        $deleted = false;
        foreach ($educationRows as $index=>$row) {
            if (($row['id'] ?? '') !== $id) continue;
            $owner = cm_education_norm($row['teacher'] ?? '') === cm_education_norm($educationTeacher);
            if (!$educationIsAdmin && (!$owner || !empty($row['approved_at']))) { http_response_code(403); exit('Không được xóa kế hoạch này.'); }
            $appendix = (string)($row['appendix'] ?? $appendix);
            unset($educationRows[$index]); $deleted = true; break;
        }
        if (!$deleted) { http_response_code(404); exit('Không tìm thấy kế hoạch.'); }
        cm_education_save($educationDataFile, $educationRows);
        flash('Đã xóa kế hoạch.', 'warning');
        cm_education_redirect($appendix);
    }

    if ($action === 'approve_plan') {
        if (!$educationIsAdmin && !$educationIsLeader) { http_response_code(403); exit('Chỉ TTCM hoặc quản trị được duyệt.'); }
        $id = trim((string)($_POST['id'] ?? ''));
        $approved = false;
        foreach ($educationRows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            if (!$educationIsAdmin && ($educationGroup === '' || cm_education_norm($row['teacher_group'] ?? '') !== cm_education_norm($educationGroup))) { http_response_code(403); exit('TTCM chỉ được duyệt kế hoạch trong tổ của mình.'); }
            $appendix = (string)($row['appendix'] ?? $appendix);
            if (empty($row['approved_at'])) {
                $row['approved_at'] = date('c');
                $row['approved_by'] = $educationTeacher ?: (string)($educationUser['name'] ?? 'Quản trị');
                $row['updated_at'] = date('c');
            }
            $approved = true;
            break;
        }
        unset($row);
        if (!$approved) { http_response_code(404); exit('Không tìm thấy kế hoạch.'); }
        cm_education_save($educationDataFile, $educationRows);
        flash('Đã duyệt kế hoạch. Giáo viên không thể sửa hoặc xóa sau thời điểm này.');
        cm_education_redirect($appendix);
    }
}

$educationVisibleRows = array_values(array_filter($educationRows, fn($row)=>cm_education_is_visible($row,$educationIsAdmin,$educationIsLeader,$educationTeacher,$educationGroup)));

/* Thống kê dự kiến phải nộp = mỗi cặp Giáo viên + Môn + Khối trong PCCM x 3 phụ lục. */
$educationSubmittedMap = [];
foreach ($educationVisibleRows as $row) {
    $key = cm_education_requirement_key($row['teacher'] ?? '', $row['subject'] ?? '', $row['grade'] ?? '', $row['appendix'] ?? '');
    if (!isset($educationSubmittedMap[$key])) $educationSubmittedMap[$key] = ['submitted'=>true,'approved'=>!empty($row['approved_at'])];
    elseif (!empty($row['approved_at'])) $educationSubmittedMap[$key]['approved'] = true;
}
$educationRequirements = [];
foreach (get_assignments() as $assignment) {
    $teacher = trim((string)($assignment['teacher'] ?? ''));
    $subject = trim((string)($assignment['subject'] ?? ''));
    $grade = cm_education_grade($assignment['class'] ?? '');
    if ($teacher === '' || $subject === '' || $grade === '') continue;
    $group = trim((string)get_teacher_group($teacher));
    if (!$educationIsAdmin) {
        if ($educationIsLeader) {
            if ($educationGroup === '' || cm_education_norm($group) !== cm_education_norm($educationGroup)) continue;
        } elseif (cm_education_norm($teacher) !== cm_education_norm($educationTeacher)) continue;
    }
    $base = cm_education_norm($teacher).'|'.cm_education_norm($subject).'|'.cm_education_norm($grade);
    foreach (['I','II','III'] as $appendix) {
        $key = $base.'|'.$appendix;
        $educationRequirements[$key] = ['teacher'=>$teacher,'group'=>$group,'subject'=>$subject,'grade'=>$grade,'appendix'=>$appendix];
    }
}
$educationStats = ['required'=>count($educationRequirements),'submitted'=>0,'approved'=>0,'missing'=>0];
$educationStatsByGroup = $educationStatsBySubject = $educationStatsByGrade = [];
$educationMissingRows = [];
foreach ($educationRequirements as $key=>$requirement) {
    $submitted = !empty($educationSubmittedMap[$key]['submitted']);
    $approved = !empty($educationSubmittedMap[$key]['approved']);
    if ($submitted) $educationStats['submitted']++;
    if ($approved) $educationStats['approved']++;
    if (!$submitted) { $educationStats['missing']++; $educationMissingRows[] = $requirement; }
    cm_education_stat_add($educationStatsByGroup, $requirement['group'], $submitted, $approved);
    cm_education_stat_add($educationStatsBySubject, $requirement['subject'], $submitted, $approved);
    cm_education_stat_add($educationStatsByGrade, 'Khối '.$requirement['grade'], $submitted, $approved);
}
foreach ([$educationStatsByGroup, $educationStatsBySubject, $educationStatsByGrade] as &$statsBucket) {
    uasort($statsBucket, fn($a,$b)=>($b['missing'] <=> $a['missing']) ?: strnatcasecmp($a['name'],$b['name']));
}
unset($statsBucket);
usort($educationMissingRows, fn($a,$b)=>strnatcasecmp($a['group'].'|'.$a['subject'].'|'.$a['grade'].'|'.$a['teacher'].'|'.$a['appendix'], $b['group'].'|'.$b['subject'].'|'.$b['grade'].'|'.$b['teacher'].'|'.$b['appendix']));

$educationAppendix = in_array($_GET['appendix'] ?? '', ['I','II','III'], true) ? (string)$_GET['appendix'] : 'I';
$educationFilterAppendix = trim((string)($_GET['appendix_filter'] ?? ''));
if (!in_array($educationFilterAppendix, ['', 'I','II','III'], true)) $educationFilterAppendix = '';
if (!array_key_exists('appendix_filter', $_GET) && isset($_GET['appendix'])) $educationFilterAppendix = $educationAppendix;
$educationSearch = trim((string)($_GET['q'] ?? ''));
$educationFilterGroup = trim((string)($_GET['group'] ?? ''));
$educationFilterSubject = trim((string)($_GET['subject'] ?? ''));
$educationFilterGrade = trim((string)($_GET['grade'] ?? ''));
$educationFilterStatus = trim((string)($_GET['status'] ?? ''));
if (!in_array($educationFilterStatus, ['', 'pending','approved'], true)) $educationFilterStatus = '';
$educationSort = trim((string)($_GET['sort'] ?? 'submitted_at'));
$educationDir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$educationSortFields = ['teacher','appendix','teacher_group','subject','grade','submitted_at','status'];
if (!in_array($educationSort, $educationSortFields, true)) $educationSort = 'submitted_at';
$educationFilterGroups = array_values(array_unique(array_filter(array_column($educationVisibleRows,'teacher_group'))));
$educationFilterSubjects = array_values(array_unique(array_filter(array_column($educationVisibleRows,'subject'))));
$educationFilterGrades = array_values(array_unique(array_filter(array_column($educationVisibleRows,'grade'))));
sort($educationFilterGroups, SORT_NATURAL|SORT_FLAG_CASE);
sort($educationFilterSubjects, SORT_NATURAL|SORT_FLAG_CASE);
sort($educationFilterGrades, SORT_NATURAL);

$educationList = array_values(array_filter($educationVisibleRows, function($row) use($educationFilterAppendix,$educationSearch,$educationFilterGroup,$educationFilterSubject,$educationFilterGrade,$educationFilterStatus) {
    if ($educationFilterAppendix !== '' && ($row['appendix'] ?? '') !== $educationFilterAppendix) return false;
    if ($educationFilterGroup !== '' && ($row['teacher_group'] ?? '') !== $educationFilterGroup) return false;
    if ($educationFilterSubject !== '' && ($row['subject'] ?? '') !== $educationFilterSubject) return false;
    if ($educationFilterGrade !== '' && (string)($row['grade'] ?? '') !== $educationFilterGrade) return false;
    $approved = !empty($row['approved_at']);
    if ($educationFilterStatus === 'approved' && !$approved) return false;
    if ($educationFilterStatus === 'pending' && $approved) return false;
    if ($educationSearch !== '') {
        $haystack = cm_education_norm(implode(' ', [$row['teacher'] ?? '',$row['teacher_group'] ?? '',$row['subject'] ?? '',$row['grade'] ?? '',$row['appendix'] ?? '']));
        if (!str_contains($haystack, cm_education_norm($educationSearch))) return false;
    }
    return true;
}));
usort($educationList, function($a,$b) use($educationSort,$educationDir) {
    if ($educationSort === 'status') { $av=!empty($a['approved_at'])?1:0; $bv=!empty($b['approved_at'])?1:0; $cmp=$av<=>$bv; }
    elseif ($educationSort === 'grade') $cmp = strnatcasecmp((string)($a[$educationSort] ?? ''),(string)($b[$educationSort] ?? ''));
    else $cmp = strcasecmp((string)($a[$educationSort] ?? ''),(string)($b[$educationSort] ?? ''));
    return $educationDir === 'asc' ? $cmp : -$cmp;
});
$sortUrl = function(string $field) use($educationAppendix,$educationFilterAppendix,$educationSearch,$educationFilterGroup,$educationFilterSubject,$educationFilterGrade,$educationFilterStatus,$educationSort,$educationDir) {
    $dir = $educationSort === $field && $educationDir === 'asc' ? 'desc' : 'asc';
    return BASE_URL.'kehoach.php?'.http_build_query(['tab'=>'vanban','appendix'=>$educationAppendix,'appendix_filter'=>$educationFilterAppendix,'q'=>$educationSearch,'group'=>$educationFilterGroup,'subject'=>$educationFilterSubject,'grade'=>$educationFilterGrade,'status'=>$educationFilterStatus,'sort'=>$field,'dir'=>$dir]);
};
$sortIcon = function(string $field) use($educationSort,$educationDir) {
    return $educationSort === $field ? ($educationDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : ' <i class="bi bi-arrow-down-up text-muted"></i>';
};

require __DIR__.'/header.php';
?>
<style>
.education-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.education-tabs .nav-link{min-width:130px;text-align:center}.education-filter{display:grid;grid-template-columns:2fr repeat(5,minmax(120px,1fr)) auto;gap:.65rem;align-items:end}.education-table{min-width:1240px}.education-table td{vertical-align:middle}.education-table th a{color:inherit;text-decoration:none;white-space:nowrap}.education-table th a:hover{color:var(--bs-primary)}.education-appendix{display:inline-flex;align-items:center;justify-content:center;min-width:76px;padding:.28rem .55rem;border-radius:999px;background:#e8f1fb;color:#164f7b;font-size:.78rem;font-weight:800}.education-status{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .58rem;border-radius:999px;font-size:.78rem;font-weight:700}.education-status.pending{background:#fff3cd;color:#664d03}.education-status.approved{background:#d1e7dd;color:#0f5132}.education-meta{font-size:.78rem;line-height:1.45;color:#64748b}.education-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}.education-form-grid .wide{grid-column:1/-1}
.education-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.education-stat{border:1px solid #dbe6ef;border-radius:14px;background:#fff;padding:1rem}.education-stat .value{font-size:1.65rem;font-weight:800;line-height:1}.education-stat small{color:#64748b}.education-stat.missing .value{color:#dc3545}.education-stat.approved .value{color:#198754}.education-stat.submitted .value{color:#0d6efd}.education-stat.required .value{color:#334155}.education-stat-tables{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.education-stat-table{max-height:320px;overflow:auto}.education-stat-table table{font-size:.82rem}.education-stat-table th,.education-stat-table td{white-space:nowrap}.education-missing-table{max-height:360px;overflow:auto}.education-progress-mini{height:5px;background:#e9ecef;border-radius:999px;overflow:hidden;margin-top:.3rem}.education-progress-mini span{display:block;height:100%;background:#198754}
@media(max-width:1200px){.education-filter{grid-template-columns:1fr 1fr 1fr}.education-filter .search{grid-column:1/-1}.education-stat-tables{grid-template-columns:1fr}.education-stats{grid-template-columns:1fr 1fr}}
@media(max-width:900px){.education-filter{grid-template-columns:1fr 1fr}.education-form-grid{grid-template-columns:1fr}.education-toolbar{align-items:flex-start;flex-direction:column}}
@media(max-width:575px){.education-filter{grid-template-columns:1fr}.education-filter .search{grid-column:auto}.education-stats{grid-template-columns:1fr 1fr}.education-stat{padding:.75rem}.education-stat .value{font-size:1.35rem}}
</style>

<div class="education-toolbar">
  <div><h3 class="mb-1"><i class="bi bi-file-earmark-check text-primary"></i> Kế hoạch giáo dục</h3><div class="text-muted">Nộp và duyệt văn bản PDF ký số theo Phụ lục I, II, III</div></div>
  <?php if($educationTeacher!==''&&$educationAssignments):?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#educationPlanModal" onclick="resetEducationForm()"><i class="bi bi-cloud-arrow-up"></i> Nhập kế hoạch</button><?php endif;?>
</div>
<?php if($educationTeacher===''):?><div class="alert alert-warning">Tài khoản chưa liên kết với giáo viên. Quản trị cần cập nhật trường <strong>Giáo viên liên kết</strong>.</div><?php elseif($educationGroup===''):?><div class="alert alert-warning">Chưa xác định được tổ chuyên môn của <strong><?=e($educationTeacher)?></strong>.</div><?php elseif(!$educationAssignments):?><div class="alert alert-info">Không tìm thấy môn/khối được phân công cho <strong><?=e($educationTeacher)?></strong>.</div><?php endif;?>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between gap-2"><strong><i class="bi bi-bar-chart-line me-1"></i> Thống kê tiến độ nộp Kế hoạch giáo dục</strong><small class="text-muted"><?= $educationIsAdmin ? 'Toàn trường' : ($educationIsLeader ? 'Tổ '.e($educationGroup) : 'Cá nhân') ?></small></div>
  <div class="card-body">
    <div class="education-stats mb-3">
      <div class="education-stat required"><small>Dự kiến phải nộp</small><div class="value mt-1"><?= (int)$educationStats['required'] ?></div><small>PCCM × 3 phụ lục</small></div>
      <div class="education-stat submitted"><small>Đã nộp</small><div class="value mt-1"><?= (int)$educationStats['submitted'] ?></div><small><?= $educationStats['required'] ? round($educationStats['submitted']*100/$educationStats['required']) : 0 ?>% yêu cầu</small></div>
      <div class="education-stat approved"><small>Đã duyệt</small><div class="value mt-1"><?= (int)$educationStats['approved'] ?></div><small><?= $educationStats['submitted'] ? round($educationStats['approved']*100/$educationStats['submitted']) : 0 ?>% đã nộp</small></div>
      <div class="education-stat missing"><small>Còn thiếu</small><div class="value mt-1"><?= (int)$educationStats['missing'] ?></div><small>Chưa có văn bản</small></div>
    </div>

    <div class="education-stat-tables">
      <?php foreach ([['Theo tổ',$educationStatsByGroup],['Theo môn',$educationStatsBySubject],['Theo khối',$educationStatsByGrade]] as [$title,$bucket]): ?>
      <div class="border rounded-3 overflow-hidden"><div class="px-3 py-2 bg-light fw-semibold"><?= e($title) ?></div><div class="education-stat-table"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Đơn vị</th><th class="text-center">Cần</th><th class="text-center">Nộp</th><th class="text-center">Duyệt</th><th class="text-center text-danger">Thiếu</th></tr></thead><tbody>
      <?php if(!$bucket): ?><tr><td colspan="5" class="text-center text-muted py-3">Chưa có dữ liệu.</td></tr><?php else: foreach($bucket as $stat): $pct=$stat['required']?round($stat['submitted']*100/$stat['required']):0; ?><tr><td><strong><?= e($stat['name']) ?></strong><div class="education-progress-mini"><span style="width:<?= min(100,$pct) ?>%"></span></div></td><td class="text-center"><?= (int)$stat['required'] ?></td><td class="text-center"><?= (int)$stat['submitted'] ?></td><td class="text-center text-success"><?= (int)$stat['approved'] ?></td><td class="text-center <?= $stat['missing'] ? 'text-danger fw-bold' : 'text-muted' ?>"><?= (int)$stat['missing'] ?></td></tr><?php endforeach; endif; ?>
      </tbody></table></div></div>
      <?php endforeach; ?>
    </div>

    <details class="mt-3" <?= $educationStats['missing'] ? '' : 'open' ?>><summary class="fw-semibold text-danger" style="cursor:pointer"><i class="bi bi-exclamation-circle me-1"></i> Chi tiết kế hoạch còn thiếu (<?= (int)$educationStats['missing'] ?>)</summary><div class="education-missing-table border rounded mt-2"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Giáo viên</th><th>Tổ</th><th>Môn</th><th>Khối</th><th>Phụ lục</th></tr></thead><tbody><?php if(!$educationMissingRows): ?><tr><td colspan="5" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i> Đã nộp đủ theo phân công hiện tại.</td></tr><?php else: foreach($educationMissingRows as $missing): ?><tr><td><?= e($missing['teacher']) ?></td><td><?= e($missing['group']) ?></td><td><?= e($missing['subject']) ?></td><td><?= e($missing['grade']) ?></td><td><span class="education-appendix">Phụ lục <?= e($missing['appendix']) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></details>
  </div>
</div>

<ul class="nav nav-pills education-tabs gap-2 mb-3"><li class="nav-item"><a class="nav-link <?=$educationFilterAppendix===''?'active':''?>" href="<?=BASE_URL?>kehoach.php?tab=vanban&appendix=<?=e($educationAppendix)?>&appendix_filter=">Tất cả phụ lục</a></li><?php foreach(['I','II','III'] as $appendix):?><li class="nav-item"><a class="nav-link <?=$educationFilterAppendix===$appendix?'active':''?>" href="<?=BASE_URL?>kehoach.php?tab=vanban&appendix=<?=$appendix?>&appendix_filter=<?=$appendix?>">Phụ lục <?=$appendix?></a></li><?php endforeach;?></ul>

<div class="card mb-3"><div class="card-body"><form method="get" class="education-filter"><input type="hidden" name="tab" value="vanban"><input type="hidden" name="appendix" value="<?=e($educationAppendix)?>"><input type="hidden" name="sort" value="<?=e($educationSort)?>"><input type="hidden" name="dir" value="<?=e($educationDir)?>"><div class="search"><label class="form-label small fw-semibold">Tìm kiếm</label><input class="form-control" name="q" value="<?=e($educationSearch)?>" placeholder="Tên giáo viên, tổ, môn, khối hoặc phụ lục"></div><div><label class="form-label small fw-semibold">Phụ lục</label><select class="form-select" name="appendix_filter"><option value="">Tất cả phụ lục</option><?php foreach(['I','II','III'] as $value):?><option value="<?=$value?>" <?=$educationFilterAppendix===$value?'selected':''?>>Phụ lục <?=$value?></option><?php endforeach;?></select></div><div><label class="form-label small fw-semibold">Trạng thái</label><select class="form-select" name="status"><option value="">Tất cả trạng thái</option><option value="pending" <?=$educationFilterStatus==='pending'?'selected':''?>>Chờ duyệt</option><option value="approved" <?=$educationFilterStatus==='approved'?'selected':''?>>Đã duyệt</option></select></div><div><label class="form-label small fw-semibold">Tổ</label><select class="form-select" name="group"><option value="">Tất cả tổ</option><?php foreach($educationFilterGroups as $value):?><option value="<?=e($value)?>" <?=$value===$educationFilterGroup?'selected':''?>><?=e($value)?></option><?php endforeach;?></select></div><div><label class="form-label small fw-semibold">Môn</label><select class="form-select" name="subject"><option value="">Tất cả môn</option><?php foreach($educationFilterSubjects as $value):?><option value="<?=e($value)?>" <?=$value===$educationFilterSubject?'selected':''?>><?=e($value)?></option><?php endforeach;?></select></div><div><label class="form-label small fw-semibold">Khối</label><select class="form-select" name="grade"><option value="">Tất cả khối</option><?php foreach($educationFilterGrades as $value):?><option value="<?=e($value)?>" <?=(string)$value===$educationFilterGrade?'selected':''?>><?=e($value)?></option><?php endforeach;?></select></div><div class="d-flex gap-2"><button class="btn btn-primary" title="Lọc"><i class="bi bi-funnel"></i></button><a class="btn btn-outline-secondary" href="<?=BASE_URL?>kehoach.php?tab=vanban&appendix=<?=e($educationAppendix)?>&appendix_filter=" title="Bỏ lọc"><i class="bi bi-arrow-counterclockwise"></i></a></div></form></div></div>

<div class="card"><div class="card-header d-flex justify-content-between"><span><?=$educationFilterAppendix!==''?'Phụ lục '.e($educationFilterAppendix):'Tất cả phụ lục'?></span><span><?=count($educationList)?> văn bản</span></div><div class="table-responsive"><table class="table table-hover mb-0 education-table"><thead><tr><th>STT</th><th><a href="<?=e($sortUrl('teacher'))?>">Giáo viên<?=$sortIcon('teacher')?></a></th><th><a href="<?=e($sortUrl('appendix'))?>">Phụ lục<?=$sortIcon('appendix')?></a></th><th><a href="<?=e($sortUrl('teacher_group'))?>">Tổ<?=$sortIcon('teacher_group')?></a></th><th><a href="<?=e($sortUrl('subject'))?>">Môn<?=$sortIcon('subject')?></a></th><th><a href="<?=e($sortUrl('grade'))?>">Khối<?=$sortIcon('grade')?></a></th><th><a href="<?=e($sortUrl('submitted_at'))?>">Thời gian<?=$sortIcon('submitted_at')?></a></th><th><a href="<?=e($sortUrl('status'))?>">Trạng thái<?=$sortIcon('status')?></a></th><th>Thao tác</th></tr></thead><tbody><?php if(!$educationList):?><tr><td colspan="9" class="text-center text-muted py-4">Không có kế hoạch phù hợp bộ lọc.</td></tr><?php else:foreach($educationList as $index=>$row):$approved=!empty($row['approved_at']);$owner=cm_education_norm($row['teacher']??'')===cm_education_norm($educationTeacher);$rowAppendix=(string)($row['appendix']??'');?><tr><td class="text-center"><?=$index+1?></td><td><strong><?=e($row['teacher']??'')?></strong></td><td class="text-center"><span class="education-appendix">Phụ lục <?=e($rowAppendix?:'—')?></span></td><td><?=e($row['teacher_group']??'')?></td><td><?=e($row['subject']??'')?></td><td class="text-center"><?=e($row['grade']??'')?></td><td><div class="education-meta"><strong>Nộp:</strong> <?=!empty($row['submitted_at'])?e(date('H:i d/m/Y',strtotime($row['submitted_at']))):'—'?><br><strong>Duyệt:</strong> <?=$approved?e(date('H:i d/m/Y',strtotime($row['approved_at']))):'—'?><?php if($approved&&!empty($row['approved_by'])):?><br><span><?=e($row['approved_by'])?></span><?php endif;?></div></td><td><?=$approved?'<span class="education-status approved"><i class="bi bi-check-circle-fill"></i> Đã duyệt</span>':'<span class="education-status pending"><i class="bi bi-hourglass-split"></i> Chờ duyệt</span>'?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?=e(BASE_URL.'education_file.php?id='.urlencode((string)($row['id']??'')))?>" title="Xem PDF"><i class="bi bi-eye"></i></a> <?php if($owner&&!$approved):?><button class="btn btn-sm btn-outline-primary" type="button" title="Sửa" data-plan="<?=e(base64_encode(json_encode($row,JSON_UNESCAPED_UNICODE)))?>" onclick="editEducationPlan(this)"><i class="bi bi-pencil"></i></button><?php endif;?> <?php if(($owner&&!$approved)||$educationIsAdmin):?><form class="d-inline" method="post" onsubmit="return confirm('Xóa kế hoạch này?')"><input type="hidden" name="csrf" value="<?=e($educationCsrf)?>"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><input type="hidden" name="appendix" value="<?=e($rowAppendix?:$educationAppendix)?>"><button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash"></i></button></form><?php endif;?> <?php if(($educationIsAdmin||$educationIsLeader)&&!$approved):?><form class="d-inline" method="post" onsubmit="return confirm('Duyệt kế hoạch này? Sau khi duyệt, giáo viên sẽ không thể sửa hoặc xóa.')"><input type="hidden" name="csrf" value="<?=e($educationCsrf)?>"><input type="hidden" name="action" value="approve_plan"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><input type="hidden" name="appendix" value="<?=e($rowAppendix?:$educationAppendix)?>"><button class="btn btn-sm btn-success" title="Duyệt"><i class="bi bi-check2-circle"></i> Duyệt</button></form><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>

<div class="modal fade" id="educationPlanModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" action="<?=e(BASE_URL.'kehoach.php?tab=vanban&appendix='.urlencode($educationAppendix))?>" enctype="multipart/form-data" id="educationPlanForm"><div class="modal-header"><h5 class="modal-title"><i class="bi bi-cloud-arrow-up"></i> Nhập kế hoạch giáo dục</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e($educationCsrf)?>"><input type="hidden" name="action" value="save_plan"><input type="hidden" name="id" id="educationId"><div class="education-form-grid"><div><label class="form-label fw-semibold">Tên giáo viên</label><input class="form-control" value="<?=e($educationTeacher)?>" readonly></div><div><label class="form-label fw-semibold">Tổ</label><input class="form-control" value="<?=e($educationGroup)?>" readonly></div><div><label class="form-label fw-semibold">Phụ lục</label><select class="form-select" name="appendix" id="educationAppendix" required><?php foreach(['I','II','III'] as $value):?><option value="<?=$value?>" <?=$value===$educationAppendix?'selected':''?>>Phụ lục <?=$value?></option><?php endforeach;?></select></div><div><label class="form-label fw-semibold">Môn</label><select class="form-select" name="subject" id="educationSubject" required><option value="">Chọn môn</option><?php foreach($educationSubjects as $value):?><option value="<?=e($value)?>"><?=e($value)?></option><?php endforeach;?></select></div><div><label class="form-label fw-semibold">Khối</label><select class="form-select" name="grade" id="educationGrade" required><option value="">Chọn khối</option><?php foreach($educationAssignments as $assignment):?><option value="<?=e($assignment['grade'])?>" data-subject="<?=e($assignment['subject'])?>"><?=e($assignment['grade'])?></option><?php endforeach;?></select></div><div class="wide"><label class="form-label fw-semibold">Văn bản PDF ký số</label><input class="form-control" type="file" name="file" id="educationFile" accept="application/pdf,.pdf"><div class="form-text" id="educationFileNote">Chỉ tải tệp PDF đã ký số, tối đa 25 MB.</div></div></div><div class="mt-3 p-3 border rounded bg-light" id="educationUploadProgress" hidden><div class="d-flex justify-content-between align-items-center gap-2 mb-2"><span class="fw-semibold" id="educationUploadStatus"><span class="spinner-border spinner-border-sm me-2"></span>Đang chuẩn bị tải lên…</span><strong id="educationUploadPercent">0%</strong></div><div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" id="educationUploadBar" style="width:0%"></div></div><div class="small text-muted mt-2" id="educationUploadDetail">Vui lòng giữ nguyên trang cho đến khi có thông báo thành công.</div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" id="educationCancelButton" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" id="educationSubmitButton" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên và lưu</button></div></form></div></div></div>
<script>
function educationFilterGrades(){var s=document.getElementById('educationSubject').value,g=document.getElementById('educationGrade'),c=g.value;Array.from(g.options).forEach(function(o,i){if(i===0)return;o.hidden=o.dataset.subject!==s;o.disabled=o.hidden});if(!Array.from(g.options).some(function(o){return !o.hidden&&o.value===c}))g.value=''}
document.getElementById('educationSubject').addEventListener('change',educationFilterGrades);
function resetEducationForm(){document.getElementById('educationPlanForm').reset();document.getElementById('educationId').value='';document.getElementById('educationAppendix').value='<?=e($educationAppendix)?>';document.getElementById('educationFile').required=true;document.getElementById('educationFileNote').textContent='Chỉ tải tệp PDF đã ký số, tối đa 25 MB.';document.getElementById('educationUploadProgress').hidden=true;educationFilterGrades()}
function editEducationPlan(b){try{var r=JSON.parse(decodeURIComponent(escape(atob(b.dataset.plan||''))));document.getElementById('educationId').value=r.id||'';document.getElementById('educationAppendix').value=r.appendix||'I';document.getElementById('educationSubject').value=r.subject||'';educationFilterGrades();document.getElementById('educationGrade').value=r.grade||'';document.getElementById('educationFile').required=false;document.getElementById('educationFileNote').textContent='Để trống nếu giữ nguyên PDF hiện tại; chọn tệp mới để thay thế.';bootstrap.Modal.getOrCreateInstance(document.getElementById('educationPlanModal')).show()}catch(e){alert('Không đọc được dữ liệu kế hoạch.')}}
(function(){var f=document.getElementById('educationPlanForm'),box=document.getElementById('educationUploadProgress'),bar=document.getElementById('educationUploadBar'),pct=document.getElementById('educationUploadPercent'),st=document.getElementById('educationUploadStatus'),dt=document.getElementById('educationUploadDetail'),sb=document.getElementById('educationSubmitButton'),cb=document.getElementById('educationCancelButton');function p(v){v=Math.max(0,Math.min(100,Math.round(v)));bar.style.width=v+'%';pct.textContent=v+'%'}function busy(v){sb.disabled=v;cb.disabled=v}function fail(m){busy(false);bar.className='progress-bar bg-danger';st.innerHTML='<i class="bi bi-x-circle-fill text-danger me-2"></i>Tải lên chưa thành công';dt.textContent=m||'Không kết nối được máy chủ.'}f.addEventListener('submit',function(e){e.preventDefault();if(!f.reportValidity())return;box.hidden=false;bar.className='progress-bar progress-bar-striped progress-bar-animated';p(0);busy(true);var x=new XMLHttpRequest(),d=new FormData(f);x.open('POST',f.getAttribute('action')||location.href,true);x.timeout=600000;x.setRequestHeader('X-Requested-With','XMLHttpRequest');x.upload.onprogress=function(a){if(a.lengthComputable)p(a.loaded/a.total*100)};x.onload=function(){var r;try{r=JSON.parse(x.responseText||'{}')}catch(e){r={ok:false,message:'Máy chủ trả về dữ liệu không hợp lệ.'}}if(x.status<200||x.status>=300||!r.ok){fail(r.message);return}p(100);bar.className='progress-bar bg-success';st.innerHTML='<i class="bi bi-check-circle-fill text-success me-2"></i>Tải lên thành công';dt.textContent=r.message||'Đã lưu PDF vào Google Drive.';setTimeout(function(){location.href=r.redirect||location.href},500)};x.onerror=function(){fail('Mất kết nối tới máy chủ cPanel trong khi tải tệp.')};x.ontimeout=function(){fail('Máy chủ xử lý quá lâu. Vui lòng kiểm tra lại kết nối Google Drive.')};x.send(d)})})();
</script>
<?php require __DIR__.'/footer.php'; ?>
