<?php
/** Giao diện quản lý lịch trực nội trú. */
$dutySection = $_GET['section'] ?? 'calendar';
$dutySections = ['calendar','assign','manage','stats','settings'];
if (!in_array($dutySection, $dutySections, true)) $dutySection = 'calendar';
// Lịch trực là nghiệp vụ toàn trường: quyền Sửa là chốt quản lý đầy đủ.
$canManageDuty = $canEditCurrent;
if (!$canManageDuty && !in_array($dutySection, ['calendar','stats'], true)) $dutySection = 'calendar';

$dutyMonth = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $dutyMonth)) $dutyMonth = date('Y-m');
$dutyMonthStart = $dutyMonth . '-01';
$dutyDays = (int)date('t', strtotime($dutyMonthStart));
$dutyPrevMonth = date('Y-m', strtotime($dutyMonthStart . ' -1 month'));
$dutyNextMonth = date('Y-m', strtotime($dutyMonthStart . ' +1 month'));
$dutyToday = date('Y-m-d');
$dutySettings = noitru_duty_settings();
$dutyAllRows = noitru_duty_all();
$dutyMonthRows = noitru_duty_for_month($dutyMonth);
$dutyManagers = noitru_duty_managers_all();
$dutyGroups = noitru_duty_groups_all();

$dutyTeacherMap = [];
foreach ($teachers as $teacher) {
    $teacherId = (string)($teacher['id'] ?? '');
    if ($teacherId !== '') $dutyTeacherMap[$teacherId] = (string)($teacher['name'] ?? '');
}
$dutyRoster = noitru_duty_roster_all($dutyTeacherMap);
$dutyRosterMap = [];
$dutyRosterLimits = [];
foreach ($dutyRoster as $row) {
    $teacherId = (string)($row['teacher_id'] ?? '');
    if ($teacherId === '') continue;
    $dutyRosterMap[$teacherId] = $dutyTeacherMap[$teacherId] ?? (string)($row['teacher_name'] ?? '');
    $dutyRosterLimits[$teacherId] = (int)($row['max_per_month'] ?? 0);
}
$dutyTeacherFilter = trim($_GET['teacher'] ?? '');
if ($dutyTeacherFilter !== '' && !isset($dutyTeacherMap[$dutyTeacherFilter])) $dutyTeacherFilter = '';

$dutyByDate = [];
$dutyByTeacher = [];
foreach ($dutyMonthRows as $row) {
    $date = (string)($row['date'] ?? '');
    $teacherId = (string)($row['teacher_id'] ?? '');
    $dutyByDate[$date][] = $row;
    if ($teacherId !== '') {
        $dutyByTeacher[$teacherId][$date] = $row;
    }
}
$dutyManagerByDate = [];
foreach ($dutyManagers as $row) $dutyManagerByDate[(string)($row['date'] ?? '')] = $row;

$dutyStartTime = $dutySettings['start_time'] ?? '06:00';
$dutyEndTime = $dutySettings['end_time'] ?? '06:00';
$now = time();
$todayStart = strtotime($dutyToday . ' ' . $dutyStartTime);
$currentDutyDate = $now < $todayStart ? date('Y-m-d', strtotime($dutyToday . ' -1 day')) : $dutyToday;
$currentDutyStart = strtotime($currentDutyDate . ' ' . $dutyStartTime);
$currentDutyEnd = strtotime($currentDutyDate . ' ' . $dutyEndTime);
if ($currentDutyEnd <= $currentDutyStart) $currentDutyEnd = strtotime('+1 day', $currentDutyEnd);
$remainingSeconds = max(0, $currentDutyEnd - $now);
$remainingHours = intdiv($remainingSeconds, 3600);
$remainingMinutes = intdiv($remainingSeconds % 3600, 60);
$currentDutyRows = array_values(array_filter($dutyAllRows, fn($row) => ($row['date'] ?? '') === $currentDutyDate));
$nextDutyDate = date('Y-m-d', $currentDutyEnd);
$nextDutyRows = array_values(array_filter($dutyAllRows, fn($row) => ($row['date'] ?? '') === $nextDutyDate));

$dutyWeekdayNames = ['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ Nhật'];
$dutyShortWeekdays = ['T2','T3','T4','T5','T6','T7','CN'];
$dutyMonthStats = [];
foreach ($dutyRosterMap as $teacherId=>$teacherName) $dutyMonthStats[$teacherId] = 0;
$dutySundayStats = array_fill_keys(array_keys($dutyRosterMap), 0);
foreach ($dutyMonthRows as $row) {
    $teacherId = (string)($row['teacher_id'] ?? '');
    if (isset($dutyMonthStats[$teacherId])) $dutyMonthStats[$teacherId]++;
    if (isset($dutySundayStats[$teacherId]) && (int)date('N', strtotime($row['date'] ?? '')) === 7) $dutySundayStats[$teacherId]++;
}
$dutyPreviousStats = array_fill_keys(array_keys($dutyRosterMap), 0);
$dutyPreviousSundayStats = array_fill_keys(array_keys($dutyRosterMap), 0);
foreach (noitru_duty_for_month($dutyPrevMonth) as $row) {
    $teacherId = (string)($row['teacher_id'] ?? '');
    if (isset($dutyPreviousStats[$teacherId])) $dutyPreviousStats[$teacherId]++;
    if (isset($dutyPreviousSundayStats[$teacherId]) && (int)date('N', strtotime($row['date'] ?? '')) === 7) $dutyPreviousSundayStats[$teacherId]++;
}
$dutySuggestionIds = array_keys($dutyRosterMap);
usort($dutySuggestionIds, function ($a, $b) use ($dutyPreviousStats, $dutyPreviousSundayStats, $dutyRosterMap) {
    $cmp = ($dutyPreviousStats[$a] ?? 0) <=> ($dutyPreviousStats[$b] ?? 0);
    if ($cmp === 0) $cmp = ($dutyPreviousSundayStats[$a] ?? 0) <=> ($dutyPreviousSundayStats[$b] ?? 0);
    return $cmp !== 0 ? $cmp : strcasecmp($dutyRosterMap[$a] ?? '', $dutyRosterMap[$b] ?? '');
});
arsort($dutyMonthStats);

