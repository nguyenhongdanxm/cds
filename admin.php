<?php
require_once 'includes/auth.php';
require_once 'includes/modules.php';
require_login();
$user = current_user();
$modules = get_ecosystem_modules();
$live = count(array_filter($modules, fn($m) => $m['status'] !== 'soon'));
$soon = count($modules) - $live;
$isAdmin = ($user['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản trị CDS – <?= e(SCHOOL_SHORT) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#1F4E79}
body{background:#f0f4f8}
.stat{background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center}
.stat .n{font-size:1.75rem;font-weight:800;color:var(--primary)}
.mod-card{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 2px 10px rgba(0,0,0,.06);height:100%;border-left:4px solid #ccc}
.mod-card.live{border-left-color:#198754}
.mod-card.link{border-left-color:#0d6efd}
.mod-card.soon{border-left-color:#adb5bd;opacity:.85}
</style>
</head>
<body>
<?php
$nav_title = 'CDS Quản trị';
$nav_icon = 'bi-speedometer2';
$nav_color = '#1F4E79';
$nav_module = 'admin';
if (is_file(__DIR__ . '/includes/nav_top.php')) include __DIR__ . '/includes/nav_top.php';
?>

<div class="container pb-5">
  <?php show_flash(); ?>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h3 class="mb-1">Bảng điều khiển tập trung</h3>
      <p class="text-muted mb-0">Xin chào, <strong><?= e($user['name'] ?? '') ?></strong> · vai trò: <?= e($user['role'] ?? '') ?></p>
    </div>
    <?php if ($isAdmin): ?>
    <div class="d-flex flex-wrap gap-2">
      <a href="database_admin.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-database-check"></i> Trạng thái MySQL</a>
      <a href="users.php" class="btn btn-primary btn-sm"><i class="bi bi-shield-lock"></i> Tài khoản & phân quyền</a>
    </div>
    <?php endif; ?>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count($modules) ?></div><div class="text-muted small">Tổng module</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-success"><?= $live ?></div><div class="text-muted small">Đang có / liên kết</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-secondary"><?= $soon ?></div><div class="text-muted small">Đang xây dựng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><i class="bi bi-person-check"></i></div><div class="text-muted small">SSO session</div></div></div>
  </div>

  <h5 class="mb-3">Các nhánh hệ sinh thái</h5>
  <div class="row g-3">
    <?php foreach ($modules as $m):
      $modId = $m['id'] ?? '';
      // map id sang key quyền
      $permKey = $modId === 'chuyenmon' ? 'chuyenmon' : ($modId === 'csdl' ? 'csdl' : ($modId === 'noitru' ? 'noitru' : $modId));
      $allowed = ($user['role'] ?? '') === 'admin' || can_module($permKey, 'view') || $m['status'] === 'link' || $m['status'] === 'soon';
      if (!$allowed && in_array($modId, ['chuyenmon','csdl','noitru'], true)) continue;
    ?>
    <div class="col-md-6 col-lg-3">
      <div class="mod-card <?= e($m['status']) ?>">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi <?= e($m['icon']) ?> fs-4" style="color:<?= e($m['color']) ?>"></i>
          <strong><?= e($m['title']) ?></strong>
        </div>
        <div class="small text-muted mb-2"><?= e($m['subtitle']) ?></div>
        <?php if ($m['status'] === 'soon'): ?>
          <span class="badge bg-secondary">Sắp ra mắt</span>
        <?php else: ?>
          <a href="<?= e($m['url']) ?>" class="btn btn-sm btn-outline-primary" <?= !empty($m['external']) ? 'target="_blank"' : '' ?>>
            Mở <i class="bi bi-arrow-right-short"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
