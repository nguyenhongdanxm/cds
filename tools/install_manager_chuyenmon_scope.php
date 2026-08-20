<?php
/** Patch phạm vi Chuyên môn cho nhóm Quản trị viên trên bản deploy. */
$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '') { fwrite(STDERR, "Thiếu DEPLOYPATH.\n"); exit(1); }

function patch_one(string $file, array $replacements, string $marker): void {
    if (!is_file($file)) { fwrite(STDERR, "Không tìm thấy $file\n"); exit(2); }
    $src = file_get_contents($file);
    if ($src === false) exit(2);
    if (strpos($src, $marker) !== false) return;
    foreach ($replacements as [$from, $to]) {
        if (strpos($src, $from) === false) { fwrite(STDERR, "Không tìm thấy điểm patch trong $file\n"); exit(3); }
        $src = str_replace($from, $to, $src, $count);
        if ($count !== 1) { fwrite(STDERR, "Điểm patch không duy nhất trong $file\n"); exit(4); }
    }
    $src .= "\n/* $marker */\n";
    if (file_put_contents($file, $src, LOCK_EX) === false) exit(5);
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $out, $code);
    if ($code !== 0) { fwrite(STDERR, implode("\n", $out)."\n"); exit(6); }
}

$runtime = $root . '/includes/chuyenmon_permission_runtime.php';
patch_one($runtime, [[
    "function cds_cm_feature_access_for_user(array \$user, string \$code, string \$rootDataPath): string {\n    if ((\$user['role'] ?? '') === 'admin') return 'delete';",
    "function cds_cm_feature_access_for_user(array \$user, string \$code, string \$rootDataPath): string {\n    if ((\$user['role'] ?? '') === 'admin') return 'delete';\n    if ((\$user['role'] ?? '') === 'manager' || in_array('manager', (array)(\$user['groups'] ?? []), true)) return 'edit';"
]], 'CDS_MANAGER_CHUYENMON_SCOPE_V2');

$plans = $root . '/chuyenmon/includes/education_plans.php';
patch_one($plans, [[
    "\$educationIsAdmin = \$educationRole === 'admin';\n\$educationIsLeader = \$educationRole === 'totruong' || in_array('totruong', \$educationGroups, true);",
    "// Quản trị viên là quản trị nội dung trong phân hệ Kế hoạch giáo dục.\n// Không đồng nghĩa Admin hệ thống: users.php và cấu hình lõi vẫn chỉ dành cho role admin.\n\$educationIsAdmin = \$educationRole === 'admin' || \$educationRole === 'manager' || in_array('manager', \$educationGroups, true);\n\$educationIsLeader = \$educationRole === 'totruong' || in_array('totruong', \$educationGroups, true);"
]], 'CDS_MANAGER_EDUCATION_PLAN_SCOPE_V2');

echo "MANAGER_CHUYENMON_SCOPE_OK\n";
