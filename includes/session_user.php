<?php
/** Chuẩn hóa cấu trúc người dùng được lưu trong session CDS. */
if (!function_exists('cds_session_user_from_record')) {
    function cds_session_user_from_record(array $record): array {
        $role = (string)($record['role'] ?? 'gv');
        $groups = is_array($record['groups'] ?? null) ? $record['groups'] : [];
        if ((int)($record['permission_model_version'] ?? 1) < 2 && !$groups && $role === 'gvcn') {
            $groups[] = 'gvcn';
        }
        return [
            'id' => $record['id'] ?? '',
            'username' => $record['username'] ?? '',
            'name' => $record['name'] ?? ($record['username'] ?? ''),
            'role' => $role,
            'modules' => is_array($record['modules'] ?? null) ? $record['modules'] : [],
            'perms' => is_array($record['perms'] ?? null) ? $record['perms'] : [],
            'groups' => $groups,
            'permission_overrides' => is_array($record['permission_overrides'] ?? null) ? $record['permission_overrides'] : [],
            'permission_model_version' => (int)($record['permission_model_version'] ?? 1),
            'classes' => is_array($record['classes'] ?? null) ? $record['classes'] : [],
            'homeroom_classes' => is_array($record['homeroom_classes'] ?? null) ? $record['homeroom_classes'] : [],
            'teacher_name' => $record['teacher_name'] ?? '',
            'teacher_id' => $record['teacher_id'] ?? '',
            'phone' => $record['phone'] ?? '',
            'email' => $record['email'] ?? '',
            'dob' => $record['dob'] ?? '',
            'gender' => $record['gender'] ?? '',
            'hometown' => $record['hometown'] ?? '',
            'address' => $record['address'] ?? '',
        ];
    }
}

/**
 * Kiểm tra nhóm quyền đang có hiệu lực của tài khoản.
 *
 * Từ mô hình phân quyền v2, `groups` là nguồn chính xác. Trường `role` chỉ
 * được dùng làm phương án tương thích cho tài khoản cũ chưa có danh sách
 * nhóm, tránh việc một vai trò cũ còn lưu lại tiếp tục cấp quyền đã bị gỡ.
 */
if (!function_exists('cds_user_has_group')) {
    function cds_user_has_group(array $user, string $group): bool {
        $groups = is_array($user['groups'] ?? null) ? $user['groups'] : [];
        if ((int)($user['permission_model_version'] ?? 1) >= 2 || array_key_exists('groups', $user)) {
            return in_array($group, $groups, true);
        }
        return in_array($group, $groups, true) || (string)($user['role'] ?? '') === $group;
    }
}
