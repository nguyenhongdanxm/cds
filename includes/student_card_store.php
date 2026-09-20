<?php
/** Dữ liệu và mã xác minh dùng chung cho thẻ học sinh. */
require_once __DIR__ . '/csdl_store.php';

if (!defined('STUDENT_CARD_SETTINGS')) define('STUDENT_CARD_SETTINGS', DATA_PATH . '/student_card_settings.json');
if (!defined('STUDENT_CARD_PRINT_HISTORY')) define('STUDENT_CARD_PRINT_HISTORY', DATA_PATH . '/student_card_print_history.json');

function student_card_settings(): array {
    $settings = load_json(STUDENT_CARD_SETTINGS, []);
    if (empty($settings['secret']) || !is_string($settings['secret'])) {
        try { $secret = bin2hex(random_bytes(32)); }
        catch (Throwable $e) { $secret = hash('sha256', uniqid('', true) . microtime(true)); }
        $settings = array_merge([
            'secret' => $secret,
            'issued_at' => date('c'),
            'school_name' => defined('SCHOOL_NAME') ? SCHOOL_NAME : '',
        ], is_array($settings) ? $settings : []);
        save_json(STUDENT_CARD_SETTINGS, $settings);
    }
    return $settings;
}

function student_card_public_code(array $student): string {
    $id = (string)($student['id'] ?? '');
    $code = trim((string)($student['code'] ?? ''));
    $base = $code !== '' ? $code : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $id), -10));
    return 'XM-' . strtoupper($base);
}

function student_card_token(string $studentId): string {
    $secret = (string)(student_card_settings()['secret'] ?? '');
    return substr(hash_hmac('sha256', $studentId, $secret), 0, 24);
}

function student_card_verify_url(array $student): string {
    $id = (string)($student['id'] ?? '');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'cds.noitruxinman.edu.vn');
    $path = rtrim((defined('BASE_URL') ? BASE_URL : '/'), '/') . '/student_verify.php';
    return $scheme . '://' . $host . $path . '?id=' . rawurlencode($id) . '&t=' . rawurlencode(student_card_token($id));
}

function student_card_is_valid_token(string $studentId, string $token): bool {
    return $studentId !== '' && $token !== '' && hash_equals(student_card_token($studentId), $token);
}

function student_card_class_map(): array {
    $map = [];
    foreach (csdl_classes_all() as $class) {
        $map[(string)($class['id'] ?? '')] = $class;
    }
    return $map;
}

function student_card_print_year_key(?string $year = null): string {
    $value = trim((string)($year ?? (csdl_year_current()['label'] ?? SCHOOL_YEAR)));
    return $value !== '' ? $value : 'unknown';
}

function student_card_printed_map(?string $year = null): array {
    $data = load_json(STUDENT_CARD_PRINT_HISTORY, []);
    $rows = $data['years'][student_card_print_year_key($year)] ?? [];
    return is_array($rows) ? $rows : [];
}

function student_card_update_printed(array $studentIds, bool $printed, array $actor = [], ?string $year = null): bool {
    $studentIds = array_values(array_unique(array_filter(array_map(static fn($id) => trim((string)$id), $studentIds))));
    if (!$studentIds) return false;
    $yearKey = student_card_print_year_key($year);
    return cds_json_update(STUDENT_CARD_PRINT_HISTORY, static function ($data) use ($studentIds, $printed, $actor, $yearKey) {
        $data = is_array($data) ? $data : [];
        if (!isset($data['years']) || !is_array($data['years'])) $data['years'] = [];
        $rows = is_array($data['years'][$yearKey] ?? null) ? $data['years'][$yearKey] : [];
        foreach ($studentIds as $studentId) {
            if ($printed) {
                $rows[$studentId] = [
                    'printed_at' => date('c'),
                    'printed_by_id' => (string)($actor['id'] ?? ''),
                    'printed_by' => (string)($actor['name'] ?? $actor['username'] ?? ''),
                ];
            } else {
                unset($rows[$studentId]);
            }
        }
        $data['years'][$yearKey] = $rows;
        $data['updated_at'] = date('c');
        return $data;
    }, ['years' => []]);
}
