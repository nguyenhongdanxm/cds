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
function cm_education_status(array $row): string {
    if (!empty($row['rejected_at'])) return 'rejected';
    if (!empty($row['approved_at'])) return 'approved';
    return 'pending';
}
function cm_education_reviewer_name(array $user, string $teacher): string {
    return $teacher !== '' ? $teacher : trim((string)($user['name'] ?? 'Quản trị hệ thống'));
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
            $row['rejected_at'] = '';
            $row['rejected_by'] = '';
            $row['rejection_reason'] = '';
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
                'approved_at'=>'', 'approved_by'=>'', 'rejected_at'=>'', 'rejected_by'=>'', 'rejection_reason'=>'',
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
                $row['approved_by'] = cm_education_reviewer_name($educationUser, $educationTeacher);
                $row['rejected_at'] = '';
                $row['rejected_by'] = '';
                $row['rejection_reason'] = '';
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

    if ($action === 'reject_plan') {
        if (!$educationIsAdmin && !$educationIsLeader) { http_response_code(403); exit('Chỉ TTCM hoặc quản trị được từ chối kế hoạch.'); }
        $id = trim((string)($_POST['id'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') { flash('Vui lòng nhập lý do từ chối.', 'danger'); cm_education_redirect($appendix); }
        $rejected = false;
        foreach ($educationRows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            if (!$educationIsAdmin && ($educationGroup === '' || cm_education_norm($row['teacher_group'] ?? '') !== cm_education_norm($educationGroup))) { http_response_code(403); exit('TTCM chỉ được từ chối kế hoạch trong tổ của mình.'); }
            $appendix = (string)($row['appendix'] ?? $appendix);
            $row['approved_at'] = '';
            $row['approved_by'] = '';
            $row['rejected_at'] = date('c');
            $row['rejected_by'] = cm_education_reviewer_name($educationUser, $educationTeacher);
            $row['rejection_reason'] = $reason;
            $row['updated_at'] = date('c');
            $rejected = true;
            break;
        }
        unset($row);
        if (!$rejected) { http_response_code(404); exit('Không tìm thấy kế hoạch.'); }
        cm_education_save($educationDataFile, $educationRows);
        flash('Đã từ chối kế hoạch và lưu lý do.', 'warning');
        cm_education_redirect($appendix);
    }

    if ($action === 'bulk_plan') {
        if (!$educationIsAdmin && !$educationIsLeader) { http_response_code(403); exit('Tài khoản không có quyền thao tác hàng loạt.'); }
        $operation = (string)($_POST['bulk_operation'] ?? '');
        if (!in_array($operation, ['approve','reject','delete'], true)) { http_response_code(400); exit('Thao tác không hợp lệ.'); }
        if ($operation === 'delete' && !$educationIsAdmin) { http_response_code(403); exit('Chỉ quản trị được xóa kế hoạch hàng loạt.'); }
        $selectedIds = [];
        foreach ((array)($_POST['plan_ids'] ?? []) as $selectedId) {
            if (!is_scalar($selectedId)) continue;
            $selectedId = trim((string)$selectedId);
            if ($selectedId !== '') $selectedIds[$selectedId] = $selectedId;
        }
        $selectedIds = array_values($selectedIds);
        if (!$selectedIds) { flash('Vui lòng chọn ít nhất một kế hoạch.', 'warning'); cm_education_redirect($appendix); }
        if (count($selectedIds) > 1000) { http_response_code(400); exit('Số lượng kế hoạch được chọn vượt giới hạn.'); }
        $reason = trim((string)($_POST['bulk_reject_reason'] ?? ''));
        if ($operation === 'reject' && $reason === '') { flash('Vui lòng nhập lý do từ chối.', 'danger'); cm_education_redirect($appendix); }
        $selectedMap = array_fill_keys($selectedIds, true);
        $changed = 0;
        foreach ($educationRows as $index => &$row) {
            $id = (string)($row['id'] ?? '');
            if (!isset($selectedMap[$id])) continue;
            if (!$educationIsAdmin && ($educationGroup === '' || cm_education_norm($row['teacher_group'] ?? '') !== cm_education_norm($educationGroup))) continue;
            $appendix = (string)($row['appendix'] ?? $appendix);
            if ($operation === 'delete') {
                unset($educationRows[$index]);
            } elseif ($operation === 'approve') {
                $row['approved_at'] = date('c');
                $row['approved_by'] = cm_education_reviewer_name($educationUser, $educationTeacher);
                $row['rejected_at'] = '';
                $row['rejected_by'] = '';
                $row['rejection_reason'] = '';
                $row['updated_at'] = date('c');
            } else {
                $row['approved_at'] = '';
                $row['approved_by'] = '';
                $row['rejected_at'] = date('c');
                $row['rejected_by'] = cm_education_reviewer_name($educationUser, $educationTeacher);
                $row['rejection_reason'] = $reason;
                $row['updated_at'] = date('c');
            }
            $changed++;
        }
        unset($row);
        if ($changed === 0) { flash('Không có kế hoạch nào thuộc phạm vi được phép thao tác.', 'warning'); cm_education_redirect($appendix); }
        if (!cm_education_save($educationDataFile, $educationRows)) { flash('Không lưu được thay đổi hàng loạt.', 'danger'); cm_education_redirect($appendix); }
        $messages = ['approve'=>'Đã duyệt','reject'=>'Đã từ chối','delete'=>'Đã xóa'];
        flash($messages[$operation].' '.$changed.' kế hoạch.', $operation === 'delete' || $operation === 'reject' ? 'warning' : 'success');
        cm_education_redirect($appendix);
    }
}

$educationVisibleRows = array_values(array_filter($educationRows, fn($row)=>cm_education_is_visible($row,$educationIsAdmin,$educationIsLeader,$educationTeacher,$educationGroup)));

/* Thống kê chỉ dựa trên dữ liệu thực tế đã nộp, không suy diễn số phải nộp từ PCCM. */
$educationStats = ['total'=>0,'I'=>0,'II'=>0,'III'=>0,'approved'=>0,'rejected'=>0];
$educationStatsByGroup = [];
$educationSubmittedRows = [];
foreach ($educationVisibleRows as $row) {
    $appendix = strtoupper(trim((string)($row['appendix'] ?? '')));
    if (!in_array($appendix, ['I','II','III'], true)) continue;
    $group = trim((string)($row['teacher_group'] ?? '')) ?: 'Chưa xác định';
    $educationStats['total']++;
    $educationStats[$appendix]++;
    if (!empty($row['approved_at'])) $educationStats['approved']++;
    if (cm_education_status($row) === 'rejected') $educationStats['rejected']++;
    if (!isset($educationStatsByGroup[$group])) $educationStatsByGroup[$group] = ['group'=>$group,'total'=>0,'I'=>0,'II'=>0,'III'=>0,'approved'=>0];
    $educationStatsByGroup[$group]['total']++;
    $educationStatsByGroup[$group][$appendix]++;
    if (!empty($row['approved_at'])) $educationStatsByGroup[$group]['approved']++;
    $educationSubmittedRows[] = $row;
}
uasort($educationStatsByGroup, fn($a,$b)=>strnatcasecmp($a['group'],$b['group']));
usort($educationSubmittedRows, fn($a,$b)=>strcmp((string)($b['submitted_at'] ?? ''),(string)($a['submitted_at'] ?? '')));

$educationAppendix = in_array($_GET['appendix'] ?? '', ['I','II','III'], true) ? (string)$_GET['appendix'] : 'I';
$educationFilterAppendix = trim((string)($_GET['appendix_filter'] ?? ''));
if (!in_array($educationFilterAppendix, ['', 'I','II','III'], true)) $educationFilterAppendix = '';
if (!array_key_exists('appendix_filter', $_GET) && isset($_GET['appendix'])) $educationFilterAppendix = $educationAppendix;
$educationSearch = trim((string)($_GET['q'] ?? ''));
$educationFilterGroup = trim((string)($_GET['group'] ?? ''));
$educationFilterSubject = trim((string)($_GET['subject'] ?? ''));
$educationFilterGrade = trim((string)($_GET['grade'] ?? ''));
$educationFilterStatus = trim((string)($_GET['status'] ?? ''));
if (!in_array($educationFilterStatus, ['', 'pending','approved','rejected'], true)) $educationFilterStatus = '';
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
    $status = cm_education_status($row);
    if ($educationFilterStatus !== '' && $status !== $educationFilterStatus) return false;
    if ($educationSearch !== '') {
        $haystack = cm_education_norm(implode(' ', [$row['teacher'] ?? '',$row['teacher_group'] ?? '',$row['subject'] ?? '',$row['grade'] ?? '',$row['appendix'] ?? '']));
        if (!str_contains($haystack, cm_education_norm($educationSearch))) return false;
    }
    return true;
}));
usort($educationList, function($a,$b) use($educationSort,$educationDir) {
    if ($educationSort === 'status') { $order=['pending'=>0,'rejected'=>1,'approved'=>2]; $av=$order[cm_education_status($a)]??0; $bv=$order[cm_education_status($b)]??0; $cmp=$av<=>$bv; }
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
.education-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.education-tabs .nav-link{min-width:130px;text-align:center}.education-filter{display:grid;grid-template-columns:2fr repeat(5,minmax(120px,1fr)) auto;gap:.65rem;align-items:end}.education-table{min-width:1240px}.education-table td{vertical-align:middle}.education-table th a{color:inherit;text-decoration:none;white-space:nowrap}.education-table th a:hover{color:var(--bs-primary)}.education-appendix{display:inline-flex;align-items:center;justify-content:center;min-width:76px;padding:.28rem .55rem;border-radius:999px;background:#e8f1fb;color:#164f7b;font-size:.78rem;font-weight:800}.education-status{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .58rem;border-radius:999px;font-size:.78rem;font-weight:700}.education-status.pending{background:#fff3cd;color:#664d03}.education-status.approved{background:#d1e7dd;color:#0f5132}.education-meta{font-size:.78rem;line-height:1.45;color:#64748b}.education-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}.education-form-grid .wide{grid-column:1/-1}.education-stat-toggle{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem}.education-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem}.education-summary-card{border:1px solid #dbe6ef;border-radius:14px;padding:.9rem;background:#fff}.education-summary-card small{color:#64748b}.education-summary-card strong{display:block;font-size:1.5rem;line-height:1.1;margin-top:.25rem}.education-stat-detail{max-height:390px;overflow:auto}.education-stat-detail table{font-size:.82rem}.education-stat-detail th,.education-stat-detail td{white-space:nowrap}
@media(max-width:1200px){.education-filter{grid-template-columns:1fr 1fr 1fr}.education-filter .search{grid-column:1/-1}.education-summary{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.education-filter{grid-template-columns:1fr 1fr}.education-form-grid{grid-template-columns:1fr}.education-toolbar{align-items:flex-start;flex-direction:column}.education-summary{grid-template-columns:1fr 1fr}}
@media(max-width:575px){.education-filter{grid-template-columns:1fr}.education-filter .search{grid-column:auto}.education-stat-toggle{align-items:stretch;flex-direction:column}.education-summary{grid-template-columns:1fr 1fr}}
.education-status.rejected{background:#f8d7da;color:#842029}.education-bulk-bar{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;padding:.7rem .85rem;margin-bottom:1rem;border:1px solid #cfe2ff;border-radius:12px;background:#f5f9ff}.education-select-cell{width:42px;text-align:center}.education-rejection-reason{max-width:260px;white-space:normal;font-size:.75rem;color:#842029;margin-top:.3rem}
</style>

<div class="education-toolbar">
  <div><h3 class="mb-1"><i class="bi bi-file-earmark-check text-primary"></i> Kế hoạch giáo dục</h3><div class="text-muted">Nộp và duyệt văn bản PDF ký số theo Phụ lục I, II, III</div></div>
  <?php if($educationTeacher!==''&&$educationAssignments):?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#educationPlanModal" onclick="resetEducationForm()"><i class="bi bi-cloud-arrow-up"></i> Nhập kế hoạch</button><?php endif;?>
</div>
<?php if($educationIsAdmin||$educationIsLeader): ?>
<form id="educationBulkForm" method="post" class="education-bulk-bar">
  <input type="hidden" name="csrf" value="<?=e($educationCsrf)?>">
  <input type="hidden" name="action" value="bulk_plan">
  <input type="hidden" name="appendix" value="<?=e($educationAppendix ?? 'I')?>">
  <input type="hidden" name="bulk_operation" id="educationBulkOperation">
  <input type="hidden" name="bulk_reject_reason" id="educationBulkRejectReason">
  <span class="fw-semibold me-1"><i class="bi bi-check2-square me-1"></i><span id="educationSelectedCount">0</span> đã chọn</span>
  <button class="btn btn-sm btn-success" type="button" onclick="submitEducationBulk('approve')"><i class="bi bi-check2-circle"></i> Duyệt</button>
  <button class="btn btn-sm btn-outline-warning" type="button" onclick="submitEducationBulk('reject')"><i class="bi bi-x-circle"></i> Từ chối</button>
  <?php if($educationIsAdmin): ?><button class="btn btn-sm btn-outline-danger" type="button" onclick="submitEducationBulk('delete')"><i class="bi bi-trash"></i> Xóa</button><?php endif; ?>
  <small class="text-muted ms-auto"><?= $educationIsAdmin ? 'Quản trị: duyệt, từ chối hoặc xóa hàng loạt.' : 'TTCM: duyệt hoặc từ chối kế hoạch trong tổ.' ?></small>
</form>
<?php endif; ?>
<?php if($educationTeacher===''):?><div class="alert alert-warning">Tài khoản chưa liên kết với giáo viên. Quản trị cần cập nhật trường <strong>Giáo viên liên kết</strong>.</div><?php elseif($educationGroup===''):?><div class="alert alert-warning">Chưa xác định được tổ chuyên môn của <strong><?=e($educationTeacher)?></strong>.</div><?php elseif(!$educationAssignments):?><div class="alert alert-info">Không tìm thấy môn/khối được phân công cho <strong><?=e($educationTeacher)?></strong>.</div><?php endif;?>

<div class="education-stat-toggle">
  <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#educationStatistics" aria-expanded="false" aria-controls="educationStatistics"><i class="bi bi-bar-chart-line me-1"></i> Xem thống kê đã nộp <span class="badge text-bg-primary ms-1"><?= (int)$educationStats['total'] ?></span><i class="bi bi-chevron-down ms-1"></i></button>
  <small class="text-muted"><?= $educationIsAdmin ? 'Phạm vi: Toàn trường' : ($educationIsLeader ? 'Phạm vi: Tổ '.e($educationGroup) : 'Phạm vi: Cá nhân') ?></small>
</div>

<div class="collapse mb-3" id="educationStatistics">
  <div class="card"><div class="card-body">
    <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i> Số liệu dưới đây chỉ tính <strong>văn bản đã nộp thực tế</strong>, không suy ra số còn thiếu từ phân công chuyên môn do có các môn tích hợp/gộp.</div>
    <div class="education-summary mb-3">
      <div class="education-summary-card"><small>Tổng đã nộp</small><strong><?= (int)$educationStats['total'] ?></strong></div>
      <div class="education-summary-card"><small>Phụ lục I</small><strong><?= (int)$educationStats['I'] ?></strong></div>
      <div class="education-summary-card"><small>Phụ lục II</small><strong><?= (int)$educationStats['II'] ?></strong></div>
      <div class="education-summary-card"><small>Phụ lục III</small><strong><?= (int)$educationStats['III'] ?></strong></div>
      <div class="education-summary-card"><small>Đã duyệt</small><strong><?= (int)$educationStats['approved'] ?></strong></div>
      <div class="education-summary-card"><small>Đã từ chối</small><strong class="text-danger"><?= (int)$educationStats['rejected'] ?></strong></div>
    </div>

    <div class="border rounded-3 overflow-hidden mb-3">
      <div class="px-3 py-2 bg-light fw-semibold">Số phụ lục đã nộp theo tổ</div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Tổ</th><th class="text-center">PL I</th><th class="text-center">PL II</th><th class="text-center">PL III</th><th class="text-center">Tổng</th><th class="text-center">Đã duyệt</th></tr></thead><tbody>
      <?php if(!$educationStatsByGroup): ?><tr><td colspan="6" class="text-center text-muted py-3">Chưa có dữ liệu.</td></tr><?php else: foreach($educationStatsByGroup as $stat): ?><tr><td><strong><?= e($stat['group']) ?></strong></td><td class="text-center"><?= (int)$stat['I'] ?></td><td class="text-center"><?= (int)$stat['II'] ?></td><td class="text-center"><?= (int)$stat['III'] ?></td><td class="text-center fw-bold"><?= (int)$stat['total'] ?></td><td class="text-center text-success"><?= (int)$stat['approved'] ?></td></tr><?php endforeach; endif; ?>
      </tbody></table></div>
    </div>

    <div class="border rounded-3 overflow-hidden">
      <div class="px-3 py-2 bg-light fw-semibold">Ai đã nộp và nộp môn gì</div>
      <div class="education-stat-detail"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Giáo viên</th><th>Tổ</th><th>Môn</th><th>Khối</th><th>Phụ lục</th><th>Ngày nộp</th><th>Trạng thái</th></tr></thead><tbody>
      <?php if(!$educationSubmittedRows): ?><tr><td colspan="7" class="text-center text-muted py-3">Chưa có kế hoạch đã nộp.</td></tr><?php else: foreach($educationSubmittedRows as $row): $approved=!empty($row['approved_at']); ?><tr><td><strong><?= e($row['teacher'] ?? '') ?></strong></td><td><?= e($row['teacher_group'] ?? '') ?></td><td><?= e($row['subject'] ?? '') ?></td><td><?= e($row['grade'] ?? '') ?></td><td><span class="education-appendix">Phụ lục <?= e($row['appendix'] ?? '') ?></span></td><td><?= !empty($row['submitted_at']) ? e(date('d/m/Y H:i',strtotime($row['submitted_at']))) : '—' ?></td><td><?= $approved ? '<span class="education-status approved">Đã duyệt</span>' : '<span class="education-status pending">Chờ duyệt</span>' ?></td></tr><?php endforeach; endif; ?>
      </tbody></table></div>
    </div>
  </div></div>
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
<?php if($educationIsAdmin||$educationIsLeader): ?>
<script>
(function(){
  var table=document.querySelector('.education-table'),form=document.getElementById('educationBulkForm');
  if(!table||!form)return;
  var tableCard=table.closest('.card');if(tableCard)tableCard.parentNode.insertBefore(form,tableCard);
  var planRows=<?=json_encode(array_map(function($row){return ['id'=>(string)($row['id']??''),'status'=>cm_education_status($row),'rejected_at'=>(string)($row['rejected_at']??''),'rejected_by'=>(string)($row['rejected_by']??''),'reason'=>(string)($row['rejection_reason']??'')];},$educationList),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
  var headRow=table.tHead&&table.tHead.rows[0];
  if(headRow){var th=document.createElement('th');th.className='education-select-cell';th.innerHTML='<input class="form-check-input" type="checkbox" id="educationSelectAll" title="Chọn tất cả">';headRow.insertBefore(th,headRow.firstChild)}
  Array.from(table.tBodies[0].rows).forEach(function(row,index){
    if(!planRows[index]){var empty=row.cells[0];if(empty)empty.colSpan=(parseInt(empty.colSpan||'9',10)+1);return}
    var info=planRows[index],timeCell=row.cells[6],statusCell=row.cells[7];
    if(info.status==='rejected'){
      statusCell.innerHTML='<span class="education-status rejected"><i class="bi bi-x-circle-fill"></i> Từ chối</span><div class="education-rejection-reason"></div>';
      statusCell.querySelector('.education-rejection-reason').textContent=info.reason||'Không có lý do';
      if(timeCell&&info.rejected_at){var date=new Date(info.rejected_at);var stamp=isNaN(date.getTime())?'':date.toLocaleString('vi-VN',{hour:'2-digit',minute:'2-digit',day:'2-digit',month:'2-digit',year:'numeric'});timeCell.innerHTML+='<br><strong>Từ chối:</strong> '+stamp+(info.rejected_by?'<br><span></span>':'');if(info.rejected_by){timeCell.querySelector('span:last-child').textContent=info.rejected_by}}
    }
    var td=document.createElement('td');td.className='education-select-cell';td.innerHTML='<input class="form-check-input education-row-check" type="checkbox" name="plan_ids[]" form="educationBulkForm">';td.querySelector('input').value=info.id;row.insertBefore(td,row.firstChild)
  });
  var all=document.getElementById('educationSelectAll'),checks=function(){return Array.from(document.querySelectorAll('.education-row-check'))},count=document.getElementById('educationSelectedCount');
  function refresh(){var selected=checks().filter(function(c){return c.checked}).length;count.textContent=selected;if(all){all.checked=selected>0&&selected===checks().length;all.indeterminate=selected>0&&selected<checks().length}}
  if(all)all.addEventListener('change',function(){checks().forEach(function(c){c.checked=all.checked});refresh()});
  checks().forEach(function(c){c.addEventListener('change',refresh)});refresh();
  var statusSelect=document.querySelector('select[name="status"]');
  if(statusSelect&&!Array.from(statusSelect.options).some(function(o){return o.value==='rejected'})){var option=document.createElement('option');option.value='rejected';option.textContent='Đã từ chối';statusSelect.appendChild(option)}
  if(statusSelect&&<?=json_encode($educationFilterStatus)?>==='rejected')statusSelect.value='rejected';
})();
function submitEducationBulk(operation){
  var selected=document.querySelectorAll('.education-row-check:checked');
  if(!selected.length){alert('Vui lòng chọn ít nhất một kế hoạch.');return}
  var reason='';
  if(operation==='reject'){reason=prompt('Nhập lý do từ chối để giáo viên biết nội dung cần sửa:','');if(reason===null)return;reason=reason.trim();if(!reason){alert('Vui lòng nhập lý do từ chối.');return}}
  var messages={approve:'Duyệt '+selected.length+' kế hoạch đã chọn?',reject:'Từ chối '+selected.length+' kế hoạch đã chọn?',delete:'Xóa vĩnh viễn '+selected.length+' kế hoạch đã chọn?'};
  if(!confirm(messages[operation]||'Thực hiện thao tác?'))return;
  document.getElementById('educationBulkOperation').value=operation;
  document.getElementById('educationBulkRejectReason').value=reason;
  document.getElementById('educationBulkForm').submit();
}
</script>
<?php endif; ?>
<?php require __DIR__.'/footer.php'; ?>
