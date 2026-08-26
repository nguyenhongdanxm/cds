<?php
/**
 * Đảm bảo có nhóm quyền "Nhập liệu phòng nội trú" trên host.
 * Chạy sau deploy: php tools/ensure_room_input_only_group.php /path/to/site
 */
$root = rtrim((string)($argv[1] ?? dirname(__DIR__)), '/');
$file = $root . '/data/permission_groups.json';
if (!is_dir(dirname($file))) {
    fwrite(STDERR, "DATA_DIR_NOT_FOUND\n");
    exit(2);
}
$groups = [];
if (is_file($file)) {
    $decoded = json_decode((string)file_get_contents($file), true);
    if (is_array($decoded)) $groups = $decoded;
}
$key = 'nhaplieu_phongnoitru';
$desired = [
    'version' => 11,
    'label' => 'Nhập liệu phòng nội trú',
    'access' => [
        'td.student_room_input' => 'edit',
    ],
];
// Luôn chuẩn hóa đúng nhóm chuyên dụng: không cho quyền Cài đặt, Thống kê,
// xóa dữ liệu, mở khóa hoặc các module/chức năng khác.
$groups[$key] = $desired;
$json = json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "ROOM_INPUT_GROUP_SAVE_FAILED\n");
    exit(3);
}
echo "ROOM_INPUT_ONLY_GROUP_READY\n";
