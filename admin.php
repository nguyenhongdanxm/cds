<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/modules.php';
require_once __DIR__ . '/includes/dashboard_data.php';
require_login();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
if (empty($_SESSION['dashboard_csrf'])) $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(20));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mute_birthday') {
    $token = (string)($_POST['csrf'] ?? '');
    if (hash_equals((string)$_SESSION['dashboard_csrf'], $token)) cds_dashboard_mute_birthday($user, trim((string)($_POST['teacher_id'] ?? '')));
    header('Location: ' . BASE_URL . 'admin.php'); exit;
}

$modules = [];
foreach (get_ecosystem_modules() as $module) {
    $id = $module['id'] ?? '';
    if (($module['status'] ?? '') === 'soon') continue;
    if (($module['status'] ?? '') === 'link' || $isAdmin || can_module($id, 'view')) $modules[] = $module;
}
$teachers = array_values(array_filter(csdl_teachers_all(), fn($row)=>!isset($row['active']) || !empty($row['active'])));
$preferences = cds_dashboard_preferences($user);
$birthday = cds_dashboard_birthday($teachers, $preferences['muted_birthdays'] ?? []);
$dashboard = cds_dashboard_scope_data($user);
$quickActions = cds_dashboard_quick_actions($user);
$feedItems = cds_dashboard_notice_tasks($user, 10);
$observations = can_module('chuyenmon','view') ? cds_dashboard_observations() : [];
$lunar = cds_dashboard_solar_to_lunar((int)date('d'), (int)date('m'), (int)date('Y'));
$weekdays = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
$shiftLabels = ['morning'=>'Buổi sáng','noon'=>'Giờ ngủ trưa','afternoon'=>'Buổi chiều','evening'=>'Buổi tối','night'=>'Ban đêm','sang'=>'Buổi sáng','trua'=>'Buổi trưa','toi'=>'Buổi tối'];
$hour = (int)date('G');
$greeting = $hour < 11 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
$scope = allowed_classes();
$scopeText = $scope === null ? 'Toàn trường' : ($scope ? implode(', ', $scope) : 'Chưa được gán lớp');
$avatarName = (string)($user['name'] ?? 'U');
$avatar = function_exists('mb_substr') ? mb_substr($avatarName, 0, 1, 'UTF-8') : substr($avatarName, 0, 1);
$avatarUpper = function_exists('mb_strtoupper') ? mb_strtoupper($avatar, 'UTF-8') : strtoupper($avatar);
$duty = $dashboard['noitru']['duty'] ?? null;
$dutyHours = $duty ? intdiv((int)$duty['remaining'], 3600) : 0;
$dutyMinutes = $duty ? intdiv((int)$duty['remaining'] % 3600, 60) : 0;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0f4c81">
  <title>Trang chủ quản trị – <?= e(SCHOOL_SHORT) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(BASE_URL) ?>assets/admin-dashboard.css?v=20260807-1" rel="stylesheet">
</head>
<body>
<header class="app-header">
  <a class="school-brand" href="<?= e(BASE_URL) ?>">
    <span class="school-mark"><i class="bi bi-mortarboard-fill"></i></span>
    <span><strong><?= e(SCHOOL_NAME) ?></strong><small>Hệ sinh thái quản lý nhà trường</small></span>
  </a>
  <div class="header-actions">
    <details class="module-picker">
      <summary><i class="bi bi-grid-3x3-gap-fill"></i><span>Chuyển module</span><i class="bi bi-chevron-down"></i></summary>
      <div class="module-menu">
        <?php foreach ($modules as $module): ?><a href="<?= e($module['url']) ?>" style="--module-color:<?= e($module['color']) ?>"><i class="bi <?= e($module['icon']) ?>"></i><span><?= e($module['title']) ?></span></a><?php endforeach; ?>
        <?php if ($isAdmin): ?><a href="users.php" style="--module-color:#7c3aed"><i class="bi bi-shield-check"></i><span>Phân quyền</span></a><a href="activity.php" style="--module-color:#475569"><i class="bi bi-activity"></i><span>Nhật ký</span></a><?php endif; ?>
      </div>
    </details>
    <details class="user-picker">
      <summary><span class="avatar"><?= e($avatarUpper) ?></span><span class="user-copy"><strong><?= e($user['name'] ?? '') ?></strong><small><?= e($scopeText) ?></small></span><i class="bi bi-chevron-down"></i></summary>
      <div class="user-menu"><?php if ($isAdmin): ?><a href="users.php"><i class="bi bi-person-gear"></i>Tài khoản và quyền</a><?php endif; ?><a href="logout.php" class="logout"><i class="bi bi-box-arrow-right"></i>Đăng xuất</a></div>
    </details>
  </div>
