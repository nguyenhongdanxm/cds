<?php
require_once 'includes/auth.php';
require_once 'includes/modules.php';
require_login();
$user = current_user();
$modules = get_ecosystem_modules();
$live = count(array_filter($modules, fn($m) => $m['status'] !== 'soon'));
$soon = count($modules) - $live;
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
.navbar{background:var(--primary)!important}
.stat{background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center}
.stat .n{font-size:1.75rem;font-weight:800;color:var(--primary)}
.mod-card{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 2px 10px rgba(0,0,0,.06);height:100%;border-left:4px solid #ccc}
.mod-card.live{border-left-color:#198754}
.mod-card.link{border-left-color:#0d6efd}
.mod-card.soon{border-left-color:#adb5bd;opacity:.85}
</style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>"><i class="bi bi-hexagon-fill"></i> CDS Quản trị</a>
    <div class="d-flex align-items-center gap-3 text-white">
      <span class="small opacity-75"><?= e($user['name'] ?? '') ?> (<?= e($user['role'] ?? '') ?>)</span>
      <a href="<?= BASE_URL ?>" class="btn btn-outline-light btn-sm">Hệ sinh thái</a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark">Thoát</a>
    </div>
  </div>
</nav>

<div class="container pb-5">
  <?php show_flash(); ?>
  <h3 class="mb-1">Bảng điều khiển tập trung</h3>
  <p class="text-muted mb-4">Một tài khoản · Theo dõi các nhánh hệ sinh thái · Thống kê mở rộng khi thêm module</p>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count($modules) ?></div><div class="text-muted small">Tổng module</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-success"><?= $live ?></div><div class="text-muted small">Đang có / liên kết</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-secondary"><?= $soon ?></div><div class="text-muted small">Đang xây dựng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><i class="bi bi-person-check"></i></div><div class="text-muted small">SSO sẵn sàng</div></div></div>
  </div>

  <h5 class="mb-3">Các nhánh hệ sinh thái</h5>
  <div class="row g-3">
    <?php foreach ($modules as $m): ?>
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

  <div class="alert alert-info mt-4">
    <strong>Bước tiếp theo:</strong>
    <ul class="mb-0 mt-1">
      <li>Gắn đăng nhập CDS với PCCM (cùng session domain)</li>
      <li>Module Văn bản, Tuyên truyền, Nội trú, Thi đua lần lượt</li>
      <li>Đưa danh sách GV–lớp lên “Cơ sở dữ liệu dùng chung”</li>
    </ul>
  </div>
</div>
</body>
</html>
