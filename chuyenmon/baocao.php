<?php
$page_title = 'Báo cáo chuyên môn';
require_once 'includes/functions.php';
require_once 'includes/cm_docs.php';
require_once 'includes/lesson_book_store.php';

$tabs = [
    'dinhky' => ['Báo cáo định kỳ', 'bi-calendar-month', 'cm.baocao.dinhky'],
    'tiendo' => ['Tiến độ chương trình', 'bi-graph-up', 'cm.baocao.tiendo'],
    'dugio' => ['Dự giờ', 'bi-eye', 'cm.baocao.dugio'],
    'kythi' => ['Dữ liệu cuộc thi cũ', 'bi-archive', 'cm.baocao.kythi'],
];
$requestedTab = $_GET['tab'] ?? '';
if ($requestedTab === 'thang') $requestedTab = 'dinhky';
$tab = isset($tabs[$requestedTab]) ? $requestedTab : '';
if ($tab === '') {
    foreach ($tabs as $tabKey => $tabInfo) {
        if (cds_can_feature($tabInfo[2], 'view')) { $tab = $tabKey; break; }
    }
}
if ($tab === '') $tab = 'dinhky';
$_GET['tab'] = $tab;
require_login();
if ($tab === 'dugio') {
    header('Location: ' . BASE_URL . 'dugio.php');
    exit;
}

$tabs = array_filter($tabs, fn($tabInfo) => cds_can_feature($tabInfo[2], 'view'));
$section = 'bc_' . $tab;
$teachers = get_teachers_sorted();

function cm_progress_assignment_key($assignment) {
    return substr(sha1(
        trim($assignment['teacher'] ?? '') . '|' .
        trim($assignment['class'] ?? '') . '|' .
        trim($assignment['subject'] ?? '')
    ), 0, 20);
}
function cm_progress_school_year() {
    $file = dirname(__DIR__) . '/data/school_years.json';
    $years = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($years)) $years = [];
    foreach ($years as $year) if (!empty($year['is_current'])) return $year;
    return $years[0] ?? [
        'id' => 'default', 'label' => date('Y') . '–' . (date('Y') + 1),
        'start' => date('Y') . '-09-01', 'end' => (date('Y') + 1) . '-05-31',
    ];
}
function cm_progress_weeks($year) {
    $start = $year['start'] ?? '';
    $end = $year['end'] ?? '';
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $start) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $end)) return [];
    $saved = [];
    foreach (($year['weeks'] ?? []) as $row) {
        $number = (int)($row['number'] ?? 0);
        if ($number > 0 && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $row['start'] ?? '')) $saved[$number] = $row['start'];
    }
    $weeks = [];
    $cursor = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    for ($number = 1; $number <= 60 && $cursor <= $endDate; $number++) {
        if (isset($saved[$number])) $cursor = new DateTimeImmutable($saved[$number]);
        $weeks[] = [
            'number' => $number,
            'start' => $cursor->format('Y-m-d'),
            'end' => $cursor->modify('+6 days')->format('Y-m-d'),
        ];
        $cursor = $cursor->modify('+7 days');
    }
    return $weeks;
}
function cm_progress_num($value) {
    return is_numeric($value) ? max(0, (float)$value) : 0;
}
function cm_progress_name_key($value) {
    $value = trim(preg_replace('/\s+/u', ' ', (string)$value));
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
function cm_progress_diff_class($diff) {
    if ($diff >= 2) return 'danger';
    if ($diff > 0) return 'warning';
    if ($diff <= -2) return 'danger';
    if ($diff < 0) return 'warning';
    return 'success';
}
function cm_progress_scope_key($class, $subject) {
    return substr(sha1(cm_progress_name_key($class) . '|' . lb_subject_key((string)$subject)), 0, 20);
}
function cm_progress_status($diff) {
    if ($diff > 0) return 'fast';
    if ($diff < 0) return 'slow';
    return 'ontime';
}
function cm_progress_tkb_plan_index($startDate, $endDate) {
    static $cache = [];
    $cacheKey = $startDate . '|' . $endDate;
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];
    $index = [];
    $makeupSeen = [];
    foreach (tkb_weeks() as $week) {
        if (($week['start_date'] ?? '') > $endDate || ($week['end_date'] ?? '') < $startDate) continue;
        foreach (tkb_resolved_slots($week) as $slot) {
            $date = tkb_week_slot_date($week, (int)($slot['day'] ?? 2));
            if ($date < $startDate || $date > $endDate) continue;
            $class = (string)($slot['class'] ?? $slot['class_raw'] ?? '');
            $slotSubject = (string)($slot['subject'] ?? '');
            $sub = tkb_substitution_for_slot($week, $slot);
            $replacementSubject = trim((string)($sub['replacement_subject'] ?? $sub['subject'] ?? ''));
            if ($sub && $replacementSubject !== '') {
                $slotSubject = $replacementSubject;
                $subKind = tkb_key((string)($sub['kind'] ?? $sub['type'] ?? $sub['registration_type'] ?? ''));
                $subId = (string)($sub['id'] ?? '');
                if ($subId === '') $subId = sha1(json_encode($sub));
                if (str_contains($subKind, 'makeup') || str_contains($subKind, 'daybu')) $makeupSeen[$subId] = true;
            }
            $classKey = cm_progress_name_key($class);
            if ($classKey !== '' && $slotSubject !== '') $index[$classKey][$slotSubject] = ($index[$classKey][$slotSubject] ?? 0) + 1;
        }
    }
    foreach (tkb_substitutions() as $row) {
        $kind = tkb_key((string)($row['kind'] ?? $row['type'] ?? $row['registration_type'] ?? ''));
        if (tkb_substitution_status($row) !== 'approved' || (!str_contains($kind, 'makeup') && !str_contains($kind, 'daybu'))) continue;
        if (($row['date'] ?? '') < $startDate || ($row['date'] ?? '') > $endDate) continue;
        $id = (string)($row['id'] ?? sha1(json_encode($row)));
        if (!isset($makeupSeen[$id])) {
            $classKey = cm_progress_name_key((string)($row['class'] ?? ''));
            $rowSubject = trim((string)($row['replacement_subject'] ?? $row['subject'] ?? ''));
            if ($classKey !== '' && $rowSubject !== '') $index[$classKey][$rowSubject] = ($index[$classKey][$rowSubject] ?? 0) + 1;
            $makeupSeen[$id] = true;
        }
    }
    return $cache[$cacheKey] = $index;
}
function cm_progress_tkb_planned($class, $subject, $startDate, $endDate) {
    $count = 0;
    $subjects = cm_progress_tkb_plan_index($startDate, $endDate)[cm_progress_name_key((string)$class)] ?? [];
    foreach ($subjects as $rowSubject => $value) if (lb_subject_match((string)$subject, (string)$rowSubject)) $count += (int)$value;
    return $count;
}
function cm_progress_actual($class, $subject, $startDate, $endDate, $records) {
    $highest = 0;
    foreach ($records as $row) {
        if (empty($row['signed_at']) || ($row['date'] ?? '') < $startDate || ($row['date'] ?? '') > $endDate) continue;
        if (!lb_same((string)($row['class'] ?? ''), (string)$class) || !lb_subject_match((string)$subject, (string)($row['subject'] ?? ''))) continue;
        $status = (string)($row['status'] ?? '');
        if (in_array($status, ['holiday','teacher_absent','class_absent','postponed','cancelled','pending'], true)) continue;
        $highest = max($highest, (int)($row['ppct_period'] ?? 0));
    }
    return $highest;
}
function cm_progress_lesson_title($class, $subject, $period) {
    if ($period < 1) return '';
    $row = lb_curriculum_for((string)$subject, lb_grade((string)$class), (int)$period, (string)$class);
    return trim((string)($row['title'] ?? ''));
}
function cm_progress_milestone($value) {
    return min(999, max(0, (int)round(cm_progress_num($value))));
}

