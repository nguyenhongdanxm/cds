<?php
/**
 * Đọc/ghi JSON dùng chung cho toàn bộ CDS.
 *
 * Hàm ghi dùng tệp tạm cùng thư mục rồi đổi tên nguyên tử. Cách này giữ
 * nguyên định dạng dữ liệu hiện có nhưng tránh để lại JSON dở dang khi hai
 * yêu cầu lưu đồng thời hoặc tiến trình PHP bị ngắt giữa chừng.
 */

if (!function_exists('cds_json_load')) {
    function cds_json_load(string $file, $default = []) {
        if (!is_file($file)) return $default;
        $raw = file_get_contents($file);
        if ($raw === false) return $default;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $default;
    }
}

if (!function_exists('cds_json_save')) {
    function cds_json_save(string $file, $data): bool {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) return false;

        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return false;

        $tmp = tempnam($dir, basename($file) . '.tmp.');
        if ($tmp === false) return false;
        $written = file_put_contents($tmp, $json, LOCK_EX);
        if ($written === false || $written !== strlen($json)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        clearstatcache(true, $file);
        return true;
    }
}

if (!function_exists('cds_json_update')) {
    function cds_json_update(string $file, callable $update, $default = []): bool {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return false;
        $lock = @fopen($file . '.lock', 'c');
        if (!$lock || !@flock($lock, LOCK_EX)) {
            if (is_resource($lock)) @fclose($lock);
            return false;
        }
        try {
            $current = cds_json_load($file, $default);
            $next = $update($current);
            return $next === false ? false : cds_json_save($file, $next);
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }
}

if (!function_exists('cds_json_prepend_bounded')) {
    function cds_json_prepend_bounded(string $file, array $row, int $limit = 1000): bool {
        return cds_json_update($file, static function ($rows) use ($row, $limit) {
            $rows = is_array($rows) ? $rows : [];
            array_unshift($rows, $row);
            return array_slice($rows, 0, max(1, $limit));
        });
    }
}
