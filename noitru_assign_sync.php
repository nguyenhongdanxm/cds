<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_assignment_store.php';

require_login();
require_perm_level('nt.danhsach', 'edit');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . BASE_URL . 'noitru_assign.php?mode=rooms');
    exit;
}

$mode = (($_POST['mode'] ?? 'rooms') === 'meals') ? 'meals' : 'rooms';
$field = $mode === 'rooms' ? 'room_ktx' : 'meal_group';
$label = $mode === 'rooms' ? 'phòng' : 'mâm';
$data = noitru_assignments_data();
$map = is_array($data[$mode] ?? null) ? $data[$mode] : [];

if (!$map) {
    flash('Chưa có kết quả chia ' . $label . ' để cập nhật vào Cơ sở dữ liệu.', 'warning');
    header('Location: ' . BASE_URL . 'noitru_assign.php?mode=' . $mode);
    exit;
}

$allowedIds = [];
foreach (noitru_boarders_live() as $student) {
    $className = (string)($student['class_name'] ?? '');
    if (function_exists('can_class') && !can_class($className)) continue;
    $id = (string)($student['id'] ?? '');
    if ($id !== '') $allowedIds[$id] = true;
}

$updated = 0;
$unchanged = 0;
$skipped = 0;
$failed = 0;
$shadowSyncOk = true;
cds_shadow_batch_begin();
try {
foreach ($map as $studentId => $target) {
    $studentId = (string)$studentId;
    $target = trim((string)$target);
    if ($studentId === '' || !isset($allowedIds[$studentId])) {
        $skipped++;
        continue;
    }
    $student = csdl_student_find($studentId);
    if (!$student) {
        $skipped++;
        continue;
    }
    if ((string)($student[$field] ?? '') === $target) {
        $unchanged++;
        continue;
    }
    try {
        csdl_student_save(['id' => $studentId, $field => $target]);
        $updated++;
    } catch (Throwable $e) {
        $failed++;
    }
}
} finally {
    $shadowSyncOk = cds_shadow_batch_end();
}

$user = current_user();
$by = (string)($user['name'] ?? $user['username'] ?? '');
$data['history'] = is_array($data['history'] ?? null) ? $data['history'] : [];
$data['history'][] = [
    'mode' => $mode,
    'action' => 'sync_csdl',
    'by' => $by,
    'at' => date('c'),
    'updated' => $updated,
    'unchanged' => $unchanged,
    'skipped' => $skipped,
    'failed' => $failed,
];
noitru_assignments_save($data, $by);

if ($failed > 0 || !$shadowSyncOk) {
    $message = 'Đã cập nhật ' . $updated . ' học sinh; có ' . $failed
        . ' bản ghi lỗi, ' . $skipped . ' bản ghi bỏ qua.';
    if (!$shadowSyncOk) $message .= ' JSON đã lưu; MySQL chưa đồng bộ được.';
    flash($message, 'warning');
} else {
    flash('Đã cập nhật kết quả chia ' . $label . ' vào Cơ sở dữ liệu cho ' . $updated . ' học sinh'
        . ($unchanged ? '; ' . $unchanged . ' học sinh đã đúng dữ liệu' : '')
        . ($skipped ? '; bỏ qua ' . $skipped . ' bản ghi ngoài phạm vi/không hợp lệ' : '') . '.', 'success');
}

header('Location: ' . BASE_URL . 'noitru_assign.php?mode=' . $mode);
exit;
