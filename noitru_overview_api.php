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

/*
 * Điểm danh: attendance_reports.json là nguồn chốt chuẩn.
 * attendance.json chỉ lưu ngoại lệ (vắng/có phép/đi muộn), vì vậy tuyệt đối
 * không dùng số dòng attendance.json làm tổng số HS đã điểm danh.
 */
$reports = noitru_att_reports_all();
$latestReport = null;
$shiftOrder = [
    'morning'=>10,'sang'=>10,'ngay'=>10,
    'noon'=>20,'trua'=>20,
    'afternoon'=>30,'chieu'=>30,
    'evening'=>40,'toi'=>40,
    'hoc_toi'=>50,'night'=>60,'dem'=>60,
];
foreach ($reports as $report) {
    $date = trim((string)($report['date'] ?? ''));
    if ($date === '' || $date > $today) continue;
    if ($latestReport === null) { $latestReport = $report; continue; }
    $latestDate = (string)($latestReport['date'] ?? '');
    if ($date > $latestDate) { $latestReport = $report; continue; }
    if ($date < $latestDate) continue;
    $aTime = strtotime((string)($report['updated_at'] ?? $report['created_at'] ?? '')) ?: 0;
    $bTime = strtotime((string)($latestReport['updated_at'] ?? $latestReport['created_at'] ?? '')) ?: 0;
    if ($aTime > $bTime) { $latestReport = $report; continue; }
    if ($aTime === $bTime && ($shiftOrder[(string)($report['shift'] ?? '')] ?? 0) >= ($shiftOrder[(string)($latestReport['shift'] ?? '')] ?? 0)) {
        $latestReport = $report;
    }
}

/* Tương thích dữ liệu cũ: nếu chưa có report thì xác định ca mới nhất từ attendance.json. */
$legacyRows = noitru_att_all();
$attDate = '';
$attShift = '';
if ($latestReport !== null) {
    $attDate = (string)($latestReport['date'] ?? '');
    $attShift = (string)($latestReport['shift'] ?? '');
} else {
    foreach ($legacyRows as $row) {
        $date = trim((string)($row['date'] ?? ''));
        $shift = trim((string)($row['shift'] ?? ''));
        if ($date === '' || $date > $today) continue;
        if ($date > $attDate || ($date === $attDate && ($shiftOrder[$shift] ?? 0) >= ($shiftOrder[$attShift] ?? 0))) {
            $attDate = $date;
            $attShift = $shift;
        }
    }
}

$counts = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
$reportTotal = 0;
$source = 'none';
if ($attDate !== '') {
    if ($latestReport !== null && $allowed === null) {
        foreach ($counts as $key => $_) $counts[$key] = max(0, (int)($latestReport[$key] ?? 0));
        $reportTotal = max(0, (int)($latestReport['total'] ?? array_sum($counts)));
        $source = 'report';
    } else {
        /* Với tài khoản chỉ xem một số lớp, lấy ngoại lệ của đúng phạm vi rồi suy ra có mặt. */
        foreach ($legacyRows as $row) {
            if (($row['date'] ?? '') !== $attDate || ($row['shift'] ?? '') !== $attShift) continue;
            if (!isset($studentIds[(string)($row['student_id'] ?? '')])) continue;
            $status = (string)($row['status'] ?? 'present');
            if (isset($counts[$status])) $counts[$status]++;
        }
        $counts['present'] = max(0, $total - $counts['absent'] - $counts['excused'] - $counts['late']);
        $reportTotal = $total;
        $source = $latestReport !== null ? 'report_scope' : 'legacy';
    }
}

/* Đi muộn vẫn là có mặt; có phép/vắng mới làm giảm số có mặt. */
$presentEffective = $attDate !== '' ? max(0, $counts['present'] + $counts['late']) : 0;
if ($latestReport !== null && $allowed === null) {
    /* Bảo vệ báo cáo cũ có present được suy ra theo kiểu khác. */
    $expectedPresent = max(0, $total - $counts['absent'] - $counts['excused']);
    if ($reportTotal === $total || $reportTotal <= 0) $presentEffective = $expectedPresent;
}

$shiftLabels = [
    'morning'=>'Buổi sáng','sang'=>'Sáng','ngay'=>'Ca ngày','noon'=>'Buổi trưa','trua'=>'Trưa',
    'afternoon'=>'Buổi chiều','chieu'=>'Chiều','evening'=>'Buổi tối','toi'=>'Tối','hoc_toi'=>'Học tối',
    'night'=>'Ban đêm','dem'=>'Đêm',
];

echo json_encode([
    'ok'=>true,
    'generated_at'=>date('c'),
    'students'=>['total'=>$total],
    'attendance'=>[
        'date'=>$attDate,
        'shift'=>$attShift,
        'shift_label'=>$shiftLabels[$attShift] ?? $attShift,
        'present'=>$presentEffective,
        'absent'=>$counts['absent'],
        'excused'=>$counts['excused'],
        'late'=>$counts['late'],
        'report_total'=>$reportTotal,
        'current_total'=>$total,
        'source'=>$source,
    ],
    'pending_exits'=>$pendingExits,
    'health_today'=>$healthToday,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
