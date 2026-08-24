<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$today = date('Y-m-d');
$allowed = allowed_classes();
$boarders = array_values(array_filter(noitru_boarders_live(), static function(array $student) use ($allowed): bool {
    return $allowed === null || in_array((string)($student['class_name'] ?? ''), $allowed, true);
}));
$studentIds = array_fill_keys(array_map('strval', array_column($boarders, 'id')), true);
$total = count($boarders);

/* Phiếu chờ duyệt: chỉ tính HS hiện còn thuộc phạm vi và bản ghi thực sự pending. */
$pendingExits = 0;
foreach (noitru_exits_all() as $row) {
    if (($row['status'] ?? '') !== 'pending') continue;
    if (!isset($studentIds[(string)($row['student_id'] ?? '')])) continue;
    $pendingExits++;
}

/* Y tế hôm nay: dùng cùng nguồn health.json và cùng phạm vi HS. */
$healthToday = 0;
foreach (noitru_health_all() as $row) {
    if (($row['date'] ?? '') !== $today) continue;
    if (!isset($studentIds[(string)($row['student_id'] ?? '')])) continue;
    $healthToday++;
}

$recentAttendance = noitru_att_recent_reports(3, $allowed === null ? null : array_keys($studentIds), $total, $today);
$latestAttendance = $recentAttendance[0] ?? [
    'date'=>'','shift'=>'','shift_label'=>'','present'=>0,'absent'=>0,'excused'=>0,'late'=>0,'total'=>$total,'by'=>'',
];

echo json_encode([
    'ok'=>true,
    'generated_at'=>date('c'),
    'students'=>['total'=>$total],
    'attendance'=>[
        'date'=>$latestAttendance['date'],
        'shift'=>$latestAttendance['shift'],
        'shift_label'=>$latestAttendance['shift_label'],
        'present'=>$latestAttendance['present'],
        'absent'=>$latestAttendance['absent'],
        'excused'=>$latestAttendance['excused'],
        'late'=>$latestAttendance['late'],
        'report_total'=>$latestAttendance['total'],
        'current_total'=>$total,
        'by'=>$latestAttendance['by'],
        'source'=>$recentAttendance ? 'attendance_reports' : 'none',
    ],
    'attendance_recent'=>$recentAttendance,
    'pending_exits'=>$pendingExits,
    'health_today'=>$healthToday,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
