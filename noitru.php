<?php
/**
 * Quản lý nội trú – link riêng trong hệ sinh thái CDS.
 * Nguồn HS: CSDL (đồng bộ 1 chiều).
 */
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_login();

$tab = $_GET['tab'] ?? 'overview';
$allowed = ['overview', 'boarders', 'exits', 'meals', 'attendance', 'duty', 'health', 'menu', 'stats'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';

$soon = ['exits', 'meals', 'attendance', 'duty', 'health', 'menu', 'stats'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'sync_from_csdl') {
        $r = noitru_sync_from_csdl();
        flash($r['message'], $r['ok'] ? 'success' : 'danger');
        header('Location: ' . BASE_URL . 'noitru.php?tab=' . urlencode($tab));
        exit;
    }
}

$stats = noitru_stats();
$boarders = noitru_boarders_live();
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $qq = mb_strtolower($q, 'UTF-8');
    $boarders = array_values(array_filter($boarders, function ($s) use ($qq) {
        $blob = mb_strtolower(implode(' ', [
            $s['name'], $s['code'], $s['cccd'], $s['class_name'],
            $s['room_ktx'], $s['meal_group'], $s['parent_name'], $s['phone'],
        ]), 'UTF-8');
        return mb_strpos($blob, $qq) !== false;
    }));
}

