<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_student_photo.php';
require_login();
$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['id'] ?? ''));
$file = CSDL_STUDENT_PHOTO_DIR . '/' . $id . '.jpg';
if ($id === '' || !is_file($file)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=86400');
readfile($file);
