<?php
/**
 * Shell: sidebar (PC) + bottom nav (mobile) — menu đầy đủ
 * Set $nt_sec trước khi include, hoặc tự detect
 */
if (!isset($nt_sec)) {
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $tab  = $_GET['tab'] ?? '';
    if ($page === 'noitru_list.php' || $tab === 'boarders') $nt_sec = 'boarders';
    elseif ($page === 'noitru_attendance.php' || $tab === 'attendance') $nt_sec = 'attendance';
    elseif ($page === 'noitru_meals.php' || $tab === 'meals') $nt_sec = 'meals';
    elseif (in_array($tab, ['leave','ra_vao','exit'], true)) $nt_sec = 'leave';
    elseif ($tab === 'duty') $nt_sec = 'duty';
    elseif (in_array($tab, ['health','yte'], true)) $nt_sec = 'health';
    elseif (in_array($tab, ['menu','thucdon'], true)) $nt_sec = 'menu';
    elseif (in_array($tab, ['stats','thongke'], true)) $nt_sec = 'stats';
    else $nt_sec = 'overview';
}
$nt_items = [
    'overview'   => [BASE_URL . 'noitru.php?tab=overview', 'bi-grid',            'Tổng quan'],
    'boarders'   => [BASE_URL . 'noitru_list.php',         'bi-people',          'Danh sách'],
    'leave'      => [BASE_URL . 'noitru.php?tab=leave',    'bi-door-open',       'Xin ra/vào'],
    'meals'      => [BASE_URL . 'noitru_meals.php',        'bi-cup-hot',         'Báo ăn'],
    'attendance' => [BASE_URL . 'noitru_attendance.php',   'bi-clipboard-check', 'Điểm danh'],
    'duty'       => [BASE_URL . 'noitru.php?tab=duty',     'bi-calendar-week',   'Lịch trực'],
    'health'     => [BASE_URL . 'noitru.php?tab=health',   'bi-heart-pulse',     'Y tế'],
    'menu'       => [BASE_URL . 'noitru.php?tab=menu',     'bi-journal-text',    'Thực đơn'],
    'stats'      => [BASE_URL . 'noitru.php?tab=stats',    'bi-bar-chart',       'Thống kê'],
];
?>
<aside class="side">
  <div class="brand"><a href="<?= e(BASE_URL . 'noitru.php') ?>"><i class="bi bi-building"></i> QL Nội trú</a></div>
  <nav>
    <?php foreach ($nt_items as $k => $v): ?>
      <a href="<?= e($v[0]) ?>" class="<?= $nt_sec === $k ? 'on' : '' ?>"><i class="bi <?= e($v[1]) ?>"></i> <?= e($v[2]) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="foot"><a href="<?= e(BASE_URL . 'logout.php') ?>">Thoát</a></div>
</aside>
<nav class="bot">
  <?php
  foreach ($nt_items as $k => $v): ?>
    <a href="<?= e($v[0]) ?>" class="<?= $nt_sec === $k ? 'on' : '' ?>" style="min-width:3.2rem"><i class="bi <?= e($v[1]) ?>"></i><span style="font-size:9px"><?= e($v[2]) ?></span></a>
  <?php endforeach; ?>
</nav>
