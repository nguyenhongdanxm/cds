<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_once __DIR__ . '/includes/noitru_att_shifts.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

if (!can_perm('nt.lichtruc') && !can_perm('nt.diemdanh')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Bạn chưa có quyền xem dữ liệu điểm danh.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Ngày không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$studentMap = [];
foreach (noitru_attendance_students_all() as $student) {
    $studentMap[(string)($student['id'] ?? '')] = $student;
}
$shiftLabels = [];
foreach (noitru_att_shifts_all() as $shift) {
    $id = trim((string)($shift['id'] ?? ''));
    if ($id !== '') $shiftLabels[$id] = trim((string)($shift['label'] ?? '')) ?: $id;
}
$shiftLabels['dot_xuat'] = $shiftLabels['dot_xuat'] ?? 'Điểm danh đột xuất';

$legacyPrefix = '[Có phép sau thời gian đăng ký bữa ăn] ';
$rows = [];
foreach (noitru_att_all() as $row) {
    if ((string)($row['date'] ?? '') !== $date) continue;
    $status = (string)($row['status'] ?? 'present');
    if (!in_array($status, ['absent','excused'], true)) continue;
    $student = $studentMap[(string)($row['student_id'] ?? '')] ?? [];
    $reason = trim((string)($row['reason'] ?? ''));
    $excuse = trim((string)($row['excuse'] ?? ''));
    $legacyLate = str_starts_with($reason, $legacyPrefix);
    if ($legacyLate) $reason = trim(substr($reason, strlen($legacyPrefix)));
    if ($legacyLate) $excuse = 'P_SAU_AN';
    if ($excuse === '') $excuse = $status === 'excused' ? 'P' : 'KP';
    if (!in_array($excuse, ['P','P_SAU_AN','KP'], true)) $excuse = $status === 'excused' ? 'P' : 'KP';
    $shift = trim((string)($row['shift'] ?? '')) ?: 'dot_xuat';
    $rows[] = [
        'shift' => $shift,
        'shift_label' => $shiftLabels[$shift] ?? $shift,
        'student_id' => (string)($row['student_id'] ?? ''),
        'name' => (string)($student['name'] ?? ($row['student_name'] ?? 'Học sinh')),
        'class' => (string)($student['class_name'] ?? ($row['class_name'] ?? '')),
        'status' => $status,
        'excuse' => $excuse,
        'absence_type' => $excuse,
        'meal_after_registration' => $excuse === 'P_SAU_AN',
        'reason' => $reason,
    ];
}
usort($rows, function($a,$b){
    $ca=(string)($a['class']??'');$cb=(string)($b['class']??'');
    $cmp=function_exists('csdl_compare_class_names')?csdl_compare_class_names($ca,$cb):strnatcasecmp($ca,$cb);
    if($cmp!==0)return $cmp;
    return strnatcasecmp((string)($a['name']??''),(string)($b['name']??''));
});

echo json_encode(['ok'=>true,'date'=>$date,'rows'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
