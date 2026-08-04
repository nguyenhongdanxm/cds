<?php
$page_title = 'Báo cáo chuyên môn';
require_once 'includes/functions.php';
require_once 'includes/cm_docs.php';
require_login();

$tabs = [
    'dinhky' => ['Báo cáo định kỳ', 'bi-calendar-month'],
    'tiendo' => ['Tiến độ chương trình', 'bi-graph-up'],
    'dugio' => ['Dự giờ', 'bi-eye'],
    'kythi' => ['Kết quả cuộc thi', 'bi-trophy'],
];
$tab = $_GET['tab'] ?? 'dinhky';
if (!isset($tabs[$tab])) $tab = 'dinhky';
if ($tab === 'thang') $tab = 'dinhky';
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
function cm_progress_diff_class($diff) {
    if ($diff >= 2) return 'danger';
    if ($diff > 0) return 'warning';
    if ($diff <= -2) return 'danger';
    if ($diff < 0) return 'warning';
    return 'success';
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
$progressIsAdmin = !empty($_SESSION['pccm_admin']);
$sessionTeacherName = trim($_SESSION['cds_user']['teacher_name'] ?? $_SESSION['cds_user']['name'] ?? '');
$progressTeacher = $progressIsAdmin ? trim($_GET['teacher'] ?? $sessionTeacherName) : $sessionTeacherName;
$progressView = ($progressIsAdmin && ($_GET['view'] ?? '') === 'thongke') ? 'thongke' : 'nhaplieu';
$progressAssignments = array_values(array_filter($progressAssignmentMap, function($assignment) use ($progressTeacher) {
    return $progressTeacher !== '' && mb_strtolower(trim($assignment['teacher'] ?? ''), 'UTF-8') === mb_strtolower($progressTeacher, 'UTF-8');
}));
usort($progressAssignments, fn($a, $b) => strnatcasecmp(($a['class'] ?? '') . ($a['subject'] ?? ''), ($b['class'] ?? '') . ($b['subject'] ?? '')));
$progressByAssignment = [];
$progressPreviousByAssignment = [];
foreach ($progressRecords as $record) {
    if (($record['year_id'] ?? '') !== ($progressYear['id'] ?? '')) continue;
    $recordWeek = (int)($record['week_number'] ?? 0);
    $assignmentKey = $record['assignment_key'] ?? '';
    if ($recordWeek === $progressWeekNumber) {
        $progressByAssignment[$assignmentKey] = $record;
    } elseif ($recordWeek < $progressWeekNumber) {
        $previousWeek = (int)($progressPreviousByAssignment[$assignmentKey]['week_number'] ?? 0);
        if ($recordWeek > $previousWeek) $progressPreviousByAssignment[$assignmentKey] = $record;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'progress_save') {
        $weekNumber = max(1, (int)($_POST['week_number'] ?? $progressWeekNumber));
        $teacherName = $progressIsAdmin ? trim($_POST['teacher'] ?? '') : $sessionTeacherName;
        $allowed = [];
        foreach ($progressAssignmentMap as $key => $assignment) {
            if (mb_strtolower(trim($assignment['teacher'] ?? ''), 'UTF-8') === mb_strtolower($teacherName, 'UTF-8')) $allowed[$key] = $assignment;
        }
        $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
        $savedCount = 0;
        foreach ($rows as $assignmentKey => $values) {
            if (!isset($allowed[$assignmentKey]) || !is_array($values)) continue;
            $assignment = $allowed[$assignmentKey];
            $payload = [
                'id' => 'pg_' . substr(sha1(($progressYear['id'] ?? '') . '|' . $weekNumber . '|' . $assignmentKey), 0, 16),
                'year_id' => $progressYear['id'] ?? '',
                'year_label' => $progressYear['label'] ?? '',
                'week_number' => $weekNumber,
                'assignment_key' => $assignmentKey,
                'teacher' => $assignment['teacher'] ?? '',
                'class' => $assignment['class'] ?? '',
                'subject' => $assignment['subject'] ?? '',
                'standard_weekly' => cm_progress_num($values['standard_weekly'] ?? $assignment['periods'] ?? 0),
                'actual_period' => cm_progress_num($values['actual_period'] ?? 0),
                'ppct_period' => cm_progress_num($values['ppct_period'] ?? 0),
                'mid_hk1' => cm_progress_num($values['mid_hk1'] ?? 0),
                'final_hk1' => cm_progress_num($values['final_hk1'] ?? 0),
                'mid_hk2' => cm_progress_num($values['mid_hk2'] ?? 0),
                'final_hk2' => cm_progress_num($values['final_hk2'] ?? 0),
                'updated_by' => $_SESSION['cds_user']['name'] ?? $teacherName,
                'updated_at' => date('c'),
            ];
            $found = false;
            foreach ($progressRecords as &$record) {
                if (($record['id'] ?? '') === $payload['id']) { $record = array_merge($record, $payload); $found = true; break; }
            }
            unset($record);
            if (!$found) { $payload['created_at'] = date('c'); $progressRecords[] = $payload; }
            $savedCount++;
        }
        save_json($progressFile, array_values($progressRecords));
        flash('Đã lưu tiến độ tuần ' . $weekNumber . ' cho ' . $savedCount . ' môn/lớp.');
        header('Location: ' . BASE_URL . 'baocao.php?tab=tiendo&week=' . $weekNumber . '&teacher=' . urlencode($teacherName));
        exit;
    }
    if ($action === 'save') {
        $file = cm_handle_upload('file');
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

function cm_view_btns($it) {
    $html = '<button type="button" class="btn btn-sm btn-outline-success" title="Xem" onclick=\'viewDoc(' . json_encode($it, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) . ')\'><i class="bi bi-eye"></i></button> ';
    return $html;
}
?>

<h3 class="mb-3"><i class="bi bi-file-earmark-text"></i> Báo cáo chuyên môn</h3>

<ul class="nav nav-pills gap-1 mb-4 flex-wrap">
  <?php foreach ($tabs as $k => $info): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$k?'active':'' ?>" href="<?= BASE_URL ?>baocao.php?tab=<?= urlencode($k) ?>">
      <i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'tiendo'): ?>
<style>
.progress-toolbar{display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;margin-bottom:1rem}
.progress-toolbar>div{min-width:190px}.progress-table{min-width:1680px}
.progress-table th{font-size:.78rem;vertical-align:middle;text-align:center;white-space:normal;min-width:115px}
.progress-table th:first-child{min-width:190px}.progress-table th:nth-child(2){min-width:80px}.progress-table th:nth-child(3){min-width:140px}
.progress-table td{vertical-align:middle}.progress-table input{min-width:92px;text-align:center}
.progress-derived{font-weight:700;text-align:center;white-space:nowrap}
.progress-summary{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:.75rem;margin-bottom:1rem}
.progress-summary .card-body{text-align:center}.progress-summary strong{font-size:1.8rem;display:block}
@media(max-width:767px){.progress-summary{grid-template-columns:repeat(2,1fr)}}
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
  <?php endif; ?>
  <noscript><button class="btn btn-primary">Xem</button></noscript>
</form>

<?php if ($progressView === 'nhaplieu'): ?>
<?php if ($progressTeacher === ''): ?><div class="alert alert-warning">Tài khoản chưa được liên kết với tên giáo viên. Quản trị cần đặt trường <strong>Giáo viên liên kết</strong> trong phần Tài khoản.</div>
<?php elseif (!$progressAssignments): ?><div class="alert alert-info">Không tìm thấy phân công môn/lớp hiện hành của <strong><?= e($progressTeacher) ?></strong>.</div>
<?php else: ?>
<form method="post">
  <input type="hidden" name="action" value="progress_save">
  <input type="hidden" name="week_number" value="<?= $progressWeekNumber ?>">
  <input type="hidden" name="teacher" value="<?= e($progressTeacher) ?>">
  <div class="card"><div class="card-header d-flex justify-content-between"><span><?= e($progressTeacher) ?> — Tuần <?= $progressWeekNumber ?></span><span><?= count($progressAssignments) ?> môn/lớp</span></div>
  <div class="table-responsive"><table class="table table-bordered table-sm mb-0 progress-table">
    <thead><tr>
      <th>Họ tên</th><th>Lớp</th><th>Môn</th>
      <th>Số tiết tiêu chuẩn trên tuần</th>
      <th>Tiết thực tế đã dạy hết tuần <?= $progressWeekNumber ?></th>
      <th>Tiết hết tuần <?= $progressWeekNumber ?> theo PPCT (Phụ lục 3)</th>
      <th>Tiết kiểm tra giữa HK I</th><th>Tiết kiểm tra cuối HK I</th>
      <th>Tiết kiểm tra giữa HK II</th><th>Tiết kiểm tra cuối HK II</th>
      <th>Số tiết nhanh/chậm hiện tại</th>
      <th>Tiết còn lại kiểm tra GK I</th><th>Tiết còn lại kiểm tra CK I</th>
      <th>Tiết còn lại kiểm tra GK II</th><th>Tiết còn lại kiểm tra CK II</th>
    </tr></thead>
    <tbody>
    <?php foreach ($progressAssignments as $assignment):
      $key=cm_progress_assignment_key($assignment);$row=$progressByAssignment[$key]??($progressPreviousByAssignment[$key]??[]);
      $std=$row['standard_weekly']??($assignment['periods']??0);$actual=$row['actual_period']??0;$ppct=$row['ppct_period']??0;
      $m1=$row['mid_hk1']??0;$f1=$row['final_hk1']??0;$m2=$row['mid_hk2']??0;$f2=$row['final_hk2']??0;
      $diff=(float)$actual-(float)$ppct;
    ?>
    <tr data-progress-row>
      <td><strong><?= e($assignment['teacher']??'') ?></strong></td><td><?= e($assignment['class']??'') ?></td><td><?= e($assignment['subject']??'') ?></td>
      <?php foreach (['standard_weekly'=>$std,'actual_period'=>$actual,'ppct_period'=>$ppct,'mid_hk1'=>$m1,'final_hk1'=>$f1,'mid_hk2'=>$m2,'final_hk2'=>$f2] as $field=>$value): ?>
      <td><input class="form-control form-control-sm" type="number" min="0" step=".5" name="rows[<?= e($key) ?>][<?= e($field) ?>]" value="<?= e((string)$value) ?>" data-field="<?= e($field) ?>"></td>
      <?php endforeach; ?>
      <td class="progress-derived" data-result="diff"><?= e((string)$diff) ?></td>
      <td class="progress-derived" data-result="mid_hk1"><?= e((string)((float)$m1-(float)$actual)) ?></td>
      <td class="progress-derived" data-result="final_hk1"><?= e((string)((float)$f1-(float)$actual)) ?></td>
      <td class="progress-derived" data-result="mid_hk2"><?= e((string)((float)$m2-(float)$actual)) ?></td>
      <td class="progress-derived" data-result="final_hk2"><?= e((string)((float)$f2-(float)$actual)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card-body text-end"><button class="btn btn-primary px-4"><i class="bi bi-floppy"></i> Lưu tiến độ tuần <?= $progressWeekNumber ?></button></div></div>
</form>
<script>
document.querySelectorAll('[data-progress-row]').forEach(function(row){
  function n(field){return parseFloat(row.querySelector('[data-field="'+field+'"]').value||0)}
  function show(name,value){
    var cell=row.querySelector('[data-result="'+name+'"]');cell.textContent=(Math.round(value*10)/10);
    if(name==='diff'){cell.className='progress-derived '+(value===0?'text-success':(Math.abs(value)>=2?'text-danger':'text-warning'))}
  }
  function calc(){var actual=n('actual_period');show('diff',actual-n('ppct_period'));['mid_hk1','final_hk1','mid_hk2','final_hk2'].forEach(function(field){show(field,n(field)-actual)})}
  row.querySelectorAll('input').forEach(function(input){input.addEventListener('input',calc)});calc();
});
</script>
<?php endif; ?>

<?php else:
  $statsRows=[];$fast=0;$slow=0;$onTime=0;$missing=0;
  foreach($progressAssignmentMap as $key=>$assignment){
    $record=$progressByAssignment[$key]??null;
    if(!$record){$missing++;$statsRows[]=['assignment'=>$assignment,'record'=>null,'diff'=>null];continue;}
    $diff=(float)($record['actual_period']??0)-(float)($record['ppct_period']??0);
    if($diff>0)$fast++;elseif($diff<0)$slow++;else$onTime++;
    $statsRows[]=['assignment'=>$assignment,'record'=>$record,'diff'=>$diff];
  }
  usort($statsRows,function($a,$b){if($a['diff']===null)return 1;if($b['diff']===null)return -1;return abs($b['diff'])<=>abs($a['diff']);});
?>
<div class="progress-summary">
  <div class="card"><div class="card-body"><strong class="text-primary"><?= count($progressAssignmentMap) ?></strong>Tổng môn/lớp</div></div>
  <div class="card"><div class="card-body"><strong class="text-success"><?= $onTime ?></strong>Đúng tiến độ</div></div>
  <div class="card"><div class="card-body"><strong class="text-warning"><?= $fast+$slow ?></strong>Cần điều chỉnh</div></div>
  <div class="card"><div class="card-body"><strong class="text-secondary"><?= $missing ?></strong>Chưa nhập</div></div>
</div>
<?php if($fast+$slow): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Tuần <?= $progressWeekNumber ?> có <strong><?= $fast ?></strong> môn/lớp nhanh và <strong><?= $slow ?></strong> môn/lớp chậm so với PPCT.</div><?php endif; ?>
<div class="card"><div class="card-header">Thống kê tiến độ tuần <?= $progressWeekNumber ?></div><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead><tr><th>Giáo viên</th><th>Lớp</th><th>Môn</th><th>Thực tế</th><th>PPCT</th><th>Nhanh/chậm</th><th>Cảnh báo</th><th>Cập nhật</th></tr></thead><tbody>
<?php foreach($statsRows as $item):$a=$item['assignment'];$r=$item['record'];$d=$item['diff']; ?>
<tr class="<?= $d!==null&&abs($d)>=2?'table-danger':($d!==null&&$d!=0?'table-warning':'') ?>">
<td><strong><?= e($a['teacher']??'') ?></strong></td><td><?= e($a['class']??'') ?></td><td><?= e($a['subject']??'') ?></td>
<?php if(!$r): ?><td colspan="5" class="text-muted">Chưa nhập tiến độ tuần này</td>
<?php else: ?><td><?= e((string)($r['actual_period']??0)) ?></td><td><?= e((string)($r['ppct_period']??0)) ?></td><td class="fw-bold <?= $d==0?'text-success':(abs($d)>=2?'text-danger':'text-warning') ?>"><?= $d>0?'+':'' ?><?= e((string)$d) ?></td>
<td><?php if($d>0): ?><span class="badge bg-warning text-dark">Nhanh <?= e((string)$d) ?> tiết</span><?php elseif($d<0): ?><span class="badge bg-danger">Chậm <?= e((string)abs($d)) ?> tiết</span><?php else: ?><span class="badge bg-success">Đúng tiến độ</span><?php endif; ?></td><td class="small"><?= e(isset($r['updated_at'])?date('d/m/Y H:i',strtotime($r['updated_at'])):'') ?></td><?php endif; ?>
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
