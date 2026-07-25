<?php
/**
 * Cổng dữ liệu số (CDS) - Hệ sinh thái số nhà trường
 * Deploy: cds.noitruxinman.edu.vn
 */
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0755, true);
}

define('BASE_URL', '/');

define('SCHOOL_NAME', 'Trường PTDTNT THCS&THPT Xín Mần');
define('SCHOOL_SHORT', 'Xín Mần');
define('SCHOOL_SO', 'Sở GD&ĐT Tuyên Quang');
define('SCHOOL_YEAR', '2025–2026');

define('URL_TIN_TUC', 'https://noitruxinman.edu.vn');
define('URL_CHUYEN_MON', 'https://noitruxinman.edu.vn/pccm/');
define('URL_QLHS', 'https://noitruxinman.edu.vn/qlhs/');
define('URL_CSDL', '');

define('USERS_FILE', DATA_PATH . '/users.json');
define('SETTINGS_FILE', DATA_PATH . '/settings.json');

define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'Xinman@2021');

/* —— PCCM data path (file JSON cùng server) —— */
if (!defined('PCCM_DATA_PATH')) {
    $candidates = [
        dirname(BASE_PATH) . '/public_html/pccm/data',
        dirname(BASE_PATH) . '/noitruxinman.edu.vn/pccm/data',
        dirname(BASE_PATH) . '/public_html/pccm/pccm/data',
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

/*
 * —— QLHS / Supabase (đồng bộ học sinh · lớp) ——
 * service_role key: chỉ đặt trên host (bỏ qua RLS), KHÔNG commit public.
 * Trên GitHub giữ anon key; trên host thay bằng service_role.
 *
 * school_id: UUID trường Xín Mần trong bảng schools.
 */
if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://qrxvkbcuggeitgxbbgrd.supabase.co');
}
if (!defined('SUPABASE_KEY')) {
    // Mặc định: anon key (bị RLS chặn students).
    // Trên host: thay bằng service_role key từ Supabase → Settings → API.
    define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFyeHZrYmN1Z2dlaXRneGJiZ3JkIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Njg3NzUwNDYsImV4cCI6MjA4NDM1MTA0Nn0.pqKM_DeS0nUzbzgqGJfbjILAUrKOoaKbgeOESSktFTc');
}
if (!defined('QLHS_SCHOOL_ID')) {
    // PTDTNT THCS&THPT Xín Mần
    define('QLHS_SCHOOL_ID', '584c3e94-ff37-43fe-9185-86d3cc59b858');
}
