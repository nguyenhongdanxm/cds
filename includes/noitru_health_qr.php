<?php
/** Tra cứu QR thẻ trong phiên đăng nhập và phạm vi y tế hiện tại. */
require_once __DIR__.'/student_card_store.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$raw = trim((string)($_GET['health_qr'] ?? ''));
$parts = strlen($raw) <= 2048 ? parse_url($raw) : false;
$params = [];
if (is_array($parts)) parse_str($parts['query'] ?? '', $params);
$id = is_string($params['id'] ?? null) ? $params['id'] : '';
$token = is_string($params['t'] ?? null) ? $params['t'] : '';
$student = null;
if (is_array($parts) && basename($parts['path'] ?? '') === 'student_verify.php' && student_card_is_valid_token($id, $token)) {
    foreach (noitru_assignment_apply(noitru_boarders_live()) as $candidate) {
        if ((string)($candidate['id'] ?? '') === $id && can_class($candidate['class_name'] ?? '')) { $student = $candidate; break; }
    }
}
if (!$student) {
    http_response_code(404);
    echo json_encode(['error'=>'Không nhận diện được thẻ hợp lệ của học sinh nội trú trong phạm vi quản lý.'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['student'=>['id'=>$id,'name'=>$student['name'] ?? '', 'class_name'=>$student['class_name'] ?? '', 'code'=>$student['code'] ?? ''], 'can_record'=>can_edit_perm('nt.yte')], JSON_UNESCAPED_UNICODE);
exit;
