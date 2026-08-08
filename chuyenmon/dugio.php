<?php
$page_title = 'Đăng ký và thống kê dự giờ';
require_once 'includes/functions.php';
require_login();

function cm_observation_norm($value): string {
    $value = trim((string)$value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
function cm_observation_rating($score): string {
    if ($score === '' || $score === null || !is_numeric($score)) return '';
    $score = (float)$score;
    if ($score >= 18) return 'Giỏi';
    if ($score >= 13.5) return 'Khá';
    if ($score >= 10) return 'Trung bình';
    return 'Không đạt';
}
function cm_observation_observers(array $record): array {
    $observers = $record['observers'] ?? ($record['assignees'] ?? []);
    if (!is_array($observers)) $observers = [$observers];
    if (!$observers && trim((string)($record['observer'] ?? '')) !== '') $observers[] = $record['observer'];
    $observers = array_map(fn($name)=>preg_replace('/\s+/u', ' ', trim((string)$name)), $observers);
    return array_values(array_unique(array_filter($observers, fn($name)=>$name !== '')));
}
function cm_observation_short_name($fullName): string {
    $fullName = preg_replace('/\s+/u', ' ', trim((string)$fullName));
    if ($fullName === '') return '';
    $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $count = count($parts);
    if ($count >= 4) return implode(' ', array_slice($parts, -2));
    if ($count === 3) return (string)end($parts);
    return $fullName;
}
function cm_observation_observer_display(array $record): string {
    return implode(', ', array_map('cm_observation_short_name', cm_observation_observers($record)));
}
function cm_observation_teacher_id($name): string {
    foreach(load_json(dirname(__DIR__).'/data/teachers.json',[]) as $teacher) {
        if(cm_observation_norm($teacher['name']??'')===cm_observation_norm($name)) return (string)($teacher['id']??'');
    }
    return '';
}
function cm_observation_school_year(): array {
    $file = dirname(__DIR__) . '/data/school_years.json';
    $years = load_json($file, []);
    foreach ($years as $year) if (!empty($year['is_current'])) return $year;
    return $years[0] ?? ['id'=>'default','label'=>date('Y').'–'.(date('Y')+1),'start'=>date('Y').'-09-01','end'=>(date('Y')+1).'-05-31'];
}
function cm_observation_weeks(array $year): array {
    $start = $year['start'] ?? ''; $end = $year['end'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) return [];
    $saved = [];
    foreach (($year['weeks'] ?? []) as $row) {
        $number = (int)($row['number'] ?? 0);
        if ($number > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['start'] ?? '')) $saved[$number] = $row['start'];
    }
    $weeks = []; $cursor = new DateTimeImmutable($start); $last = new DateTimeImmutable($end);
    for ($number=1; $number<=60 && $cursor<=$last; $number++) {
        if (isset($saved[$number])) $cursor = new DateTimeImmutable($saved[$number]);
        $weeks[$number] = ['number'=>$number,'start'=>$cursor->format('Y-m-d'),'end'=>$cursor->modify('+6 days')->format('Y-m-d')];
        $cursor = $cursor->modify('+7 days');
    }
    return $weeks;
}
function cm_observation_is_leader(array $user): bool {
    if (($user['role'] ?? '') === 'totruong') return true;
    return in_array('totruong', (array)($user['groups'] ?? []), true);
}

$user = cds_user() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';
$isLeader = cm_observation_is_leader($user);
$teacherName = trim((string)($user['teacher_name'] ?? $user['name'] ?? ''));
$teacherGroup = $teacherName !== '' ? trim((string)get_teacher_group($teacherName)) : '';
$year = cm_observation_school_year();
$weeks = cm_observation_weeks($year);
$today = date('Y-m-d');
$currentWeek = 1;
foreach ($weeks as $number=>$week) if ($today >= $week['start'] && $today <= $week['end']) { $currentWeek = $number; break; }

$assignments = [];
foreach (get_assignments() as $assignment) {
    if (cm_observation_norm($assignment['teacher'] ?? '') !== cm_observation_norm($teacherName)) continue;
    $subject = trim((string)($assignment['subject'] ?? ''));
    $class = trim((string)($assignment['class'] ?? ''));
    if ($subject === '' || $class === '') continue;
    $assignments[$subject.'|'.$class] = ['subject'=>$subject,'class'=>$class];
}
$assignments = array_values($assignments);
usort($assignments, fn($a,$b)=>strnatcasecmp($a['subject'].'|'.$a['class'],$b['subject'].'|'.$b['class']));
$subjects = array_values(array_unique(array_column($assignments, 'subject')));

$allTeachers = get_teachers_sorted();
$teamTeachers = [];
foreach ($allTeachers as $name) {
    if ($isAdmin || ($teacherGroup !== '' && cm_observation_norm(get_teacher_group($name)) === cm_observation_norm($teacherGroup))) $teamTeachers[] = $name;
}

$dataFile = DATA_PATH . '/observations.json';
$records = load_json($dataFile, []);
if (!is_array($records)) $records = [];
// Luôn tính lại xếp loại từ điểm để dữ liệu cũ tuân theo thang dự giờ hiện hành.
foreach ($records as &$record) {
    if (($record['score'] ?? '') !== '' && is_numeric($record['score'])) {
        $record['rating'] = cm_observation_rating($record['score']);
    }
}
unset($record);
if (empty($_SESSION['cm_observation_csrf'])) $_SESSION['cm_observation_csrf'] = bin2hex(random_bytes(20));
$csrf = $_SESSION['cm_observation_csrf'];

$canSeeRecord = function(array $record) use ($isAdmin,$isLeader,$teacherName,$teacherGroup): bool {
    if ($isAdmin) return true;
    if ($isLeader && $teacherGroup !== '' && cm_observation_norm($record['teacher_group'] ?? '') === cm_observation_norm($teacherGroup)) return true;
    return cm_observation_norm($record['teacher'] ?? '') === cm_observation_norm($teacherName);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'observation_save') {
        if ($teacherName === '') { flash('Tài khoản chưa liên kết với giáo viên trong PCCM.', 'danger'); header('Location: '.BASE_URL.'dugio.php'); exit; }
        $subject = trim((string)($_POST['subject'] ?? '')); $class = trim((string)($_POST['class'] ?? ''));
        $allowedAssignment = false;
        foreach ($assignments as $assignment) if ($assignment['subject'] === $subject && $assignment['class'] === $class) { $allowedAssignment = true; break; }
        $weekNumber = (int)($_POST['week_number'] ?? 0); $week = $weeks[$weekNumber] ?? null;
        $date = trim((string)($_POST['date'] ?? ''));
        if (!$allowedAssignment || !$week || $date < $week['start'] || $date > $week['end']) {
            flash('Môn, lớp, tuần hoặc ngày không đúng dữ liệu PCCM.', 'danger'); header('Location: '.BASE_URL.'dugio.php'); exit;
        }
        $ppct = max(1, (int)($_POST['ppct_period'] ?? 0)); $timetable = max(1, (int)($_POST['timetable_period'] ?? 0));
        $lesson = trim((string)($_POST['lesson_title'] ?? ''));
        if ($lesson === '') { flash('Vui lòng nhập tên bài dạy.', 'danger'); header('Location: '.BASE_URL.'dugio.php'); exit; }
        $id = trim((string)($_POST['id'] ?? '')); $found = false;
        foreach ($records as &$record) {
            if (($record['id'] ?? '') !== $id) continue;
            if (cm_observation_norm($record['teacher'] ?? '') !== cm_observation_norm($teacherName)) { http_response_code(403); exit('Không được sửa đăng ký của giáo viên khác.'); }
            $record = array_merge($record, ['year_id'=>$year['id']??'','year_label'=>$year['label']??'','teacher_id'=>cm_observation_teacher_id($teacherName),'teacher'=>$teacherName,'teacher_group'=>$teacherGroup,'subject'=>$subject,'class'=>$class,'week_number'=>$weekNumber,'week_start'=>$week['start'],'week_end'=>$week['end'],'date'=>$date,'start_date'=>$date,'ppct_period'=>$ppct,'timetable_period'=>$timetable,'lesson_title'=>$lesson,'title'=>'Dự giờ: '.$lesson,'kind'=>'observation','updated_at'=>date('c')]);
            $found = true; break;
        }
        unset($record);
        if (!$found) $records[] = ['id'=>'obs_'.bin2hex(random_bytes(8)),'year_id'=>$year['id']??'','year_label'=>$year['label']??'','teacher_id'=>cm_observation_teacher_id($teacherName),'teacher'=>$teacherName,'teacher_group'=>$teacherGroup,'subject'=>$subject,'class'=>$class,'week_number'=>$weekNumber,'week_start'=>$week['start'],'week_end'=>$week['end'],'date'=>$date,'start_date'=>$date,'ppct_period'=>$ppct,'timetable_period'=>$timetable,'lesson_title'=>$lesson,'title'=>'Dự giờ: '.$lesson,'kind'=>'observation','observer'=>'','observers'=>[],'observer_ids'=>[],'assignees'=>[],'score'=>'','rating'=>'','created_by'=>$user['id']??'','created_at'=>date('c'),'updated_at'=>date('c')];
        save_json($dataFile, array_values($records)); flash($found?'Đã cập nhật đăng ký dự giờ.':'Đã đăng ký tiết dự giờ.'); header('Location: '.BASE_URL.'dugio.php'); exit;
    }
    if ($action === 'observation_review') {
        if (!$isAdmin && !$isLeader) { http_response_code(403); exit('Chỉ tổ trưởng chuyên môn hoặc quản trị được đánh giá.'); }
        $id = trim((string)($_POST['id'] ?? ''));
        $observers = array_values(array_unique(array_filter(array_map(fn($name)=>trim((string)$name), (array)($_POST['observers'] ?? [])), fn($name)=>$name !== '')));
        $scoreRaw = trim((string)($_POST['score'] ?? ''));
        $invalidObserver = !$observers || count(array_diff($observers, $teamTeachers)) > 0;
        if ($invalidObserver || $scoreRaw === '' || !is_numeric($scoreRaw) || (float)$scoreRaw < 0 || (float)$scoreRaw > 20) {
            flash('Người dự hoặc điểm đánh giá chưa hợp lệ.', 'danger'); header('Location: '.BASE_URL.'dugio.php'); exit;
        }
        $updated = false;
        foreach ($records as &$record) {
            if (($record['id'] ?? '') !== $id || !$canSeeRecord($record)) continue;
            $score = round((float)$scoreRaw, 2); $record['observer']=$observers[0]; $record['observers']=$observers; $record['observer_ids']=array_values(array_filter(array_map('cm_observation_teacher_id',$observers))); $record['assignees']=$observers; $record['score']=$score; $record['rating']=cm_observation_rating($score); $record['reviewed_by']=$teacherName; $record['reviewed_at']=date('c'); $record['updated_at']=date('c'); $updated=true; break;
        }
        unset($record);
        if (!$updated) { http_response_code(403); exit('Không tìm thấy tiết dự giờ trong phạm vi quản lý.'); }
        save_json($dataFile, array_values($records)); flash('Đã lưu người dự, điểm và xếp loại.'); header('Location: '.BASE_URL.'dugio.php'); exit;
    }
    if ($action === 'observation_delete') {
        if (!$isAdmin) { http_response_code(403); exit('Chỉ quản trị được xóa dữ liệu dự giờ.'); }
        $id = trim((string)($_POST['id'] ?? '')); $records = array_values(array_filter($records, fn($record)=>($record['id']??'')!==$id));
        save_json($dataFile, $records); flash('Đã xóa đăng ký dự giờ.', 'warning'); header('Location: '.BASE_URL.'dugio.php'); exit;
    }
}

