<?php
/**
 * Patch quyền Quản trị viên vào bản chạy sau khi cPanel copy includes/.
 * Nhóm manager đứng ngay dưới admin: toàn quyền xem/tạo/sửa nội dung,
 * không được vào các màn hình require_admin() (tài khoản, cấu hình lõi...).
 */
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Không tìm thấy permissions.php cần cập nhật.\n");
    exit(1);
}
$src = file_get_contents($path);
if ($src === false) exit(1);
if (strpos($src, 'CDS_MANAGER_PERMISSION_GROUP_V1') !== false) {
    echo "MANAGER_PERMISSION_GROUP_OK\n";
    exit(0);
}

$replacements = [];

// 1) Nhóm quyền mặc định: manager luôn nhận toàn bộ chức năng ở mức edit.
$needle = "    \$tdNonAttendance = ['td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'];\n    return [\n        'bgh' => [";
$replace = "    \$tdNonAttendance = ['td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'];\n    return [\n        // CDS_MANAGER_PERMISSION_GROUP_V1\n        'manager' => [\n            'label' => 'Quản trị viên',\n            'access' => \$edit(array_keys(permission_features_catalog())),\n        ],\n        'bgh' => [";
$replacements[] = [$needle, $replace];

// 2) Nếu permission_groups.json đã tồn tại, manager vẫn được đồng bộ theo catalog mới.
$needle = "        \$saved[\$key]['label'] = trim((string)(\$saved[\$key]['label'] ?? \$group['label'])) ?: \$group['label'];\n        \$saved[\$key]['access'] = is_array(\$saved[\$key]['access'] ?? null) ? \$saved[\$key]['access'] : [];\n    }";
$replace = "        \$saved[\$key]['label'] = trim((string)(\$saved[\$key]['label'] ?? \$group['label'])) ?: \$group['label'];\n        \$saved[\$key]['access'] = is_array(\$saved[\$key]['access'] ?? null) ? \$saved[\$key]['access'] : [];\n        if (\$key === 'manager') {\n            // Nhóm Quản trị viên là nhóm hệ thống: luôn theo catalog hiện hành.\n            \$saved[\$key]['label'] = 'Quản trị viên';\n            \$saved[\$key]['access'] = \$group['access'];\n        }\n    }";
$replacements[] = [$needle, $replace];

// 3) Quyền hiệu lực: admin = delete toàn bộ; manager = edit toàn bộ.
$needle = "    if ((\$user['role'] ?? '') === 'admin') {\n        return array_fill_keys(array_keys(permission_features_catalog()), 'delete');\n    }\n\n    // Có danh sách nhóm rõ ràng";
$replace = "    if ((\$user['role'] ?? '') === 'admin') {\n        return array_fill_keys(array_keys(permission_features_catalog()), 'delete');\n    }\n    if ((\$user['role'] ?? '') === 'manager' || in_array('manager', (array)(\$user['groups'] ?? []), true)) {\n        return array_fill_keys(array_keys(permission_features_catalog()), 'edit');\n    }\n\n    // Có danh sách nhóm rõ ràng";
$replacements[] = [$needle, $replace];

// 4) Role mẫu để Quản trị viên xuất hiện ngay dưới Admin trong màn hình tài khoản.
$needle = "        'admin' => [\n            'label' => 'Quản trị hệ thống',\n            'modules' => ['chuyenmon'=>'admin','csdl'=>'admin','noitru'=>'admin','vanban'=>'admin','thidua'=>'admin'],\n            'perms' => array_merge(\$allCm, \$allCs, \$allNt),\n            'classes' => [], // mọi lớp\n        ],\n        'bgh' => [";
$replace = "        'admin' => [\n            'label' => 'Quản trị hệ thống',\n            'modules' => ['chuyenmon'=>'admin','csdl'=>'admin','noitru'=>'admin','vanban'=>'admin','thidua'=>'admin'],\n            'perms' => array_merge(\$allCm, \$allCs, \$allNt),\n            'classes' => [], // mọi lớp\n        ],\n        'manager' => [\n            'label' => 'Quản trị viên',\n            'modules' => array_fill_keys(array_keys(permission_modules_catalog()), 'edit'),\n            'perms' => array_keys(permission_features_catalog()),\n            'classes' => [], // mọi lớp, nhưng không có quyền require_admin()\n        ],\n        'bgh' => [";
$replacements[] = [$needle, $replace];

// 5) Manager không bị giới hạn phạm vi lớp/dữ liệu.
$needle = "    if ((\$u['role'] ?? '') === 'admin') return null;\n    \$cls = is_array(\$u['classes'] ?? null) ? \$u['classes'] : [];";
$replace = "    if ((\$u['role'] ?? '') === 'admin') return null;\n    if ((\$u['role'] ?? '') === 'manager' || in_array('manager', (array)(\$u['groups'] ?? []), true)) return null;\n    \$cls = is_array(\$u['classes'] ?? null) ? \$u['classes'] : [];";
$replacements[] = [$needle, $replace];

foreach ($replacements as [$from, $to]) {
    if (strpos($src, $from) === false) {
        fwrite(STDERR, "Không tìm thấy điểm chèn quyền manager; dừng để tránh sửa nhầm source.\n");
        exit(2);
    }
    $src = str_replace($from, $to, $src, $count);
    if ($count !== 1) {
        fwrite(STDERR, "Điểm chèn manager không duy nhất; dừng deploy.\n");
        exit(3);
    }
}

if (file_put_contents($path, $src, LOCK_EX) === false) {
    fwrite(STDERR, "Không ghi được permissions.php.\n");
    exit(4);
}

// Kiểm tra cú pháp ngay trên bản đã patch.
$php = PHP_BINARY;
exec(escapeshellarg($php).' -l '.escapeshellarg($path).' 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, implode("\n", $out)."\n");
    exit(5);
}
echo "MANAGER_PERMISSION_GROUP_OK\n";
