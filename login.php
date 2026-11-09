<?php
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
define('CDS_SKIP_DRIVE_ACTION_REGISTRATION', true);
define('CDS_AUTH_LOGIN_ONLY', true);
$loginStarted=hrtime(true);
require_once __DIR__.'/includes/auth.php';
function cds_login_home_url(): string {
    if (function_exists('cds_instance_config')
        && (current_user()['role'] ?? '') === 'admin'
        && cds_instance_config('deployment.profile', '') === 'school'
        && trim((string)cds_instance_config('database.database', '')) === '') return BASE_URL . 'instance_settings.php';
    return BASE_URL . 'admin.php';
}
if (is_logged_in()) {
    session_write_close();
    header('Location: ' . cds_login_home_url());
    exit;
}
$error = '';
$user = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = is_string($_POST['username']??null)?trim($_POST['username']):'';
    $pass = is_string($_POST['password']??null)?$_POST['password']:'';
    if (attempt_login($user, $pass)) {
        $home = cds_login_home_url();
        $next = $home === BASE_URL . 'instance_settings.php' ? $home : ($_GET['next'] ?? $home);
        if (!str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '://')) $next = BASE_URL . 'admin.php';
        header('Server-Timing: login;dur='.round((hrtime(true)-$loginStarted)/1e6,1));
        session_write_close();
        header('Location: ' . $next);
        exit;
    }
    $error = $GLOBALS['cds_login_error']??'Sai tài khoản hoặc mật khẩu.';
    header('Server-Timing: login;dur='.round((hrtime(true)-$loginStarted)/1e6,1));
}
// Release the session before rendering so parallel login/asset requests need not wait.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0f4c81">
<link rel="manifest" href="<?= e(BASE_URL) ?>manifest.php">
<link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>assets/icons/cds-192.png">
<title>Đăng nhập – CDS <?= e(SCHOOL_SHORT) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#182a42}.container{width:100%;padding:24px}.card{background:#fff;overflow:hidden}.mx-auto{margin-inline:auto}.text-center{text-align:center}.py-3{padding-block:16px}.p-4{padding:24px}.small,.form-text{font-size:.875rem}.opacity-75{opacity:.75}.mb-3{margin-bottom:16px}.mt-3{margin-top:16px}.fw-semibold{font-weight:600}.form-label{display:block;margin-bottom:6px}.form-control{display:block;width:100%;padding:11px 12px;border:1px solid #b8c5d4;border-radius:8px;font:inherit}.form-control:focus{outline:2px solid #78b0de;outline-offset:1px}.form-check{padding-left:24px}.form-check-input{float:left;margin:5px 0 0 -24px;width:16px;height:16px}.form-text,.text-muted{color:#596b7f}.form-text{margin-top:5px}.btn{cursor:pointer;color:white;padding:11px 16px;border-radius:8px;font:inherit;font-weight:600}.btn:disabled{opacity:.7;cursor:wait}.w-100{width:100%}.alert{padding:10px 12px;border-radius:8px;margin-bottom:16px}.alert-danger{background:#fff0f0;color:#8b2323;border:1px solid #efb5b5}a{color:#245e8c}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
background:radial-gradient(ellipse at center,#124a8a 0%,#0a2a5c 60%,#061428 100%);}
.card{border:none;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.3);max-width:400px;width:100%}
.card-header{background:#1F4E79;color:#fff;border-radius:16px 16px 0 0!important;font-weight:700}
</style>
</head>
<body>
<div class="container">
  <div class="card mx-auto">
    <div class="card-header text-center py-3">
      <div class="small opacity-75">Cổng dữ liệu số</div>
      <div><?= e(SCHOOL_NAME) ?></div>
    </div>
    <div class="card-body p-4">
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <form method="post" id="loginForm" autocomplete="on">
        <div class="mb-3">
          <label class="form-label fw-semibold">Tài khoản</label>
          <input type="text" name="username" class="form-control" value="<?= e($user) ?>" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Mật khẩu</label>
          <input type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="rememberLogin">
          <label class="form-check-label fw-semibold" for="rememberLogin">Ghi nhớ tài khoản và mật khẩu</label>
          <div class="form-text">Mật khẩu được lưu an toàn bởi trình duyệt. Không chọn trên máy dùng chung.</div>
        </div>
        <button type="submit" id="loginSubmit" class="btn btn-primary w-100" style="background:#1F4E79;border:0">
          Đăng nhập
        </button>
      </form>
      <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>" class="small text-muted">← Về trang hệ sinh thái</a>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const form = document.getElementById('loginForm');
  const username = form.querySelector('input[name="username"]');
  const remember = document.getElementById('rememberLogin');
  const storageKey = 'cds_remember_username';

  try {
    const savedUsername = localStorage.getItem(storageKey);
    if (savedUsername) {
      if (!username.value) username.value = savedUsername;
      remember.checked = true;
    }
  } catch (error) {
    // Trình duyệt có thể chặn bộ nhớ cục bộ; đăng nhập vẫn hoạt động bình thường.
  }

  let submitting=false;
  form.addEventListener('submit', function (event) {
    if(submitting){event.preventDefault();return;}
    submitting=true;const button=document.getElementById('loginSubmit');button.disabled=true;button.textContent='Đang đăng nhập…';
    form.setAttribute('aria-busy','true');
    try {
      if (remember.checked) localStorage.setItem(storageKey, username.value.trim());
      else localStorage.removeItem(storageKey);
    } catch (error) {
      // Không ảnh hưởng tới quá trình gửi biểu mẫu đăng nhập.
    }
  });
  window.addEventListener('pageshow',()=>{submitting=false;const button=document.getElementById('loginSubmit');button.disabled=false;button.textContent='Đăng nhập';form.removeAttribute('aria-busy');});
})();
</script>
</body>
</html>
