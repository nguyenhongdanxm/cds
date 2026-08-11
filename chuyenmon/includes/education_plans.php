<?php
/**
 * Nộp và duyệt Kế hoạch giáo dục theo Phụ lục I, II, III.
 * Được include từ kehoach.php sau khi require_login().
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
    if (preg_match('/(?:^|\D)(1[0-2]|[6-9])(?:\D|$)/u', $class, $match)) return $match[1];
    if (preg_match('/^(1[0-2]|[6-9])/u', $class, $match)) return $match[1];
    return $class;
}

function cm_education_load(string $file): array {
    $rows = load_json($file, []);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

function cm_education_save(string $file, array $rows): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return false;
    return file_put_contents($file, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function cm_education_file_url(string $path): string {
    if ($path === '') return '';
    if (str_starts_with($path, 'gdrive:')) return cds_storage_file_url($path);
    if (preg_match('#^https?://#i', $path)) return $path;
    return BASE_URL . ltrim($path, '/');
}

function cm_education_upload_pdf(string $field): string {
    $upload = $_FILES[$field] ?? null;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Tải tệp không thành công.');
    $name = basename((string)($upload['name'] ?? 'ke-hoach.pdf'));
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $signature = $tmp !== '' ? file_get_contents($tmp, false, null, 0, 5) : false;
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || $signature !== '%PDF-') {
        throw new RuntimeException('Chỉ chấp nhận văn bản PDF đã ký số.');
    }
    if ($size <= 0 || $size > 25 * 1024 * 1024) throw new RuntimeException('Tệp PDF phải có dung lượng không quá 25 MB.');

    $settings = function_exists('cds_drive_settings') ? cds_drive_settings() : [];
    $category = 'education_plans';
    // Luôn ưu tiên loại chuẩn đã được kiểm tra trong Kho Google Drive.
    // Chỉ tìm loại tùy chỉnh cùng tên để tương thích dữ liệu cũ khi loại chuẩn chưa có thư mục.
    if (empty($settings['types'][$category]['folder_id'])) {
        foreach ((array)($settings['types'] ?? []) as $key => $type) {
            if (cm_education_norm($type['label'] ?? '') === cm_education_norm('Kế hoạch giáo dục') && !empty($type['folder_id'])) {
                $category = (string)$key;
                break;
            }
        }
    }
    if (empty($settings['enabled'])) throw new RuntimeException('Kho Google Drive chưa được bật.');
    if (empty($settings['types'][$category]['folder_id'])) {
        throw new RuntimeException('Chưa chọn thư mục “Kế hoạch giáo dục” trong Kho Google Drive.');
    }
    $bytes = file_get_contents($tmp);
    if ($bytes === false) throw new RuntimeException('Không đọc được tệp PDF.');
    $result = cds_drive_upload_bytes($bytes, $name, 'application/pdf', $category);
    if (empty($result['ok'])) throw new RuntimeException($result['message'] ?? 'Không lưu được tệp vào thư mục Kế hoạch giáo dục.');
    return (string)$result['path'];
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
        echo json_encode(['ok' => $type === 'success', 'message' => (string)($flash['message'] ?? ''), 'redirect' => $redirect], JSON_UNESCAPED_UNICODE);
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
    $educationAssignments[$subject . '|' . $grade] = ['subject' => $subject, 'grade' => $grade];
}
$educationAssignments = array_values($educationAssignments);
usort($educationAssignments, fn($a, $b) => strnatcasecmp($a['subject'] . '|' . $a['grade'], $b['subject'] . '|' . $b['grade']));
$educationSubjects = array_values(array_unique(array_column($educationAssignments, 'subject')));
$educationGrades = array_values(array_unique(array_column($educationAssignments, 'grade')));
sort($educationSubjects, SORT_NATURAL | SORT_FLAG_CASE);
sort($educationGrades, SORT_NATURAL);

$educationRows = cm_education_load($educationDataFile);
if (empty($_SESSION['cm_education_csrf'])) $_SESSION['cm_education_csrf'] = bin2hex(random_bytes(24));
$educationCsrf = (string)$_SESSION['cm_education_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($educationCsrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Phiên làm việc không hợp lệ.');
    }
    $action = (string)($_POST['action'] ?? '');
    $appendix = in_array($_POST['appendix'] ?? '', ['I', 'II', 'III'], true) ? (string)$_POST['appendix'] : 'I';

    if ($action === 'save_plan') {
        if ($educationTeacher === '' || $educationGroup === '') {
            flash('Tài khoản chưa liên kết đầy đủ với giáo viên và tổ chuyên môn.', 'danger');
            cm_education_redirect($appendix);
        }
        $subject = trim((string)($_POST['subject'] ?? ''));
        $grade = trim((string)($_POST['grade'] ?? ''));
        $allowed = false;
        foreach ($educationAssignments as $assignment) {
            if ($assignment['subject'] === $subject && $assignment['grade'] === $grade) { $allowed = true; break; }
        }
        if (!$allowed) {
            flash('Môn hoặc khối không thuộc phân công chuyên môn của tài khoản.', 'danger');
            cm_education_redirect($appendix);
        }

        $id = trim((string)($_POST['id'] ?? ''));
        $found = false;
        try {
            $newFile = cm_education_upload_pdf('file');
        } catch (RuntimeException $error) {
            flash($error->getMessage(), 'danger');
            cm_education_redirect($appendix);
        }
        foreach ($educationRows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            if (!cm_education_is_visible($row, $educationIsAdmin, false, $educationTeacher, $educationGroup)
                || cm_education_norm($row['teacher'] ?? '') !== cm_education_norm($educationTeacher)) {
                http_response_code(403); exit('Không được sửa kế hoạch của giáo viên khác.');
            }
            if (!empty($row['approved_at'])) {
                flash('Kế hoạch đã được duyệt nên không thể chỉnh sửa.', 'warning');
                cm_education_redirect((string)($row['appendix'] ?? $appendix));
            }
            $row['appendix'] = $appendix;
            $row['subject'] = $subject;
            $row['grade'] = $grade;
            if ($newFile !== '') {
                $row['file_path'] = $newFile;
                $row['submitted_at'] = date('c');
            }
            if (empty($row['file_path'])) {
                flash('Vui lòng tải lên văn bản PDF đã ký số.', 'danger');
                cm_education_redirect($appendix);
            }
            $row['updated_at'] = date('c');
            $found = true;
            break;
        }
        unset($row);
        if (!$found) {
            if ($newFile === '') {
                flash('Vui lòng tải lên văn bản PDF đã ký số.', 'danger');
                cm_education_redirect($appendix);
            }
            $educationRows[] = [
                'id' => 'khdg_' . bin2hex(random_bytes(8)),
                'teacher_id' => (string)($educationUser['id'] ?? ''),
                'teacher' => $educationTeacher,
                'teacher_group' => $educationGroup,
                'appendix' => $appendix,
                'subject' => $subject,
                'grade' => $grade,
                'file_path' => $newFile,
                'submitted_at' => date('c'),
                'created_at' => date('c'),
                'updated_at' => date('c'),
                'approved_at' => '',
                'approved_by' => '',
            ];
        }
        if (!cm_education_save($educationDataFile, $educationRows)) {
            flash('Không lưu được dữ liệu kế hoạch.', 'danger');
            cm_education_redirect($appendix);
        }
        flash($found ? 'Đã cập nhật kế hoạch giáo dục.' : 'Đã nộp kế hoạch giáo dục.');
        cm_education_redirect($appendix);
    }

    if ($action === 'delete_plan') {
        $id = trim((string)($_POST['id'] ?? ''));
        $deleted = false;
        foreach ($educationRows as $index => $row) {
            if (($row['id'] ?? '') !== $id) continue;
            $owner = cm_education_norm($row['teacher'] ?? '') === cm_education_norm($educationTeacher);
            if (!$educationIsAdmin && (!$owner || !empty($row['approved_at']))) {
                http_response_code(403); exit('Không được xóa kế hoạch này.');
            }
            $appendix = (string)($row['appendix'] ?? $appendix);
            unset($educationRows[$index]);
            $deleted = true;
            break;
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
            if (!$educationIsAdmin && ($educationGroup === '' || cm_education_norm($row['teacher_group'] ?? '') !== cm_education_norm($educationGroup))) {
                http_response_code(403); exit('TTCM chỉ được duyệt kế hoạch trong tổ của mình.');
            }
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

$educationVisibleRows = array_values(array_filter($educationRows, fn($row) => cm_education_is_visible($row, $educationIsAdmin, $educationIsLeader, $educationTeacher, $educationGroup)));
$educationAppendix = in_array($_GET['appendix'] ?? '', ['I', 'II', 'III'], true) ? (string)$_GET['appendix'] : 'I';
$educationSearch = trim((string)($_GET['q'] ?? ''));
$educationFilterGroup = trim((string)($_GET['group'] ?? ''));
$educationFilterSubject = trim((string)($_GET['subject'] ?? ''));
$educationFilterGrade = trim((string)($_GET['grade'] ?? ''));

$educationFilterGroups = array_values(array_unique(array_filter(array_column($educationVisibleRows, 'teacher_group'))));
$educationFilterSubjects = array_values(array_unique(array_filter(array_column($educationVisibleRows, 'subject'))));
$educationFilterGrades = array_values(array_unique(array_filter(array_column($educationVisibleRows, 'grade'))));
sort($educationFilterGroups, SORT_NATURAL | SORT_FLAG_CASE);
sort($educationFilterSubjects, SORT_NATURAL | SORT_FLAG_CASE);
sort($educationFilterGrades, SORT_NATURAL);

$educationList = array_values(array_filter($educationVisibleRows, function($row) use ($educationAppendix, $educationSearch, $educationFilterGroup, $educationFilterSubject, $educationFilterGrade) {
    if (($row['appendix'] ?? '') !== $educationAppendix) return false;
    if ($educationFilterGroup !== '' && ($row['teacher_group'] ?? '') !== $educationFilterGroup) return false;
    if ($educationFilterSubject !== '' && ($row['subject'] ?? '') !== $educationFilterSubject) return false;
    if ($educationFilterGrade !== '' && (string)($row['grade'] ?? '') !== $educationFilterGrade) return false;
    if ($educationSearch !== '') {
        $haystack = cm_education_norm(implode(' ', [$row['teacher'] ?? '', $row['teacher_group'] ?? '', $row['subject'] ?? '', $row['grade'] ?? '']));
        if (!str_contains($haystack, cm_education_norm($educationSearch))) return false;
    }
    return true;
}));
usort($educationList, fn($a, $b) => strcmp(($b['submitted_at'] ?? ''), ($a['submitted_at'] ?? '')));

require __DIR__ . '/header.php';
?>
<style>
.education-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.education-tabs .nav-link{min-width:130px;text-align:center}.education-filter{display:grid;grid-template-columns:2fr repeat(3,minmax(130px,1fr)) auto;gap:.65rem;align-items:end}.education-table{min-width:1120px}.education-table td{vertical-align:middle}.education-status{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .58rem;border-radius:999px;font-size:.78rem;font-weight:700}.education-status.pending{background:#fff3cd;color:#664d03}.education-status.approved{background:#d1e7dd;color:#0f5132}.education-meta{font-size:.78rem;line-height:1.45;color:#64748b}.education-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}.education-form-grid .wide{grid-column:1/-1}@media(max-width:900px){.education-filter{grid-template-columns:1fr 1fr}.education-filter .search{grid-column:1/-1}.education-form-grid{grid-template-columns:1fr}.education-toolbar{align-items:flex-start;flex-direction:column}}@media(max-width:575px){.education-filter{grid-template-columns:1fr}.education-filter .search{grid-column:auto}}
</style>

<div class="education-toolbar">
  <div><h3 class="mb-1"><i class="bi bi-file-earmark-check text-primary"></i> Kế hoạch giáo dục</h3><div class="text-muted">Nộp và duyệt văn bản PDF ký số theo từng phụ lục</div></div>
  <?php if ($educationTeacher !== '' && $educationAssignments): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#educationPlanModal" onclick="resetEducationForm()"><i class="bi bi-cloud-arrow-up"></i> Nhập kế hoạch</button><?php endif; ?>
</div>

<?php if ($educationTeacher === ''): ?><div class="alert alert-warning">Tài khoản chưa liên kết với giáo viên. Quản trị cần cập nhật trường <strong>Giáo viên liên kết</strong>.</div>
<?php elseif ($educationGroup === ''): ?><div class="alert alert-warning">Chưa xác định được tổ chuyên môn của <strong><?= e($educationTeacher) ?></strong>.</div>
<?php elseif (!$educationAssignments): ?><div class="alert alert-info">Không tìm thấy môn/khối được phân công cho <strong><?= e($educationTeacher) ?></strong>.</div><?php endif; ?>

<ul class="nav nav-pills education-tabs gap-2 mb-3">
<?php foreach (['I', 'II', 'III'] as $appendix): ?>
  <li class="nav-item"><a class="nav-link <?= $educationAppendix === $appendix ? 'active' : '' ?>" href="<?= BASE_URL ?>kehoach.php?tab=vanban&appendix=<?= $appendix ?>">Phụ lục <?= $appendix ?></a></li>
<?php endforeach; ?>
</ul>

<div class="card mb-3"><div class="card-body">
  <form method="get" class="education-filter">
    <input type="hidden" name="tab" value="vanban"><input type="hidden" name="appendix" value="<?= e($educationAppendix) ?>">
    <div class="search"><label class="form-label small fw-semibold">Tìm kiếm</label><input class="form-control" name="q" value="<?= e($educationSearch) ?>" placeholder="Tên giáo viên, tổ, môn hoặc khối"></div>
    <div><label class="form-label small fw-semibold">Tổ</label><select class="form-select" name="group"><option value="">Tất cả tổ</option><?php foreach ($educationFilterGroups as $value): ?><option value="<?= e($value) ?>" <?= $value === $educationFilterGroup ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small fw-semibold">Môn</label><select class="form-select" name="subject"><option value="">Tất cả môn</option><?php foreach ($educationFilterSubjects as $value): ?><option value="<?= e($value) ?>" <?= $value === $educationFilterSubject ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small fw-semibold">Khối</label><select class="form-select" name="grade"><option value="">Tất cả khối</option><?php foreach ($educationFilterGrades as $value): ?><option value="<?= e($value) ?>" <?= (string)$value === $educationFilterGrade ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" title="Lọc"><i class="bi bi-funnel"></i></button><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>kehoach.php?tab=vanban&appendix=<?= e($educationAppendix) ?>" title="Bỏ lọc"><i class="bi bi-arrow-counterclockwise"></i></a></div>
  </form>
</div></div>

<div class="card"><div class="card-header d-flex justify-content-between"><span>Phụ lục <?= e($educationAppendix) ?></span><span><?= count($educationList) ?> văn bản</span></div><div class="table-responsive">
<table class="table table-hover mb-0 education-table"><thead><tr><th>STT</th><th>Giáo viên</th><th>Tổ</th><th>Môn</th><th>Khối</th><th>Thời gian</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php if (!$educationList): ?><tr><td colspan="8" class="text-center text-muted py-4">Chưa có kế hoạch trong Phụ lục <?= e($educationAppendix) ?>.</td></tr>
<?php else: foreach ($educationList as $index => $row): $approved = !empty($row['approved_at']); $owner = cm_education_norm($row['teacher'] ?? '') === cm_education_norm($educationTeacher); ?>
<tr>
  <td class="text-center"><?= $index + 1 ?></td><td><strong><?= e($row['teacher'] ?? '') ?></strong></td><td><?= e($row['teacher_group'] ?? '') ?></td><td><?= e($row['subject'] ?? '') ?></td><td class="text-center"><?= e($row['grade'] ?? '') ?></td>
  <td><div class="education-meta"><strong>Nộp:</strong> <?= !empty($row['submitted_at']) ? e(date('H:i d/m/Y', strtotime($row['submitted_at']))) : '—' ?><br><strong>Duyệt:</strong> <?= $approved ? e(date('H:i d/m/Y', strtotime($row['approved_at']))) : '—' ?><?php if ($approved && !empty($row['approved_by'])): ?><br><span><?= e($row['approved_by']) ?></span><?php endif; ?></div></td>
  <td><?= $approved ? '<span class="education-status approved"><i class="bi bi-check-circle-fill"></i> Đã duyệt</span>' : '<span class="education-status pending"><i class="bi bi-hourglass-split"></i> Chờ duyệt</span>' ?></td>
  <td class="text-nowrap"><a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?= e(cm_education_file_url((string)($row['file_path'] ?? ''))) ?>" title="Xem PDF"><i class="bi bi-eye"></i></a>
  <?php if ($owner && !$approved): ?><button class="btn btn-sm btn-outline-primary" type="button" title="Sửa" data-plan="<?= e(base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE))) ?>" onclick="editEducationPlan(this)"><i class="bi bi-pencil"></i></button><?php endif; ?>
  <?php if (($owner && !$approved) || $educationIsAdmin): ?><form class="d-inline" method="post" onsubmit="return confirm('Xóa kế hoạch này?')"><input type="hidden" name="csrf" value="<?= e($educationCsrf) ?>"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="<?= e($row['id'] ?? '') ?>"><input type="hidden" name="appendix" value="<?= e($educationAppendix) ?>"><button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash"></i></button></form><?php endif; ?>
  <?php if (($educationIsAdmin || $educationIsLeader) && !$approved): ?><form class="d-inline" method="post" onsubmit="return confirm('Duyệt kế hoạch này? Sau khi duyệt, giáo viên sẽ không thể sửa hoặc xóa.')"><input type="hidden" name="csrf" value="<?= e($educationCsrf) ?>"><input type="hidden" name="action" value="approve_plan"><input type="hidden" name="id" value="<?= e($row['id'] ?? '') ?>"><input type="hidden" name="appendix" value="<?= e($educationAppendix) ?>"><button class="btn btn-sm btn-success" title="Duyệt"><i class="bi bi-check2-circle"></i> Duyệt</button></form><?php endif; ?></td>
</tr>
<?php endforeach; endif; ?></tbody></table></div></div>

<div class="modal fade" id="educationPlanModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" enctype="multipart/form-data" id="educationPlanForm">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-cloud-arrow-up"></i> Nhập kế hoạch giáo dục</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><input type="hidden" name="csrf" value="<?= e($educationCsrf) ?>"><input type="hidden" name="action" value="save_plan"><input type="hidden" name="id" id="educationId">
<div class="education-form-grid"><div><label class="form-label fw-semibold">Tên giáo viên</label><input class="form-control" value="<?= e($educationTeacher) ?>" readonly></div><div><label class="form-label fw-semibold">Tổ</label><input class="form-control" value="<?= e($educationGroup) ?>" readonly></div><div><label class="form-label fw-semibold">Phụ lục</label><select class="form-select" name="appendix" id="educationAppendix" required><?php foreach (['I','II','III'] as $value): ?><option value="<?= $value ?>" <?= $value === $educationAppendix ? 'selected' : '' ?>>Phụ lục <?= $value ?></option><?php endforeach; ?></select></div>
<div><label class="form-label fw-semibold">Môn</label><select class="form-select" name="subject" id="educationSubject" required><option value="">Chọn môn</option><?php foreach ($educationSubjects as $value): ?><option value="<?= e($value) ?>"><?= e($value) ?></option><?php endforeach; ?></select></div>
<div><label class="form-label fw-semibold">Khối</label><select class="form-select" name="grade" id="educationGrade" required><option value="">Chọn khối</option><?php foreach ($educationAssignments as $assignment): ?><option value="<?= e($assignment['grade']) ?>" data-subject="<?= e($assignment['subject']) ?>"><?= e($assignment['grade']) ?></option><?php endforeach; ?></select></div>
<div class="wide"><label class="form-label fw-semibold">Văn bản PDF ký số</label><input class="form-control" type="file" name="file" id="educationFile" accept="application/pdf,.pdf"><div class="form-text" id="educationFileNote">Chỉ tải tệp PDF đã ký số, tối đa 25 MB.</div></div></div>
<div class="mt-3 p-3 border rounded bg-light" id="educationUploadProgress" hidden aria-live="polite">
  <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><span class="fw-semibold" id="educationUploadStatus"><span class="spinner-border spinner-border-sm me-2"></span>Đang chuẩn bị tải lên…</span><strong id="educationUploadPercent">0%</strong></div>
  <div class="progress" role="progressbar" aria-label="Tiến độ tải kế hoạch" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar progress-bar-striped progress-bar-animated" id="educationUploadBar" style="width:0%" aria-valuenow="0"></div></div>
  <div class="small text-muted mt-2" id="educationUploadDetail">Vui lòng giữ nguyên trang cho đến khi có thông báo thành công.</div>
</div></div>
<div class="modal-footer"><button class="btn btn-outline-secondary" id="educationCancelButton" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" id="educationSubmitButton" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên và lưu</button></div></form></div></div></div>

<script>
var educationAssignments=<?= json_encode($educationAssignments, JSON_UNESCAPED_UNICODE) ?>;
function educationFilterGrades(){var subject=document.getElementById('educationSubject').value,grade=document.getElementById('educationGrade'),current=grade.value;Array.from(grade.options).forEach(function(option,index){if(index===0)return;option.hidden=option.dataset.subject!==subject;option.disabled=option.hidden});if(!Array.from(grade.options).some(function(option){return !option.hidden&&option.value===current}))grade.value=''}
document.getElementById('educationSubject').addEventListener('change',educationFilterGrades);
function resetEducationForm(){document.getElementById('educationPlanForm').reset();document.getElementById('educationId').value='';document.getElementById('educationAppendix').value='<?= e($educationAppendix) ?>';document.getElementById('educationFile').required=true;document.getElementById('educationFileNote').textContent='Chỉ tải tệp PDF đã ký số, tối đa 25 MB.';educationFilterGrades()}
function editEducationPlan(button){try{var row=JSON.parse(decodeURIComponent(escape(atob(button.dataset.plan||''))));document.getElementById('educationId').value=row.id||'';document.getElementById('educationAppendix').value=row.appendix||'I';document.getElementById('educationSubject').value=row.subject||'';educationFilterGrades();document.getElementById('educationGrade').value=row.grade||'';document.getElementById('educationFile').required=false;document.getElementById('educationFileNote').textContent='Để trống nếu giữ nguyên PDF hiện tại; chọn tệp mới để thay thế.';bootstrap.Modal.getOrCreateInstance(document.getElementById('educationPlanModal')).show()}catch(error){alert('Không đọc được dữ liệu kế hoạch.')}}
(function(){
  var form=document.getElementById('educationPlanForm'),box=document.getElementById('educationUploadProgress'),bar=document.getElementById('educationUploadBar'),percent=document.getElementById('educationUploadPercent'),status=document.getElementById('educationUploadStatus'),detail=document.getElementById('educationUploadDetail'),submit=document.getElementById('educationSubmitButton'),cancel=document.getElementById('educationCancelButton');
  function setProgress(value){value=Math.max(0,Math.min(100,Math.round(value)));bar.style.width=value+'%';bar.setAttribute('aria-valuenow',String(value));percent.textContent=value+'%'}
  function setBusy(busy){submit.disabled=busy;cancel.disabled=busy;form.querySelectorAll('.btn-close').forEach(function(button){button.disabled=busy})}
  function fail(message){setBusy(false);bar.classList.remove('bg-success');bar.classList.add('bg-danger');status.innerHTML='<i class="bi bi-x-circle-fill text-danger me-2"></i>Tải lên chưa thành công';detail.textContent=message||'Không kết nối được máy chủ. Vui lòng thử lại.'}
  form.addEventListener('submit',function(event){
    event.preventDefault();if(!form.reportValidity())return;
    box.hidden=false;bar.className='progress-bar progress-bar-striped progress-bar-animated';setProgress(0);setBusy(true);
    status.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Đang tải PDF lên máy chủ…';detail.textContent='Vui lòng giữ nguyên trang cho đến khi có thông báo thành công.';
    var xhr=new XMLHttpRequest();xhr.open('POST',form.action||window.location.href,true);xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');xhr.timeout=300000;
    xhr.upload.addEventListener('progress',function(progress){if(progress.lengthComputable){setProgress(progress.loaded/progress.total*100);detail.textContent='Đã gửi '+Math.round(progress.loaded/1024)+' KB / '+Math.round(progress.total/1024)+' KB';}});
    xhr.upload.addEventListener('load',function(){setProgress(100);status.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Đã tải 100% — đang lưu vào Google Drive…';detail.textContent='Máy chủ đang hoàn tất lưu tệp và cập nhật danh sách.'});
    xhr.addEventListener('load',function(){if(xhr.status>=200&&xhr.status<400){var result={};try{result=JSON.parse(xhr.responseText||'{}')}catch(error){fail('Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.');return}if(!result.ok){fail(result.message||'Google Drive chưa lưu được tệp. Vui lòng thử lại.');return}bar.classList.remove('progress-bar-animated','bg-danger');bar.classList.add('bg-success');status.innerHTML='<i class="bi bi-check-circle-fill text-success me-2"></i>Tải lên thành công';detail.textContent=result.message||'Đã lưu PDF vào Google Drive. Đang mở danh sách…';setTimeout(function(){window.location.href=result.redirect||window.location.href},700);}else fail('Máy chủ trả về lỗi '+xhr.status+'. Vui lòng thử lại.')});
    xhr.addEventListener('error',function(){fail('Mất kết nối trong khi tải lên. Vui lòng kiểm tra mạng và thử lại.')});xhr.addEventListener('timeout',function(){fail('Quá thời gian chờ lưu Google Drive. Vui lòng thử lại với tệp nhỏ hơn.')});
    xhr.send(new FormData(form));
  });
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
