<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff_card_store.php';
require_login();
require_perm('csdl.teachers');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$group = trim((string)($_GET['group'] ?? ''));
$query = mb_strtolower(trim((string)($_GET['q'] ?? '')), 'UTF-8');
$photo = trim((string)($_GET['photo'] ?? ''));
$limit = max(1, min(1000, (int)($_GET['limit'] ?? 500)));
$rows = [];

foreach (csdl_teachers_all() as $teacher) {
    if (empty($teacher['active'])) continue;
    $groupName = trim((string)($teacher['to_chuyen_mon'] ?? $teacher['pccm_group'] ?? ''));
    $position = trim((string)($teacher['position'] ?? $teacher['chuc_vu'] ?? ''));
    if ($position === '' && function_exists('csdl_format_kiem_nhiem')) {
        $position = trim((string)csdl_format_kiem_nhiem($teacher['kiem_nhiem'] ?? []));
    }
    if ($position === '') $position = 'Giáo viên';
    $specialty = trim((string)($teacher['specialty'] ?? ''));
    if ($group !== '' && $groupName !== $group) continue;
    $haystack = mb_strtolower(implode(' ', [
        (string)($teacher['name'] ?? ''), (string)($teacher['code'] ?? ''),
        $position, $specialty, $groupName
    ]), 'UTF-8');
    if ($query !== '' && mb_strpos($haystack, $query) === false) continue;
    $photoFile = staff_card_photo_file((string)($teacher['id'] ?? ''));
    $hasPhoto = $photoFile !== '';
    if ($photo === 'yes' && !$hasPhoto) continue;
    if ($photo === 'no' && $hasPhoto) continue;
    $rows[] = [
        'id' => (string)($teacher['id'] ?? ''),
        'name' => (string)($teacher['name'] ?? ''),
        'code' => (string)($teacher['code'] ?? ''),
        'dob' => (string)($teacher['dob'] ?? ''),
        'gender' => (string)($teacher['gender'] ?? ''),
        'position' => $position,
        'specialty' => $specialty,
        'group_name' => $groupName,
        'has_photo' => $hasPhoto,
        'photo_url' => $hasPhoto ? BASE_URL . 'staff_photo.php?id=' . rawurlencode((string)($teacher['id'] ?? '')) : '',
        'verify_url' => staff_card_verify_url($teacher),
        'public_code' => staff_card_public_code($teacher),
    ];
    if (count($rows) >= $limit) break;
}
echo json_encode(['ok' => true, 'count' => count($rows), 'staff' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
