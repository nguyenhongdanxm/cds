<?php
/**
 * Dọn cấu hình nhóm manager đã thêm thử nghiệm.
 * Tương thích PHP 5.6+ để không làm cPanel Deployment dừng vì lỗi cú pháp.
 */
$root = rtrim(isset($argv[1]) ? (string)$argv[1] : '', '/');
if ($root === '') {
    fwrite(STDERR, "Thiếu DEPLOYPATH.\n");
    exit(1);
}
$data = $root . '/data';

function cds_manager_cleanup_read_json($file) {
    if (!is_file($file)) return array();
    $content = @file_get_contents($file);
    if ($content === false) return array();
    $raw = json_decode($content, true);
    return is_array($raw) ? $raw : array();
}

function cds_manager_cleanup_write_json($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        fwrite(STDERR, "Không tạo được " . $dir . "\n");
        return false;
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        fwrite(STDERR, "Không mã hóa được " . $file . "\n");
        return false;
    }
    if (@file_put_contents($file, $json . "\n", LOCK_EX) === false) {
        fwrite(STDERR, "Không ghi được " . $file . "\n");
        return false;
    }
    return true;
}

$groupsFile = $data . '/permission_groups.json';
$groups = cds_manager_cleanup_read_json($groupsFile);
if (array_key_exists('manager', $groups)) {
    unset($groups['manager']);
    cds_manager_cleanup_write_json($groupsFile, $groups);
}

$usersFile = $data . '/users.json';
$users = cds_manager_cleanup_read_json($usersFile);
$changed = false;
$knownRoleGroups = array('bgh','totruong','gvcn','gv','qlnt','vanthu','ketoan','doandoi','thuvien_thietbi');

foreach ($users as $index => $user) {
    if (!is_array($user)) continue;

    $role = isset($user['role']) ? (string)$user['role'] : '';
    $oldGroups = isset($user['groups']) && is_array($user['groups']) ? $user['groups'] : array();
    $userGroups = array();
    foreach ($oldGroups as $groupKey) {
        if ((string)$groupKey !== 'manager') $userGroups[] = $groupKey;
    }

    if ($role === 'manager') {
        $role = 'bgh';
        $user['role'] = 'bgh';
        $changed = true;
    }

    if ($userGroups !== array_values($oldGroups)) $changed = true;

    if (count($userGroups) === 0 && in_array($role, $knownRoleGroups, true)) {
        $userGroups = array($role);
        $changed = true;
    }

    if (!isset($user['role']) || $user['role'] !== 'admin') {
        $user['groups'] = $userGroups;
    }
    $users[$index] = $user;
}

if ($changed && !cds_manager_cleanup_write_json($usersFile, $users)) {
    fwrite(STDERR, "Cảnh báo: chưa dọn được users.json; tiếp tục deploy.\n");
}

echo "MANAGER_PERMISSION_GROUP_REMOVED\n";
exit(0);
