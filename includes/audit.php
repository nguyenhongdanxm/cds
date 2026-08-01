<?php
/** Nhật ký hoạt động nhẹ: JSON Lines, ghi nối tiếp có khóa tệp. */
function cds_audit_file(): string { return DATA_PATH . '/audit-' . date('Y-m') . '.jsonl'; }

function cds_client_ip(): string {
    $raw = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    return trim(explode(',', $raw)[0]);
}

function cds_audit_log(string $action, string $module = 'system', array $context = [], ?array $actor = null): void {
    $actor = $actor ?? (function_exists('current_user') ? current_user() : null);
    $row = [
        'id' => bin2hex(random_bytes(8)), 'at' => date('c'),
        'user_id' => (string)($actor['id'] ?? ''),
        'username' => (string)($actor['username'] ?? ($context['username'] ?? '')),
        'user_name' => (string)($actor['name'] ?? ''),
        'action' => $action, 'module' => $module,
        'path' => substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
        'ip' => cds_client_ip(),
        'agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
        'context' => $context,
    ];
    @file_put_contents(cds_audit_file(), json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function cds_audit_touch(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) return;
    $route = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($route === '') $route = 'index.php';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $requested = strtolower(trim((string)($_POST['action'] ?? 'submit')));
        $action = str_contains($requested, 'delete') ? 'delete'
            : (str_contains($requested, 'export') ? 'export'
            : (str_contains($requested, 'create') || str_contains($requested, 'add') ? 'create' : 'update'));
        cds_audit_log($action, pathinfo($route, PATHINFO_FILENAME), ['requested_action'=>$requested]);
        return;
    }
    $key = 'cds_audit_touch_' . sha1($route);
    if (isset($_SESSION[$key]) && time() - (int)$_SESSION[$key] < 600) return;
    $_SESSION[$key] = time();
    cds_audit_log('page_view', pathinfo($route, PATHINFO_FILENAME));
}

function cds_audit_read(string $from, string $to, string $userId = '', string $action = '', int $limit = 2000): array {
    $rows = [];
    $cursor = strtotime(date('Y-m-01', strtotime($from)));
    $endMonth = strtotime(date('Y-m-01', strtotime($to)));
    while ($cursor <= $endMonth) {
        $file = DATA_PATH . '/audit-' . date('Y-m', $cursor) . '.jsonl';
        if (is_readable($file)) {
            $handle = fopen($file, 'rb');
            if ($handle) { while (($line = fgets($handle)) !== false) {
                $row = json_decode($line, true); if (!is_array($row)) continue;
                $day = substr((string)($row['at'] ?? ''), 0, 10);
                if ($day < $from || $day > $to) continue;
                if ($userId !== '' && (string)($row['user_id'] ?? '') !== $userId) continue;
                if ($action !== '' && (string)($row['action'] ?? '') !== $action) continue;
                $rows[] = $row;
            } fclose($handle); }
        }
        $cursor = strtotime('+1 month', $cursor);
    }
    usort($rows, fn($a, $b) => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
    return array_slice($rows, 0, max(1, $limit));
}
