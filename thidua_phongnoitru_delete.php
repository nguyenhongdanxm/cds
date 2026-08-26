<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_login();
require_module('thidua', 'view');

if (!can_perm_level('td.student_room_input', 'delete')) {
    http_response_code(403);
    exit('Tài khoản chưa có quyền xóa dữ liệu chấm phòng nội trú.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$csrf = (string)($_POST['csrf'] ?? '');
$sessionCsrf = (string)($_SESSION['td_room_csrf'] ?? '');
if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    http_response_code(403);
    exit('Phiên làm việc không hợp lệ.');
}

$dataFile = DATA_PATH . '/thidua_rooms.json';
$data = load_json($dataFile, []);
if (!is_array($data)) $data = [];
$data = array_merge(['entries'=>[], 'sessions'=>[], 'skip_dates'=>[]], $data);
$data['entries'] = array_values(is_array($data['entries'] ?? null) ? $data['entries'] : []);
$data['sessions'] = array_values(is_array($data['sessions'] ?? null) ? $data['sessions'] : []);

$week = trim((string)($_POST['week'] ?? ''));
$date = trim((string)($_POST['date'] ?? ''));
$shift = trim((string)($_POST['shift'] ?? ''));
$scope = trim((string)($_POST['scope'] ?? 'shift'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('Ngày hoặc tuần không hợp lệ.');
}
if ($scope === 'shift' && !in_array($shift, ['sang','chieu'], true)) {
    http_response_code(400);
    exit('Buổi chấm không hợp lệ.');
}

$removedItems = 0;
foreach ($data['entries'] as &$entry) {
    if ((string)($entry['week_start'] ?? '') !== $week) continue;
    $items = array_values(is_array($entry['items'] ?? null) ? $entry['items'] : []);
    $kept = [];
    foreach ($items as $item) {
        $sameDate = (string)($item['date'] ?? '') === $date;
        $itemShift = (string)($item['shift'] ?? 'sang');
        $remove = $sameDate && ($scope === 'day' || $itemShift === $shift);
        if ($remove) $removedItems++; else $kept[] = $item;
    }
    $entry['items'] = $kept;
}
unset($entry);

// Xóa các entry rỗng để trả dữ liệu về trạng thái chưa chấm sạch sẽ.
$data['entries'] = array_values(array_filter($data['entries'], function($entry) {
    return !empty($entry['items']);
}));

$beforeSessions = count($data['sessions']);
$data['sessions'] = array_values(array_filter($data['sessions'], function($s) use ($week, $date, $shift, $scope) {
    if ((string)($s['week_start'] ?? '') !== $week || (string)($s['date'] ?? '') !== $date) return true;
    if ($scope === 'day') return false;
    return (string)($s['shift'] ?? '') !== $shift;
}));
$removedSessions = $beforeSessions - count($data['sessions']);

save_json($dataFile, $data);

if ($scope === 'day') {
    flash('Đã xóa toàn bộ dữ liệu chấm ngày ' . date('d/m/Y', strtotime($date)) . '. Điểm đã được trả về trạng thái trước khi chấm ngày này.', 'warning');
} else {
    $label = $shift === 'chieu' ? 'Chiều' : 'Sáng';
    flash('Đã xóa dữ liệu chấm buổi ' . $label . ' ngày ' . date('d/m/Y', strtotime($date)) . '. Điểm đã được tính lại.', 'warning');
}

header('Location: ' . BASE_URL . 'thidua_phongnoitru.php?' . http_build_query([
    'tab'=>'input',
    'week'=>$week,
    'day'=>$date,
]));
exit;