$visibleRecords = array_values(array_filter($records, $canSeeRecord));
usort($visibleRecords, fn($a,$b)=>strcmp(($b['date']??'').'|'.($b['created_at']??''),($a['date']??'').'|'.($a['created_at']??'')));
$view = ($_GET['view'] ?? '') === 'stats' && ($isAdmin || $isLeader) ? 'stats' : 'list';
$filterGroup = trim((string)($_GET['group'] ?? '')); $filterSubject = trim((string)($_GET['subject'] ?? ''));
$filterFromWeek = max(0, (int)($_GET['from_week'] ?? 0)); $filterToWeek = max(0, (int)($_GET['to_week'] ?? 0));
if ($filterFromWeek && !isset($weeks[$filterFromWeek])) $filterFromWeek = 0;
if ($filterToWeek && !isset($weeks[$filterToWeek])) $filterToWeek = 0;
if ($filterFromWeek && $filterToWeek && $filterFromWeek > $filterToWeek) [$filterFromWeek,$filterToWeek] = [$filterToWeek,$filterFromWeek];
$filterFromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'] ?? '') ? (string)$_GET['from_date'] : '';
$filterToDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'] ?? '') ? (string)$_GET['to_date'] : '';
if ($filterFromDate !== '' && $filterToDate !== '' && $filterFromDate > $filterToDate) [$filterFromDate,$filterToDate] = [$filterToDate,$filterFromDate];
$statsRecords = array_values(array_filter($visibleRecords, function($record) use($filterGroup,$filterSubject,$filterFromWeek,$filterToWeek,$filterFromDate,$filterToDate){
    $recordWeek = (int)($record['week_number'] ?? 0); $recordDate = (string)($record['date'] ?? '');
    return ($filterGroup==='' || ($record['teacher_group']??'')===$filterGroup)
        && ($filterSubject==='' || ($record['subject']??'')===$filterSubject)
        && (!$filterFromWeek || $recordWeek >= $filterFromWeek) && (!$filterToWeek || $recordWeek <= $filterToWeek)
        && ($filterFromDate==='' || $recordDate >= $filterFromDate) && ($filterToDate==='' || $recordDate <= $filterToDate);
}));
$filterGroups = array_values(array_unique(array_filter(array_column($visibleRecords,'teacher_group')))); sort($filterGroups,SORT_NATURAL|SORT_FLAG_CASE);
$filterSubjects = array_values(array_unique(array_filter(array_column($visibleRecords,'subject')))); sort($filterSubjects,SORT_NATURAL|SORT_FLAG_CASE);
$summary = [];
foreach ($statsRecords as $record) {
    $group = trim((string)($record['teacher_group']??'')) ?: 'Chưa xếp tổ'; $subject = trim((string)($record['subject']??'')) ?: 'Chưa có môn'; $key=$group.'|'.$subject;
    if (!isset($summary[$key])) $summary[$key]=['group'=>$group,'subject'=>$subject,'total'=>0,'rated'=>0,'Giỏi'=>0,'Khá'=>0,'Trung bình'=>0,'Không đạt'=>0];
    $summary[$key]['total']++; $rating=$record['rating']??''; if ($rating!=='' && isset($summary[$key][$rating])) { $summary[$key]['rated']++; $summary[$key][$rating]++; }
}
usort($summary,fn($a,$b)=>strnatcasecmp($a['group'].'|'.$a['subject'],$b['group'].'|'.$b['subject']));

