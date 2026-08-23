<?php
/**
 * noitru_list.php — Danh sách học sinh nội trú
 * 4 tab: Học sinh | Lớp | Phòng | Mâm ăn
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_once __DIR__ . '/includes/noitru_assignment_store.php';

require_login();
require_perm('nt.danhsach');

$page_title = 'Danh sách nội trú';
$tab = 'boarders';
$nt_sec = 'boarders';

if (function_exists('noitru_boarders_live')) {
    $boarders = noitru_boarders_live();
} elseif (function_exists('noitru_get_boarders_live')) {
    $boarders = noitru_get_boarders_live();
} else {
    $boarders = [];
}
$boarders = noitru_assignment_apply(array_values($boarders));
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

        /*
         * Khi người dùng bấm tab / “Tất cả lớp-phòng-mâm”, không được giữ lại
         * bộ lọc chi tiết của màn hình trước. Chỉ giữ khóa chi tiết được truyền
         * rõ ràng trong chính URL mới.
         */
        if (array_key_exists('view', $p)) {
            $target = (string)$p['view'];
            $b['q'] = '';
            $b['class'] = '';
            $b['room'] = '';
            $b['meal'] = '';
            if ($target === 'students') {
                foreach (['q','class','room','meal'] as $key) {
                    if (array_key_exists($key, $p)) $b[$key] = (string)($p[$key] ?? '');
                }
            } elseif ($target === 'classes' && array_key_exists('class', $p)) {
                $b['class'] = (string)($p['class'] ?? '');
            } elseif ($target === 'rooms' && array_key_exists('room', $p)) {
                $b['room'] = (string)($p['room'] ?? '');
            } elseif ($target === 'meals' && array_key_exists('meal', $p)) {
                $b['meal'] = (string)($p['meal'] ?? '');
            }
        }

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
    <div class="d-flex flex-wrap gap-2 justify-content-end">
      <?php if ($view === 'rooms' && can_edit_perm('nt.chiaphong')): ?><a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>noitru_assign.php?mode=rooms"><i class="bi bi-door-open"></i> Chia phòng</a><?php endif; ?>
      <?php if ($view === 'meals' && can_edit_perm('nt.chiamam')): ?><a class="btn btn-sm btn-warning" href="<?= BASE_URL ?>noitru_assign.php?mode=meals"><i class="bi bi-diagram-3"></i> Chia mâm</a><?php endif; ?>
      <?php if (can_edit_perm('nt.danhsach') && allowed_classes() === null): ?>
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="sync_boarders">
        <button type="submit" class="btn btn-sm text-white" style="background:#d63384" onclick="return confirm('Đồng bộ danh sách nội trú từ CSDL?')">
          <i class="bi bi-arrow-repeat"></i> Đồng bộ từ CSDL
        </button>
      </form>
      <?php endif; ?>
    </div>
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
