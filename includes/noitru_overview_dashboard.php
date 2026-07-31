<?php
/**
 * Bảng điều hành nhanh cho trang Tổng quan Nội trú.
 * File này được nạp từ noitru_shell.php và chỉ thay phần giao diện overview ở phía trình duyệt.
 */
if (($nt_sec ?? '') !== 'overview' || ($ntPage ?? '') !== 'noitru.php') return;

$overviewToday = date('Y-m-d');
$overviewNow = time();
$overviewStudents = isset($boarders) && is_array($boarders) ? $boarders : [];
$overviewStats = isset($stats) && is_array($stats) ? $stats : noitru_stats();

$overviewStudentMap = [];
$overviewClassTotals = [];
foreach ($overviewStudents as $student) {
    $studentId = (string)($student['id'] ?? '');
    if ($studentId === '') continue;
    $overviewStudentMap[$studentId] = $student;
    $className = trim((string)($student['class_name'] ?? '')) ?: '(Chưa lớp)';
    $overviewClassTotals[$className] = ($overviewClassTotals[$className] ?? 0) + 1;
}
ksort($overviewClassTotals, SORT_NATURAL);

$overviewShiftLabels = [
    'sang' => 'Buổi sáng',
    'trua' => 'Buổi trưa',
    'chieu' => 'Buổi chiều',
    'toi' => 'Buổi tối',
    'dem' => 'Ban đêm',
];
$overviewShiftTimes = ['sang'=>'06:00', 'trua'=>'11:00', 'chieu'=>'14:00', 'toi'=>'18:00', 'dem'=>'22:00'];
$overviewStatusLabels = ['present'=>'Có mặt', 'absent'=>'Vắng', 'late'=>'Muộn', 'excused'=>'Có phép'];
$overviewStatusClasses = ['present'=>'success', 'absent'=>'danger', 'late'=>'warning', 'excused'=>'info'];
$overviewMealLabels = ['sang'=>'Sáng', 'trua'=>'Trưa', 'toi'=>'Tối'];

$overviewSessionMap = [];
foreach (noitru_att_all() as $attendance) {
    $studentId = (string)($attendance['student_id'] ?? '');
    if (!isset($overviewStudentMap[$studentId])) continue;
    $date = trim((string)($attendance['date'] ?? ''));
    $shift = trim((string)($attendance['shift'] ?? '')) ?: 'toi';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
    $key = $date . '|' . $shift;
    if (!isset($overviewSessionMap[$key])) {
        $time = $overviewShiftTimes[$shift] ?? '12:00';
        $overviewSessionMap[$key] = [
            'date'=>$date,
            'shift'=>$shift,
            'sort'=>strtotime($date . ' ' . $time),
            'rows'=>[],
            'counts'=>['present'=>0,'absent'=>0,'late'=>0,'excused'=>0],
        ];
    }
    $status = (string)($attendance['status'] ?? 'present');
    if (!isset($overviewSessionMap[$key]['counts'][$status])) $status = 'present';
    $overviewSessionMap[$key]['rows'][$studentId] = array_merge($attendance, ['status'=>$status]);
}
foreach ($overviewSessionMap as &$session) {
    $session['counts'] = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    foreach ($session['rows'] as $row) $session['counts'][$row['status']]++;
    $session['recorded'] = count($session['rows']);
    $session['missing'] = max(0, count($overviewStudents) - $session['recorded']);
}
unset($session);
usort($overviewSessionMap, fn($a, $b) => ($b['sort'] <=> $a['sort']));
$overviewRecentSessions = array_slice($overviewSessionMap, 0, 3);
$overviewLatestSession = $overviewRecentSessions[0] ?? null;

