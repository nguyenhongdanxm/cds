<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cds_auth_gate.php';

$canEdit = function_exists('cds_can_feature')
    ? cds_can_feature('cm.pccm', 'edit')
    : !empty($_SESSION['pccm_admin']);
if (!$canEdit) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Bạn không có quyền sửa phân công chuyên môn.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'message'=>'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$teacher = trim((string)($_POST['manual_teacher'] ?? ''));
$subject = trim((string)($_POST['manual_subject'] ?? ''));
$className = trim((string)($_POST['manual_class'] ?? ''));
$periodRaw = str_replace(',', '.', trim((string)($_POST['manual_periods'] ?? '')));
$periods = round((float)$periodRaw, 1);
$note = trim((string)($_POST['manual_note'] ?? ''));

if ($teacher === '' || $subject === '' || $className === '' || $periods <= 0) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Vui lòng chọn giáo viên, lớp, nhập môn và số tiết lớn hơn 0.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = dirname(__DIR__) . '/data/manual_assignments.json';
$rows = [];
if (is_file($file)) {
    $decoded = json_decode((string)file_get_contents($file), true);
    if (is_array($decoded)) $rows = array_values(array_filter($decoded, 'is_array'));
}
$rows[] = [
    'id' => 'ma_' . bin2hex(random_bytes(5)),
    'teacher' => $teacher,
    'subject' => $subject,
    'class_name' => $className,
    'periods' => $periods,
    'note' => $note,
    'created_by' => (string)($_SESSION['cds_user']['name'] ?? $_SESSION['cds_user']['username'] ?? ''),
    'created_at' => date('c'),
];
$dir = dirname($file);
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Không tạo được thư mục dữ liệu phân công thủ công.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$encoded = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($encoded === false || file_put_contents($file, $encoded, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Không ghi được dữ liệu phân công thủ công.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>true,'message'=>'Đã thêm phân công thủ công.'], JSON_UNESCAPED_UNICODE);
