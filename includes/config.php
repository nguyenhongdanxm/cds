<?php
/**
 * Cổng dữ liệu số (CDS) - Hệ sinh thái số nhà trường.
 * Thông tin nhận diện trường được quản lý tập trung tại school_config.php.
 */
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0755, true);
}

require_once __DIR__ . '/school_config.php';

define('BASE_URL', '/');

define('SCHOOL_NAME', school_name());
define('SCHOOL_SHORT', school_short_name());
define('SCHOOL_SO', school_department());
define('SCHOOL_YEAR', school_year());
define('SCHOOL_CODE', school_code());
define('SCHOOL_ADDRESS', school_address());
define('SCHOOL_PHONE', school_phone());
define('SCHOOL_EMAIL', school_email());
define('SCHOOL_LOGO', school_logo());
define('CDS_TITLE', school_cds_title());
define('CDS_SHORT_TITLE', school_cds_short_title());
define('CDS_DESCRIPTION', school_description());

define('URL_TIN_TUC', school_website());
/* Chuyên môn (PCCM) – module thư mục trên cùng domain CDS */
define('URL_CHUYEN_MON', BASE_URL . 'chuyenmon/');
define('URL_CSDL', BASE_URL . 'csdl.php');
define('URL_NOITRU', BASE_URL . 'noitru.php');

define('USERS_FILE', DATA_PATH . '/users.json');
define('SETTINGS_FILE', DATA_PATH . '/settings.json');

define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'Xinman@2021');

/* Data PCCM khi đã copy vào CDS/chuyenmon/data */
if (!defined('PCCM_DATA_PATH')) {
    $candidates = [
        BASE_PATH . '/chuyenmon/data',
        dirname(BASE_PATH) . '/public_html/pccm/data',
        dirname(BASE_PATH) . '/noitruxinman.edu.vn/pccm/data',
        '/home/capnachi/public_html/pccm/data',
        '/home/capnachi/noitruxinman.edu.vn/pccm/data',
        BASE_PATH . '/../pccm/data',
    ];
    $resolved = '';
    foreach ($candidates as $p) {
        if (is_dir($p)) { $resolved = $p; break; }
    }
    define('PCCM_DATA_PATH', $resolved);
}
