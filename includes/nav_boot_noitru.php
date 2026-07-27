<?php
/**
 * Nav chung module Nội trú — đầy đủ menu như trang chính
 * Desktop: hàng nút | Mobile: cuộn ngang
 */
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$cur_tab = $_GET['tab'] ?? '';

$sec = 'overview';
if ($current_page === 'noitru_list.php' || $cur_tab === 'boarders') $sec = 'boarders';
elseif ($current_page === 'noitru_attendance.php' || $cur_tab === 'attendance') $sec = 'attendance';
elseif ($current_page === 'noitru_meals.php' || $cur_tab === 'meals') $sec = 'meals';
elseif (in_array($cur_tab, ['leave','ra_vao','exit','xin_ra'], true)) $sec = 'leave';
elseif ($cur_tab === 'duty') $sec = 'duty';
elseif (in_array($cur_tab, ['health','yte','y_te'], true)) $sec = 'health';
elseif (in_array($cur_tab, ['menu','thucdon','thuc_don'], true)) $sec = 'menu';
elseif (in_array($cur_tab, ['stats','thongke','thong_ke'], true)) $sec = 'stats';
elseif ($current_page === 'noitru.php' && ($cur_tab === '' || $cur_tab === 'overview')) $sec = 'overview';

// Menu đầy đủ theo giao diện hiện tại của trường
$nav_items = [
    'overview'   => [BASE_URL . 'noitru.php?tab=overview',     'bi-grid',            'Tổng quan'],
    'boarders'   => [BASE_URL . 'noitru_list.php',             'bi-people',          'Danh sách'],
    'leave'      => [BASE_URL . 'noitru.php?tab=leave',        'bi-door-open',       'Xin ra/vào KTX'],
    'meals'      => [BASE_URL . 'noitru_meals.php',            'bi-cup-hot',         'Báo ăn'],
    'attendance' => [BASE_URL . 'noitru_attendance.php',       'bi-clipboard-check', 'Điểm danh'],
    'duty'       => [BASE_URL . 'noitru.php?tab=duty',         'bi-calendar-week',   'Lịch trực'],
    'health'     => [BASE_URL . 'noitru.php?tab=health',       'bi-heart-pulse',     'Y tế'],
    'menu'       => [BASE_URL . 'noitru.php?tab=menu',         'bi-journal-text',    'Thực đơn'],
    'stats'      => [BASE_URL . 'noitru.php?tab=stats',        'bi-bar-chart',       'Thống kê'],
];
?>
<nav class="navbar navbar-dark mb-0 sticky-top" style="background:linear-gradient(135deg,#d63384 0%,#a61e4d 100%);z-index:1030">
  <div class="container-fluid px-2 px-md-3 py-1">
    <a class="navbar-brand fw-bold py-1 me-2" href="<?= e(BASE_URL . 'noitru.php') ?>" style="font-size:.95rem">
      <i class="bi bi-building"></i> <span class="d-none d-sm-inline">QL Nội trú</span>
    </a>
    <div class="d-flex gap-1 align-items-center ms-auto">
      <a href="<?= e(BASE_URL) ?>" class="btn btn-outline-light btn-sm d-none d-md-inline-flex">Hệ sinh thái</a>
      <a href="<?= e(BASE_URL . 'csdl.php') ?>" class="btn btn-outline-light btn-sm d-none d-md-inline-flex">CSDL</a>
      <a href="<?= e(BASE_URL . 'logout.php') ?>" class="btn btn-warning btn-sm text-dark">Thoát</a>
    </div>
  </div>
  <!-- Tab strip: luôn hiện, cuộn ngang trên mobile -->
  <div class="w-100 border-top border-light border-opacity-25">
    <div class="d-flex overflow-auto px-2 py-1 gap-1" style="-webkit-overflow-scrolling:touch;scrollbar-width:none">
      <?php foreach ($nav_items as $k => $v): ?>
        <a href="<?= e($v[0]) ?>"
           class="btn btn-sm flex-shrink-0 <?= $sec === $k ? 'btn-light text-dark' : 'btn-outline-light' ?>"
           style="font-size:.72rem;font-weight:600;padding:.32rem .55rem;white-space:nowrap">
          <i class="bi <?= e($v[1]) ?>"></i> <?= e($v[2]) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>
