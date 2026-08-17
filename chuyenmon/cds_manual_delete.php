<?php
/** Endpoint độc lập để xóa phân công thủ công, trả JSON. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'message'=>'Chỉ chấp nhận POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$canEdit = function_exists('cds_can_feature')
    ? cds_can_feature('cm.pccm', 'edit')
    : !empty($_SESSION['pccm_admin']);
if (!$canEdit) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Bạn không có quyền xóa phân công chuyên môn.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = trim((string)($_POST['manual_id'] ?? ''));
if ($id === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Thiếu mã phân công cần xóa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = __DIR__ . '/data/manual_assignments.json';
$rows = [];
if (is_file($file)) {
    $decoded = json_decode((string)file_get_contents($file), true);
    if (is_array($decoded)) $rows = array_values(array_filter($decoded, 'is_array'));
}

$before = count($rows);
$rows = array_values(array_filter($rows, static fn($row) => (string)($row['id'] ?? '') !== $id));
if (count($rows) === $before) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'message'=>'Không tìm thấy phân công thủ công cần xóa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!cds_json_save($file, $rows)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Không thể ghi lại dữ liệu phân công.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>true,'message'=>'Đã xóa phân công thủ công.'], JSON_UNESCAPED_UNICODE);
