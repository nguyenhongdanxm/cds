<?php
/** Dữ liệu và mã xác minh dùng chung cho thẻ cán bộ, giáo viên. */
require_once __DIR__ . '/csdl_store.php';

if (!defined('STAFF_CARD_SETTINGS')) define('STAFF_CARD_SETTINGS', DATA_PATH . '/staff_card_settings.json');

function staff_card_settings(): array {
    $settings = load_json(STAFF_CARD_SETTINGS, []);
    if (empty($settings['secret']) || !is_string($settings['secret'])) {
        try { $secret = bin2hex(random_bytes(32)); }
        catch (Throwable $e) { $secret = hash('sha256', uniqid('', true) . microtime(true)); }
        $settings = array_merge([
            'secret' => $secret,
            'issued_at' => date('c'),
            'school_name' => defined('SCHOOL_NAME') ? SCHOOL_NAME : '',
        ], is_array($settings) ? $settings : []);
        save_json(STAFF_CARD_SETTINGS, $settings);
    }
    return $settings;
}

function staff_card_public_code(array $teacher): string {
    $id = (string)($teacher['id'] ?? '');
    $code = trim((string)($teacher['code'] ?? ''));
    $base = $code !== '' ? $code : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $id), -10));
    return 'XM-GV-' . strtoupper($base);
}

function staff_card_token(string $teacherId): string {
    $secret = (string)(staff_card_settings()['secret'] ?? '');
    return substr(hash_hmac('sha256', $teacherId, $secret), 0, 24);
}

function staff_card_verify_url(array $teacher): string {
    $id = (string)($teacher['id'] ?? '');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'cds.noitruxinman.edu.vn');
    $path = rtrim((defined('BASE_URL') ? BASE_URL : '/'), '/') . '/staff_verify.php';
    return $scheme . '://' . $host . $path . '?id=' . rawurlencode($id) . '&t=' . rawurlencode(staff_card_token($id));
}

function staff_card_is_valid_token(string $teacherId, string $token): bool {
    return $teacherId !== '' && $token !== '' && hash_equals(staff_card_token($teacherId), $token);
}

function staff_card_photo_file(string $teacherId): string {
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $teacherId);
    if ($safeId === '') return '';
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $file = DATA_PATH . '/teacher_photos/' . $safeId . '.' . $ext;
        if (is_file($file)) return $file;
    }
    return '';
}
