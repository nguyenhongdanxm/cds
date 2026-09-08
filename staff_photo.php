<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff_card_store.php';
require_login();
require_perm('csdl.teachers');

$id = trim((string)($_GET['id'] ?? ''));
$teacher = csdl_teacher_find($id);
$file = $teacher ? staff_card_photo_file($id) : '';
if ($file === '') {
    http_response_code(404);
    exit;
}
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$type = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
header('Content-Type: ' . $type);
header('Cache-Control: private, max-age=300');
header('Content-Length: ' . filesize($file));
readfile($file);