$tabs = [
    'overview' => ['Tổng quan', 'bi-grid'],
    'boarders' => ['Danh sách nội trú', 'bi-people'],
    'exits' => ['Xin ra / vào KTX', 'bi-door-open'],
    'meals' => ['Báo ăn hàng ngày', 'bi-egg-fried'],
    'attendance' => ['Điểm danh', 'bi-clipboard-check'],
    'duty' => ['Lịch trực', 'bi-calendar2-week'],
    'health' => ['Sức khỏe / y tế', 'bi-heart-pulse'],
    'menu' => ['Thực đơn & nhóm ăn', 'bi-journal-text'],
    'stats' => ['Thống kê', 'bi-bar-chart'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản lý nội trú – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#d63384;--primary-dark:#a61e5c}
body{background:#f8f0f4}
.navbar{background:var(--primary-dark)!important}
.stat{background:#fff;border-radius:12px;padding:1.1rem;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center}
.stat .n{font-size:1.6rem;font-weight:800;color:var(--primary)}
.nav-pills .nav-link{border-radius:999px;font-weight:600;color:#445;font-size:.9rem}
.nav-pills .nav-link.active{background:var(--primary)}
.nav-pills .nav-link.soon{opacity:.55}
.card-soft{background:#fff;border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.table thead th{font-size:.72rem;text-transform:uppercase;letter-spacing:.02em;color:#667;background:#fce8f0;white-space:nowrap}
.badge-room{background:#fce8f0;color:#a61e5c;font-weight:600}
.badge-meal{background:#e8f5e9;color:#2e7d32;font-weight:600}
</style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>noitru.php">
      <i class="bi bi-building"></i> Quản lý nội trú
    </a>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>" class="btn btn-outline-light btn-sm">Hệ sinh thái</a>
      <a href="<?= BASE_URL ?>csdl.php" class="btn btn-outline-light btn-sm">CSDL</a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark">Thoát</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-3 px-lg-4 pb-5">
  <?php show_flash(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h3 class="mb-0">Quản lý nội trú</h3>
      <div class="text-muted small">
        HS nội trú lấy từ <strong>CSDL</strong> (đồng bộ một chiều) ·
        <?= SCHOOL_NAME ?>
      </div>
    </div>
    <form method="post" class="m-0">
      <input type="hidden" name="action" value="sync_from_csdl">
      <button type="submit" class="btn btn-primary" style="background:var(--primary);border-color:var(--primary)">
        <i class="bi bi-arrow-repeat"></i> Đồng bộ từ CSDL
      </button>
    </form>
  </div>

  <ul class="nav nav-pills gap-1 mb-4 flex-wrap">
    <?php foreach ($tabs as $key => $info):
      $isSoon = in_array($key, $soon, true);
      $href = $isSoon ? '#' : '?tab=' . urlencode($key);
      $cls = ($tab === $key ? 'active' : '') . ($isSoon ? ' soon' : '');
    ?>
      <li class="nav-item">
        <a class="nav-link <?= $cls ?>" href="<?= $href ?>" <?= $isSoon ? 'title="Sắp triển khai" onclick="return false"' : '' ?>>
          <i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
          <?php if ($isSoon): ?><span class="badge bg-secondary ms-1" style="font-size:.65rem">Sau</span><?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

<?php if ($tab === 'overview'): ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat"><div class="n"><?= (int)$stats['total'] ?></div><div class="text-muted small">HS nội trú</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat"><div class="n" style="font-size:1rem;padding-top:.35rem">
        <?php
          $ts = $stats['last_sync_at'] ?? null;
          echo $ts ? e(date('d/m/Y H:i', strtotime($ts))) : 'Chưa đồng bộ';
        ?>
      </div><div class="text-muted small">Lần đồng bộ CSDL</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat"><div class="n"><?= count($stats['by_room']) ?></div><div class="text-muted small">Phòng (đang ghi)</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat"><div class="n"><?= count($stats['by_meal']) ?></div><div class="text-muted small">Nhóm ăn</div></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="mb-3">Theo lớp</h6>
        <?php if (!$stats['by_class']): ?>
          <p class="text-muted small mb-0">Chưa có HS nội trú trong CSDL. Đánh dấu «Nội trú» ở CSDL rồi bấm Đồng bộ.</p>
        <?php else: foreach ($stats['by_class'] as $k => $n): ?>
          <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= e($k) ?></span><strong><?= (int)$n ?></strong></div>
        <?php endforeach; endif; ?>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="mb-3">Theo phòng KTX</h6>
        <?php if (!$stats['by_room']): ?>
          <p class="text-muted small mb-0">—</p>
        <?php else: foreach ($stats['by_room'] as $k => $n): ?>
          <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= e($k) ?></span><strong><?= (int)$n ?></strong></div>
        <?php endforeach; endif; ?>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card card-soft h-100"><div class="card-body">
        <h6 class="mb-3">Theo nhóm ăn</h6>
        <?php if (!$stats['by_meal']): ?>
          <p class="text-muted small mb-0">—</p>
        <?php else: foreach ($stats['by_meal'] as $k => $n): ?>
          <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= e($k) ?></span><strong><?= (int)$n ?></strong></div>
        <?php endforeach; endif; ?>
      </div></div>
    </div>
  </div>

  <div class="card card-soft"><div class="card-body">
    <h5 class="mb-2">Lộ trình chức năng</h5>
    <p class="small text-muted mb-2">Đã mở: Tổng quan · Danh sách nội trú. Các mục còn lại triển khai lần lượt.</p>
    <ol class="mb-0 small">
      <li><strong>Xin ra / vào KTX</strong> — phiếu + duyệt</li>
      <li><strong>Báo ăn hàng ngày</strong> — suất theo bữa, chốt số liệu</li>
      <li><strong>Điểm danh</strong> · <strong>Lịch trực</strong> · <strong>Y tế</strong> · <strong>Thực đơn</strong> · <strong>Thống kê</strong></li>
    </ol>
  </div></div>

<?php elseif ($tab === 'boarders'): ?>
  <div class="card card-soft mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="boarders">
      <div class="col-md-8">
        <label class="form-label small mb-1">Tìm kiếm</label>
        <input type="search" name="q" class="form-control" placeholder="Họ tên, mã, lớp, phòng, nhóm ăn, SĐT…" value="<?= e($q) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit" style="background:var(--primary);border:none">Tìm</button>
      </div>
      <div class="col-md-2">
        <a href="?tab=boarders" class="btn btn-outline-secondary w-100">Xóa</a>
      </div>
    </form>
  </div></div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Danh sách nội trú (<?= count($boarders) ?>)</h5>
    <a href="<?= BASE_URL ?>csdl.php?tab=students" class="btn btn-sm btn-outline-secondary">Sửa HS trên CSDL</a>
  </div>

  <div class="card card-soft"><div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>STT</th><th>Họ tên</th><th>Mã</th><th>Lớp</th><th>GT</th>
            <th>Phòng KTX</th><th>Nhóm ăn</th><th>PH / SĐT</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$boarders): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              Chưa có học sinh nội trú.<br>
              Vào <a href="<?= BASE_URL ?>csdl.php?tab=students">CSDL → Học sinh</a>,
              tick <strong>Nội trú</strong>, điền phòng/nhóm ăn, rồi bấm <strong>Đồng bộ từ CSDL</strong>.
            </td>
          </tr>
        <?php else: foreach ($boarders as $i => $s): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= e($s['name']) ?></strong></td>
            <td class="small"><?= e($s['code']) ?></td>
            <td><?= e($s['class_name']) ?></td>
            <td><?= e($s['gender']) ?></td>
            <td><?php if ($s['room_ktx'] !== ''): ?><span class="badge badge-room"><?= e($s['room_ktx']) ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?php if ($s['meal_group'] !== ''): ?><span class="badge badge-meal"><?= e($s['meal_group']) ?></span><?php else: ?>—<?php endif; ?></td>
            <td class="small">
              <?= e($s['parent_name']) ?>
              <?php if ($s['parent_phone'] !== ''): ?><br><span class="text-muted"><?= e($s['parent_phone']) ?></span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div></div>

<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
