<?php
require_once 'includes/functions.php';
require_login();

$data = (array)load_json(DATA_PATH . '/cm_activities.json', []);
$template = (array)($data['online_settings']['template_file'] ?? []);
$stored = basename((string)($template['stored_name'] ?? ''));
$path = DATA_PATH . '/cm_online_templates/' . $stored;
if ($stored === '' || !is_file($path)) {
    http_response_code(404);
    exit('Chưa có tệp mẫu đơn để tải xuống.');
}
$ext = strtolower((string)pathinfo($stored, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'odt' => 'application/vnd.oasis.opendocument.text',
];
$downloadName = basename((string)($template['original_name'] ?? ('Mau-don-hoc-online.' . $ext)));
header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="Mau-don-hoc-online.' . $ext . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