$overviewMissingStudents = [];
$overviewClassAttendance = [];
if ($overviewLatestSession) {
    foreach ($overviewClassTotals as $className=>$total) {
        $overviewClassAttendance[$className] = [
            'total'=>$total, 'recorded'=>0, 'present'=>0, 'absent'=>0, 'late'=>0, 'excused'=>0, 'missing'=>$total,
        ];
    }
    foreach ($overviewLatestSession['rows'] as $studentId=>$row) {
        $student = $overviewStudentMap[$studentId] ?? [];
        $className = trim((string)($student['class_name'] ?? '')) ?: '(Chưa lớp)';
        if (!isset($overviewClassAttendance[$className])) {
            $overviewClassAttendance[$className] = ['total'=>0,'recorded'=>0,'present'=>0,'absent'=>0,'late'=>0,'excused'=>0,'missing'=>0];
        }
        $status = $row['status'];
        $overviewClassAttendance[$className]['recorded']++;
        $overviewClassAttendance[$className][$status]++;
        $overviewClassAttendance[$className]['missing'] = max(0, $overviewClassAttendance[$className]['total'] - $overviewClassAttendance[$className]['recorded']);
        if ($status !== 'present') {
            $overviewMissingStudents[] = [
                'name'=>$student['name'] ?? 'Học sinh',
                'class'=>$className,
                'status'=>$status,
            ];
        }
    }
    usort($overviewMissingStudents, fn($a,$b) => strcmp($a['class'], $b['class']) ?: strcmp($a['name'], $b['name']));
}

$overviewDuties = array_values(array_filter(noitru_duty_all(), function ($row) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($row['date'] ?? ''));
}));
foreach ($overviewDuties as &$duty) {
    $shift = trim((string)($duty['shift'] ?? '')) ?: 'toi';
    $duty['_sort'] = strtotime(($duty['date'] ?? $overviewToday) . ' ' . ($overviewShiftTimes[$shift] ?? '12:00'));
}
unset($duty);
usort($overviewDuties, fn($a,$b) => ($a['_sort'] <=> $b['_sort']));
$overviewCurrentDuty = null;
$overviewNextDuty = null;
foreach ($overviewDuties as $duty) {
    if (($duty['date'] ?? '') === $overviewToday && ($duty['_sort'] ?? 0) <= $overviewNow) $overviewCurrentDuty = $duty;
    if (($duty['_sort'] ?? 0) > $overviewNow) { $overviewNextDuty = $duty; break; }
}

$overviewWeekStart = date('Y-m-d', strtotime($overviewToday . ' -' . ((int)date('N') - 1) . ' days'));
$overviewMenu = noitru_menu_for_week($overviewWeekStart);
$overviewDayKeys = [1=>'mon',2=>'tue',3=>'wed',4=>'thu',5=>'fri',6=>'sat',7=>'sun'];
$overviewTodayMenu = $overviewMenu['meals'][$overviewDayKeys[(int)date('N')]] ?? [];
$overviewMealCounts = noitru_meals_count_day($overviewToday);
$overviewReports = noitru_meal_reports_for_date($overviewToday);
$overviewScopeClasses = array_keys($overviewClassTotals);
$overviewMealCoverage = [];
foreach (array_keys($overviewMealLabels) as $meal) {
    $reported = [];
    foreach ($overviewReports as $report) {
        if (($report['meal'] ?? '') !== $meal) continue;
        $className = trim((string)($report['class_name'] ?? '')) ?: '(Chưa lớp)';
        if (isset($overviewClassTotals[$className])) $reported[$className] = true;
    }
    $state = noitru_meal_state($overviewToday, $meal)['status'] ?? 'open';
    $overviewMealCoverage[$meal] = [
        'reported'=>count($reported),
        'total'=>count($overviewScopeClasses),
        'missing'=>max(0, count($overviewScopeClasses)-count($reported)),
        'state'=>$state,
    ];
}

