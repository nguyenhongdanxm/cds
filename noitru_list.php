<?php
/**
 * noitru_list.php — Danh sách học sinh nội trú
 * 4 tab: Học sinh | Lớp | Phòng | Mâm ăn
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';

require_login();
require_perm('nt.danhsach');

$page_title = 'Danh sách nội trú';
$tab = 'boarders';
$nt_sec = 'boarders';

// --- Load dữ liệu ---
if (function_exists('noitru_boarders_live')) {
    $boarders = noitru_boarders_live();
} elseif (function_exists('noitru_get_boarders_live')) {
    $boarders = noitru_get_boarders_live();
} else {
    $boarders = [];
}
$boarders = array_values($boarders);
$stats = function_exists('noitru_boarders_stats') ? noitru_boarders_stats($boarders) : [
    'total' => count($boarders), 'male' => 0, 'female' => 0, 'rooms' => 0, 'meals' => 0,
];

$view = $_GET['view'] ?? 'students';
$allowed_views = ['students', 'classes', 'rooms', 'meals'];
if (!in_array($view, $allowed_views, true)) $view = 'students';

$q     = trim($_GET['q'] ?? '');
$class = trim($_GET['class'] ?? '');
$room  = trim($_GET['room'] ?? '');
$meal  = trim($_GET['meal'] ?? '');

if (!function_exists('nt_list_url')) {
    function nt_list_url(array $p = []) {
        $b = [
            'view'  => $_GET['view'] ?? 'students',
            'q'     => $_GET['q'] ?? '',
            'class' => $_GET['class'] ?? '',
            'room'  => $_GET['room'] ?? '',
            'meal'  => $_GET['meal'] ?? '',
        ];
        $x = array_filter(array_merge($b, $p), fn($v) => $v !== null && $v !== '');
        return BASE_URL . 'noitru_list.php' . ($x ? ('?' . http_build_query($x)) : '');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'sync_boarders') {
    require_perm_level('nt.danhsach', 'edit');
    if (allowed_classes() !== null) {
        flash('Chỉ người có phạm vi toàn trường được đồng bộ danh sách.', 'danger');
        header('Location: ' . BASE_URL . 'noitru_list.php'); exit;
    }
    $r = function_exists('noitru_sync_from_csdl')
        ? noitru_sync_from_csdl()
        : ['ok' => false, 'message' => 'Chưa có hàm đồng bộ'];
    if (function_exists('flash')) {
        flash($r['message'] ?? ($r['ok'] ? 'Đồng bộ thành công' : 'Lỗi đồng bộ'), $r['ok'] ? 'success' : 'danger');
    }
    header('Location: ' . BASE_URL . 'noitru_list.php?' . http_build_query(array_filter([
        'view' => $view, 'q' => $q ?: null, 'class' => $class ?: null,
        'room' => $room ?: null, 'meal' => $meal ?: null,
    ])));
    exit;
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> – CDS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>includes/noitru_layout.css?v=20260731-4" rel="stylesheet">
</head>
<body class="nt-body">
<?php require __DIR__ . '/includes/noitru_shell.php'; ?>
<main class="nt-main"><div class="nt-content">
  <div class="nt-page-head">
    <div>
      <h4 class="mb-0 fw-bold" style="font-size:1.15rem"><i class="bi bi-people-fill" style="color:#d63384"></i> Danh sách học sinh nội trú</h4>
      <div class="text-muted small mt-1">
        Tổng: <strong><?= (int)($stats['total'] ?? count($boarders)) ?></strong>
        · Nam: <?= (int)($stats['male'] ?? 0) ?>
        · Nữ: <?= (int)($stats['female'] ?? 0) ?>
        · Phòng: <?= (int)($stats['rooms'] ?? 0) ?>
        · Mâm: <?= (int)($stats['meals'] ?? 0) ?>
      </div>
    </div>
    <?php if (can_edit_perm('nt.danhsach') && allowed_classes() === null): ?>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="sync_boarders">
      <button type="submit" class="btn btn-sm text-white" style="background:#d63384" onclick="return confirm('Đồng bộ danh sách nội trú từ CSDL?')">
        <i class="bi bi-arrow-repeat"></i> Đồng bộ từ CSDL
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php if (function_exists('show_flash')) show_flash(); ?>

  <?php
  if (is_file(__DIR__ . '/includes/noitru_tab_boarders.php')) {
      require __DIR__ . '/includes/noitru_tab_boarders.php';
  } else {
      echo '<div class="alert alert-warning">Thiếu includes/noitru_tab_boarders.php</div>';
  }
  ?>
</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
