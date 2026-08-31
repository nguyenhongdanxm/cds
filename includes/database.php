<?php
/**
 * Kết nối MySQL/MariaDB dùng chung cho CDS.
 * Ưu tiên instance.json; database.conf cũ vẫn là fallback tương thích.
 */

$instanceConfigHelper = __DIR__ . '/instance_config.php';
if (is_file($instanceConfigHelper)) require_once $instanceConfigHelper;
unset($instanceConfigHelper);

function cds_db_config_path()
{
    $custom = getenv('CDS_DB_CONFIG');
    if (is_string($custom) && $custom !== '') return $custom;
    return dirname(__DIR__, 2) . '/cds_private/database.conf';
}

function cds_db_read_config()
{
    static $config = null;
    if (is_array($config)) return $config;

    $instance = function_exists('cds_instance_config') ? cds_instance_config('database', []) : [];
    if (is_array($instance) && $instance) {
        $config = $instance;
    } else {
        $path = cds_db_config_path();
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Không tìm thấy cấu hình MySQL trong instance.json hoặc database.conf.');
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) throw new RuntimeException('Không đọc được tệp cấu hình MySQL.');
        $config = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || substr($line, 0, 1) === '#') continue;
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key !== '') $config[$key] = $value;
        }
    }

    $defaults = ['port'=>'3306','charset'=>'utf8mb4'];
    $config = array_merge($defaults, $config);
    $required = array('host', 'port', 'database', 'username', 'password', 'charset');
    foreach ($required as $key) {
        if (!array_key_exists($key, $config) || $config[$key] === '') {
            throw new RuntimeException('Cấu hình MySQL thiếu trường: ' . $key);
        }
    }
    if (!ctype_digit((string)$config['port'])) throw new RuntimeException('Cổng MySQL không hợp lệ.');
    return $config;
}

function cds_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('PHP chưa bật extension pdo_mysql.');

    $config = cds_db_read_config();
    $dsn = 'mysql:host=' . $config['host']
        . ';port=' . (int)$config['port']
        . ';dbname=' . $config['database']
        . ';charset=' . $config['charset'];

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            /* Tránh request chờ quá lâu khi MySQL trên hosting mất kết nối. */
            PDO::ATTR_TIMEOUT => 5,
        )
    );
    return $pdo;
}

function cds_db_status()
{
    $status = array(
        'config_path' => function_exists('cds_instance_config') && cds_instance_config('database', []) ? cds_instance_config_path() : cds_db_config_path(),
        'config_exists' => false,
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'connected' => false,
        'server_version' => '',
        'database' => '',
        'error' => '',
    );

    try {
        $status['config_exists'] = is_readable($status['config_path']);
        $config = cds_db_read_config();
        $status['database'] = $config['database'];
        $pdo = cds_db();
        $status['server_version'] = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $status['connected'] = true;
    } catch (Throwable $e) {
        $status['error'] = $e->getMessage();
    }
    return $status;
}