$overviewPendingExits = 0;
foreach (noitru_exits_all() as $exit) {
    if (($exit['status'] ?? '') !== 'pending') continue;
    if (isset($overviewStudentMap[(string)($exit['student_id'] ?? '')])) $overviewPendingExits++;
}
$overviewMealTotal = (int)($overviewMealCounts['sang'] ?? 0) + (int)($overviewMealCounts['trua'] ?? 0) + (int)($overviewMealCounts['toi'] ?? 0);
$overviewLatestPresent = (int)($overviewLatestSession['counts']['present'] ?? 0);
$overviewLatestRecorded = (int)($overviewLatestSession['recorded'] ?? 0);
?>
<style>
.nt-ov{display:grid;gap:1rem}.nt-ov a{text-decoration:none}.nt-ov-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.nt-ov-kpi{position:relative;overflow:hidden;background:#fff;border:1px solid #edf0f4;border-radius:18px;padding:1rem;box-shadow:0 7px 22px rgba(15,23,42,.06)}.nt-ov-kpi i{position:absolute;right:.85rem;top:.8rem;font-size:1.35rem;opacity:.22}.nt-ov-kpi strong{display:block;font-size:1.65rem;line-height:1.05;color:#a61e5c}.nt-ov-kpi small{color:#64748b}.nt-ov-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.75fr);gap:1rem}.nt-ov-card{background:#fff;border:1px solid #edf0f4;border-radius:18px;box-shadow:0 7px 22px rgba(15,23,42,.05);overflow:hidden}.nt-ov-card-head{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:1rem 1rem .75rem}.nt-ov-card-head h5,.nt-ov-card-head h6{margin:0}.nt-ov-card-body{padding:0 1rem 1rem}.nt-ov-muted{color:#64748b}.nt-ov-session-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem}.nt-ov-session{border:1px solid #e7ebf0;border-radius:14px;padding:.8rem;background:#fbfcfe}.nt-ov-session:first-child{border-color:#f3b6d1;background:#fff7fb}.nt-ov-session-title{font-weight:800}.nt-ov-counts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.35rem;margin-top:.65rem}.nt-ov-counts span{display:flex;justify-content:space-between;border-radius:10px;padding:.35rem .5rem;background:#f1f5f9;font-size:.78rem}.nt-ov-counts b{font-size:.88rem}.nt-ov-list{display:grid;gap:.55rem}.nt-ov-person,.nt-ov-duty{display:flex;align-items:center;justify-content:space-between;gap:.75rem;border-bottom:1px solid #eef2f7;padding:.55rem 0}.nt-ov-person:last-child,.nt-ov-duty:last-child{border-bottom:0}.nt-ov-person-main{min-width:0}.nt-ov-person-main strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.nt-ov-empty{padding:1.2rem;text-align:center;border:1px dashed #d9e0e8;border-radius:14px;color:#64748b;background:#fbfcfe}.nt-ov-class-table{width:100%;font-size:.82rem}.nt-ov-class-table th{color:#64748b;font-weight:700;padding:.45rem .35rem;border-bottom:1px solid #e8edf3}.nt-ov-class-table td{padding:.55rem .35rem;border-bottom:1px solid #eef2f7}.nt-ov-class-table tr:last-child td{border-bottom:0}.nt-ov-class-bar{height:6px;border-radius:999px;background:#edf2f7;overflow:hidden;margin-top:.3rem}.nt-ov-class-bar span{display:block;height:100%;background:#22c55e}.nt-ov-side{display:grid;gap:1rem;align-content:start}.nt-ov-duty-box{border-radius:14px;padding:.85rem;background:#f8fafc;border:1px solid #e8edf3}.nt-ov-duty-box.current{background:#fff7fb;border-color:#f3b6d1}.nt-ov-duty-box+.nt-ov-duty-box{margin-top:.65rem}.nt-ov-duty-box small{display:block;color:#64748b}.nt-ov-meals{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem}.nt-ov-meal{border:1px solid #e8edf3;border-radius:14px;padding:.7rem;text-align:center;background:#fbfcfe}.nt-ov-meal strong{display:block;font-size:1.25rem;color:#0f766e}.nt-ov-menu{display:grid;gap:.5rem;margin-top:.8rem}.nt-ov-menu-row{display:grid;grid-template-columns:62px 1fr;gap:.65rem;padding:.55rem .65rem;border-radius:12px;background:#f8fafc}.nt-ov-menu-row b{color:#a61e5c}.nt-ov-actions{display:flex;gap:.45rem;flex-wrap:wrap}.nt-ov-actions .btn{border-radius:999px}.nt-ov-badge{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.25rem .5rem;font-size:.72rem;font-weight:700}.nt-ov-badge.open{background:#fff7db;color:#9a6700}.nt-ov-badge.locked{background:#dcfce7;color:#166534}.nt-ov-badge.off{background:#fee2e2;color:#991b1b}
@media(max-width:991.98px){.nt-ov-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.nt-ov-grid{grid-template-columns:1fr}.nt-ov-side{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){.nt-ov-session-grid{grid-template-columns:1fr}.nt-ov-side{grid-template-columns:1fr}.nt-ov-card-head{align-items:flex-start}.nt-ov-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.nt-ov-actions .btn{border-radius:12px}.nt-ov-class-table{font-size:.76rem}}
</style>
<template id="nt-overview-dashboard-template">
  <section class="nt-ov" aria-label="Tổng quan vận hành nội trú">
    <div class="nt-ov-kpis">
      <a class="nt-ov-kpi" href="<?= e(BASE_URL . 'noitru_list.php') ?>"><i class="bi bi-people-fill"></i><strong><?= count($overviewStudents) ?></strong><small>Học sinh nội trú</small></a>
      <a class="nt-ov-kpi" href="<?= e(BASE_URL . 'noitru_attendance.php') ?>"><i class="bi bi-clipboard2-check-fill"></i><strong><?= $overviewLatestSession ? ($overviewLatestPresent . '/' . $overviewLatestRecorded) : '—' ?></strong><small>Có mặt buổi gần nhất</small></a>
      <a class="nt-ov-kpi" href="<?= e(BASE_URL . 'noitru.php?tab=meal_summary&date=' . $overviewToday) ?>"><i class="bi bi-cup-hot-fill"></i><strong><?= $overviewMealTotal ?></strong><small>Tổng lượt suất ăn hôm nay</small></a>
      <a class="nt-ov-kpi" href="<?= e(BASE_URL . 'noitru.php?tab=exits') ?>"><i class="bi bi-door-open-fill"></i><strong><?= $overviewPendingExits ?></strong><small>Phiếu ra/vào chờ duyệt</small></a>
    </div>

    <div class="nt-ov-card">
      <div class="nt-ov-card-head">
        <div><h5>3 buổi điểm danh gần nhất</h5><div class="small nt-ov-muted">Số liệu theo phạm vi lớp bạn được quyền xem</div></div>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL . 'noitru_attendance.php') ?>">Mở điểm danh</a>
      </div>
      <div class="nt-ov-card-body">
        <?php if (!$overviewRecentSessions): ?>
          <div class="nt-ov-empty">Chưa có dữ liệu điểm danh.</div>
        <?php else: ?>
          <div class="nt-ov-session-grid">
            <?php foreach ($overviewRecentSessions as $index=>$session): ?>
              <article class="nt-ov-session">
                <div class="d-flex justify-content-between gap-2"><div><div class="nt-ov-session-title"><?= e($overviewShiftLabels[$session['shift']] ?? ucfirst($session['shift'])) ?></div><small class="nt-ov-muted"><?= e(date('d/m/Y', strtotime($session['date']))) ?><?= $index===0?' · Mới nhất':'' ?></small></div><span class="badge text-bg-light"><?= (int)$session['recorded'] ?>/<?= count($overviewStudents) ?></span></div>
                <div class="nt-ov-counts">
                  <span>Có mặt <b><?= (int)$session['counts']['present'] ?></b></span>
                  <span>Vắng <b><?= (int)$session['counts']['absent'] ?></b></span>
                  <span>Muộn <b><?= (int)$session['counts']['late'] ?></b></span>
                  <span>Có phép <b><?= (int)$session['counts']['excused'] ?></b></span>
                </div>
                <?php if ($session['missing'] > 0): ?><div class="small text-warning mt-2"><i class="bi bi-exclamation-circle"></i> <?= (int)$session['missing'] ?> HS chưa ghi nhận</div><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="nt-ov-grid">
      <div class="d-grid gap-3">
        <div class="nt-ov-card">
          <div class="nt-ov-card-head"><div><h5>Tình hình từng lớp</h5><div class="small nt-ov-muted"><?= $overviewLatestSession ? e(($overviewShiftLabels[$overviewLatestSession['shift']] ?? $overviewLatestSession['shift']) . ' · ' . date('d/m/Y', strtotime($overviewLatestSession['date']))) : 'Chưa có buổi điểm danh' ?></div></div></div>
          <div class="nt-ov-card-body table-responsive">
            <?php if (!$overviewLatestSession): ?><div class="nt-ov-empty">Chưa có dữ liệu để tổng hợp theo lớp.</div><?php else: ?>
            <table class="nt-ov-class-table"><thead><tr><th>Lớp</th><th class="text-center">Có mặt</th><th class="text-center">Vắng</th><th class="text-center">Muộn</th><th class="text-center">Có phép</th><th class="text-center">Chưa ghi</th></tr></thead><tbody>
              <?php foreach ($overviewClassAttendance as $className=>$row): $rate=$row['total']?round($row['present']*100/$row['total']):0; ?>
                <tr><td><strong><?= e($className) ?></strong><div class="nt-ov-class-bar"><span style="width:<?= min(100,max(0,$rate)) ?>%"></span></div></td><td class="text-center text-success fw-bold"><?= (int)$row['present'] ?></td><td class="text-center text-danger"><?= (int)$row['absent'] ?></td><td class="text-center text-warning"><?= (int)$row['late'] ?></td><td class="text-center text-info"><?= (int)$row['excused'] ?></td><td class="text-center"><?= (int)$row['missing'] ?></td></tr>
              <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
          </div>
        </div>

        <div class="nt-ov-card">
          <div class="nt-ov-card-head"><div><h5>Học sinh chưa có mặt</h5><div class="small nt-ov-muted">Buổi điểm danh gần nhất</div></div><span class="badge text-bg-danger"><?= count($overviewMissingStudents) ?></span></div>
          <div class="nt-ov-card-body">
            <?php if (!$overviewLatestSession): ?><div class="nt-ov-empty">Chưa có dữ liệu điểm danh.</div>
            <?php elseif (!$overviewMissingStudents): ?><div class="nt-ov-empty text-success"><i class="bi bi-check-circle"></i> Tất cả học sinh đã có mặt.</div>
            <?php else: ?><div class="nt-ov-list">
              <?php foreach (array_slice($overviewMissingStudents,0,12) as $student): ?>
                <div class="nt-ov-person"><div class="nt-ov-person-main"><strong><?= e($student['name']) ?></strong><small class="nt-ov-muted"><?= e($student['class']) ?></small></div><span class="badge text-bg-<?= e($overviewStatusClasses[$student['status']] ?? 'secondary') ?>"><?= e($overviewStatusLabels[$student['status']] ?? $student['status']) ?></span></div>
              <?php endforeach; ?>
              <?php if (count($overviewMissingStudents)>12): ?><div class="small text-center nt-ov-muted">Còn <?= count($overviewMissingStudents)-12 ?> học sinh khác</div><?php endif; ?>
            </div><?php endif; ?>
          </div>
        </div>
      </div>

      <aside class="nt-ov-side">
        <div class="nt-ov-card">
          <div class="nt-ov-card-head"><div><h6>Ca trực</h6><div class="small nt-ov-muted">Hiện tại và kế tiếp</div></div><a class="small" href="<?= e(BASE_URL . 'noitru.php?tab=duty') ?>">Xem lịch</a></div>
          <div class="nt-ov-card-body">
            <?php if ($overviewCurrentDuty): ?><div class="nt-ov-duty-box current"><small>Đang trực</small><strong><?= e($overviewCurrentDuty['teacher_name'] ?? 'Chưa phân công') ?></strong><div class="small"><?= e($overviewShiftLabels[$overviewCurrentDuty['shift'] ?? ''] ?? ($overviewCurrentDuty['shift'] ?? '')) ?> · <?= e(date('d/m/Y', strtotime($overviewCurrentDuty['date']))) ?></div><?php if (!empty($overviewCurrentDuty['note'])): ?><div class="small nt-ov-muted mt-1"><?= e($overviewCurrentDuty['note']) ?></div><?php endif; ?></div><?php else: ?><div class="nt-ov-duty-box"><small>Đang trực</small><strong>Chưa có ca phù hợp</strong></div><?php endif; ?>
            <?php if ($overviewNextDuty): ?><div class="nt-ov-duty-box"><small>Ca kế tiếp</small><strong><?= e($overviewNextDuty['teacher_name'] ?? 'Chưa phân công') ?></strong><div class="small"><?= e($overviewShiftLabels[$overviewNextDuty['shift'] ?? ''] ?? ($overviewNextDuty['shift'] ?? '')) ?> · <?= e(date('d/m/Y', strtotime($overviewNextDuty['date']))) ?></div></div><?php else: ?><div class="nt-ov-duty-box"><small>Ca kế tiếp</small><strong>Chưa xếp lịch</strong></div><?php endif; ?>
          </div>
        </div>

        <div class="nt-ov-card">
          <div class="nt-ov-card-head"><div><h6>Báo ăn hôm nay</h6><div class="small nt-ov-muted"><?= e(date('d/m/Y', strtotime($overviewToday))) ?></div></div><a class="small" href="<?= e(BASE_URL . 'noitru.php?tab=meal_summary&date=' . $overviewToday) ?>">Chi tiết</a></div>
          <div class="nt-ov-card-body"><div class="nt-ov-meals">
            <?php foreach ($overviewMealLabels as $meal=>$label): $coverage=$overviewMealCoverage[$meal]; ?>
              <div class="nt-ov-meal"><small><?= e($label) ?></small><strong><?= (int)($overviewMealCounts[$meal] ?? 0) ?></strong><span class="nt-ov-badge <?= e($coverage['state']) ?>"><?= $coverage['state']==='locked'?'Đã chốt':($coverage['state']==='off'?'Nghỉ':($coverage['reported'].'/'.$coverage['total'].' lớp')) ?></span></div>
            <?php endforeach; ?>
          </div></div>
        </div>

        <div class="nt-ov-card">
          <div class="nt-ov-card-head"><div><h6>Thực đơn hôm nay</h6><div class="small nt-ov-muted">Tuần từ <?= e(date('d/m', strtotime($overviewWeekStart))) ?></div></div><a class="small" href="<?= e(BASE_URL . 'noitru.php?tab=menu') ?>">Cập nhật</a></div>
          <div class="nt-ov-card-body">
            <?php if (!$overviewMenu): ?><div class="nt-ov-empty">Chưa có thực đơn tuần này.</div>
            <?php else: ?><div class="nt-ov-menu"><?php foreach ($overviewMealLabels as $meal=>$label): ?><div class="nt-ov-menu-row"><b><?= e($label) ?></b><span><?= e(trim((string)($overviewTodayMenu[$meal] ?? '')) ?: 'Chưa cập nhật') ?></span></div><?php endforeach; ?></div><?php endif; ?>
          </div>
        </div>

        <div class="nt-ov-actions">
          <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL . 'noitru_attendance.php') ?>"><i class="bi bi-check2-square"></i> Điểm danh</a>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL . 'noitru.php?tab=meals') ?>"><i class="bi bi-cup-hot"></i> Báo ăn</a>
          <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL . 'noitru.php?tab=duty') ?>"><i class="bi bi-calendar2-week"></i> Lịch trực</a>
          <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL . 'noitru.php?tab=menu') ?>"><i class="bi bi-journal-text"></i> Thực đơn</a>
        </div>
      </aside>
    </div>
  </section>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var template = document.getElementById('nt-overview-dashboard-template');
  var head = document.querySelector('.nt-content > .nt-page-head');
  if (!template || !head) return;
  var node = head.nextElementSibling;
  while (node) { var next = node.nextElementSibling; node.remove(); node = next; }
  head.insertAdjacentElement('afterend', template.content.firstElementChild.cloneNode(true));
});
</script>
