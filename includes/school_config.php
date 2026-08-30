<?php
/**
 * Cấu hình nhận diện nhà trường dùng chung toàn hệ sinh thái CDS.
 *
 * Mục tiêu: khi nhân bản CDS sang trường khác, các thông tin nhận diện chính
 * chỉ cần thay tại một nơi. Không đặt thông tin bí mật (mật khẩu, OAuth secret,
 * database password...) trong tệp này.
 */

if (!function_exists('cds_school_config')) {
    function cds_school_config(?string $key = null, $default = null)
    {
        static $config = [
            'code' => 'XMNT',
            'name' => 'Trường PTDTNT THCS&THPT Xín Mần',
            'short_name' => 'Xín Mần',
            'department' => 'Sở GD&ĐT Tuyên Quang',
            'school_year' => '2025–2026',
            'website' => 'https://noitruxinman.edu.vn',
            'cds_title' => 'CDS - Trường PTDTNT THCS&THPT Xín Mần',
            'cds_short_title' => 'CDS Xín Mần',
            'description' => 'Hệ sinh thái quản lý nhà trường',
            'address' => '',
            'phone' => '',
            'email' => '',
            'logo' => 'assets/logo.png',
            'pwa' => [
                'theme_color' => '#0f4c81',
                'background_color' => '#f4f7fb',
                'icon_192' => 'assets/icons/cds-192.png',
                'icon_512' => 'assets/icons/cds-512.png',
            ],
            'levels' => [
                'thcs' => true,
                'thpt' => true,
            ],
            'modules' => [
                'chuyenmon' => true,
                'csdl' => true,
                'noitru' => true,
                'vanban' => true,
                'thuvien' => true,
                'yte' => true,
            ],
        ];

        if ($key === null || $key === '') {
            return $config;
        }

        $value = $config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

if (!function_exists('school_name')) {
    function school_name(): string { return (string)cds_school_config('name', ''); }
}
if (!function_exists('school_short_name')) {
    function school_short_name(): string { return (string)cds_school_config('short_name', ''); }
}
if (!function_exists('school_department')) {
    function school_department(): string { return (string)cds_school_config('department', ''); }
}
if (!function_exists('school_year')) {
    function school_year(): string { return (string)cds_school_config('school_year', ''); }
}
if (!function_exists('school_website')) {
    function school_website(): string { return (string)cds_school_config('website', ''); }
}
if (!function_exists('school_code')) {
    function school_code(): string { return (string)cds_school_config('code', ''); }
}
if (!function_exists('school_cds_title')) {
    function school_cds_title(): string { return (string)cds_school_config('cds_title', 'CDS - ' . school_name()); }
}
if (!function_exists('school_cds_short_title')) {
    function school_cds_short_title(): string { return (string)cds_school_config('cds_short_title', 'CDS ' . school_short_name()); }
}
if (!function_exists('school_description')) {
    function school_description(): string { return (string)cds_school_config('description', 'Hệ sinh thái quản lý nhà trường'); }
}
if (!function_exists('school_address')) {
    function school_address(): string { return (string)cds_school_config('address', ''); }
}
if (!function_exists('school_phone')) {
    function school_phone(): string { return (string)cds_school_config('phone', ''); }
}
if (!function_exists('school_email')) {
    function school_email(): string { return (string)cds_school_config('email', ''); }
}
if (!function_exists('school_logo')) {
    function school_logo(): string { return (string)cds_school_config('logo', 'assets/logo.png'); }
}
if (!function_exists('school_has_level')) {
    function school_has_level(string $level): bool { return (bool)cds_school_config('levels.' . strtolower($level), false); }
}
if (!function_exists('school_module_enabled')) {
    function school_module_enabled(string $module): bool { return (bool)cds_school_config('modules.' . strtolower($module), false); }
}
