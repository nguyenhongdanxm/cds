<?php
/** Danh sách nội trú – 4 view: students, classes, rooms, meals */
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync_from_csdl') {
    $r = noitru_sync_from_csdl();
    flash($r['message'], $r['ok'] ? 'success' : 'danger');
    header('Location: ' . BASE_URL . 'noitru_list.php?' . http_build_query(array_filter([
        'view' => $_GET['view'] ?? 'students',
        'class' => $_GET['class'] ?? null,
        'room' => $_GET['room'] ?? null,
        'meal' => $_GET['meal'] ?? null,
    ])));
    exit;
}

$boarders = noitru_boarders_live();
$stats = noitru_stats();

$tabs = [
    'overview' => ['Tổng quan', 'bi-grid', BASE_URL . 'noitru.php?tab=overview'],
    'boarders' => ['Danh sách', 'bi-people', BASE_URL . 'noitru_list.php'],
    'exits' => ['Xin ra/vào KTX', 'bi-door-open', BASE_URL . 'noitru.php?tab=exits'],
    'meals' => ['Báo ăn', 'bi-egg-fried', BASE_URL . 'noitru.php?tab=meals'],
    'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru.php?tab=attendance'],
    'duty' => ['Lịch trực', 'bi-calendar2-week', BASE_URL . 'noitru.php?tab=duty'],
    'health' => ['Y tế', 'bi-heart-pulse', BASE_URL . 'noitru.php?tab=health'],
    'menu' => ['Thực đơn', 'bi-journal-text', BASE_URL . 'noitru.php?tab=menu'],
    'stats' => ['Thống kê', 'bi-bar-chart', BASE_URL . 'noitru.php?tab=stats'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Danh sách nội trú – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#d63384;--pd:#a61e5c}
body{background:#f8f0f4}
.navbar{background:var(--pd)!important}
.nav-pills .nav-link{border-radius:999px;font-weight:600;color:#445;font-size:.85rem}
.nav-pills .nav-link.active{background:var(--primary);color:#fff}
.nav-tabs .nav-link{color:#a61e5c;font-weight:600}
.nav-tabs .nav-link.active{color:#fff;background:var(--primary);border-color:var(--primary)}
.card-soft{background:#fff;border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.table thead th{font-size:.7rem;text-transform:uppercase;color:#667;background:#fce8f0;white-space:nowrap}
.btn-nt{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-nt:hover{background:var(--pd);color:#fff}
.badge-room{background:#fce8f0;color:#a61e5c}
.badge-meal{background:#e8f5e9;color:#2e7d32}
a .card-soft:hover{box-shadow:0 4px 16px rgba(214,51,132,.2);transform:translateY(-2px);transition:.15s}
</style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>noitru.php"><i class="bi bi-building"></i> Quản lý nội trú</a>
    <div class="d-flex gap-2">
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
    <h3 class="mb-0">Danh sách nội trú</h3>
    <div class="text-muted small">Nguồn HS: <strong>CSDL</strong> · <?= e(SCHOOL_NAME) ?></div>
  </div>
  <form method="post" class="m-0">
    <input type="hidden" name="action" value="sync_from_csdl">
    <button class="btn btn-nt btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Đồng bộ từ CSDL</button>
  </form>
</div>

<ul class="nav nav-pills gap-1 mb-4 flex-wrap">
  <?php foreach ($tabs as $k => $info): ?>
  <li class="nav-item">
    <a class="nav-link <?= $k === 'boarders' ? 'active' : '' ?>" href="<?= e($info[2]) ?>">
      <i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php
// noitru_tab_boarders.php expects $boarders and builds links with ?tab=boarders&
// Override base links: rewrite via output buffer? Simpler: set $_GET['tab']=boarders for include
$_GET['tab'] = 'boarders';
ob_start();
require __DIR__ . '/includes/noitru_tab_boarders.php';
$html = ob_get_clean();
// Đổi link nội bộ cho khớp noitru_list.php
$html = str_replace('?tab=boarders&', '?', $html);
$html = str_replace('?tab=boarders', '?', $html);
echo $html;
?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
