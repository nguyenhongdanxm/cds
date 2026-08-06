<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_card_store.php';
require_login();
require_module('csdl', 'view');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$grade = trim((string)($_GET['grade'] ?? ''));
$className = trim((string)($_GET['class'] ?? ''));
$query = mb_strtolower(trim((string)($_GET['q'] ?? '')), 'UTF-8');
$photo = trim((string)($_GET['photo'] ?? ''));
$limit = max(1, min(1000, (int)($_GET['limit'] ?? 500)));

$classes = student_card_class_map();
$rows = [];
foreach (csdl_students_all() as $student) {
    if (empty($student['active'])) continue;
    $class = $classes[(string)($student['class_id'] ?? '')] ?? [];
    $name = (string)($class['name'] ?? '');
    $g = (string)($class['grade'] ?? '');
    if (function_exists('can_class') && !can_class($name)) continue;
    if ($grade !== '' && $g !== $grade) continue;
    if ($className !== '' && $name !== $className) continue;
    $haystack = mb_strtolower(trim((string)($student['name'] ?? '')) . ' ' . trim((string)($student['code'] ?? '')), 'UTF-8');
    if ($query !== '' && mb_strpos($haystack, $query) === false) continue;
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($student['id'] ?? ''));
    $hasPhoto = $safeId !== '' && is_file(DATA_PATH . '/student_photos/' . $safeId . '.jpg');
    if ($photo === 'yes' && !$hasPhoto) continue;
    if ($photo === 'no' && $hasPhoto) continue;
    $rows[] = [
        'id' => (string)($student['id'] ?? ''),
        'name' => (string)($student['name'] ?? ''),
        'code' => (string)($student['code'] ?? ''),
        'class_name' => $name,
        'grade' => $g,
        'dob' => (string)($student['dob'] ?? ''),
        'gender' => (string)($student['gender'] ?? ''),
        'has_photo' => $hasPhoto,
        'photo_url' => BASE_URL . 'student_photo.php?id=' . rawurlencode((string)($student['id'] ?? '')),
        'verify_url' => student_card_verify_url($student),
        'public_code' => student_card_public_code($student),
    ];
    if (count($rows) >= $limit) break;
}

usort($rows, static fn($a, $b) => strnatcasecmp($a['class_name'], $b['class_name']) ?: strcasecmp($a['name'], $b['name']));
echo json_encode(['ok' => true, 'count' => count($rows), 'students' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
