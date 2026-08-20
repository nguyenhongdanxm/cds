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
]], 'CDS_MANAGER_CHUYENMON_SCOPE_V1');

$plans = $root . '/chuyenmon/includes/education_plans.php';
patch_one($plans, [
    [
        "\$educationIsAdmin = \$educationRole === 'admin';\n\$educationIsLeader = \$educationRole === 'totruong' || in_array('totruong', \$educationGroups, true);",
        "\$educationIsAdmin = \$educationRole === 'admin';\n\$educationIsManager = \$educationRole === 'manager' || in_array('manager', \$educationGroups, true);\n\$educationCanViewAll = \$educationIsAdmin || \$educationIsManager;\n\$educationCanApproveAll = \$educationIsAdmin || \$educationIsManager;\n\$educationIsLeader = \$educationRole === 'totruong' || in_array('totruong', \$educationGroups, true);"
    ],
    [
        "\$educationVisibleRows = array_values(array_filter(\$educationRows, fn(\$row)=>cm_education_is_visible(\$row,\$educationIsAdmin,\$educationIsLeader,\$educationTeacher,\$educationGroup)));",
        "\$educationVisibleRows = array_values(array_filter(\$educationRows, fn(\$row)=>cm_education_is_visible(\$row,\$educationCanViewAll,\$educationIsLeader,\$educationTeacher,\$educationGroup)));"
    ],
    [
        "if (!\$educationIsAdmin && !\$educationIsLeader) { http_response_code(403); exit('Chỉ TTCM hoặc quản trị được duyệt.'); }",
        "if (!\$educationCanApproveAll && !\$educationIsLeader) { http_response_code(403); exit('Chỉ TTCM hoặc quản trị được duyệt.'); }"
    ],
    [
        "if (!\$educationIsAdmin && (\$educationGroup === '' || cm_education_norm(\$row['teacher_group'] ?? '') !== cm_education_norm(\$educationGroup))) { http_response_code(403); exit('TTCM chỉ được duyệt kế hoạch trong tổ của mình.'); }",
        "if (!\$educationCanApproveAll && (\$educationGroup === '' || cm_education_norm(\$row['teacher_group'] ?? '') !== cm_education_norm(\$educationGroup))) { http_response_code(403); exit('TTCM chỉ được duyệt kế hoạch trong tổ của mình.'); }"
    ]
], 'CDS_MANAGER_EDUCATION_PLAN_SCOPE_V1');

echo "MANAGER_CHUYENMON_SCOPE_OK\n";
