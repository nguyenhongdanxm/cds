<?php
/**
 * Nạp dữ liệu khởi tạo Chuyên môn theo từng trường mà không đụng dữ liệu vận hành.
 *
 * Thứ tự ưu tiên:
 * 1) CDS_CHUYENMON_DEFAULTS (đường dẫn JSON ngoài web root)
 * 2) school.json -> chuyenmon.defaults_file
 * 3) school.json -> chuyenmon.seed_profile = "empty"
 * 4) fallback lịch sử defaults_xinman.php để giữ nguyên vận hành hiện tại.
 *
 * Nếu người quản trị đã chỉ định một defaults_file nhưng file không hợp lệ,
 * hệ thống trả về bộ mặc định rỗng thay vì vô tình nạp dữ liệu của Xín Mần.
 */

if (!function_exists('cds_empty_chuyenmon_defaults')) {
    function cds_empty_chuyenmon_defaults(): array
    {
        return [
            'teachers' => [],
            'classes' => [],
            'groups' => [],
            'subjects' => [],
            'roles' => [],
        ];
    }
}

if (!function_exists('cds_normalize_chuyenmon_defaults')) {
    function cds_normalize_chuyenmon_defaults($data): array
    {
        $empty = cds_empty_chuyenmon_defaults();
        if (!is_array($data)) return $empty;

        foreach (array_keys($empty) as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $empty[$key] = $data[$key];
            }
        }
        return $empty;
    }
}

if (!function_exists('cds_load_chuyenmon_defaults_json')) {
    function cds_load_chuyenmon_defaults_json(string $path): ?array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) return null;
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') return null;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) return null;
        return cds_normalize_chuyenmon_defaults($decoded);
    }
}

if (!function_exists('cds_load_chuyenmon_seed_defaults')) {
    function cds_load_chuyenmon_seed_defaults(): array
    {
        $configuredPath = trim((string)getenv('CDS_CHUYENMON_DEFAULTS'));
        if ($configuredPath === '' && function_exists('cds_school_config')) {
            $configuredPath = trim((string)cds_school_config('chuyenmon.defaults_file', ''));
        }

        if ($configuredPath !== '') {
            $custom = cds_load_chuyenmon_defaults_json($configuredPath);
            if ($custom !== null) return $custom;

            error_log('CDS: chuyenmon defaults_file is configured but invalid/unreadable: ' . $configuredPath);
            return cds_empty_chuyenmon_defaults();
        }

        $profile = function_exists('cds_school_config')
            ? strtolower(trim((string)cds_school_config('chuyenmon.seed_profile', 'xinman')))
            : 'xinman';

        if ($profile === 'empty' || $profile === 'none') {
            return cds_empty_chuyenmon_defaults();
        }

        $legacyFile = __DIR__ . '/defaults_xinman.php';
        $legacy = is_file($legacyFile) ? require $legacyFile : [];
        return cds_normalize_chuyenmon_defaults($legacy);
    }
}
