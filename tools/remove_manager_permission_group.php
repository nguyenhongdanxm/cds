<?php
/**
 * Dọn toàn bộ cấu hình nhóm manager đã thêm thử nghiệm.
 * - Xóa manager khỏi permission_groups.json.
 * - Gỡ manager khỏi groups của người dùng.
 * - Nếu role=manager, chuyển về bgh để tài khoản không bị mất toàn bộ menu.
 * - Nếu permission model v2 mà groups rỗng, khôi phục group theo role hiện có.
 */
$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '') { fwrite(STDERR, "Thiếu DEPLOYPATH.\n"); exit(1); }
$data = $root . '/data';

function read_json_file(string $file): array {
    if (!is_file($file)) return [];
    $raw = json_decode((string)file_get_contents($file), true);
    return is_array($raw) ? $raw : [];
}
function write_json_file(string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { fwrite(STDERR, "Không tạo được $dir\n"); exit(2); }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($file, $json . "\n", LOCK_EX) === false) { fwrite(STDERR, "Không ghi được $file\n"); exit(3); }
}

$groupsFile = $data . '/permission_groups.json';
$groups = read_json_file($groupsFile);
if (array_key_exists('manager', $groups)) {
    unset($groups['manager']);
    write_json_file($groupsFile, $groups);
}

$usersFile = $data . '/users.json';
$users = read_json_file($usersFile);
$changed = false;
$knownRoleGroups = ['bgh','totruong','gvcn','gv','qlnt','vanthu','ketoan','doandoi','thuvien_thietbi'];
foreach ($users as &$user) {
    if (!is_array($user)) continue;
    $role = (string)($user['role'] ?? '');
    $userGroups = array_values(array_filter((array)($user['groups'] ?? []), static fn($g) => (string)$g !== 'manager'));
    if ($role === 'manager') {
        $role = 'bgh';
        $user['role'] = 'bgh';
        $changed = true;
    }
    $oldGroups = (array)($user['groups'] ?? []);
    if ($userGroups !== array_values($oldGroups)) $changed = true;
    if (!$userGroups && in_array($role, $knownRoleGroups, true)) {
        $userGroups = [$role];
        $changed = true;
    }
    if (($user['role'] ?? '') !== 'admin') $user['groups'] = $userGroups;
}
unset($user);
if ($changed) write_json_file($usersFile, $users);

echo "MANAGER_PERMISSION_GROUP_REMOVED\n";
