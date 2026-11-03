<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
if (($user['role'] ?? '') !== 'admin') { http_response_code(403); exit('Chỉ quản trị viên được quản lý tài khoản iOffice.'); }
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
// Do not trust client-supplied X-Forwarded-Proto. Configure HTTPS at the origin.
$https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
if (!$https) { http_response_code(400); exit('Trang lưu tài khoản chỉ hoạt động qua HTTPS tại máy chủ.'); }
require_once __DIR__ . '/includes/ioffice_vault.php';
if (empty($_SESSION['ioffice_csrf'])) $_SESSION['ioffice_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['ioffice_csrf'], $_POST['csrf'])) {
            throw new RuntimeException('Phiên làm việc không hợp lệ. Hãy tải lại trang.');
        }
        if (($_POST['consent'] ?? '') !== '1') throw new RuntimeException('Cần xác nhận cho phép lưu tài khoản.');
        if (!is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null)) {
            throw new RuntimeException('Hãy nhập tên truy cập và mật khẩu.');
        }
        cds_ioffice_vault_save($_POST['username'], $_POST['password']);
        flash('Đã lưu tài khoản mã hóa. Chưa thực hiện đăng nhập hoặc đồng bộ iOffice.', 'success');
        $_SESSION['ioffice_csrf'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Không lưu được tài khoản. Kiểm tra cấu hình kho.', 'danger');
    } finally {
        if (function_exists('sodium_memzero') && isset($_POST['password']) && is_string($_POST['password'])) sodium_memzero($_POST['password']);
        unset($_POST['password']);
    }
    header('Location: ' . BASE_URL . 'ioffice_settings.php'); exit;
}
$status = cds_ioffice_vault_status();
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tài khoản iOffice – CDS</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:#182a42;font:16px/1.6 system-ui,sans-serif}main{max-width:850px;margin:auto;padding:24px}header{background:linear-gradient(120deg,#273d71,#176c85);color:white;padding:24px;border-radius:20px}h1{margin:0;font-size:26px}h2{font-size:19px}section{background:white;padding:24px;margin-top:18px;border:1px solid #dce4ef;border-radius:16px}a{color:#255a93}header a{color:white}label{display:block;font-weight:600;margin-top:16px}input:not([type=checkbox]){width:100%;font:inherit;padding:12px;border:1px solid #aab9cb;border-radius:8px}button{background:#215b91;color:white;font:inherit;border:0;border-radius:9px;padding:12px 20px;margin-top:20px}button:disabled{opacity:.5}small{color:#52647b}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #e4eaf2}.alert{padding:12px;border:1px solid #9bb1cb;border-radius:8px;margin-top:16px}.alert-danger{background:#fff1f1}.alert-success{background:#ecfff4}code{overflow-wrap:anywhere}input[type=checkbox]{width:20px;height:20px;vertical-align:middle}
</style></head><body><main><header><a href="vanban.php">← Văn bản</a><h1>Tài khoản VNPT iOffice</h1><p>Lưu tài khoản trên hosting CDS để chuẩn bị kết nối đọc văn bản.</p></header>
<?php show_flash(); ?>
<section><h2>Trạng thái</h2><table><tr><th>Kho tài khoản</th><td><?= e($status['message']) ?></td></tr><tr><th>Tài khoản</th><td><?= $status['saved'] ? 'Đã lưu mã hóa' : 'Chưa xác nhận có tài khoản sử dụng được' ?></td></tr><tr><th>Tự đăng nhập và cập nhật văn bản</th><td>Chưa triển khai bộ thu thập</td></tr></table><p><small>Chỉ lưu tài khoản chưa kích hoạt lịch cập nhật. CAPTCHA hoặc OTP vẫn cần người dùng xác nhận khi iOffice yêu cầu.</small></p></section>
<section><h2><?= $status['saved'] ? 'Thay tài khoản đã lưu' : 'Lưu tài khoản' ?></h2><p>Dịch vụ: <strong>vpdttq.vnptioffice.vn</strong>. Tài khoản dùng cho bộ thu thập đọc văn bản của CDS. Mật khẩu đã lưu không được hiển thị lại.</p>
<form method="post" action="ioffice_settings.php" autocomplete="off"><input type="hidden" name="csrf" value="<?= e($_SESSION['ioffice_csrf']) ?>"><fieldset <?= $status['ready'] ? '' : 'disabled' ?> style="border:0;padding:0;margin:0"><label for="username">Tên truy cập iOffice</label><input id="username" name="username" autocomplete="off" maxlength="250" required><label for="password">Mật khẩu iOffice</label><input type="password" id="password" name="password" autocomplete="new-password" maxlength="4096" required><label><input type="checkbox" name="consent" value="1" required> Cho phép lưu tài khoản mã hóa trên hosting CDS để bộ thu thập đăng nhập và đọc văn bản.</label><button type="submit">Lưu tài khoản bảo mật</button></fieldset></form>
<?php if (!$status['ready']): ?><p>Quản trị hosting cần khởi tạo kho bằng Terminal cPanel theo tài liệu <code>docs/ioffice-cpanel.md</code>, sau đó tải lại trang.</p><?php endif; ?></section>
<section><h2>Khả năng của hosting</h2><ul><li>PHP Sodium: <?= function_exists('sodium_crypto_secretbox') ? 'Có' : 'Cần bật' ?></li><li>PHP cURL: <?= extension_loaded('curl') ? 'Có' : 'Cần bật' ?></li><li>Khởi chạy tiến trình PHP: <?= function_exists('proc_open') ? 'Có hàm proc_open; chưa xác minh quyền chạy' : 'proc_open bị tắt' ?></li><li>Chromium/Playwright và Cron Jobs: cần kiểm tra với nhà cung cấp hosting.</li></ul></section>
</main></body></html>
