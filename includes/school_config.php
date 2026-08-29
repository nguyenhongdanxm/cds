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
            'address' => '',
            'phone' => '',
            'email' => '',
            'logo' => '',
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
