<?php
require_once 'includes/functions.php';
require_login();

$assignments = get_assignments();
$roles = get_role_assignments();

// Sort by ten
usort($assignments, fn($a, $b) => strcmp(ten_cuoi($a['teacher']), ten_cuoi($b['teacher'])) ?: strcmp($a['teacher'], $b['teacher']));
usort($roles, fn($a, $b) => strcmp(ten_cuoi($a['teacher']), ten_cuoi($b['teacher'])) ?: strcmp($a['teacher'], $b['teacher']));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="phan_cong_' . date('Ymd') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

fputcsv($out, ['=== PHÂN CÔNG DẠY MÔN ===']);
fputcsv($out, ['Giáo viên', 'Môn học', 'Lớp', 'Số tiết', 'Ghi chú']);
foreach ($assignments as $a) {
    fputcsv($out, [$a['teacher'], $a['subject'], $a['class'], $a['periods'], $a['note'] ?? '']);
}

fputcsv($out, []);
fputcsv($out, ['=== KIÊM NHIỆM ===']);
fputcsv($out, ['Giáo viên', 'Chức vụ', 'Lớp', 'Số tiết', 'Ghi chú']);
foreach ($roles as $a) {
    fputcsv($out, [$a['teacher'], $a['role'], $a['class'] ?? '', $a['periods'] ?? 0, $a['note'] ?? '']);
}

fputcsv($out, []);
fputcsv($out, ['=== TỔNG HỢP THEO GIÁO VIÊN ===']);
fputcsv($out, ['Giáo viên', 'Tiết dạy', 'Tiết kiêm nhiệm', 'Tổng tiết', 'Số lớp']);
$loads = get_teacher_loads();
$names = sort_teachers_by_ten(array_keys($loads));
foreach ($names as $t) {
    $r = $loads[$t];
    fputcsv($out, [$t, $r['day'], $r['role'], $r['total'], $r['class_count']]);
}

fclose($out);
exit;
