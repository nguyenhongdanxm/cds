<?php
require_once 'includes/auth.php';
if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'admin.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (attempt_login($user, $pass)) {
        $next = $_GET['next'] ?? (BASE_URL . 'admin.php');
        if (strpos($next, '://') !== false) $next = BASE_URL . 'admin.php';
        header('Location: ' . $next);
        exit;
    }
    $error = 'Sai tài khoản hoặc mật khẩu.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập – CDS <?= e(SCHOOL_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
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
      <form method="post">
        <div class="mb-3">
          <label class="form-label fw-semibold">Tài khoản</label>
          <input type="text" name="username" class="form-control" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Mật khẩu</label>
          <input type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary w-100" style="background:#1F4E79;border:0">
          <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
        </button>
      </form>
      <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>" class="small text-muted">← Về trang hệ sinh thái</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