</header>

<main class="dashboard">
  <section class="welcome-card">
    <div class="clock-block">
      <strong id="liveClock"><?= date('H:i') ?></strong>
      <span><?= e($weekdays[(int)date('w')]) ?>, <?= date('d/m/Y') ?></span>
      <small>Âm lịch: ngày <?= (int)$lunar['day'] ?> tháng <?= (int)$lunar['month'] ?><?= $lunar['leap'] ? ' nhuận' : '' ?> năm <?= (int)$lunar['year'] ?></small>
    </div>
    <div class="welcome-copy">
      <span class="eyebrow"><i class="bi bi-stars"></i><?= e($greeting) ?></span>
      <h1><?= e($user['name'] ?? 'Thầy/Cô') ?></h1>
      <?php if ($birthday): ?>
        <div class="birthday-line"><span class="birthday-icon">🎂</span><div><strong><?= $birthday['today'] ? 'Chúc mừng sinh nhật ' : 'Sinh nhật sắp tới: ' ?><?= e($birthday['name']) ?></strong><small><?= $birthday['today'] ? 'Chúc một tuổi mới nhiều sức khỏe, niềm vui và thành công!' : 'Còn ' . (int)$birthday['days'] . ' ngày · ' . date('d/m', strtotime($birthday['date'])) ?></small></div><form method="post"><input type="hidden" name="action" value="mute_birthday"><input type="hidden" name="csrf" value="<?= e($_SESSION['dashboard_csrf']) ?>"><input type="hidden" name="teacher_id" value="<?= e($birthday['id']) ?>"><button title="Ẩn thông báo sinh nhật của người này" aria-label="Ẩn thông báo sinh nhật"><i class="bi bi-x-lg"></i></button></form></div>
      <?php else: ?><p class="daily-quote">“<?= e(cds_dashboard_quote()) ?>”</p><?php endif; ?>
    </div>
    <div class="school-year"><i class="bi bi-calendar3"></i><span>Năm học</span><strong><?= e(SCHOOL_YEAR) ?></strong></div>
  </section>

  <section class="stat-grid" aria-label="Số liệu toàn trường">
    <article class="stat-card class-stat"><i class="bi bi-buildings"></i><div><span>Số lớp</span><strong><?= (int)$dashboard['csdl']['classes'] ?></strong><small>Trong phạm vi được xem</small></div></article>
    <article class="stat-card student-stat"><i class="bi bi-people-fill"></i><div><span>Học sinh</span><strong><?= (int)$dashboard['csdl']['students']['total'] ?></strong><small><b><?= (int)$dashboard['csdl']['students']['male'] ?></b> nam · <b><?= (int)$dashboard['csdl']['students']['female'] ?></b> nữ</small></div></article>
    <article class="stat-card teacher-stat"><i class="bi bi-person-badge-fill"></i><div><span>CBGVNV</span><strong><?= (int)$dashboard['csdl']['teachers']['total'] ?></strong><small><b><?= (int)$dashboard['csdl']['teachers']['male'] ?></b> nam · <b><?= (int)$dashboard['csdl']['teachers']['female'] ?></b> nữ</small></div></article>
  </section>

  <?php if ($quickActions): ?><section class="quick-section"><div class="section-heading"><div><span class="section-kicker">Truy cập nhanh</span><h2>Thao tác thường dùng</h2></div></div><div class="quick-grid"><?php foreach ($quickActions as $action): ?><a href="<?= e($action['url']) ?>" style="--quick-color:<?= e($action['color']) ?>"><i class="bi <?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span><i class="bi bi-arrow-up-right"></i></a><?php endforeach; ?></div></section><?php endif; ?>

  <div class="content-grid">
    <section class="panel feed-panel">
      <div class="panel-head"><div><span class="section-kicker">Chuyên môn</span><h2>Công việc đang và sắp diễn ra</h2></div><span class="count-pill"><?= count($feedItems) ?>/10</span></div>
      <div class="feed-list" id="professionalFeed">
        <?php foreach ($feedItems as $feedIndex => $item):
          $title = cds_dashboard_feed_title($item) ?: 'Nội dung Chuyên môn';
          $url = $item['url'] ?? $item['link'] ?? '';
          $nearestDate = $item['_dashboard_nearest'] ?? '';
          $endDate = $item['_dashboard_end'] ?? '';
          $state = $item['_dashboard_state'] ?? 'Đang diễn ra';
          $assigneeText = implode(', ', array_slice($item['_dashboard_assignees'] ?? [], 0, 3));
        ?>
          <?php if ($url): ?><a href="<?= e($url) ?>" class="feed-row<?= $feedIndex >= 5 ? ' feed-page-hidden' : '' ?>" data-feed-item data-feed-page="<?= intdiv($feedIndex, 5) + 1 ?>"><?php else: ?><div class="feed-row<?= $feedIndex >= 5 ? ' feed-page-hidden' : '' ?>" data-feed-item data-feed-page="<?= intdiv($feedIndex, 5) + 1 ?>"><?php endif; ?>
            <span class="feed-icon task"><i class="bi bi-check2-square"></i></span>
            <span class="feed-copy">
              <strong><?= e($title) ?></strong>
              <small><i class="bi bi-person-check"></i> <?= e($assigneeText) ?><?php if ($nearestDate): ?> · <i class="bi bi-calendar-event"></i> <?= $endDate ? 'Hạn ' : '' ?><?= e(date('d/m/Y', strtotime($nearestDate))) ?><?php endif; ?></small>
            </span>
            <span class="schedule-pill <?= $state === 'Sắp diễn ra' ? 'upcoming' : 'active' ?>"><?= e($state) ?></span>
          <?php if ($url): ?></a><?php else: ?></div><?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$feedItems): ?><div class="empty-state"><i class="bi bi-inbox"></i><strong>Chưa có công việc Chuyên môn phù hợp</strong><span>Chỉ hiện nội dung có thời gian hoặc hạn thực hiện và đã giao người phụ trách.</span></div><?php endif; ?>
      </div>
      <?php if (count($feedItems) > 5): ?><nav class="feed-pagination" aria-label="Trang công việc Chuyên môn"><button type="button" class="active" data-feed-page-button="1" aria-current="page">1</button><button type="button" data-feed-page-button="2">2</button></nav><?php endif; ?>
    </section>

    <div class="side-stack">
      <?php if ($observations): ?><section class="panel observation-panel"><div class="panel-head"><div><span class="section-kicker">Chuyên môn</span><h2>Lịch dự giờ sắp tới</h2></div><i class="bi bi-journal-check panel-symbol"></i></div><div class="compact-list"><?php foreach($observations as $row): ?><div><time><strong><?= date('d',strtotime($row['dashboard_date'])) ?></strong><span>Th <?= date('m',strtotime($row['dashboard_date'])) ?></span></time><p><strong><?= e($row['teacher']??$row['teacher_name']??$row['name']??'Lịch dự giờ') ?></strong><small><?= e(implode(' · ',array_filter([$row['time']??'',$row['subject']??'',$row['class']??$row['class_name']??'']))) ?></small></p></div><?php endforeach; ?></div></section><?php endif; ?>

      <section class="panel leave-panel"><div class="panel-head"><div><span class="section-kicker">Nhân sự</span><h2>Lịch nghỉ giáo viên</h2></div><i class="bi bi-calendar2-week panel-symbol"></i></div><div class="compact-list leave-list"><?php foreach($dashboard['leave'] as $row): ?><div><time><strong><?= date('d',strtotime($row['from'])) ?></strong><span>Th <?= date('m',strtotime($row['from'])) ?></span></time><p><strong><?= e($row['name']) ?></strong><small><?= e($row['reason'] ?: $row['permission']) ?><?= $row['to']!==$row['from']?' · đến '.date('d/m',strtotime($row['to'])):'' ?></small></p></div><?php endforeach; ?><?php if(!$dashboard['leave']): ?><div class="mini-empty"><i class="bi bi-calendar-check"></i><span>Không có lịch nghỉ hiện tại hoặc sắp tới.</span></div><?php endif; ?></div></section>
    </div>

    <?php if(can_module('noitru','view')): ?><section class="panel operation-panel">
      <div class="panel-head"><div><span class="section-kicker">Nội trú</span><h2>Vận hành hôm nay</h2></div><a href="noitru.php?tab=overview">Xem chi tiết <i class="bi bi-arrow-right"></i></a></div>
      <div class="operation-grid">
        <article class="duty-box"><span class="op-icon"><i class="bi bi-calendar2-check"></i></span><div class="op-copy"><span>Lịch trực hiện tại</span><?php if($duty && $duty['people']): ?><strong><?= e(implode(', ',$duty['people'])) ?></strong><small><?= e($duty['start']) ?> – <?= e($duty['end']) ?> hôm sau · còn <?= $dutyHours ?>h <?= $dutyMinutes ?>p</small><?php else: ?><strong>Chưa phân công</strong><small>Chưa có người trực trong ca hiện tại</small><?php endif; ?><?php if($duty && $duty['managers']): ?><em>Quản lý: <?= e(implode(', ',$duty['managers'])) ?></em><?php endif; ?></div></article>
        <article class="attendance-box"><span class="op-icon"><i class="bi bi-person-check-fill"></i></span><div class="op-copy"><span>Sĩ số điểm danh gần nhất</span><?php if($dashboard['noitru']['attendance_date']): ?><strong><b><?= (int)$dashboard['noitru']['present'] ?></b> có mặt · <b class="absent"><?= (int)$dashboard['noitru']['absent'] ?></b> vắng</strong><small><?= e($shiftLabels[$dashboard['noitru']['attendance_shift']]??$dashboard['noitru']['attendance_shift']) ?> · <?= date('d/m/Y',strtotime($dashboard['noitru']['attendance_date'])) ?></small><?php else: ?><strong>Chưa có dữ liệu</strong><small>Chưa ghi nhận báo cáo điểm danh</small><?php endif; ?></div><a href="noitru_attendance.php" aria-label="Mở điểm danh"><i class="bi bi-chevron-right"></i></a></article>
      </div>
    </section><?php endif; ?>
  </div>

  <section class="module-section"><div class="section-heading"><div><span class="section-kicker">Hệ sinh thái CDS</span><h2>Các module được sử dụng</h2></div></div><div class="module-grid"><?php foreach($modules as $module): ?><a href="<?= e($module['url']) ?>" style="--module-color:<?= e($module['color']) ?>"><i class="bi <?= e($module['icon']) ?>"></i><span><strong><?= e($module['title']) ?></strong><small><?= e($module['subtitle']) ?></small></span><i class="bi bi-chevron-right"></i></a><?php endforeach; ?></div></section>
