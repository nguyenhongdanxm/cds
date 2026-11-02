<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/ioffice_vault.php';
try {
    if (($argv[1] ?? '') === 'init') cds_ioffice_vault_init();
    elseif (($argv[1] ?? '') !== 'check') throw new RuntimeException('Dùng: php bin/ioffice-vault.php init|check');
    $status = cds_ioffice_vault_status();
    echo $status['message'] . PHP_EOL;
    if ($status['ready']) echo $status['saved'] ? "Đã lưu tài khoản; chưa kiểm tra đăng nhập iOffice.\n" : "Chưa nhập tài khoản.\n";
    echo 'PHP cURL: ' . (extension_loaded('curl') ? 'Có' : 'Thiếu') . PHP_EOL;
    echo 'PHP proc_open: ' . (function_exists('proc_open') ? 'Có' : 'Bị tắt') . PHP_EOL;
    echo "Khả năng chạy Chromium/Playwright và Cron Jobs: cần nhà cung cấp hosting xác nhận.\n";
    exit($status['ready'] ? 0 : 1);
} catch (Throwable $e) { fwrite(STDERR, "Không khởi tạo được kho. Kiểm tra đường dẫn, quyền và PHP Sodium.\n"); exit(1); }