$progressFile = DATA_PATH . '/program_progress.json';
$progressRecords = load_json($progressFile, []);
$progressYear = cm_progress_school_year();
$progressWeeks = cm_progress_weeks($progressYear);
$progressWeekNumber = max(1, (int)($_GET['week'] ?? 0));
if (empty($_GET['week'])) {
    $today = date('Y-m-d');
    foreach ($progressWeeks as $week) {
        if ($today >= $week['start'] && $today <= $week['end']) { $progressWeekNumber = (int)$week['number']; break; }
    }
}
$progressWeek = null;
foreach ($progressWeeks as $week) if ((int)$week['number'] === $progressWeekNumber) { $progressWeek = $week; break; }
if (!$progressWeek && $progressWeeks) { $progressWeek = $progressWeeks[0]; $progressWeekNumber = 1; }

$progressAssignmentsAll = get_assignments();
$progressAssignmentMap = [];
foreach ($progressAssignmentsAll as $assignment) {
    if (empty($assignment['teacher']) || empty($assignment['class']) || empty($assignment['subject'])) continue;
    $progressAssignmentMap[cm_progress_assignment_key($assignment)] = $assignment;
}
$progressCanEdit = cds_can_feature('cm.baocao.tiendo', 'edit');
$progressIsAdmin = (($_SESSION['cds_user']['role'] ?? '') === 'admin')
    || cds_can_feature('cm.baocao.tiendo', 'delete');