require_once 'includes/header.php';
?>
<style>
.obs-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.obs-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem}.obs-form-grid .wide{grid-column:span 2}.obs-table{min-width:1450px}.obs-table th,.obs-table td{vertical-align:middle}.obs-rating{display:inline-flex;padding:.28rem .55rem;border-radius:999px;font-size:.75rem;font-weight:800}.obs-rating.good{background:#d1e7dd;color:#0f5132}.obs-rating.fair{background:#cff4fc;color:#055160}.obs-rating.pass{background:#fff3cd;color:#664d03}.obs-rating.fail{background:#f8d7da;color:#842029}.obs-review{display:grid;grid-template-columns:minmax(190px,1fr) 72px auto;gap:.35rem;min-width:365px}.obs-observer-picker{min-width:0}.obs-observer-picker summary{display:flex;align-items:center;justify-content:space-between;gap:.5rem;min-height:31px;padding:.25rem .5rem;border:1px solid #ced4da;border-radius:.25rem;background:#fff;cursor:pointer;list-style:none;white-space:nowrap;overflow:hidden}.obs-observer-picker summary::-webkit-details-marker{display:none}.obs-observer-picker summary::after{content:'▾';flex:none;color:#64748b}.obs-observer-options{max-height:150px;overflow:auto;margin-top:.25rem;padding:.35rem;border:1px solid #dbe3ec;border-radius:.4rem;background:#fff}.obs-observer-option{display:flex;align-items:flex-start;gap:.45rem;padding:.25rem .2rem;line-height:1.25;cursor:pointer}.obs-observer-option input{flex:none;margin-top:.12rem}.obs-observer-name{overflow-wrap:anywhere}.obs-observer-display{line-height:1.35}.obs-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:.75rem;margin-bottom:1rem}.obs-kpis>div{padding:1rem;border-radius:12px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.07);text-align:center}.obs-kpis strong,.obs-kpis span{display:block}.obs-kpis strong{font-size:1.65rem;color:var(--primary)}.obs-kpis span{font-size:.78rem;color:#64748b}.obs-summary{min-width:850px}@media(max-width:767px){.obs-toolbar{align-items:flex-start;flex-direction:column}.obs-form-grid{grid-template-columns:1fr 1fr}.obs-form-grid .wide{grid-column:1/-1}.obs-kpis{grid-template-columns:repeat(2,1fr)}.obs-kpis>div:first-child{grid-column:1/-1}}
.obs-table{table-layout:fixed}.obs-register-table{min-width:1520px}.obs-detail-table{min-width:1455px}.obs-table th{text-align:center;vertical-align:middle}.obs-table td{overflow-wrap:anywhere}.obs-register-table td:nth-child(1),.obs-register-table td:nth-child(4),.obs-register-table td:nth-child(5),.obs-register-table td:nth-child(6),.obs-register-table td:nth-child(7),.obs-register-table td:nth-child(8),.obs-register-table td:nth-child(11),.obs-register-table td:nth-child(12),.obs-register-table td:nth-child(13),.obs-detail-table td:nth-child(1),.obs-detail-table td:nth-child(5),.obs-detail-table td:nth-child(6),.obs-detail-table td:nth-child(7),.obs-detail-table td:nth-child(8),.obs-detail-table td:nth-child(9),.obs-detail-table td:nth-child(12),.obs-detail-table td:nth-child(13){text-align:center}.obs-summary th,.obs-summary td{text-align:center;vertical-align:middle}.obs-summary th:nth-child(2),.obs-summary th:nth-child(3),.obs-summary td:nth-child(2),.obs-summary td:nth-child(3){text-align:left}.obs-filter-note{font-size:.78rem;color:#64748b}.obs-filter-actions{display:flex;gap:.5rem;align-items:center}@media(max-width:767px){.obs-filter-actions{width:100%}.obs-filter-actions .btn{flex:1}}
</style>
<div class="obs-toolbar"><div><h3 class="mb-1"><i class="bi bi-eye text-primary"></i> Đăng ký dự giờ</h3><div class="text-muted">Năm học <?= e($year['label']??'') ?> · Dữ liệu theo phân công chuyên môn hiện hành</div></div><div class="btn-group"><a class="btn <?= $view==='list'?'btn-primary':'btn-outline-primary' ?>" href="<?= BASE_URL ?>dugio.php"><i class="bi bi-table"></i> Bảng dự giờ</a><?php if($isAdmin||$isLeader):?><a class="btn <?= $view==='stats'?'btn-primary':'btn-outline-primary' ?>" href="<?= BASE_URL ?>dugio.php?view=stats"><i class="bi bi-bar-chart-line"></i> Thống kê</a><?php endif;?></div></div>

<?php if($view==='list'):?>
<div class="card mb-3"><div class="card-header">Giáo viên đăng ký tiết dạy</div><div class="card-body">
<?php if($teacherName===''):?><div class="alert alert-warning mb-0">Tài khoản chưa được liên kết với tên giáo viên. Quản trị cần cập nhật trường <strong>Giáo viên liên kết</strong> trong phần Tài khoản.</div>
<?php elseif(!$assignments):?><div class="alert alert-info mb-0">Không tìm thấy môn/lớp được phân công trong PCCM của <strong><?=e($teacherName)?></strong>.</div>
<?php else:?><form method="post" id="observationForm"><input type="hidden" name="action" value="observation_save"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" id="obsId">
<div class="obs-form-grid"><div><label class="form-label fw-semibold">Họ tên giáo viên</label><input class="form-control" value="<?=e($teacherName)?>" disabled></div>
<div><label class="form-label fw-semibold">Môn</label><select class="form-select" name="subject" id="obsSubject" required><option value="">Chọn môn</option><?php foreach($subjects as $subject):?><option value="<?=e($subject)?>"><?=e($subject)?></option><?php endforeach;?></select></div>
<div><label class="form-label fw-semibold">Lớp</label><select class="form-select" name="class" id="obsClass" required><option value="">Chọn lớp</option><?php foreach($assignments as $assignment):?><option value="<?=e($assignment['class'])?>" data-subject="<?=e($assignment['subject'])?>"><?=e($assignment['class'])?></option><?php endforeach;?></select></div>
<div><label class="form-label fw-semibold">Tuần</label><select class="form-select" name="week_number" id="obsWeek" required><?php foreach($weeks as $week):?><option value="<?=$week['number']?>" data-start="<?=e($week['start'])?>" data-end="<?=e($week['end'])?>" <?=$week['number']===$currentWeek?'selected':''?>>Tuần <?=$week['number']?> (<?=date('d/m',strtotime($week['start']))?>–<?=date('d/m',strtotime($week['end']))?>)</option><?php endforeach;?></select></div>
<div><label class="form-label fw-semibold">Ngày</label><input class="form-control" type="date" name="date" id="obsDate" value="<?=e($today)?>" required></div>
<div><label class="form-label fw-semibold">Tiết PPCT</label><input class="form-control" type="number" name="ppct_period" id="obsPpct" min="1" required></div>
<div><label class="form-label fw-semibold">Tiết TKB</label><input class="form-control" type="number" name="timetable_period" id="obsTimetable" min="1" required></div>
<div class="wide"><label class="form-label fw-semibold">Tên bài dạy</label><input class="form-control" name="lesson_title" id="obsLesson" maxlength="300" required></div></div>
<div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-floppy"></i> Lưu đăng ký</button><button class="btn btn-outline-secondary" type="button" id="obsReset">Làm mới</button></div></form><?php endif;?>
</div></div>

<div class="card"><div class="card-header d-flex justify-content-between"><span>Danh sách dự giờ</span><span><?=count($visibleRecords)?> tiết</span></div><div class="table-responsive"><table class="table table-bordered table-hover mb-0 obs-table obs-register-table"><colgroup><col style="width:52px"><col style="width:185px"><col style="width:115px"><col style="width:72px"><col style="width:82px"><col style="width:105px"><col style="width:76px"><col style="width:72px"><col style="width:245px"><col style="width:245px"><col style="width:72px"><col style="width:95px"><col style="width:82px"></colgroup><thead><tr><th>STT</th><th>Họ tên giáo viên</th><th>Môn</th><th>Lớp</th><th>Tuần</th><th>Ngày</th><th>Tiết PPCT</th><th>Tiết TKB</th><th>Tên bài dạy</th><th>Người dự</th><th>Điểm</th><th>Xếp loại</th><th>Thao tác</th></tr></thead><tbody>
<?php if(!$visibleRecords):?><tr><td colspan="13" class="text-center text-muted py-4">Chưa có đăng ký dự giờ.</td></tr><?php else:foreach($visibleRecords as $index=>$record):$rating=$record['rating']??'';$ratingClass=$rating==='Giỏi'?'good':($rating==='Khá'?'fair':($rating==='Trung bình'?'pass':'fail'));?><tr>
<td><?=$index+1?></td><td><strong><?=e($record['teacher']??'')?></strong><small class="d-block text-muted"><?=e($record['teacher_group']??'')?></small></td><td><?=e($record['subject']??'')?></td><td><?=e($record['class']??'')?></td><td>Tuần <?=(int)($record['week_number']??0)?></td><td><?=!empty($record['date'])?date('d/m/Y',strtotime($record['date'])):'—'?></td><td class="text-center"><?=(int)($record['ppct_period']??0)?></td><td class="text-center"><?=(int)($record['timetable_period']??0)?></td><td><?=e($record['lesson_title']??'')?></td>
<?php if(($isAdmin||$isLeader)&&$canSeeRecord($record)): $selectedObservers=cm_observation_observers($record);?><td colspan="3"><form method="post" class="obs-review"><input type="hidden" name="action" value="observation_review"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=e($record['id']??'')?>"><details class="obs-observer-picker"><summary><span class="obs-observer-summary"><?=$selectedObservers?e(count($selectedObservers).' người đã chọn'):'Chọn người dự'?></span></summary><div class="obs-observer-options"><?php foreach($teamTeachers as $name):?><label class="obs-observer-option"><input type="checkbox" name="observers[]" value="<?=e($name)?>" <?=in_array($name,$selectedObservers,true)?'checked':''?>><span class="obs-observer-name"><?=e($name)?></span></label><?php endforeach;?></div></details><input class="form-control form-control-sm obs-score" type="number" name="score" min="0" max="20" step=".01" value="<?=e((string)($record['score']??''))?>" placeholder="Điểm" required><button class="btn btn-sm btn-success" title="Lưu đánh giá"><i class="bi bi-check-lg"></i></button><small class="text-muted obs-live-rating" style="grid-column:1/-1"><?=$rating?e($rating):'Xếp loại tự động theo điểm'?></small></form></td>
<?php else: $observerNames=cm_observation_observers($record); $observerDisplay=cm_observation_observer_display($record);?><td class="obs-observer-display" title="<?=e(implode(', ',$observerNames))?>"><?=e($observerDisplay?:'—')?></td><td><?=($record['score']??'')!==''?e((string)$record['score']):'—'?></td><td><?=$rating?'<span class="obs-rating '.$ratingClass.'">'.e($rating).'</span>':'—'?></td><?php endif;?>
<td class="text-nowrap"><?php if(cm_observation_norm($record['teacher']??'')===cm_observation_norm($teacherName)):?><button class="btn btn-sm btn-outline-primary obs-edit" type="button" data-record="<?=e(base64_encode(json_encode($record,JSON_UNESCAPED_UNICODE)))?>"><i class="bi bi-pencil"></i></button><?php endif;?><?php if($isAdmin):?><form method="post" class="d-inline" onsubmit="return confirm('Xóa đăng ký dự giờ này?')"><input type="hidden" name="action" value="observation_delete"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=e($record['id']??'')?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>

<?php else:?>
<form method="get" class="card mb-3"><input type="hidden" name="view" value="stats"><div class="card-body"><div class="row g-2 align-items-end">
<div class="col-md-3"><label class="form-label fw-semibold">Tổ chuyên môn</label><select class="form-select" name="group" <?=$isLeader&&!$isAdmin?'disabled':''?>><option value="">Tất cả tổ</option><?php foreach($filterGroups as $group):?><option value="<?=e($group)?>" <?=$group===$filterGroup?'selected':''?>><?=e($group)?></option><?php endforeach;?></select><?php if($isLeader&&!$isAdmin):?><input type="hidden" name="group" value="<?=e($teacherGroup)?>"><?php endif;?></div>
<div class="col-md-3"><label class="form-label fw-semibold">Môn</label><select class="form-select" name="subject"><option value="">Tất cả môn</option><?php foreach($filterSubjects as $subject):?><option value="<?=e($subject)?>" <?=$subject===$filterSubject?'selected':''?>><?=e($subject)?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label fw-semibold">Từ tuần</label><select class="form-select" name="from_week"><option value="0">Đầu năm học</option><?php foreach($weeks as $week):?><option value="<?=$week['number']?>" <?=$week['number']===$filterFromWeek?'selected':''?>>Tuần <?=$week['number']?> (<?=date('d/m',strtotime($week['start']))?>)</option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label fw-semibold">Đến tuần</label><select class="form-select" name="to_week"><option value="0">Cuối năm học</option><?php foreach($weeks as $week):?><option value="<?=$week['number']?>" <?=$week['number']===$filterToWeek?'selected':''?>>Tuần <?=$week['number']?> (<?=date('d/m',strtotime($week['end']))?>)</option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label fw-semibold">Từ ngày</label><input class="form-control" type="date" name="from_date" value="<?=e($filterFromDate)?>"></div>
<div class="col-md-3"><label class="form-label fw-semibold">Đến ngày</label><input class="form-control" type="date" name="to_date" value="<?=e($filterToDate)?>"></div>
<div class="col-md-6"><div class="obs-filter-actions"><button class="btn btn-primary"><i class="bi bi-funnel"></i> Áp dụng bộ lọc</button><a class="btn btn-outline-secondary" href="<?=BASE_URL?>dugio.php?view=stats"><i class="bi bi-arrow-counterclockwise"></i> Xóa bộ lọc</a></div><div class="obs-filter-note mt-2">Có thể lọc theo khoảng tuần, khoảng ngày hoặc kết hợp cả hai.</div></div>
</div></div></form>
<?php $ratedCount=count(array_filter($statsRecords,fn($r)=>($r['rating']??'')!==''));?><div class="obs-kpis"><div><strong><?=count($statsRecords)?></strong><span>Tổng tiết đăng ký</span></div><div><strong><?=$ratedCount?></strong><span>Đã đánh giá</span></div><?php foreach(['Giỏi','Khá','Trung bình','Không đạt'] as $label):?><div><strong><?=count(array_filter($statsRecords,fn($r)=>($r['rating']??'')===$label))?></strong><span><?=e($label)?></span></div><?php endforeach;?></div>
<div class="card mb-3"><div class="card-header">Tổng hợp số tiết theo tổ và môn</div><div class="table-responsive"><table class="table table-bordered mb-0 obs-summary"><colgroup><col style="width:55px"><col style="width:220px"><col style="width:170px"><col style="width:100px"><col style="width:110px"><col style="width:75px"><col style="width:75px"><col style="width:105px"><col style="width:100px"></colgroup><thead><tr><th>STT</th><th>Tổ chuyên môn</th><th>Môn</th><th>Tổng số tiết</th><th>Đã đánh giá</th><th>Giỏi</th><th>Khá</th><th>Trung bình</th><th>Không đạt</th></tr></thead><tbody><?php if(!$summary):?><tr><td colspan="9" class="text-center text-muted py-4">Chưa có dữ liệu tổng hợp.</td></tr><?php else:foreach($summary as $i=>$row):?><tr><td><?=$i+1?></td><td><strong><?=e($row['group'])?></strong></td><td><?=e($row['subject'])?></td><td><?=$row['total']?></td><td><?=$row['rated']?></td><td><?=$row['Giỏi']?></td><td><?=$row['Khá']?></td><td><?=$row['Trung bình']?></td><td><?=$row['Không đạt']?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
<div class="card"><div class="card-header">Bảng kê chi tiết</div><div class="table-responsive"><table class="table table-bordered table-sm mb-0 obs-table obs-detail-table"><colgroup><col style="width:52px"><col style="width:185px"><col style="width:150px"><col style="width:115px"><col style="width:72px"><col style="width:82px"><col style="width:105px"><col style="width:76px"><col style="width:72px"><col style="width:245px"><col style="width:210px"><col style="width:72px"><col style="width:95px"></colgroup><thead><tr><th>STT</th><th>Họ tên giáo viên</th><th>Tổ</th><th>Môn</th><th>Lớp</th><th>Tuần</th><th>Ngày</th><th>Tiết PPCT</th><th>Tiết TKB</th><th>Tên bài dạy</th><th>Người dự</th><th>Điểm</th><th>Xếp loại</th></tr></thead><tbody><?php if(!$statsRecords):?><tr><td colspan="13" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr><?php else:foreach($statsRecords as $i=>$record): $observerNames=cm_observation_observers($record); $observerDisplay=cm_observation_observer_display($record);?><tr><td><?=$i+1?></td><td><?=e($record['teacher']??'')?></td><td><?=e($record['teacher_group']??'')?></td><td><?=e($record['subject']??'')?></td><td><?=e($record['class']??'')?></td><td>Tuần <?=(int)($record['week_number']??0)?></td><td><?=!empty($record['date'])?date('d/m/Y',strtotime($record['date'])):'—'?></td><td><?=(int)($record['ppct_period']??0)?></td><td><?=(int)($record['timetable_period']??0)?></td><td><?=e($record['lesson_title']??'')?></td><td class="obs-observer-display" title="<?=e(implode(', ',$observerNames))?>"><?=e($observerDisplay?:'—')?></td><td><?=e((string)($record['score']??''))?></td><td><?=e($record['rating']??'')?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
<?php endif;?>

<script>
(function(){
  const subject=document.getElementById('obsSubject'),classSelect=document.getElementById('obsClass'),week=document.getElementById('obsWeek'),date=document.getElementById('obsDate');
  function filterClasses(){if(!subject||!classSelect)return;Array.from(classSelect.options).forEach(function(option,index){if(!index)return;option.hidden=option.dataset.subject!==subject.value});if(classSelect.selectedOptions[0]?.hidden)classSelect.value=''}
  function syncWeekDate(){if(!week||!date)return;const option=week.selectedOptions[0];date.min=option?.dataset.start||'';date.max=option?.dataset.end||'';if(!date.value||date.value<date.min||date.value>date.max)date.value=date.min}
  subject?.addEventListener('change',filterClasses);week?.addEventListener('change',syncWeekDate);filterClasses();syncWeekDate();
  document.querySelectorAll('.obs-score').forEach(function(input){input.addEventListener('input',function(){const score=parseFloat(input.value),label=input.closest('form').querySelector('.obs-live-rating');label.textContent=Number.isNaN(score)?'Xếp loại tự động theo điểm':score>=18?'Giỏi':score>=13.5?'Khá':score>=10?'Trung bình':'Không đạt'})});
  document.querySelectorAll('.obs-review').forEach(function(form){
    const picker=form.querySelector('.obs-observer-picker'),summary=form.querySelector('.obs-observer-summary');
    function updateObserverSummary(){const count=form.querySelectorAll('input[name="observers[]"]:checked').length;summary.textContent=count?count+' người đã chọn':'Chọn người dự'}
    picker?.addEventListener('change',updateObserverSummary);updateObserverSummary();
    form.addEventListener('submit',function(event){if(!form.querySelector('input[name="observers[]"]:checked')){event.preventDefault();picker.open=true;alert('Vui lòng chọn ít nhất một người dự.')}});
  });
  document.querySelectorAll('.obs-edit').forEach(function(button){button.addEventListener('click',function(){const record=JSON.parse(decodeURIComponent(escape(atob(button.dataset.record))));document.getElementById('obsId').value=record.id||'';subject.value=record.subject||'';filterClasses();classSelect.value=record.class||'';week.value=record.week_number||'';syncWeekDate();date.value=record.date||'';document.getElementById('obsPpct').value=record.ppct_period||'';document.getElementById('obsTimetable').value=record.timetable_period||'';document.getElementById('obsLesson').value=record.lesson_title||'';document.getElementById('observationForm').scrollIntoView({behavior:'smooth'})})});
  document.getElementById('obsReset')?.addEventListener('click',function(){document.getElementById('observationForm').reset();document.getElementById('obsId').value='';filterClasses();syncWeekDate()});
})();
</script>
<?php require_once 'includes/footer.php'; ?>
