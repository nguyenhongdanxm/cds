<?php
// Tệp này được giữ đồng nhất ở nguồn chung và bản chạy trong Chuyên môn.
$cdsSwitcherRoot = basename(dirname(__DIR__)) === 'chuyenmon' ? dirname(__DIR__, 2) : dirname(__DIR__);
require_once $cdsSwitcherRoot . '/includes/module_switcher.php';

// Lối tắt riêng trong trang Thời khóa biểu: tra cứu số tiết giáo viên theo ngày.
$cdsCurrentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($cdsCurrentScript === 'thoikhoabieu.php') {
    $dailyLoadUrl = (defined('BASE_URL') ? BASE_URL : '/') . 'tkb_taigiang.php';
    echo '<style>.tkb-daily-load-shortcut{position:fixed;right:18px;bottom:82px;z-index:1035;border-radius:999px;box-shadow:0 5px 18px rgba(31,78,121,.24);font-weight:700}@media(max-width:576px){.tkb-daily-load-shortcut{right:10px;bottom:68px;font-size:.78rem;padding:.42rem .65rem}}</style>';
    echo '<a class="btn btn-warning tkb-daily-load-shortcut" href="' . htmlspecialchars($dailyLoadUrl, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-person-lines-fill"></i> Tải giảng theo ngày</a>';
}
