<?php
/** Đăng ký trang dùng Google Drive mà không ghi lại tệp ở mọi lượt xem. */
require_once __DIR__ . '/json_store.php';

function cds_drive_action_register_shared(string $file, string $action, string $label): void {
    if (!str_starts_with($action, 'page:')) return;
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return;
    $lock = @fopen($file . '.lock', 'c');
    if (!$lock || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) @fclose($lock);
        return;
    }
    try {
        $rows = cds_json_load($file, []);
        $current = is_array($rows[$action] ?? null) ? $rows[$action] : [];
        // Route và nhãn không đổi thì không cần khóa/ghi tệp thêm ở lần sau.
        if (($current['label'] ?? '') === $label) return;
        $rows[$action] = ['label' => $label, 'last_seen' => date('c')];
        cds_json_save($file, $rows);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
