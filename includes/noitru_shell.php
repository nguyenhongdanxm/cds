<?php
/**
 * App shell chung cho module Nội trú.
 * Desktop: sidebar cố định bên trái.
 * Mobile: thanh điều hướng cuộn ngang phía dưới.
 */
$ntPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$ntTab = $_GET['tab'] ?? '';
if (!isset($nt_sec)) {
    if ($ntPage === 'noitru_list.php' || $ntTab === 'boarders') $nt_sec = 'boarders';
    elseif ($ntPage === 'noitru_attendance.php' || $ntTab === 'attendance') $nt_sec = 'attendance';
    elseif ($ntTab === 'exits') $nt_sec = 'exits';
    elseif ($ntTab === 'meals') $nt_sec = 'meals';
    elseif ($ntTab === 'duty') $nt_sec = 'duty';
    elseif ($ntTab === 'health') $nt_sec = 'health';
    elseif ($ntTab === 'menu') $nt_sec = 'menu';
    elseif ($ntTab === 'stats') $nt_sec = 'stats';
    else $nt_sec = 'overview';
}

$ntItems = [
    'overview'   => [BASE_URL . 'noitru.php?tab=overview',       'bi-grid-1x2-fill',    'Tổng quan',   'nt.tongquan'],
    'boarders'   => [BASE_URL . 'noitru_list.php',               'bi-people-fill',      'Danh sách',   'nt.danhsach'],
    'exits'      => [BASE_URL . 'noitru.php?tab=exits',          'bi-door-open-fill',   'Ra/vào KTX',  'nt.ravao'],
    'meals'      => [BASE_URL . 'noitru.php?tab=meals',          'bi-cup-hot-fill',     'Báo ăn',      'nt.baoan'],
    'attendance' => [BASE_URL . 'noitru_attendance.php',         'bi-clipboard2-check-fill','Điểm danh','nt.diemdanh'],
    'duty'       => [BASE_URL . 'noitru.php?tab=duty',           'bi-calendar2-week-fill','Lịch trực',  'nt.lichtruc'],
    'health'     => [BASE_URL . 'noitru.php?tab=health',         'bi-heart-pulse-fill', 'Y tế',         'nt.yte'],
    'menu'       => [BASE_URL . 'noitru.php?tab=menu',           'bi-journal-text',     'Thực đơn',    'nt.thucdon'],
    'stats'      => [BASE_URL . 'noitru.php?tab=stats',          'bi-bar-chart-fill',   'Thống kê',    'nt.thongke'],
];
$ntItems = array_filter($ntItems, fn($item) => can_perm($item[3] ?? ''));
?>
<aside class="nt-sidebar" aria-label="Điều hướng Nội trú">
  <a class="nt-brand" href="<?= e(BASE_URL . 'noitru.php') ?>">
    <span class="nt-brand-icon"><i class="bi bi-building-fill"></i></span>
    <span><strong>Quản lý Nội trú</strong><small><?= e(SCHOOL_SHORT) ?></small></span>
  </a>
  <nav class="nt-side-nav">
    <?php foreach ($ntItems as $key => $item): ?>
    <a href="<?= e($item[0]) ?>" class="<?= $nt_sec === $key ? 'active' : '' ?>">
      <i class="bi <?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="nt-side-footer">
    <a href="<?= e(BASE_URL) ?>"><i class="bi bi-house-door"></i><span>Hệ sinh thái</span></a>
    <a href="<?= e(BASE_URL . 'csdl.php') ?>"><i class="bi bi-database"></i><span>CSDL</span></a>
    <a href="<?= e(BASE_URL . 'logout.php') ?>"><i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span></a>
  </div>
</aside>

<nav class="nt-bottom-nav" aria-label="Điều hướng Nội trú trên điện thoại">
  <?php foreach ($ntItems as $key => $item): ?>
  <a href="<?= e($item[0]) ?>" class="<?= $nt_sec === $key ? 'active' : '' ?>">
    <i class="bi <?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span>
  </a>
  <?php endforeach; ?>
</nav>
