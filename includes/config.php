<?php
/**
 * Cổng dữ liệu số (CDS) - Hệ sinh thái số nhà trường
 * Deploy: cds.noitruxinman.edu.vn (document root trỏ vào thư mục này)
 */
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0755, true);
}

// Nếu deploy ở thư mục con, sửa thành '/cds/' hoặc tương ứng
define('BASE_URL', '/');

define('SCHOOL_NAME', 'Trường PTDTNT THCS&THPT Xín Mần');
define('SCHOOL_SHORT', 'Xín Mần');
define('SCHOOL_SO', 'Sở GD&ĐT Tuyên Quang');
define('SCHOOL_YEAR', '2025–2026');

define('URL_TIN_TUC', 'https://noitruxinman.edu.vn');
define('URL_CHUYEN_MON', 'https://noitruxinman.edu.vn/pccm/');
define('URL_CSDL', '');

define('USERS_FILE', DATA_PATH . '/users.json');
define('SETTINGS_FILE', DATA_PATH . '/settings.json');

define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'Xinman@2021');

/**
 * Đường dẫn thư mục data của PCCM (cùng server).
 * Ưu tiên: hằng số tay → file settings → các đường dẫn thường gặp.
 * Sửa 1 dòng bên dưới nếu host khác cấu trúc thư mục.
 */
if (!defined('PCCM_DATA_PATH')) {
    // ← Chỉnh đúng path trên host nếu cần:
    // Ví dụ: '/home/capnachi/public_html/pccm/data'
    $candidates = [
        // Cùng account, subdomain/addon khác document root
        dirname(BASE_PATH) . '/public_html/pccm/data',
        dirname(BASE_PATH) . '/noitruxinman.edu.vn/pccm/data',
        dirname(BASE_PATH) . '/public_html/pccm/pccm/data',
        '/home/capnachi/public_html/pccm/data',
        '/home/capnachi/noitruxinman.edu.vn/pccm/data',
        // Cùng server, symlink
        BASE_PATH . '/../pccm/data',
    ];
    $resolved = '';
    foreach ($candidates as $p) {
        if (is_dir($p)) { $resolved = $p; break; }
    }
    define('PCCM_DATA_PATH', $resolved);
}
