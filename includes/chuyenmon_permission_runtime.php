<?php
/**
 * Bộ tính quyền Chuyên môn dùng chung cho trang tài khoản CDS và phân hệ
 * Chuyên môn. Chỉ đọc users.json/permission_groups.json, không ghi hay di trú
 * dữ liệu, nhờ đó việc chuẩn hóa không làm thay đổi cấu hình đang có.
 */

function cds_cm_permission_rank(string $level): int {
    return ['none'=>0, 'view'=>1, 'edit'=>2, 'delete'=>3, 'admin'=>4][$level] ?? 0;
}

function cds_cm_permission_codes(): array {
    return [
        'cm.dashboard','cm.tracuu','cm.pccm','cm.nhaplieu','cm.thongke','cm.kehoach',
        'cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi',
    ];
}

function cds_cm_default_group_access(): array {
    return [
        'bgh' => ['cm.dashboard'=>'view','cm.tracuu'=>'view','cm.thongke'=>'view','cm.kehoach'=>'view','cm.baocao.dinhky'=>'view','cm.baocao.tiendo'=>'view','cm.baocao.dugio'=>'view','cm.baocao.kythi'=>'view','cm.pccm'=>'edit','cm.nhaplieu'=>'edit'],
        'totruong' => ['cm.dashboard'=>'view','cm.tracuu'=>'view','cm.thongke'=>'view','cm.kehoach'=>'view','cm.baocao.dinhky'=>'view','cm.baocao.tiendo'=>'view','cm.baocao.dugio'=>'view','cm.baocao.kythi'=>'view','cm.pccm'=>'edit'],
        'gvcn' => ['cm.dashboard'=>'view','cm.tracuu'=>'view','cm.baocao.tiendo'=>'edit','cm.baocao.dugio'=>'view'],
        'gv' => ['cm.dashboard'=>'view','cm.tracuu'=>'view','cm.baocao.dinhky'=>'view','cm.baocao.tiendo'=>'edit','cm.baocao.dugio'=>'view','cm.baocao.kythi'=>'view'],
        'doandoi' => ['cm.dashboard'=>'view','cm.tracuu'=>'view'],
    ];
}

function cds_cm_load_array_file(string $file): array {
    if (function_exists('cds_json_load')) return cds_json_load($file, []);
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function cds_cm_feature_access_for_user(array $user, string $code, string $rootDataPath): string {
    if (($user['role'] ?? '') === 'admin') return 'delete';
    $reports = ['cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'];
    if ($code === 'cm.baocao') {
        $best = 'none';
        foreach ($reports as $child) {
            $level = cds_cm_feature_access_for_user($user, $child, $rootDataPath);
            if (cds_cm_permission_rank($level) > cds_cm_permission_rank($best)) $best = $level;
        }
        return $best;
    }

    $access = 'none';
    $usesGroups = (int)($user['permission_model_version'] ?? 1) >= 2 || !empty($user['groups']);
    $isReport = in_array($code, $reports, true);
    if (!$usesGroups) {
        $legacy = is_array($user['perms'] ?? null) ? $user['perms'] : [];
        if (in_array($code, $legacy, true) || ($isReport && in_array('cm.baocao', $legacy, true))) $access = 'view';
        $module = (string)($user['modules']['chuyenmon'] ?? 'none');
        if (cds_cm_permission_rank($module) > cds_cm_permission_rank($access)) $access = $module;
    }

    $groups = cds_cm_default_group_access();
    foreach (cds_cm_load_array_file(rtrim($rootDataPath, '/') . '/permission_groups.json') as $key => $group) {
        if (is_array($group)) $groups[$key] = is_array($group['access'] ?? null) ? $group['access'] : [];
    }
    $userGroups = (array)($user['groups'] ?? []);
    $roleGroup = (string)($user['role'] ?? '');
    if ((int)($user['permission_model_version'] ?? 1) < 2 && !$userGroups && isset($groups[$roleGroup])) {
        $userGroups[] = $roleGroup;
    }
    foreach ($userGroups as $groupKey) {
        $groupAccess = $groups[$groupKey] ?? [];
        $level = (string)($groupAccess[$code] ?? 'none');
        if ($isReport && !array_key_exists($code, $groupAccess)) $level = (string)($groupAccess['cm.baocao'] ?? 'none');
        if (cds_cm_permission_rank($level) > cds_cm_permission_rank($access)) $access = $level;
    }

    if ($code === 'cm.baocao.dugio' && !$usesGroups) {
        $roles = array_merge([(string)($user['role'] ?? '')], (array)($user['groups'] ?? []));
        if (array_intersect($roles, ['gv','gvcn']) && cds_cm_permission_rank($access) < cds_cm_permission_rank('view')) $access = 'view';
    }
    $override = (string)($user['permission_overrides'][$code] ?? 'inherit');
    if (in_array($override, ['none','view','edit','delete'], true)) $access = $override;
    return $access;
}