$sessionTeacherName = trim($_SESSION['cds_user']['teacher_name'] ?? $_SESSION['cds_user']['name'] ?? '');
$progressTeacher = $progressIsAdmin ? trim($_GET['teacher'] ?? $sessionTeacherName) : $sessionTeacherName;
$progressView = ($progressIsAdmin && ($_GET['view'] ?? '') === 'thongke') ? 'thongke' : 'nhaplieu';
$progressStatusFilter = in_array($_GET['status'] ?? 'all', ['all','fast','slow','ontime','missing'], true) ? ($_GET['status'] ?? 'all') : 'all';
$progressSubjectFilter = trim($_GET['subject'] ?? '');
$progressTeacherFilter = trim($_GET['filter_teacher'] ?? '');
$progressFilterSubjects = [];
$progressFilterTeachers = [];
foreach ($progressAssignmentMap as $assignment) {
    $subjectName = trim($assignment['subject'] ?? '');
    $teacherName = trim($assignment['teacher'] ?? '');
    if ($subjectName !== '') $progressFilterSubjects[$subjectName] = true;
    if ($teacherName !== '') $progressFilterTeachers[$teacherName] = true;
}
$progressFilterSubjects = array_keys($progressFilterSubjects);
$progressFilterTeachers = array_keys($progressFilterTeachers);
sort($progressFilterSubjects, SORT_NATURAL | SORT_FLAG_CASE);
sort($progressFilterTeachers, SORT_NATURAL | SORT_FLAG_CASE);
$progressAssignments = array_values(array_filter($progressAssignmentMap, function($assignment) use ($progressTeacher) {
    return $progressTeacher !== '' && cm_progress_name_key($assignment['teacher'] ?? '') === cm_progress_name_key($progressTeacher);
}));
usort($progressAssignments, static function ($left, $right): int {
    $classOrder = strnatcasecmp(trim((string)($left['class'] ?? '')), trim((string)($right['class'] ?? '')));
    return $classOrder !== 0 ? $classOrder : strnatcasecmp((string)($left['subject'] ?? ''), (string)($right['subject'] ?? ''));
});
$progressMilestones = [];
foreach ($progressRecords as $record) {
    if (($record['year_id'] ?? '') !== ($progressYear['id'] ?? '')) continue;
    $scopeKey = (string)($record['scope_key'] ?? cm_progress_scope_key($record['class'] ?? '', $record['subject'] ?? ''));
    $oldTime = strtotime((string)($progressMilestones[$scopeKey]['updated_at'] ?? $progressMilestones[$scopeKey]['created_at'] ?? '')) ?: 0;
    $newTime = strtotime((string)($record['updated_at'] ?? $record['created_at'] ?? '')) ?: (int)($record['week_number'] ?? 0);
    if (!isset($progressMilestones[$scopeKey]) || $newTime >= $oldTime) $progressMilestones[$scopeKey] = $record;
}
$progressEndDate = (string)($progressWeek['end'] ?? date('Y-m-d'));
$progressStartDate = (string)($progressYear['start'] ?? '0000-00-00');
$progressLessonRecords = lb_rows(LB_RECORDS_FILE);
$progressAuto = [];
foreach ($progressAssignmentMap as $assignmentKey => $assignment) {
    $class = (string)($assignment['class'] ?? '');
    $subject = (string)($assignment['subject'] ?? '');
    $scopeKey = cm_progress_scope_key($class, $subject);
    $actual = cm_progress_actual($class, $subject, $progressStartDate, $progressEndDate, $progressLessonRecords);
    $planned = cm_progress_tkb_planned($class, $subject, $progressStartDate, $progressEndDate);
    $progressAuto[$assignmentKey] = [
        'scope_key' => $scopeKey,
        'standard_weekly' => cm_progress_num($assignment['periods'] ?? 0),
        'planned_period' => $planned,
        'actual_period' => $actual,
        'diff' => $actual - $planned,
        'lesson_title' => cm_progress_lesson_title($class, $subject, $actual),
        'milestones' => $progressMilestones[$scopeKey] ?? [],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'progress_save') {
        if (!$progressCanEdit) {
            http_response_code(403);
            exit('Tài khoản chỉ có quyền xem, chưa được cấp quyền sửa Tiến độ chương trình.');
        }
        $teacherName = $progressIsAdmin ? trim($_POST['teacher'] ?? '') : $sessionTeacherName;
        $allowed = [];
        foreach ($progressAssignmentMap as $key => $assignment) {
            if (cm_progress_name_key($assignment['teacher'] ?? '') === cm_progress_name_key($teacherName)) $allowed[$key] = $assignment;
        }
        $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
        $savedCount = 0;
        foreach ($rows as $assignmentKey => $values) {
            if (!isset($allowed[$assignmentKey]) || !is_array($values)) continue;
            $assignment = $allowed[$assignmentKey];
            $scopeKey = cm_progress_scope_key($assignment['class'] ?? '', $assignment['subject'] ?? '');
            $payload = [
                'id' => 'pgm_' . substr(sha1(($progressYear['id'] ?? '') . '|' . $scopeKey), 0, 16),
                'year_id' => $progressYear['id'] ?? '',
                'year_label' => $progressYear['label'] ?? '',
                'scope_key' => $scopeKey,
                'assignment_key' => $assignmentKey,
                'teacher' => $assignment['teacher'] ?? '',
                'class' => $assignment['class'] ?? '',
                'subject' => $assignment['subject'] ?? '',
                'mid_hk1' => cm_progress_milestone($values['mid_hk1'] ?? 0),
                'final_hk1' => cm_progress_milestone($values['final_hk1'] ?? 0),
                'mid_hk2' => cm_progress_milestone($values['mid_hk2'] ?? 0),
                'final_hk2' => cm_progress_milestone($values['final_hk2'] ?? 0),
                'updated_by' => $_SESSION['cds_user']['name'] ?? $teacherName,
                'updated_at' => date('c'),
            ];
            $keptRecords = [];
            $createdAt = '';
            foreach ($progressRecords as $record) {
                $recordScope = (string)($record['scope_key'] ?? cm_progress_scope_key($record['class'] ?? '', $record['subject'] ?? ''));
                if (($record['year_id'] ?? '') === ($progressYear['id'] ?? '') && $recordScope === $scopeKey) {
                    $candidateCreatedAt = (string)($record['created_at'] ?? '');
                    if ($candidateCreatedAt !== '' && ($createdAt === '' || $candidateCreatedAt < $createdAt)) $createdAt = $candidateCreatedAt;
                    continue;
                }
                $keptRecords[] = $record;
            }
            $payload['created_at'] = $createdAt !== '' ? $createdAt : date('c');
            $keptRecords[] = $payload;
            $progressRecords = $keptRecords;
            $savedCount++;
        }
        save_json($progressFile, array_values($progressRecords));
        flash('Đã lưu 4 mốc kiểm tra năm học cho ' . $savedCount . ' môn/lớp. Các số liệu tiến độ được hệ thống tự tính.');
        header('Location: ' . BASE_URL . 'baocao.php?tab=tiendo&week=' . $progressWeekNumber . '&teacher=' . urlencode($teacherName));
        exit;
    }
    if ($action === 'save') {
        $file = cds_storage_handle_upload('file', 'plans');
        $oldFile = trim($_POST['file_path'] ?? '');
        $kind = trim($_POST['kind'] ?? 'report');
        $hasDeadline = !empty($_POST['has_deadline']);
        $hasAssignees = !empty($_POST['has_assignees']);
        $assignees = [];
        if ($hasAssignees && !empty($_POST['assignees']) && is_array($_POST['assignees'])) {
            $assignees = array_values(array_filter(array_map('trim', $_POST['assignees'])));
        }
        cm_doc_save([
            'id' => trim($_POST['id'] ?? ''),
            'section' => $section,
            'kind' => $kind,
            'parent_id' => trim($_POST['parent_id'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'date' => trim($_POST['date'] ?? date('Y-m-d')),
            'month' => trim($_POST['month'] ?? ''),
            'has_deadline' => $hasDeadline,
            'due_date' => $hasDeadline ? trim($_POST['due_date'] ?? '') : '',
            'day_from' => $hasDeadline ? trim($_POST['day_from'] ?? '') : '',
            'day_to' => $hasDeadline ? trim($_POST['day_to'] ?? '') : '',
            'has_assignees' => $hasAssignees,
            'assignees' => $assignees,
            'content' => trim($_POST['content'] ?? ''),
            'link' => trim($_POST['link'] ?? ''),
            'file_path' => $file !== '' ? $file : $oldFile,
            'by' => $_SESSION['cds_user']['name'] ?? ($_SESSION['pccm_admin'] ? 'admin' : ''),
        ]);
        flash('Đã lưu.');
        $redir = BASE_URL . 'baocao.php?tab=' . urlencode($tab);
        if (!empty($_POST['parent_id'])) $redir .= '&contest=' . urlencode($_POST['parent_id']);
        header('Location: ' . $redir);
        exit;
    }
    if ($action === 'delete') {
        cm_doc_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa.', 'warning');
        header('Location: ' . BASE_URL . 'baocao.php?tab=' . urlencode($tab));
        exit;
    }
}

$all = cm_docs_by_section($section);
if ($tab === 'dinhky') {
    $all = array_merge($all, cm_docs_by_section('bc_thang'));
    usort($all, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
}

$contest_id = $_GET['contest'] ?? '';
$contests = [];
$results = [];
$items = [];
if ($tab === 'kythi') {
    foreach ($all as $r) {
        if (($r['kind'] ?? 'contest') === 'result' || !empty($r['parent_id'])) $results[] = $r;
        else $contests[] = $r;
    }
} else {
    $items = $all;
}

require_once 'includes/header.php';
$navTabs=$tabs;unset($navTabs['kythi']);

function cm_view_btns($it) {
    $html = '<button type="button" class="btn btn-sm btn-outline-success" title="Xem" onclick=\'viewDoc(' . json_encode($it, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) . ')\'><i class="bi bi-eye"></i></button> ';
    return $html;
}
?>

<h3 class="mb-3"><i class="bi bi-file-earmark-text"></i> Báo cáo chuyên môn</h3>

<ul class="nav nav-pills gap-1 mb-4 flex-wrap">
  <?php foreach ($navTabs as $k => $info): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$k?'active':'' ?>" href="<?= BASE_URL ?>baocao.php?tab=<?= urlencode($k) ?>">
      <i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'tiendo'): ?>
<style>
.progress-toolbar{
  display:grid!important;
  grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
  gap:1rem;
  align-items:end;
  width:100%;
  margin-bottom:1rem
}
.progress-toolbar>div{min-width:0!important;width:100%;margin:0!important}
.progress-toolbar .form-select,.progress-toolbar .btn{width:100%}
.progress-table{min-width:1180px;table-layout:fixed;font-size:.78rem}
.progress-table th{font-size:.72rem;line-height:1.15;vertical-align:middle;text-align:center;white-space:normal;padding:.45rem .25rem}
.progress-table th:nth-child(1){width:54px}.progress-table th:nth-child(2){width:92px}.progress-table th:nth-child(3){width:55px}.progress-table th:nth-child(4){width:64px}.progress-table th:nth-child(5){width:88px}.progress-table th:nth-child(6){width:64px}.progress-table th:nth-child(n+7){width:72px}
.progress-table td{vertical-align:middle;padding:.35rem .25rem;text-align:center}.progress-table input{width:58px;max-width:58px;margin:auto;text-align:center;padding:.3rem .2rem;font-weight:700}
.progress-auto{background:#eef5fb!important;color:#24445f}.progress-entry{background:#fff8dc!important}.progress-derived{font-weight:800;text-align:center;white-space:nowrap;background:#edf3f8!important}.progress-lesson{display:block;max-width:88px;margin:.2rem auto 0;color:#64748b;font-size:.68rem;line-height:1.1;white-space:normal}
.progress-summary{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:.75rem;margin-bottom:1rem}
.progress-summary .card-body{text-align:center}.progress-summary strong{font-size:1.8rem;display:block}
@media(max-width:767px){
  .progress-toolbar{grid-template-columns:1fr}
  .progress-summary{grid-template-columns:repeat(2,1fr)}
}
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h4 class="mb-1"><i class="bi bi-graph-up-arrow"></i> Tiến độ chương trình</h4>
    <div class="text-muted">Năm học <?= e($progressYear['label'] ?? '') ?> · Dữ liệu phân công chuyên môn hiện hành</div>
  </div>
  <?php if ($progressIsAdmin): ?>
  <div class="btn-group">
    <a class="btn <?= $progressView==='nhaplieu'?'btn-primary':'btn-outline-primary' ?>" href="<?= BASE_URL ?>baocao.php?tab=tiendo&view=nhaplieu&week=<?= $progressWeekNumber ?>&teacher=<?= urlencode($progressTeacher) ?>"><i class="bi bi-pencil-square"></i> Nhập tiến độ</a>
    <a class="btn <?= $progressView==='thongke'?'btn-primary':'btn-outline-primary' ?>" href="<?= BASE_URL ?>baocao.php?tab=tiendo&view=thongke&week=<?= $progressWeekNumber ?>"><i class="bi bi-bar-chart-line"></i> Thống kê</a>
  </div>
  <?php endif; ?>
</div>

<form method="get" class="progress-toolbar card card-body">
  <input type="hidden" name="tab" value="tiendo">
  <input type="hidden" name="view" value="<?= e($progressView) ?>">
  <div><label class="form-label fw-semibold">Tuần học</label>
    <select class="form-select" name="week" onchange="this.form.submit()">
      <?php foreach ($progressWeeks as $week): ?><option value="<?= (int)$week['number'] ?>" <?= (int)$week['number']===$progressWeekNumber?'selected':'' ?>>Tuần <?= (int)$week['number'] ?> (<?= e(date('d/m',strtotime($week['start']))) ?> - <?= e(date('d/m',strtotime($week['end']))) ?>)</option><?php endforeach; ?>
    </select>
  </div>
  <?php if ($progressIsAdmin && $progressView === 'nhaplieu'): ?>
  <div><label class="form-label fw-semibold">Giáo viên</label>
    <select class="form-select" name="teacher" onchange="this.form.submit()">
      <option value="">— Chọn giáo viên —</option>
      <?php foreach ($teachers as $teacher): ?><option value="<?= e($teacher) ?>" <?= $teacher===$progressTeacher?'selected':'' ?>><?= e($teacher) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php elseif ($progressView === 'thongke'): ?>
  <div><label class="form-label fw-semibold">Tiến độ</label>
    <select class="form-select" name="status" onchange="this.form.submit()">
      <option value="all" <?= $progressStatusFilter==='all'?'selected':'' ?>>Tất cả trạng thái</option>
      <option value="fast" <?= $progressStatusFilter==='fast'?'selected':'' ?>>Nhanh tiến độ</option>
      <option value="slow" <?= $progressStatusFilter==='slow'?'selected':'' ?>>Chậm tiến độ</option>
      <option value="ontime" <?= $progressStatusFilter==='ontime'?'selected':'' ?>>Đúng tiến độ</option>
      <option value="missing" <?= $progressStatusFilter==='missing'?'selected':'' ?>>Chưa nhập mốc kiểm tra</option>
    </select>
  </div>
  <div><label class="form-label fw-semibold">Môn học</label>
    <select class="form-select" name="subject" onchange="this.form.submit()">
      <option value="">Tất cả môn</option>
      <?php foreach ($progressFilterSubjects as $subjectName): ?><option value="<?= e($subjectName) ?>" <?= $subjectName===$progressSubjectFilter?'selected':'' ?>><?= e($subjectName) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div><label class="form-label fw-semibold">Giáo viên</label>
    <select class="form-select" name="filter_teacher" onchange="this.form.submit()">
      <option value="">Tất cả giáo viên</option>
      <?php foreach ($progressFilterTeachers as $teacherName): ?><option value="<?= e($teacherName) ?>" <?= $teacherName===$progressTeacherFilter?'selected':'' ?>><?= e($teacherName) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php if ($progressStatusFilter!=='all' || $progressSubjectFilter!=='' || $progressTeacherFilter!==''): ?>
  <div><a class="btn btn-outline-secondary w-100" href="<?= BASE_URL ?>baocao.php?tab=tiendo&view=thongke&week=<?= $progressWeekNumber ?>"><i class="bi bi-x-circle"></i> Xóa bộ lọc</a></div>
  <?php endif; ?>
  <?php endif; ?>
  <noscript><button class="btn btn-primary">Xem</button></noscript>
</form>

<?php if ($progressView === 'nhaplieu'): ?>
<?php if ($progressTeacher === ''): ?><div class="alert alert-warning">Tài khoản chưa được liên kết với tên giáo viên. Quản trị cần đặt trường <strong>Giáo viên liên kết</strong> trong phần Tài khoản.</div>
<?php elseif (!$progressAssignments): ?><div class="alert alert-info">Không tìm thấy phân công môn/lớp hiện hành của <strong><?= e($progressTeacher) ?></strong>.</div>
<?php elseif (!$progressCanEdit): ?><div class="alert alert-warning"><i class="bi bi-lock-fill"></i> Tài khoản đang có quyền xem nhưng chưa được cấp quyền sửa Tiến độ chương trình.</div>
<?php else: ?>
<form method="post">
  <input type="hidden" name="action" value="progress_save">
  <input type="hidden" name="teacher" value="<?= e($progressTeacher) ?>">
  <div class="card"><div class="card-header d-flex justify-content-between"><span><?= e($progressTeacher) ?> — Tuần <?= $progressWeekNumber ?></span><span><?= count($progressAssignments) ?> môn/lớp</span></div>
  <div class="card-body py-2 small"><i class="bi bi-info-circle text-primary"></i> Chỉ bốn cột nền vàng được nhập và lưu một lần cho cả năm học. Các cột nền xanh được lấy tự động từ PCCM, TKB, PPCT và Sổ đầu bài đã ký.</div>
  <div class="table-responsive"><table class="table table-bordered table-sm mb-0 progress-table">
    <thead><tr>
      <th>Lớp</th><th>Môn</th>
      <th>Định mức<br>tiết/tuần</th>
      <th>Tiết TKB<br>đến hết tuần</th>
      <th>PPCT thực tế<br>đã ký</th>
      <th>Nhanh/<br>chậm</th>
      <th>Kiểm tra<br>giữa HK I</th><th>Kiểm tra<br>cuối HK I</th>
      <th>Kiểm tra<br>giữa HK II</th><th>Kiểm tra<br>cuối HK II</th>
      <th>Còn đến<br>GK I</th><th>Còn đến<br>CK I</th>
      <th>Còn đến<br>GK II</th><th>Còn đến<br>CK II</th>
    </tr></thead>
    <tbody>
    <?php foreach ($progressAssignments as $assignment):
      $key=cm_progress_assignment_key($assignment);$auto=$progressAuto[$key]??[];$row=$auto['milestones']??[];
      $std=$auto['standard_weekly']??0;$actual=$auto['actual_period']??0;$planned=$auto['planned_period']??0;
      $m1=$row['mid_hk1']??0;$f1=$row['final_hk1']??0;$m2=$row['mid_hk2']??0;$f2=$row['final_hk2']??0;
      $diff=(float)($auto['diff']??0);$lesson=$auto['lesson_title']??'';
    ?>
    <tr data-progress-row data-actual="<?= e((string)$actual) ?>">
      <td><strong><?= e($assignment['class']??'') ?></strong></td><td><?= e($assignment['subject']??'') ?></td>
      <td class="progress-auto"><?= e((string)$std) ?></td>
      <td class="progress-auto"><?= e((string)$planned) ?></td>
      <td class="progress-auto"><strong><?= e((string)$actual) ?></strong><?php if($lesson!==''): ?><span class="progress-lesson" title="<?= e($lesson) ?>"><?= e($lesson) ?></span><?php endif; ?></td>
      <td class="progress-derived <?= $diff==0?'text-success':(abs($diff)>=2?'text-danger':'text-warning') ?>"><?= $diff>0?'+':'' ?><?= e((string)$diff) ?></td>
      <?php foreach (['mid_hk1'=>$m1,'final_hk1'=>$f1,'mid_hk2'=>$m2,'final_hk2'=>$f2] as $field=>$value): ?>
      <td class="progress-entry"><input class="form-control form-control-sm" type="number" min="0" max="999" step="1" inputmode="numeric" name="rows[<?= e($key) ?>][<?= e($field) ?>]" value="<?= e((string)$value) ?>" data-field="<?= e($field) ?>"></td>
      <?php endforeach; ?>
      <td class="progress-derived" data-result="mid_hk1"><?= e((string)max(0,(float)$m1-(float)$actual)) ?></td>
      <td class="progress-derived" data-result="final_hk1"><?= e((string)max(0,(float)$f1-(float)$actual)) ?></td>
      <td class="progress-derived" data-result="mid_hk2"><?= e((string)max(0,(float)$m2-(float)$actual)) ?></td>
      <td class="progress-derived" data-result="final_hk2"><?= e((string)max(0,(float)$f2-(float)$actual)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card-body text-end"><button class="btn btn-primary px-4"><i class="bi bi-floppy"></i> Lưu 4 mốc kiểm tra của năm học</button></div></div>
</form>
<script>
document.querySelectorAll('[data-progress-row]').forEach(function(row){
  function n(field){return parseFloat(row.querySelector('[data-field="'+field+'"]').value||0)}
  function show(name,value){
    var cell=row.querySelector('[data-result="'+name+'"]');cell.textContent=(Math.round(value*10)/10);
  }
  function calc(){var actual=parseFloat(row.dataset.actual||0);['mid_hk1','final_hk1','mid_hk2','final_hk2'].forEach(function(field){show(field,Math.max(0,n(field)-actual))})}
  row.querySelectorAll('input').forEach(function(input){input.addEventListener('input',calc)});calc();
});
</script>
<?php endif; ?>

<?php else:
  $statsRows=[];$fast=0;$slow=0;$onTime=0;$missing=0;
  foreach($progressAssignmentMap as $key=>$assignment){
    if($progressSubjectFilter!=='' && ($assignment['subject']??'')!==$progressSubjectFilter)continue;
    if($progressTeacherFilter!=='' && ($assignment['teacher']??'')!==$progressTeacherFilter)continue;
    $auto=$progressAuto[$key]??null;
    $record=$auto['milestones']??null;
    $diff=$auto ? (float)($auto['diff']??0) : null;
    $status=$auto===null||!$record?'missing':cm_progress_status($diff);
    if($progressStatusFilter!=='all' && $status!==$progressStatusFilter)continue;
    if($status==='missing')$missing++;elseif($status==='fast')$fast++;elseif($status==='slow')$slow++;else$onTime++;
    $statsRows[]=['assignment'=>$assignment,'record'=>$record,'auto'=>$auto,'diff'=>$diff,'status'=>$status];
  }
  usort($statsRows,function($a,$b){if($a['diff']===null)return 1;if($b['diff']===null)return -1;return abs($b['diff'])<=>abs($a['diff']);});
?>
<div class="progress-summary">
  <div class="card"><div class="card-body"><strong class="text-primary"><?= count($statsRows) ?></strong>Kết quả lọc</div></div>
  <div class="card"><div class="card-body"><strong class="text-success"><?= $onTime ?></strong>Đúng tiến độ</div></div>
  <div class="card"><div class="card-body"><strong class="text-warning"><?= $fast+$slow ?></strong>Cần điều chỉnh</div></div>
  <div class="card"><div class="card-body"><strong class="text-secondary"><?= $missing ?></strong>Chưa nhập mốc</div></div>
</div>
<?php if($fast+$slow): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Tuần <?= $progressWeekNumber ?> có <strong><?= $fast ?></strong> môn/lớp nhanh và <strong><?= $slow ?></strong> môn/lớp chậm so với kế hoạch TKB.</div><?php endif; ?>
<div class="card"><div class="card-header">Thống kê tiến độ tuần <?= $progressWeekNumber ?></div><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead><tr><th>Giáo viên</th><th>Lớp</th><th>Môn</th><th>Định mức</th><th>TKB đến tuần</th><th>PPCT đã ký</th><th>Nhanh/chậm</th><th>Trạng thái</th><th>Mốc kiểm tra</th></tr></thead><tbody>
<?php if(!$statsRows): ?><tr><td colspan="9" class="text-center text-muted py-4">Không có môn/lớp phù hợp với bộ lọc.</td></tr><?php endif; ?>
<?php foreach($statsRows as $item):$a=$item['assignment'];$r=$item['record'];$auto=$item['auto'];$d=$item['diff']; ?>
<tr class="<?= $d!==null&&abs($d)>=2?'table-danger':($d!==null&&$d!=0?'table-warning':'') ?>">
<td><strong><?= e($a['teacher']??'') ?></strong></td><td><?= e($a['class']??'') ?></td><td><?= e($a['subject']??'') ?></td>
<?php if(!$auto): ?><td colspan="6" class="text-muted">Chưa có dữ liệu tự động</td>
<?php else: ?><td><?= e((string)($auto['standard_weekly']??0)) ?></td><td><?= e((string)($auto['planned_period']??0)) ?></td><td><strong><?= e((string)($auto['actual_period']??0)) ?></strong><?php if(!empty($auto['lesson_title'])):?><div class="small text-muted"><?=e($auto['lesson_title'])?></div><?php endif;?></td><td class="fw-bold <?= $d==0?'text-success':(abs($d)>=2?'text-danger':'text-warning') ?>"><?= $d>0?'+':'' ?><?= e((string)$d) ?></td>
<td><?php if($d>0): ?><span class="badge bg-warning text-dark">Nhanh <?= e((string)$d) ?> tiết</span><?php elseif($d<0): ?><span class="badge bg-danger">Chậm <?= e((string)abs($d)) ?> tiết</span><?php else: ?><span class="badge bg-success">Đúng tiến độ</span><?php endif; ?></td><td class="small"><?php if($r): ?>GK1 <?=e((string)($r['mid_hk1']??0))?> · CK1 <?=e((string)($r['final_hk1']??0))?><br>GK2 <?=e((string)($r['mid_hk2']??0))?> · CK2 <?=e((string)($r['final_hk2']??0))?><?php else: ?><span class="text-muted">Chưa nhập mốc</span><?php endif; ?></td><?php endif; ?>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php elseif ($tab !== 'kythi'): ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card"><div class="card-header">Ghi nhận — <?= e($tabs[$tab][0]) ?></div><div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>baocao.php?tab=<?= urlencode($tab) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="kind" value="report">
        <input type="hidden" name="id" id="doc_id" value="">
        <input type="hidden" name="file_path" id="doc_file" value="">
        <div class="mb-2"><label class="form-label small fw-semibold">Tiêu đề</label>
          <input type="text" name="title" id="doc_title" class="form-control form-control-sm" required></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small fw-semibold">Ngày</label>
            <input type="date" name="date" id="doc_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-6"><label class="form-label small fw-semibold">Kỳ / tháng</label>
            <input type="month" name="month" id="doc_month" class="form-control form-control-sm"></div>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="has_deadline" value="1" id="chkDeadline" onchange="toggleDeadline()">
          <label class="form-check-label small fw-semibold" for="chkDeadline">Có hạn nộp / hạn báo cáo</label>
        </div>
        <div id="boxDeadline" class="border rounded p-2 mb-2 bg-light" style="display:none">
          <div class="mb-2">
            <label class="form-label small">Hạn (ngày cụ thể)</label>
            <input type="date" name="due_date" id="doc_due" class="form-control form-control-sm">
          </div>
          <?php if ($tab === 'dinhky'): ?>
          <div class="row g-2">
            <div class="col-6"><label class="form-label small">Từ ngày (hàng tháng)</label>
              <input type="number" name="day_from" id="doc_from" class="form-control form-control-sm" min="1" max="31" placeholder="22"></div>
            <div class="col-6"><label class="form-label small">Đến ngày</label>
              <input type="number" name="day_to" id="doc_to" class="form-control form-control-sm" min="1" max="31" placeholder="25"></div>
          </div>
          <?php else: ?>
          <input type="hidden" name="day_from" id="doc_from" value="">
          <input type="hidden" name="day_to" id="doc_to" value="">
          <?php endif; ?>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="has_assignees" value="1" id="chkAssign" onchange="toggleAssign()">
          <label class="form-check-label small fw-semibold" for="chkAssign">Chỉ định người thực hiện</label>
        </div>
        <div id="boxAssign" class="border rounded p-2 mb-2 bg-light" style="display:none">
          <select name="assignees[]" id="doc_assignees" class="form-select form-select-sm" multiple size="7">
            <?php foreach ($teachers as $t): ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Ctrl/Cmd + click để chọn nhiều GV.</div>
        </div>

        <div class="mb-2"><label class="form-label small fw-semibold">Nội dung</label>
          <textarea name="content" id="doc_content" class="form-control form-control-sm" rows="4"></textarea></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Chèn link</label>
          <input type="url" name="link" id="doc_link" class="form-control form-control-sm" placeholder="https://…"></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Tải file</label>
          <input type="file" name="file" class="form-control form-control-sm"></div>
        <button class="btn btn-primary btn-sm w-100" type="submit">Lưu</button>
        <button class="btn btn-outline-secondary btn-sm w-100 mt-1" type="button" onclick="resetForm()">Làm mới</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header"><?= e($tabs[$tab][0]) ?> (<?= count($items) ?>)</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Ngày</th><th>Hạn</th><th>Tiêu đề</th><th>Người TH</th><th></th></tr></thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="5" class="text-muted text-center py-4">Chưa có mục nào.</td></tr>
        <?php else: foreach ($items as $it):
          $dl = (!empty($it['has_deadline']) || !empty($it['due_date']) || !empty($it['day_from'])) ? cm_resolve_deadline($it) : null;
          $asg = $it['assignees'] ?? []; if (!is_array($asg)) $asg = $asg ? [$asg] : [];
        ?>
          <tr>
            <td class="small text-nowrap"><?= e($it['date'] ?? '') ?></td>
            <td class="small"><?php if ($dl): ?><?= e(date('d/m/Y', strtotime($dl['due_date']))) ?><?php if (!empty($dl['window'])): ?><div class="text-muted"><?= e($dl['window']) ?></div><?php endif; ?><?php else: ?>—<?php endif; ?></td>
            <td><strong><?= e($it['title'] ?? '') ?></strong></td>
            <td class="small"><?= $asg ? e(implode(', ', $asg)) : '—' ?></td>
            <td class="text-nowrap">
              <?= cm_view_btns($it) ?>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick='editDoc(<?= json_encode($it, JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
              <form method="post" class="d-inline" action="<?= BASE_URL ?>baocao.php?tab=<?= urlencode($tab) ?>" onsubmit="return confirm('Xóa?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($it['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<?php else: ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">Tạo kỳ thi / cuộc thi</div><div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>baocao.php?tab=kythi">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="kind" value="contest">
        <div class="mb-2"><label class="form-label small fw-semibold">Tên kỳ thi</label>
          <input type="text" name="title" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Ngày</label>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Mô tả</label>
          <textarea name="content" class="form-control form-control-sm" rows="3"></textarea></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Link</label>
          <input type="url" name="link" class="form-control form-control-sm"></div>
        <div class="mb-3"><label class="form-label small fw-semibold">File</label>
          <input type="file" name="file" class="form-control form-control-sm"></div>
        <button class="btn btn-primary btn-sm w-100" type="submit">Lưu kỳ thi</button>
      </form>
    </div></div>
    <?php if ($contest_id):
      $ct = null;
      foreach ($contests as $c) if (($c['id']??'') === $contest_id) { $ct = $c; break; }
    ?>
    <div class="card"><div class="card-header bg-success">Nhập kết quả — <?= e($ct['title'] ?? '') ?></div><div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>baocao.php?tab=kythi&contest=<?= urlencode($contest_id) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="kind" value="result">
        <input type="hidden" name="parent_id" value="<?= e($contest_id) ?>">
        <div class="mb-2"><label class="form-label small fw-semibold">Tiêu đề kết quả</label>
          <input type="text" name="title" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Ngày</label>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Nội dung</label>
          <textarea name="content" class="form-control form-control-sm" rows="4"></textarea></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Link</label>
          <input type="url" name="link" class="form-control form-control-sm"></div>
        <div class="mb-3"><label class="form-label small fw-semibold">File</label>
          <input type="file" name="file" class="form-control form-control-sm"></div>
        <button class="btn btn-success btn-sm w-100" type="submit">Lưu kết quả</button>
        <a href="<?= BASE_URL ?>baocao.php?tab=kythi" class="btn btn-outline-secondary btn-sm w-100 mt-1">Đóng</a>
      </form>
    </div></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header">Danh sách kỳ thi (<?= count($contests) ?>)</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Ngày</th><th>Kỳ thi</th><th>Kết quả</th><th></th></tr></thead>
        <tbody>
        <?php if (!$contests): ?>
          <tr><td colspan="4" class="text-muted text-center py-4">Chưa có kỳ thi.</td></tr>
        <?php else: foreach ($contests as $c):
          $nRes = count(array_filter($results, fn($r) => ($r['parent_id']??'') === ($c['id']??'')));
        ?>
          <tr class="<?= $contest_id===($c['id']??'')?'table-success':'' ?>">
            <td class="small"><?= e($c['date']??'') ?></td>
            <td><strong><?= e($c['title']??'') ?></strong></td>
            <td><span class="badge bg-secondary"><?= $nRes ?></span></td>
            <td class="text-nowrap">
              <?= cm_view_btns($c) ?>
              <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>baocao.php?tab=kythi&contest=<?= urlencode($c['id']) ?>"><i class="bi bi-plus-lg"></i> Kết quả</a>
              <form method="post" class="d-inline" action="<?= BASE_URL ?>baocao.php?tab=kythi" onsubmit="return confirm('Xóa?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php if ($contest_id === ($c['id']??'')): foreach ($results as $r): if (($r['parent_id']??'') !== $c['id']) continue; ?>
          <tr class="table-light">
            <td class="small ps-4"><?= e($r['date']??'') ?></td>
            <td class="ps-4"><?= e($r['title']??'') ?></td>
            <td></td>
            <td><?= cm_view_btns($r) ?>
              <form method="post" class="d-inline" action="<?= BASE_URL ?>baocao.php?tab=kythi&contest=<?= urlencode($contest_id) ?>" onsubmit="return confirm('Xóa?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="viewTitle">Xem</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="small text-muted mb-2" id="viewMeta"></div>
    <div id="viewAssignees" class="mb-2 small"></div>
    <div id="viewContent" style="white-space:pre-wrap"></div>
    <div class="mt-3" id="viewLinks"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
</div></div></div>

<script>
function toggleDeadline(){
  var b=document.getElementById('boxDeadline');
  if(b) b.style.display=document.getElementById('chkDeadline').checked?'block':'none';
}
function toggleAssign(){
  var b=document.getElementById('boxAssign');
  if(b) b.style.display=document.getElementById('chkAssign').checked?'block':'none';
}
function resetForm(){
  ['doc_id','doc_file','doc_title','doc_content','doc_link','doc_month','doc_due','doc_from','doc_to'].forEach(function(id){var el=document.getElementById(id);if(el)el.value='';});
  var d=document.getElementById('doc_date'); if(d) d.value='<?= date('Y-m-d') ?>';
  var c1=document.getElementById('chkDeadline'); if(c1){c1.checked=false;toggleDeadline();}
  var c2=document.getElementById('chkAssign'); if(c2){c2.checked=false;toggleAssign();}
  var sel=document.getElementById('doc_assignees'); if(sel) Array.from(sel.options).forEach(function(o){o.selected=false;});
}
function editDoc(it){
  document.getElementById('doc_id').value=it.id||'';
  document.getElementById('doc_file').value=it.file_path||'';
  document.getElementById('doc_title').value=it.title||'';
  document.getElementById('doc_date').value=it.date||'';
  var m=document.getElementById('doc_month'); if(m) m.value=it.month||'';
  document.getElementById('doc_content').value=it.content||'';
  document.getElementById('doc_link').value=it.link||'';
  var hasDl=!!(it.has_deadline||it.due_date||it.day_from);
  var c1=document.getElementById('chkDeadline'); if(c1){c1.checked=hasDl;toggleDeadline();}
  var due=document.getElementById('doc_due'); if(due) due.value=it.due_date||'';
  var f=document.getElementById('doc_from'); if(f) f.value=it.day_from||'';
  var t=document.getElementById('doc_to'); if(t) t.value=it.day_to||'';
  var asg=it.assignees||[]; if(typeof asg==='string') asg=asg?[asg]:[];
  var c2=document.getElementById('chkAssign'); if(c2){c2.checked=!!(it.has_assignees||asg.length);toggleAssign();}
  var sel=document.getElementById('doc_assignees');
  if(sel) Array.from(sel.options).forEach(function(o){o.selected=asg.indexOf(o.value)>=0;});
  window.scrollTo({top:0,behavior:'smooth'});
}
function viewDoc(it){
  document.getElementById('viewTitle').textContent=it.title||'Xem';
  document.getElementById('viewMeta').textContent=(it.date||'')+(it.due_date?' · Hạn '+it.due_date:'');
  var asg=it.assignees||[];
  document.getElementById('viewAssignees').innerHTML=asg.length?'<strong>Người TH:</strong> '+asg.join(', '):'';
  document.getElementById('viewContent').textContent=it.content||'(Không có nội dung)';
  var links='';
  if(it.link) links+='<a class="btn btn-sm btn-outline-primary me-2" href="'+it.link+'" target="_blank">Link</a>';
  if(it.file_path) links+='<a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>data/'+it.file_path+'" target="_blank">File</a>';
  document.getElementById('viewLinks').innerHTML=links||'<span class="text-muted">Không có file/link</span>';
  new bootstrap.Modal(document.getElementById('viewModal')).show();
}
</script>
<?php require_once 'includes/footer.php'; ?>
