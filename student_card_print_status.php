<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_card_store.php';
require_login();
require_perm('csdl.students');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    echo json_encode([
        'ok' => true,
        'year' => student_card_print_year_key(),
        'printed' => student_card_printed_map(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$csrf = (string)($_SESSION['student_card_print_csrf'] ?? '');
if ($csrf === '' || !hash_equals($csrf, (string)($payload['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Phiên làm việc không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ids = is_array($payload['student_ids'] ?? null) ? $payload['student_ids'] : [];
$allowed = [];
$classes = student_card_class_map();
foreach (csdl_students_all() as $student) {
    if (empty($student['active'])) continue;
    $class = $classes[(string)($student['class_id'] ?? '')] ?? [];
    if (function_exists('can_class') && !can_class((string)($class['name'] ?? ''))) continue;
    $allowed[(string)($student['id'] ?? '')] = true;
}
$ids = array_values(array_filter(array_map('strval', $ids), static fn($id) => isset($allowed[$id])));
if (!$ids) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Chưa chọn học sinh hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($payload['action'] ?? 'mark');
if (!in_array($action, ['mark', 'unmark'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Thao tác không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!student_card_update_printed($ids, $action === 'mark', current_user() ?: [])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Không lưu được trạng thái in.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'count' => count($ids),
    'printed' => student_card_printed_map(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
