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
