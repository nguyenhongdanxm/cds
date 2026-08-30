<?php
/**
 * Cấu hình triển khai duy nhất cho một bản CDS.
 * Mặc định nằm ngoài web root: /home/<user>/cds_private/instance.json
 * Có thể ghi đè đường dẫn bằng biến môi trường CDS_INSTANCE_CONFIG.
 */

if (!function_exists('cds_instance_config_path')) {
    function cds_instance_config_path(): string
    {
        $custom = getenv('CDS_INSTANCE_CONFIG');
        if (is_string($custom) && trim($custom) !== '') return trim($custom);

        if (defined('BASE_PATH')) {
            return dirname(BASE_PATH) . '/cds_private/instance.json';
        }
        return dirname(__DIR__, 2) . '/cds_private/instance.json';
    }
}

if (!function_exists('cds_instance_config')) {
    function cds_instance_config(?string $key = null, $default = null)
    {
        static $cache = null;
        if (!is_array($cache)) {
            $cache = [];
            $path = cds_instance_config_path();
            if (is_file($path) && is_readable($path)) {
                $raw = @file_get_contents($path);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($decoded)) $cache = $decoded;
            }
        }
        if ($key === null || $key === '') return $cache;
        $value = $cache;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value;
    }
}

if (!function_exists('cds_instance_save')) {
    function cds_instance_save(array $config): bool
    {
        $path = cds_instance_config_path();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;
        @chmod($dir, 0700);
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) return false;
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) return false;
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        @chmod($path, 0600);
        return true;
    }
}