if (!function_exists('nt_duty_url')) {
    function nt_duty_url($section, $month, array $extra = []) {
        return BASE_URL . 'noitru.php?' . http_build_query(array_merge([
            'tab'=>'duty', 'section'=>$section, 'month'=>$month,
        ], $extra));
    }
}
?>
<style>
.duty-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.duty-page-head h4{font-weight:850;margin:0}.duty-page-head p{margin:.3rem 0 0;color:#64748b}.duty-title-icon{color:#0ea5e9}
.duty-current{padding:1.1rem 1.2rem;margin-bottom:1rem;border:1px solid #bae6fd;border-radius:18px;background:linear-gradient(135deg,#f0f9ff,#eaf6ff)}.duty-current-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.duty-current h6{font-weight:800}.duty-time-pill{padding:.35rem .7rem;border:1px solid #dbe7ef;border-radius:999px;background:rgba(255,255,255,.7);font-size:.76rem;font-weight:750;white-space:nowrap}.duty-current-names{display:flex;gap:.45rem;flex-wrap:wrap;margin:.65rem 0}.duty-person-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .62rem;border-radius:999px;background:#fff;color:#075985;font-size:.8rem;font-weight:750;box-shadow:0 2px 8px rgba(14,165,233,.08)}.duty-next{padding-top:.65rem;border-top:1px solid #dbeafe;color:#52657a;font-size:.8rem}
.duty-tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.35rem;padding:.32rem;margin-bottom:1rem;border-radius:14px;background:#eaf1f5}.duty-tabs a{display:flex;align-items:center;justify-content:center;gap:.42rem;min-height:42px;padding:.5rem;text-decoration:none;color:#304254;border-radius:10px;font-weight:750;font-size:.8rem}.duty-tabs a.active{background:#fff;color:#0284c7;box-shadow:0 2px 8px rgba(15,23,42,.08)}
.duty-toolbar{display:flex;align-items:center;justify-content:space-between;gap:.7rem;flex-wrap:wrap;padding:.8rem 1rem;margin-bottom:1rem;border:1px solid #e5edf3;border-radius:16px;background:#fff;box-shadow:0 3px 13px rgba(15,23,42,.05)}.duty-month-nav{display:flex;align-items:center;gap:.7rem}.duty-month-nav strong{min-width:112px;text-align:center;font-size:1rem}.duty-icon-btn{display:grid;place-items:center;width:38px;height:38px;border:1px solid #e2e8f0;border-radius:10px;color:#334155;text-decoration:none;background:#fff}.duty-toolbar-actions{display:flex;gap:.45rem;align-items:center;flex-wrap:wrap}
.duty-calendar{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:.65rem}.duty-day{min-height:150px;overflow:hidden;border:1px solid #e1e9ef;border-radius:15px;background:#fff;box-shadow:0 3px 12px rgba(15,23,42,.045)}.duty-day.weekend{border-color:#fed7aa;background:#fffaf5}.duty-day.today{border:2px solid #0ea5e9}.duty-day-head{padding:.65rem;text-align:center;background:#f7fafc;border-bottom:1px solid #e6edf2}.duty-day.weekend .duty-day-head{background:#fff7ed;color:#ea580c}.duty-day.today .duty-day-head{background:#e0f2fe;color:#0369a1}.duty-day-head small,.duty-day-head strong,.duty-day-head span{display:block}.duty-day-head strong{font-size:1.15rem}.duty-day-head small{font-size:.68rem}.duty-day-head span{font-size:.66rem;color:#718096}.duty-day-body{padding:.6rem}.duty-day-person{display:flex;align-items:center;gap:.38rem;margin-bottom:.35rem;font-size:.75rem;font-weight:650}.duty-day-person i{color:#0ea5e9}.duty-day-empty{text-align:center;color:#94a3b8;font-size:.74rem;padding-top:.75rem}.duty-manager-tag{display:block;margin-top:.55rem;padding-top:.45rem;border-top:1px dashed #dbe3ea;color:#b45309;font-size:.68rem}.duty-calendar-blank{min-height:1px}
.duty-panel{border:1px solid #e4ebf0;border-radius:17px;background:#fff;box-shadow:0 3px 14px rgba(15,23,42,.05)}.duty-panel-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem;flex-wrap:wrap;padding:1rem;border-bottom:1px solid #edf1f4}.duty-panel-head h6{margin:0;font-weight:800}.duty-panel-body{padding:1rem}.duty-action-row{display:flex;gap:.45rem;flex-wrap:wrap}.duty-action-row form{display:inline-flex}.duty-mode-note{padding:.65rem .8rem;margin-bottom:.8rem;border-radius:12px;background:#f0f9ff;color:#075985;font-size:.78rem}
.duty-tools-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem}.duty-tool-box{padding:.85rem;border:1px solid #e3edf3;border-radius:14px;background:#fbfdff}.duty-tool-box h6{font-size:.86rem;font-weight:800}.duty-tool-form{display:flex;align-items:end;gap:.5rem;flex-wrap:wrap}.duty-tool-form .form-select{min-width:180px}.duty-weekday-picks{display:flex;gap:.28rem;flex-wrap:wrap}.duty-weekday-picks label{cursor:pointer}.duty-weekday-picks input{position:absolute;opacity:0;pointer-events:none}.duty-weekday-picks span{display:grid;place-items:center;min-width:38px;height:35px;padding:0 .45rem;border:1px solid #cbdbe5;border-radius:9px;background:#fff;font-size:.76rem;font-weight:750}.duty-weekday-picks input:checked+span{border-color:#0ea5e9;background:#e0f2fe;color:#0369a1}.duty-suggestions{display:flex;gap:.4rem;flex-wrap:wrap;padding:.65rem 0 .15rem}.duty-suggestion{padding:.45rem .65rem;border:1px solid #bae6fd;border-radius:11px;background:#f0f9ff;font-size:.74rem}.duty-suggestion strong{display:block;color:#075985}.duty-suggestions-details{margin-bottom:.85rem;border:1px solid #dbeafe;border-radius:12px;background:#f8fcff}.duty-suggestions-details summary{display:flex;align-items:center;gap:.45rem;padding:.65rem .75rem;color:#52657a;font-size:.78rem;font-weight:750;cursor:pointer;list-style:none}.duty-suggestions-details summary::-webkit-details-marker{display:none}.duty-suggestions-details summary::after{content:'Mở';margin-left:auto;padding:.2rem .48rem;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:.68rem}.duty-suggestions-details[open] summary::after{content:'Ẩn'}.duty-suggestions-details .duty-suggestions{padding:.15rem .75rem .75rem}.duty-roster-row{padding:.7rem 0;border-bottom:1px solid #edf1f4}.duty-roster-row:last-child{border-bottom:0}.duty-roster-form{display:grid;grid-template-columns:minmax(160px,1fr) 110px minmax(160px,1fr) auto;gap:.45rem;align-items:center}
.duty-matrix-wrap{max-width:100%;overflow-x:auto;overflow-y:visible;border:1px solid #e5edf2;border-radius:13px;-webkit-overflow-scrolling:touch}.duty-matrix{border-collapse:separate;border-spacing:0;min-width:max-content;margin:0}.duty-matrix th,.duty-matrix td{height:48px;padding:.25rem;text-align:center;border-right:1px solid #e6edf2;border-bottom:1px solid #e6edf2;background:#fff}.duty-matrix thead th{position:sticky;top:0;z-index:3;min-width:42px;background:#edf5f9}.duty-matrix .teacher-col{position:sticky;left:0;z-index:2;min-width:225px;padding:.55rem .7rem;text-align:left;background:#fff}.duty-matrix thead .teacher-col{z-index:4;background:#e7f0f5}.duty-matrix .count-col{min-width:58px}.duty-matrix-day{display:grid;place-items:center;border:0;width:29px;height:29px;border-radius:50%;background:#fff;box-shadow:inset 0 0 0 1.5px #7dd3fc;color:transparent;cursor:pointer;transition:.12s}.duty-matrix-choice input{position:absolute;opacity:0;pointer-events:none}.duty-matrix-choice input:checked+.duty-matrix-day{background:#0ea5e9;box-shadow:none;color:#fff}.duty-matrix-day:hover{transform:scale(1.08)}.duty-weekend-col{background:#fffaf5!important;color:#ea580c}.duty-bulk-bar{position:sticky;left:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-bottom:.65rem;padding:.65rem .75rem;border:1px solid #bae6fd;border-radius:12px;background:#f0f9ff}.duty-bulk-status{font-size:.76rem;color:#075985}.duty-bulk-status strong{display:block;font-size:.82rem}.duty-bulk-status.warning{color:#b45309}
.duty-manager-table select{min-width:220px}.duty-manager-table tr.weekend>*{background:#fffaf5}.duty-manager-table td{vertical-align:middle}.duty-manager-table form{display:flex;align-items:center;gap:.45rem}.duty-manager-table .manager-note{min-width:180px}
.duty-stat-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;margin-bottom:1rem}.duty-stat-card{padding:1rem;border:1px solid #e5edf2;border-radius:15px;background:#fff}.duty-stat-card strong{display:block;font-size:1.5rem;color:#0284c7}.duty-stat-card small{color:#64748b}.duty-progress{height:7px;margin-top:.35rem;border-radius:99px;background:#e8eef2;overflow:hidden}.duty-progress span{display:block;height:100%;border-radius:inherit;background:#0ea5e9}
.duty-settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.duty-settings-card{padding:1rem;border:1px solid #e5edf2;border-radius:16px;background:#fff}.duty-settings-card h6{font-weight:800}.duty-group-row{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;padding:.65rem 0;border-bottom:1px solid #edf1f4}.duty-group-row:last-child{border-bottom:0}.duty-help{color:#64748b;font-size:.76rem}
@media(max-width:991.98px){.duty-calendar{grid-template-columns:repeat(4,minmax(0,1fr))}.duty-calendar-blank{display:none}.duty-stat-cards{grid-template-columns:1fr 1fr}.duty-settings-grid,.duty-tools-grid{grid-template-columns:1fr}.duty-tabs{overflow-x:auto;display:flex}.duty-tabs a{flex:0 0 auto;min-width:128px}.duty-roster-form{grid-template-columns:1fr 100px}.duty-roster-form .roster-note{grid-column:1/-1}}
@media(max-width:575.98px){.duty-page-head p{font-size:.78rem}.duty-current{padding:.85rem}.duty-current-top{display:block}.duty-time-pill{display:inline-block;margin-top:.45rem}.duty-tabs a{min-width:112px;font-size:.74rem}.duty-calendar{grid-template-columns:1fr 1fr;gap:.45rem}.duty-day{min-height:132px}.duty-toolbar{padding:.65rem}.duty-toolbar-actions{width:100%}.duty-toolbar-actions>*{flex:1}.duty-toolbar-actions .form-select{width:100%!important}.duty-stat-cards{gap:.45rem}.duty-stat-card{padding:.75rem}.duty-stat-card strong{font-size:1.2rem}.duty-panel-body{padding:.7rem}.duty-manager-table form{min-width:520px}.duty-matrix .teacher-col{min-width:170px}}
</style>

<div class="duty-page-head">
  <div><h4><i class="bi bi-calendar2-week duty-title-icon"></i> Lịch trực</h4><p>Quản lý phân công trực · Ca từ <?= e($dutyStartTime) ?> đến <?= e($dutyEndTime) ?> hôm sau</p></div>
</div>

<section class="duty-current">
  <div class="duty-current-top">
    <div><h6><i class="bi bi-clock text-info me-1"></i> Đang trực hiện tại</h6><div class="small text-muted">Ca trực ngày <?= e(date('d/m/Y', strtotime($currentDutyDate))) ?> (<?= e($dutyStartTime) ?> – <?= e($dutyEndTime) ?> hôm sau)</div></div>
    <span class="duty-time-pill"><i class="bi bi-clock-history"></i> Còn <?= $remainingHours ?>h <?= $remainingMinutes ?>p</span>
  </div>
  <div class="duty-current-names">
    <?php if ($currentDutyRows): foreach ($currentDutyRows as $row): ?><span class="duty-person-pill"><i class="bi bi-person-check-fill"></i><?= e($row['teacher_name'] ?? 'Chưa rõ') ?></span><?php endforeach; else: ?><span class="text-muted small">Chưa có người trực được phân công</span><?php endif; ?>
  </div>
  <div class="duty-next"><i class="bi bi-arrow-right me-1"></i> Ca tiếp theo: <strong><?= e(date('d/m/Y', strtotime($nextDutyDate))) ?></strong> · <?= $nextDutyRows ? e(implode(', ', array_column($nextDutyRows, 'teacher_name'))) : 'Chưa phân công' ?></div>
</section>

<nav class="duty-tabs" aria-label="Chức năng lịch trực">
  <?php
  $dutyTabItems = [
      'calendar'=>['bi-calendar3','Lịch trực'],
      'assign'=>['bi-people','Phân công'],
      'manage'=>['bi-shield-check','Quản lý trực'],
      'stats'=>['bi-bar-chart','Thống kê'],
      'settings'=>['bi-gear','Cài đặt'],
  ];
  foreach ($dutyTabItems as $key=>$item):
      if (!$canManageDuty && !in_array($key, ['calendar','stats'], true)) continue;
  ?>
  <a class="<?= $dutySection===$key?'active':'' ?>" href="<?= e(nt_duty_url($key,$dutyMonth)) ?>"><i class="bi <?= e($item[0]) ?>"></i><?= e($item[1]) ?></a>
  <?php endforeach; ?>
</nav>

<div class="duty-toolbar">
  <div class="duty-month-nav"><a class="duty-icon-btn" href="<?= e(nt_duty_url($dutySection,$dutyPrevMonth)) ?>"><i class="bi bi-chevron-left"></i></a><strong>Tháng <?= e(date('m/Y', strtotime($dutyMonthStart))) ?></strong><a class="duty-icon-btn" href="<?= e(nt_duty_url($dutySection,$dutyNextMonth)) ?>"><i class="bi bi-chevron-right"></i></a></div>
  <div class="duty-toolbar-actions">
    <?php if ($dutySection==='calendar'): ?>
    <form method="get"><input type="hidden" name="tab" value="duty"><input type="hidden" name="section" value="calendar"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><select name="teacher" class="form-select form-select-sm" style="width:190px" onchange="this.form.submit()"><option value="">Tất cả người trực</option><?php foreach ($dutyTeacherMap as $id=>$name): ?><option value="<?= e($id) ?>" <?= $dutyTeacherFilter===$id?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?></select></form>
    <a class="btn btn-sm btn-outline-info" href="<?= e(nt_duty_url('calendar',date('Y-m'))) ?>">Hôm nay</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($dutySection === 'calendar'): ?>
  <div class="duty-calendar">
    <?php $firstOffset=(int)date('N',strtotime($dutyMonthStart))-1; for($blank=0;$blank<$firstOffset;$blank++): ?><div class="duty-calendar-blank"></div><?php endfor; ?>
    <?php for ($day=1;$day<=$dutyDays;$day++):
      $date=$dutyMonth.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);$weekday=(int)date('N',strtotime($date));
      $rows=$dutyByDate[$date]??[];if($dutyTeacherFilter!=='')$rows=array_values(array_filter($rows,fn($row)=>($row['teacher_id']??'')===$dutyTeacherFilter));
      $manager=$dutyManagerByDate[$date]??null;
    ?>
    <article class="duty-day <?= $weekday>=6?'weekend':'' ?> <?= $date===$dutyToday?'today':'' ?>">
      <div class="duty-day-head"><small><?= e($dutyWeekdayNames[$weekday-1]) ?></small><strong><?= $day ?></strong><span><?= e(date('m/Y',strtotime($date))) ?></span></div>
      <div class="duty-day-body">
        <?php if ($rows): foreach ($rows as $row): ?><div class="duty-day-person"><i class="bi bi-person-circle"></i><span><?= e($row['teacher_name']??'') ?></span></div><?php endforeach; else: ?><div class="duty-day-empty">Chưa phân công</div><?php endif; ?>
        <?php if ($manager && !empty($manager['teacher_names'])): ?><span class="duty-manager-tag"><i class="bi bi-shield-check"></i> QL: <?= e(implode(', ',$manager['teacher_names'])) ?></span><?php endif; ?>
      </div>
    </article>
    <?php endfor; ?>
  </div>

<?php elseif ($dutySection === 'assign'): ?>
  <section class="duty-panel">
    <div class="duty-panel-head"><div><h6><i class="bi bi-people text-info"></i> Phân công giáo viên</h6><div class="duty-help">Bấm vào vòng tròn để thêm hoặc bỏ một lượt trực.</div></div><div class="duty-action-row">
      <form method="post" onsubmit="return confirm('Sao chép lịch trực tháng trước?')"><input type="hidden" name="action" value="duty_copy"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="assign"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-copy"></i> Sao chép tháng trước</button></form>
      <form method="post" onsubmit="return confirm('Tự động bổ sung các ngày còn thiếu theo cài đặt?')"><input type="hidden" name="action" value="duty_auto"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="assign"><button class="btn btn-sm btn-outline-info"><i class="bi bi-magic"></i> Tự động phân công</button></form>
      <?php if ($canDeleteCurrent): ?><form method="post" onsubmit="return confirm('Xóa toàn bộ lịch trực tháng này?')"><input type="hidden" name="action" value="duty_month_clear"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="assign"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa cả tháng</button></form><?php endif; ?>
    </div></div>
    <div class="duty-panel-body">
      <div class="duty-mode-note"><i class="bi bi-info-circle"></i> Mục tiêu hiện tại: <?= (int)$dutySettings['people_per_day'] ?> người/ngày, tối đa <?= (int)$dutySettings['max_per_month'] ?> lượt/người/tháng.</div>
      <div class="duty-tools-grid">
        <div class="duty-tool-box"><h6><i class="bi bi-calendar-plus text-info"></i> Gán nhanh theo thứ</h6><form method="post" class="duty-tool-form"><input type="hidden" name="action" value="duty_assign_weekday"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="assign"><select name="teacher_id" class="form-select form-select-sm" required><option value="">Chọn người trực</option><?php foreach($dutyRosterMap as $id=>$name):?><option value="<?= e($id) ?>"><?= e($name) ?></option><?php endforeach;?></select><div class="duty-weekday-picks"><?php foreach($dutyShortWeekdays as $index=>$label):?><label><input type="checkbox" name="weekdays[]" value="<?= $index+1 ?>"><span><?= e($label) ?></span></label><?php endforeach;?></div><button class="btn btn-sm btn-info text-white">Gán lịch</button></form></div>
        <div class="duty-tool-box"><h6><i class="bi bi-arrow-left-right text-warning"></i> Đổi lịch trực</h6><form method="post" class="duty-tool-form" onsubmit="return confirm('Xác nhận đổi ngày trực của hai lượt đã chọn?')"><input type="hidden" name="action" value="duty_swap"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="assign"><select name="row_a" class="form-select form-select-sm" required><option value="">Lượt trực thứ nhất</option><?php foreach($dutyMonthRows as $row):?><option value="<?= e($row['id']??'') ?>"><?= e(date('d/m',strtotime($row['date']??''))) ?> · <?= e($row['teacher_name']??'') ?></option><?php endforeach;?></select><select name="row_b" class="form-select form-select-sm" required><option value="">Lượt trực thứ hai</option><?php foreach($dutyMonthRows as $row):?><option value="<?= e($row['id']??'') ?>"><?= e(date('d/m',strtotime($row['date']??''))) ?> · <?= e($row['teacher_name']??'') ?></option><?php endforeach;?></select><button class="btn btn-sm btn-outline-warning">Đổi lịch</button></form></div>
      </div>
      <?php if($dutySuggestionIds):?><details class="duty-suggestions-details"><summary><i class="bi bi-lightbulb text-warning"></i> Gợi ý bù lượt tháng này <span class="fw-normal">(ưu tiên người trực ít ở tháng <?= e(date('m/Y',strtotime($dutyPrevMonth.'-01'))) ?>)</span></summary><div class="duty-suggestions"><?php foreach(array_slice($dutySuggestionIds,0,8) as $teacherId):?><span class="duty-suggestion"><strong><?= e($dutyRosterMap[$teacherId]??'') ?></strong>Tháng trước: <?= (int)($dutyPreviousStats[$teacherId]??0) ?> lượt · CN: <?= (int)($dutyPreviousSundayStats[$teacherId]??0) ?></span><?php endforeach;?></div></details><?php endif;?>
      <?php if(!$dutyRosterMap):?><div class="alert alert-warning">Danh sách trực đang trống. Hãy thêm người tại tab <strong>Cài đặt</strong>.</div><?php else:?>
      <form method="post" id="dutyBulkForm">
        <input type="hidden" name="action" value="duty_bulk_save"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="confirm_mismatch" id="dutyConfirmMismatch" value="0">
        <div class="duty-bulk-bar"><div class="duty-bulk-status" id="dutyBulkStatus"><strong>Chấm trực tiếp trên danh sách</strong><span>Tích/bỏ các vòng tròn rồi lưu một lần.</span></div><button class="btn btn-sm btn-success text-white" type="submit"><i class="bi bi-floppy"></i> Lưu phân công</button></div>
        <div class="duty-matrix-wrap"><table class="duty-matrix"><thead><tr><th class="teacher-col">Giáo viên</th><th class="count-col">Lần</th><th class="count-col">CN</th><?php for($day=1;$day<=$dutyDays;$day++):$date=$dutyMonth.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);$weekday=(int)date('N',strtotime($date));?><th class="<?= $weekday>=6?'duty-weekend-col':'' ?>"><small><?= e($dutyShortWeekdays[$weekday-1]) ?></small><br><?= $day ?></th><?php endfor;?></tr></thead><tbody>
        <?php foreach ($dutyRosterMap as $teacherId=>$teacherName):$personalLimit=($dutyRosterLimits[$teacherId]??0)>0?$dutyRosterLimits[$teacherId]:(int)$dutySettings['max_per_month']; ?><tr data-duty-teacher="<?= e($teacherId) ?>"><td class="teacher-col"><i class="bi bi-person-circle text-info me-1"></i><strong><?= e($teacherName) ?></strong></td><td class="count-col"><span class="badge bg-light text-dark border"><span data-duty-total><?= (int)($dutyMonthStats[$teacherId]??0) ?></span>/<?= (int)$personalLimit ?></span></td><td class="count-col"><span class="badge bg-light text-warning border" data-duty-sundays><?= (int)($dutySundayStats[$teacherId]??0) ?></span></td><?php for($day=1;$day<=$dutyDays;$day++):$date=$dutyMonth.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);$selected=isset($dutyByTeacher[$teacherId][$date]);$weekday=(int)date('N',strtotime($date));?><td class="<?= $weekday>=6?'duty-weekend-col':'' ?>"><label class="duty-matrix-choice" aria-label="<?= $selected?'Bỏ':'Thêm' ?> lịch trực <?= e($teacherName) ?> ngày <?= e(date('d/m/Y',strtotime($date))) ?>"><input type="checkbox" name="assignments[<?= e($teacherId) ?>][]" value="<?= e($date) ?>" data-duty-date="<?= e($date) ?>" data-duty-sunday="<?= $weekday===7?'1':'0' ?>" <?= $selected?'checked':'' ?>><span class="duty-matrix-day"><i class="bi bi-check"></i></span></label></td><?php endfor;?></tr><?php endforeach; ?>
        </tbody></table></div>
      </form>
      <?php endif;?>
    </div>
  </section>
  <script>
  (function(){
    var form=document.getElementById('dutyBulkForm');if(!form)return;
    var expected=<?= (int)$dutySettings['people_per_day'] ?>,days=<?= (int)$dutyDays ?>,month=<?= json_encode($dutyMonth) ?>,status=document.getElementById('dutyBulkStatus');
    function refresh(){
      form.querySelectorAll('tbody tr[data-duty-teacher]').forEach(function(row){
        var checked=row.querySelectorAll('input[data-duty-date]:checked');
        row.querySelector('[data-duty-total]').textContent=checked.length;
        row.querySelector('[data-duty-sundays]').textContent=Array.from(checked).filter(function(input){return input.dataset.dutySunday==='1'}).length;
      });
      var counts={};form.querySelectorAll('input[data-duty-date]:checked').forEach(function(input){counts[input.dataset.dutyDate]=(counts[input.dataset.dutyDate]||0)+1});
      var under=[],over=[];
      for(var day=1;day<=days;day++){var date=month+'-'+String(day).padStart(2,'0'),count=counts[date]||0,label=String(day).padStart(2,'0')+'/'+month.slice(5,7);if(count<expected)under.push(label+' ('+count+'/'+expected+')');else if(count>expected)over.push(label+' ('+count+'/'+expected+')')}
      status.classList.toggle('warning',under.length>0||over.length>0);
      status.innerHTML='<strong>'+((under.length||over.length)?'Chưa đúng định mức '+expected+' người/ca':'Đã đủ '+expected+' người cho tất cả các ngày')+'</strong><span>'+(under.length?'Thiếu: '+under.length+' ngày. ':'')+(over.length?'Vượt: '+over.length+' ngày. ':'')+'Tích/bỏ vòng tròn rồi bấm Lưu phân công.</span>';
      return {under:under,over:over};
    }
    form.addEventListener('change',refresh);
    form.addEventListener('submit',function(event){var mismatch=refresh();if(!mismatch.under.length&&!mismatch.over.length)return;var lines=[];if(mismatch.under.length)lines.push('Ngày thiếu: '+mismatch.under.slice(0,12).join(', ')+(mismatch.under.length>12?'…':''));if(mismatch.over.length)lines.push('Ngày vượt: '+mismatch.over.slice(0,12).join(', ')+(mismatch.over.length>12?'…':''));if(!confirm('Số người trực chưa đúng cài đặt '+expected+' người/ca.\\n\\n'+lines.join('\\n')+'\\n\\nBạn vẫn muốn lưu?')){event.preventDefault();return}document.getElementById('dutyConfirmMismatch').value='1'});
    refresh();
  })();
  </script>

<?php elseif ($dutySection === 'manage'): ?>
  <section class="duty-panel"><div class="duty-panel-head"><div><h6><i class="bi bi-shield-check text-warning"></i> Phân công quản lý trực</h6><div class="duty-help">Chọn quản lý theo từng ngày hoặc gán nhanh một người theo các thứ trong tuần.</div></div></div><div class="duty-panel-body border-bottom"><div class="duty-tool-box"><h6><i class="bi bi-lightning-charge text-warning"></i> Gán quản lý nhanh theo thứ</h6><form method="post" class="duty-tool-form"><input type="hidden" name="action" value="duty_manager_weekday"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="manage"><select name="teacher_id" class="form-select form-select-sm" required><option value="">Chọn người quản lý</option><?php foreach($dutyTeacherMap as $id=>$name):?><option value="<?= e($id) ?>"><?= e($name) ?></option><?php endforeach;?></select><div class="duty-weekday-picks"><?php foreach($dutyShortWeekdays as $index=>$label):?><label><input type="checkbox" name="weekdays[]" value="<?= $index+1 ?>"><span><?= e($label) ?></span></label><?php endforeach;?></div><label class="form-check mb-2"><input type="checkbox" name="append" value="1" class="form-check-input"><span class="form-check-label small">Thêm cùng quản lý đã có</span></label><button class="btn btn-sm btn-warning text-white">Gán nhanh</button></form></div></div><div class="table-responsive"><table class="table duty-manager-table mb-0"><thead><tr><th>Ngày</th><th>Thứ</th><th>Quản lý trực</th><th>Ghi chú</th><th></th></tr></thead><tbody>
  <?php for($day=1;$day<=$dutyDays;$day++):$date=$dutyMonth.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);$weekday=(int)date('N',strtotime($date));$manager=$dutyManagerByDate[$date]??[];$selectedIds=$manager['teacher_ids']??[]; ?><tr class="<?= $weekday>=6?'weekend':'' ?>"><td><strong><?= e(date('d/m',strtotime($date))) ?></strong></td><td><?= e($dutyWeekdayNames[$weekday-1]) ?></td><td colspan="3"><form method="post"><input type="hidden" name="action" value="duty_manager_save"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="manage"><input type="hidden" name="date" value="<?= e($date) ?>"><select name="teacher_ids[]" class="form-select form-select-sm" multiple size="2"><?php foreach($dutyTeacherMap as $id=>$name):?><option value="<?= e($id) ?>" <?= in_array($id,$selectedIds,true)?'selected':'' ?>><?= e($name) ?></option><?php endforeach;?></select><input type="text" name="note" class="form-control form-control-sm manager-note" placeholder="Ghi chú" value="<?= e($manager['note']??'') ?>"><button class="btn btn-sm btn-outline-info">Lưu</button></form></td></tr><?php endfor; ?>
  </tbody></table></div></section>

<?php elseif ($dutySection === 'stats'): ?>
  <?php $assignedDays=count(array_filter(range(1,$dutyDays),fn($day)=>!empty($dutyByDate[$dutyMonth.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT)])));$totalSlots=count($dutyMonthRows);$requiredSlots=$dutyDays*(int)$dutySettings['people_per_day']; ?>
  <div class="duty-stat-cards"><div class="duty-stat-card"><strong><?= $totalSlots ?></strong><small>Tổng lượt đã phân công</small></div><div class="duty-stat-card"><strong><?= $assignedDays ?>/<?= $dutyDays ?></strong><small>Ngày đã có lịch</small></div><div class="duty-stat-card"><strong><?= max(0,$dutyDays-$assignedDays) ?></strong><small>Ngày chưa phân công</small></div><div class="duty-stat-card"><strong><?= $requiredSlots?round($totalSlots/$requiredSlots*100):0 ?>%</strong><small>Mức hoàn thành kế hoạch</small></div></div>
  <section class="duty-panel"><div class="duty-panel-head"><h6><i class="bi bi-bar-chart text-info"></i> Số lượt trực theo giáo viên</h6></div><div class="duty-panel-body"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Giáo viên</th><th>Lượt trực</th><th style="min-width:220px">Tiến độ giới hạn tháng</th></tr></thead><tbody><?php foreach($dutyMonthStats as $teacherId=>$count):if($count===0)continue;$percent=min(100,round($count/max(1,(int)$dutySettings['max_per_month'])*100));?><tr><td><strong><?= e($dutyTeacherMap[$teacherId]??'Không rõ') ?></strong></td><td><?= $count ?></td><td><div class="duty-progress"><span style="width:<?= $percent ?>%"></span></div><small class="text-muted"><?= $count ?>/<?= (int)$dutySettings['max_per_month'] ?> lượt</small></td></tr><?php endforeach;?><?php if(!array_filter($dutyMonthStats)):?><tr><td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu phân công trong tháng.</td></tr><?php endif;?></tbody></table></div></div></section>

<?php elseif ($dutySection === 'settings'): ?>
  <div class="duty-settings-grid">
    <section class="duty-settings-card"><h6><i class="bi bi-gear text-info"></i> Cài đặt chung</h6><p class="duty-help">Các giới hạn được dùng khi tự động phân công.</p><form method="post"><input type="hidden" name="action" value="duty_settings_save"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="settings"><div class="row g-2"><div class="col-6"><label class="form-label">Số người trực/ngày</label><input type="number" min="1" max="20" name="people_per_day" class="form-control" value="<?= (int)$dutySettings['people_per_day'] ?>"></div><div class="col-6"><label class="form-label">Tối đa lượt/người/tháng</label><input type="number" min="1" max="31" name="max_per_month" class="form-control" value="<?= (int)$dutySettings['max_per_month'] ?>"></div><div class="col-6"><label class="form-label">Bắt đầu ca</label><input type="time" name="start_time" class="form-control" value="<?= e($dutyStartTime) ?>"></div><div class="col-6"><label class="form-label">Kết thúc ca hôm sau</label><input type="time" name="end_time" class="form-control" value="<?= e($dutyEndTime) ?>"></div></div><button class="btn btn-info text-white mt-3"><i class="bi bi-floppy"></i> Lưu cài đặt</button></form></section>
    <section class="duty-settings-card"><h6><i class="bi bi-people text-info"></i> Tạo nhóm trực</h6><p class="duty-help">Nhóm giúp lưu sẵn danh sách giáo viên thường trực cùng nhau.</p><form method="post"><input type="hidden" name="action" value="duty_group_save"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="settings"><label class="form-label">Tên nhóm</label><input type="text" name="name" class="form-control mb-2" required placeholder="Ví dụ: Nhóm trực số 1"><label class="form-label">Thành viên</label><select name="teacher_ids[]" class="form-select" multiple size="6"><?php foreach($dutyTeacherMap as $id=>$name):?><option value="<?= e($id) ?>"><?= e($name) ?></option><?php endforeach;?></select><button class="btn btn-info text-white mt-3"><i class="bi bi-plus-lg"></i> Thêm nhóm</button></form></section>
  </div>
  <section class="duty-panel mt-3"><div class="duty-panel-head"><div><h6><i class="bi bi-person-lines-fill text-info"></i> Danh sách người trực</h6><div class="duty-help">Thêm, sửa hoặc xóa người được phép phân công. Xóa khỏi danh sách không làm mất lịch cũ.</div></div></div><div class="duty-panel-body">
    <form method="post" class="duty-tool-form pb-3 border-bottom"><input type="hidden" name="action" value="duty_roster_save"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="settings"><select name="teacher_id" class="form-select" required><option value="">Chọn giáo viên để thêm</option><?php foreach($dutyTeacherMap as $id=>$name):if(isset($dutyRosterMap[$id]))continue;?><option value="<?= e($id) ?>"><?= e($name) ?></option><?php endforeach;?></select><div><label class="form-label small mb-1">Giới hạn riêng</label><input type="number" name="max_per_month" min="0" max="31" value="0" class="form-control" title="0 là dùng giới hạn chung"></div><input type="text" name="note" class="form-control" placeholder="Ghi chú (không bắt buộc)"><button class="btn btn-info text-white"><i class="bi bi-person-plus"></i> Thêm</button></form>
    <?php if($dutyRoster):foreach($dutyRoster as $row):$teacherId=(string)($row['teacher_id']??'');?><div class="duty-roster-row"><form method="post" class="duty-roster-form"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="settings"><input type="hidden" name="teacher_id" value="<?= e($teacherId) ?>"><div><strong><?= e($dutyRosterMap[$teacherId]??($row['teacher_name']??'')) ?></strong><div class="duty-help">Giáo viên trực</div></div><input type="number" name="max_per_month" min="0" max="31" class="form-control form-control-sm" value="<?= (int)($row['max_per_month']??0) ?>" title="0 là dùng giới hạn chung"><input type="text" name="note" class="form-control form-control-sm roster-note" value="<?= e($row['note']??'') ?>" placeholder="Ghi chú"><div class="d-flex gap-1"><button name="action" value="duty_roster_save" class="btn btn-sm btn-outline-info" title="Lưu sửa đổi"><i class="bi bi-floppy"></i></button><?php if($canDeleteCurrent):?><button name="action" value="duty_roster_delete" class="btn btn-sm btn-outline-danger" title="Xóa khỏi danh sách" onclick="return confirm('Xóa người này khỏi danh sách trực? Lịch cũ vẫn được giữ.')"><i class="bi bi-trash"></i></button><?php endif;?></div></form></div><?php endforeach;else:?><div class="text-center text-muted py-4">Chưa có người trong danh sách trực.</div><?php endif;?>
  </div></section>
  <section class="duty-panel mt-3"><div class="duty-panel-head"><h6>Danh sách nhóm trực</h6></div><div class="duty-panel-body"><?php if($dutyGroups):foreach($dutyGroups as $group):?><div class="duty-group-row"><div><strong><?= e($group['name']??'') ?></strong><div class="duty-help"><?= e(implode(', ',$group['teacher_names']??[])) ?: 'Chưa có thành viên' ?></div></div><?php if($canDeleteCurrent):?><form method="post" onsubmit="return confirm('Xóa nhóm trực này?')"><input type="hidden" name="action" value="duty_group_delete"><input type="hidden" name="id" value="<?= e($group['id']??'') ?>"><input type="hidden" name="month" value="<?= e($dutyMonth) ?>"><input type="hidden" name="section" value="settings"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif;?></div><?php endforeach;else:?><div class="text-center text-muted py-4">Chưa có nhóm trực nào.</div><?php endif;?></div></section>
<?php endif; ?>
