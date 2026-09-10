<?php
define('CDS_SKIP_DRIVE_ACTION_REGISTRATION', true);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/ai_service.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Phương thức không hợp lệ.']); exit; }
$request = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($request)) $request = [];
if (!hash_equals((string)($_SESSION['ai_csrf'] ?? ''), (string)($request['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ.']); exit; }
$assistant = trim((string)($request['assistant'] ?? ''));
$task = trim((string)($request['task'] ?? ''));
$input = trim((string)($request['input'] ?? ''));
$reference = trim((string)($request['reference'] ?? ''));
$catalog = cds_ai_assistants();
if (!isset($catalog[$assistant])) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Trợ lý không hợp lệ.']); exit; }
if (!can_perm($catalog[$assistant]['permission'])) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Bạn chưa được cấp quyền dùng trợ lý này.']); exit; }
if ($input === '') { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Vui lòng nhập nội dung hoặc yêu cầu.']); exit; }

$last = (float)($_SESSION['ai_last_request_at'] ?? 0);
if (microtime(true) - $last < 2) { http_response_code(429); echo json_encode(['ok'=>false,'message'=>'Vui lòng chờ vài giây trước khi gửi tiếp.']); exit; }
$_SESSION['ai_last_request_at'] = microtime(true);
$result = cds_ai_call($assistant, $task, $input, $reference);
if (empty($result['ok'])) http_response_code(502);
require_once __DIR__.'/includes/audit.php';
cds_audit_log(empty($result['ok'])?'ai_request_failed':'ai_request_completed', 'trolyai', ['assistant'=>$assistant,'task'=>$task,'input_chars'=>mb_strlen($input,'UTF-8'),'usage'=>$result['usage']??[]]);
echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