</main>

<nav class="mobile-dock" aria-label="Điều hướng nhanh"><a class="active" href="admin.php"><i class="bi bi-house-fill"></i><span>Trang chủ</span></a><?php foreach(array_slice($quickActions,0,3) as $action): ?><a href="<?= e($action['url']) ?>"><i class="bi <?= e($action['icon']) ?>"></i><span><?= e($action['label']) ?></span></a><?php endforeach; ?><button type="button" id="mobileModules"><i class="bi bi-grid-fill"></i><span>Module</span></button></nav>

<script>
(function(){
  const clock=document.getElementById('liveClock');
  function tick(){clock.textContent=new Intl.DateTimeFormat('vi-VN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(new Date())}
  tick();setInterval(tick,1000);
  document.getElementById('mobileModules')?.addEventListener('click',function(){document.querySelector('.module-picker').open=true;document.querySelector('.module-picker summary').focus()});
  document.querySelectorAll('[data-feed-page-button]').forEach(function(button){button.addEventListener('click',function(){
    const page=button.dataset.feedPageButton;
    document.querySelectorAll('[data-feed-item]').forEach(function(item){item.classList.toggle('feed-page-hidden',item.dataset.feedPage!==page)});
    document.querySelectorAll('[data-feed-page-button]').forEach(function(other){const active=other===button;other.classList.toggle('active',active);if(active)other.setAttribute('aria-current','page');else other.removeAttribute('aria-current')});
  })});
  document.addEventListener('click',function(e){document.querySelectorAll('details[open]').forEach(function(d){if(!d.contains(e.target)&&!e.target.closest('#mobileModules'))d.open=false})});
})();
</script>
</body></html>
