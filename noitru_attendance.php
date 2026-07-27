<?php
/**
 * Điểm danh nội trú – tối ưu máy tính + điện thoại
 * Cấu hình buổi · lọc lớp · thao tác nhanh · chạm lớn
 */
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_once 'includes/noitru_att_shifts.php';
require_login();
$user = current_user();

$date  = $_GET['date']  ?? date('Y-m-d');
$shift = $_GET['shift'] ?? '';
$class = trim($_GET['class'] ?? '');
$q     = trim($_GET['q'] ?? '');
$view  = $_GET['view'] ?? 'diemdanh';

$shifts = function_exists('noitru_att_shifts_active') ? noitru_att_shifts_active() : [
    'the_duc_sang' => 'Thể dục buổi sáng',
    'sang' => 'Điểm danh sáng',
    'toi' => 'Điểm danh tối',
    'hoc_toi' => 'Học tối',
];
if ($shift === '' || !isset($shifts[$shift])) {
    $shift = array_key_first($shifts) ?: 'toi';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redir = BASE_URL . 'noitru_attendance.php?' . http_build_query(array_filter([
        'date' => $_POST['date'] ?? $date,
        'shift' => $_POST['shift'] ?? $shift,
        'class' => ($_POST['class'] ?? $class) ?: null,
        'view' => $_POST['view'] ?? null,
    ]));

    if ($action === 'att_save') {
        $d = trim($_POST['date'] ?? $date);
        $sh = trim($_POST['shift'] ?? $shift);
        $ids = $_POST['sid'] ?? [];
        $sts = $_POST['status'] ?? [];
        foreach ($ids as $i => $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_att_upsert([
                'date' => $d, 'shift' => $sh, 'student_id' => $sid,
                'status' => $sts[$i] ?? 'present', 'by' => $user['name'] ?? '',
            ]);
        }
        flash('Đã lưu điểm danh.');
        header('Location: ' . $redir); exit;
    }

    if ($action === 'att_bulk') {
        $d = trim($_POST['date'] ?? $date);
        $sh = trim($_POST['shift'] ?? $shift);
        $st = trim($_POST['bulk_status'] ?? 'present');
        if (!in_array($st, ['present', 'absent', 'late', 'excused'], true)) $st = 'present';
        $ids = $_POST['sid'] ?? [];
        if (function_exists('noitru_att_bulk')) {
            noitru_att_bulk($ids, $d, $sh, $st, $user['name'] ?? '');
        } else {
            foreach ($ids as $sid) {
                $sid = trim($sid);
                if ($sid === '') continue;
                noitru_att_upsert(['date'=>$d,'shift'=>$sh,'student_id'=>$sid,'status'=>$st,'by'=>$user['name']??'']);
            }
        }
        flash($st === 'present' ? 'Đã đánh dấu đủ tất cả.' : 'Đã đánh dấu vắng tất cả.');
        header('Location: ' . $redir); exit;
    }

    if ($action === 'shifts_save' && function_exists('noitru_att_shifts_save')) {
        $ids = $_POST['sid'] ?? [];
        $labels = $_POST['label'] ?? [];
        $actives = $_POST['active'] ?? [];
        $sorts = $_POST['sort'] ?? [];
        $rows = [];
        foreach ($ids as $i => $id) {
            $rows[] = [
                'id' => $id,
                'label' => $labels[$i] ?? $id,
                'active' => !empty($actives[$i]),
                'sort' => (int)($sorts[$i] ?? (($i + 1) * 10)),
            ];
        }
        $newId = trim($_POST['new_id'] ?? '');
        $newLabel = trim($_POST['new_label'] ?? '');
        if ($newId !== '' && $newLabel !== '') {
            $rows[] = ['id' => $newId, 'label' => $newLabel, 'active' => true, 'sort' => 999];
        }
        noitru_att_shifts_save($rows);
        flash('Đã lưu cấu hình buổi điểm danh.');
        header('Location: ' . BASE_URL . 'noitru_attendance.php?view=settings'); exit;
    }
}

$boarders = noitru_boarders_live();
$attMap = noitru_att_for($date, $shift);

$byClass = [];
foreach ($boarders as $s) {
    $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
    $byClass[$cn][] = $s;
}
ksort($byClass, SORT_NATURAL);

$list = $boarders;
if ($class !== '') {
    $list = array_values(array_filter($list, function ($s) use ($class) {
        $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
        return $cn === $class;
    }));
}
if ($q !== '') {
    $qq = mb_strtolower($q, 'UTF-8');
    $list = array_values(array_filter($list, function ($s) use ($qq) {
        $blob = mb_strtolower(($s['name']??'') . ' ' . ($s['code']??'') . ' ' . ($s['class_name']??'') . ' ' . ($s['room_ktx']??''), 'UTF-8');
        return mb_strpos($blob, $qq) !== false;
    }));
}

$cntTotal = count($list);
$cntPresent = $cntAbsent = $cntLate = $cntExcused = 0;
foreach ($list as $s) {
    $st = $attMap[$s['id']]['status'] ?? 'present';
    if ($st === 'present') $cntPresent++;
    elseif ($st === 'absent') $cntAbsent++;
    elseif ($st === 'late') $cntLate++;
    elseif ($st === 'excused') $cntExcused++;
    else $cntPresent++;
}

function att_url($params = []) {
    $base = [
        'date' => $_GET['date'] ?? date('Y-m-d'),
        'shift' => $_GET['shift'] ?? '',
        'class' => $_GET['class'] ?? '',
        'q' => $_GET['q'] ?? '',
        'view' => $_GET['view'] ?? 'diemdanh',
    ];
    $p = array_filter(array_merge($base, $params), fn($v) => $v !== null && $v !== '');
    return BASE_URL . 'noitru_attendance.php' . ($p ? ('?' . http_build_query($p)) : '');
}

$tabs_main = [
    'overview' => ['Tổng quan', 'bi-grid', BASE_URL . 'noitru.php?tab=overview'],
    'boarders' => ['Danh sách', 'bi-people', BASE_URL . 'noitru_list.php'],
    'exits' => ['Xin ra/vào KTX', 'bi-door-open', BASE_URL . 'noitru.php?tab=exits'],
    'meals' => ['Báo ăn', 'bi-egg-fried', BASE_URL . 'noitru.php?tab=meals'],
    'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru_attendance.php'],
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Điểm danh nội trú – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#d63384;--pd:#a61e5c;--ok:#198754;--bad:#dc3545}
body{background:#f8f0f4;padding-bottom:80px}
.navbar{background:var(--pd)!important}
.nav-pills .nav-link{border-radius:999px;font-weight:600;color:#445;font-size:.8rem;padding:.35rem .75rem}
.nav-pills .nav-link.active{background:var(--primary);color:#fff}
.card-soft{background:#fff;border:none;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.btn-nt{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-nt:hover{background:var(--pd);color:#fff}
.stat-box{border-radius:14px;padding:1rem;text-align:center;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.stat-box .n{font-size:1.75rem;font-weight:800;line-height:1.1}
.stat-box.present{background:#e8f8ef}.stat-box.present .n{color:var(--ok)}
.stat-box.absent{background:#fde8ec}.stat-box.absent .n{color:var(--bad)}
.class-pill{border-radius:999px;font-size:.8rem;font-weight:600;padding:.35rem .7rem;border:1.5px solid #dee2e6;background:#fff;color:#445;white-space:nowrap}
.class-pill.active{background:var(--primary);border-color:var(--primary);color:#fff}
.class-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding-bottom:4px}
.class-scroll::-webkit-scrollbar{display:none}
.stu-row{display:flex;align-items:center;gap:.6rem;padding:.7rem .9rem;border-bottom:1px solid #f0e4ea;background:#fff;min-height:56px;user-select:none;-webkit-tap-highlight-color:transparent}
.stu-row:active{background:#fce8f0}
.stu-row .name{font-weight:600;font-size:.95rem;flex:1;min-width:0}
.stu-row .cls{font-size:.75rem;color:#889;margin-left:.35rem}
.status-btns{display:flex;gap:.3rem;flex-shrink:0}
.status-btns button{border:none;border-radius:10px;width:40px;height:40px;font-size:1.1rem;display:flex;align-items:center;justify-content:center;background:#f1f3f5;color:#889}
.status-btns button.on-present{background:#d1e7dd;color:var(--ok)}
.status-btns button.on-absent{background:#f8d7da;color:var(--bad)}
.status-btns button.on-late{background:#fff3cd;color:#997404}
.status-btns button.on-excused{background:#cff4fc;color:#087990}
.sticky-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #eee;padding:.65rem 1rem;box-shadow:0 -4px 16px rgba(0,0,0,.08);z-index:100}
.filter-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:end}
@media (max-width:576px){
  .nav-pills{flex-wrap:nowrap;overflow-x:auto}
  .stat-box .n{font-size:1.4rem}
  .stu-row{padding:.85rem .75rem}
  .status-btns button{width:44px;height:44px}
}
</style>
</head>
<body>
<nav class="navbar navbar-dark mb-3">
  <div class="container-fluid px-3">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>noitru.php"><i class="bi bi-building"></i> Quản lý nội trú</a>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>" class="btn btn-outline-light btn-sm">Hệ sinh thái</a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark">Thoát</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-3 px-lg-4 pb-4">
<?php show_flash(); ?>

<ul class="nav nav-pills gap-1 mb-3 flex-nowrap overflow-auto">
  <?php foreach ($tabs_main as $k => $info): ?>
  <li class="nav-item flex-shrink-0">
    <a class="nav-link <?= $k==='attendance'?'active':'' ?>" href="<?= e($info[2]) ?>">
      <i class="bi <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="mb-3">
  <h4 class="mb-0 fw-bold"><i class="bi bi-clipboard-check text-primary"></i> Điểm danh nội trú</h4>
  <div class="text-muted small">Báo cáo sĩ số theo buổi · GV trực thao tác nhanh</div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $view==='diemdanh'?'active':'' ?>" href="<?= e(att_url(['view'=>'diemdanh'])) ?>"><i class="bi bi-house"></i> Điểm danh</a></li>
  <li class="nav-item"><a class="nav-link <?= $view==='history'?'active':'' ?>" href="<?= e(att_url(['view'=>'history'])) ?>"><i class="bi bi-clock-history"></i> Lịch sử</a></li>
  <li class="nav-item"><a class="nav-link <?= $view==='settings'?'active':'' ?>" href="<?= e(att_url(['view'=>'settings'])) ?>"><i class="bi bi-gear"></i> Cài đặt buổi</a></li>
</ul>

<?php if ($view === 'settings'): ?>
  <?php $allShifts = function_exists('noitru_att_shifts_all') ? noitru_att_shifts_all() : []; ?>
  <div class="card card-soft"><div class="card-body">
    <h6 class="mb-3">Cấu hình buổi điểm danh</h6>
    <p class="small text-muted">Bật/tắt buổi, đổi tên, thêm buổi mới. Thứ tự theo số Sort.</p>
    <form method="post">
      <input type="hidden" name="action" value="shifts_save">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Bật</th><th>Mã</th><th>Tên hiển thị</th><th>Sort</th></tr></thead>
          <tbody>
          <?php foreach ($allShifts as $i => $s): ?>
            <tr>
              <td><input type="checkbox" class="form-check-input" name="active[<?= $i ?>]" value="1" <?= !empty($s['active'])?'checked':'' ?>></td>
              <td><input type="text" class="form-control form-control-sm" name="sid[<?= $i ?>]" value="<?= e($s['id']) ?>" readonly></td>
              <td><input type="text" class="form-control form-control-sm" name="label[<?= $i ?>]" value="<?= e($s['label']) ?>" required></td>
              <td style="max-width:80px"><input type="number" class="form-control form-control-sm" name="sort[<?= $i ?>]" value="<?= (int)$s['sort'] ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="new_id" placeholder="Mã buổi mới (vd: chieu)"></div>
        <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="new_label" placeholder="Tên buổi mới"></div>
      </div>
      <button class="btn btn-nt" type="submit"><i class="bi bi-save"></i> Lưu cấu hình</button>
    </form>
  </div></div>

<?php elseif ($view === 'history'): ?>
  <?php
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    $hist = [];
    foreach (noitru_att_all() as $a) {
      $d = $a['date'] ?? '';
      if ($d < $from || $d > $to) continue;
      $key = $d . '|' . ($a['shift'] ?? '');
      if (!isset($hist[$key])) $hist[$key] = ['date'=>$d,'shift'=>$a['shift']??'','present'=>0,'absent'=>0,'late'=>0,'excused'=>0,'total'=>0];
      $st = $a['status'] ?? 'present';
      if (isset($hist[$key][$st])) $hist[$key][$st]++;
      $hist[$key]['total']++;
    }
    krsort($hist);
  ?>
  <form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="view" value="history">
    <div class="col-auto"><label class="form-label small mb-0">Từ</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
    <div class="col-auto"><label class="form-label small mb-0">Đến</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
    <div class="col-auto"><button class="btn btn-nt btn-sm">Xem</button></div>
  </form>
  <div class="card card-soft"><div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr><th>Ngày</th><th>Buổi</th><th>Tổng</th><th class="text-success">Có mặt</th><th class="text-danger">Vắng</th><th>Muộn</th><th>Có phép</th><th></th></tr></thead>
      <tbody>
      <?php if (!$hist): ?><tr><td colspan="8" class="text-muted text-center py-4">Chưa có dữ liệu.</td></tr>
      <?php else: foreach ($hist as $h):
        $sl = $shifts[$h['shift']] ?? $h['shift'];
      ?>
        <tr>
          <td><?= e($h['date']) ?></td>
          <td><?= e($sl) ?></td>
          <td><strong><?= $h['total'] ?></strong></td>
          <td class="text-success"><?= $h['present'] ?></td>
          <td class="text-danger"><?= $h['absent'] ?></td>
          <td><?= $h['late'] ?></td>
          <td><?= $h['excused'] ?></td>
          <td><a class="btn btn-sm btn-outline-secondary" href="<?= e(att_url(['view'=>'diemdanh','date'=>$h['date'],'shift'=>$h['shift']])) ?>">Mở</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div></div>

<?php else: ?>

  <form method="get" class="card card-soft mb-3" id="filterForm">
    <input type="hidden" name="view" value="diemdanh">
    <input type="hidden" name="class" id="classInput" value="<?= e($class) ?>">
    <div class="card-body py-2">
      <div class="filter-row">
        <div>
          <label class="form-label small mb-0">Ngày</label>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" onchange="this.form.submit()" style="min-width:140px">
        </div>
        <div>
          <label class="form-label small mb-0">Buổi</label>
          <select name="shift" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:160px">
            <?php foreach ($shifts as $sk=>$sv): ?>
              <option value="<?= e($sk) ?>" <?= $shift===$sk?'selected':'' ?>><?= e($sv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex-grow-1">
          <label class="form-label small mb-0">Tìm HS</label>
          <input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Tên, mã, lớp, phòng…" id="searchInput">
        </div>
      </div>
    </div>
  </form>

  <div class="class-scroll d-flex gap-1 mb-3">
    <a href="<?= e(att_url(['class'=>'','q'=>$q])) ?>" class="class-pill <?= $class===''?'active':'' ?>">Tất cả (<?= count($boarders) ?>)</a>
    <?php foreach ($byClass as $ck => $arr): ?>
      <a href="<?= e(att_url(['class'=>$ck,'q'=>$q])) ?>" class="class-pill <?= $class===$ck?'active':'' ?>"><?= e($ck) ?> (<?= count($arr) ?>)</a>
    <?php endforeach; ?>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-4"><div class="stat-box"><div class="n"><?= $cntTotal ?></div><div class="text-muted small">Tổng số</div></div></div>
    <div class="col-4"><div class="stat-box present"><div class="n"><?= $cntPresent ?></div><div class="text-muted small">Có mặt</div></div></div>
    <div class="col-4"><div class="stat-box absent"><div class="n"><?= $cntAbsent + $cntLate + $cntExcused ?></div><div class="text-muted small">Vắng / khác</div></div></div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post" class="d-inline" onsubmit="return confirm('Đánh dấu CÓ MẶT tất cả HS đang lọc?')">
      <input type="hidden" name="action" value="att_bulk">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="shift" value="<?= e($shift) ?>">
      <input type="hidden" name="class" value="<?= e($class) ?>">
      <input type="hidden" name="bulk_status" value="present">
      <?php foreach ($list as $s): ?><input type="hidden" name="sid[]" value="<?= e($s['id']) ?>"><?php endforeach; ?>
      <button class="btn btn-outline-success btn-sm" type="submit"><i class="bi bi-check-all"></i> Đủ tất cả</button>
    </form>
    <form method="post" class="d-inline" onsubmit="return confirm('Đánh dấu VẮNG tất cả HS đang lọc?')">
      <input type="hidden" name="action" value="att_bulk">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="shift" value="<?= e($shift) ?>">
      <input type="hidden" name="class" value="<?= e($class) ?>">
      <input type="hidden" name="bulk_status" value="absent">
      <?php foreach ($list as $s): ?><input type="hidden" name="sid[]" value="<?= e($s['id']) ?>"><?php endforeach; ?>
      <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg"></i> Vắng tất cả</button>
    </form>
    <span class="align-self-center small text-muted ms-1"><?= e($shifts[$shift] ?? $shift) ?> · <?= e(date('d/m/Y', strtotime($date))) ?></span>
  </div>

  <form method="post" id="attForm">
    <input type="hidden" name="action" value="att_save">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <input type="hidden" name="shift" value="<?= e($shift) ?>">
    <input type="hidden" name="class" value="<?= e($class) ?>">
    <div class="card card-soft overflow-hidden">
      <?php if (!$list): ?>
        <div class="text-muted text-center py-5">Không có học sinh.</div>
      <?php else: foreach ($list as $s):
        $cur = $attMap[$s['id']]['status'] ?? 'present';
      ?>
        <div class="stu-row" data-sid="<?= e($s['id']) ?>">
          <input type="hidden" name="sid[]" value="<?= e($s['id']) ?>">
          <input type="hidden" name="status[]" value="<?= e($cur) ?>" class="status-val">
          <div class="name text-truncate">
            <?= e($s['name']) ?>
            <span class="cls"><?= e($s['class_name']) ?><?= $s['room_ktx']!==''?' · '.e($s['room_ktx']):'' ?></span>
          </div>
          <div class="status-btns">
            <button type="button" class="st-btn on-<?= $cur==='present'?'present':'' ?>" data-st="present" title="Có mặt"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="st-btn on-<?= $cur==='absent'?'absent':'' ?>" data-st="absent" title="Vắng"><i class="bi bi-x-lg"></i></button>
            <button type="button" class="st-btn on-<?= $cur==='late'?'late':'' ?>" data-st="late" title="Muộn"><i class="bi bi-clock"></i></button>
            <button type="button" class="st-btn on-<?= $cur==='excused'?'excused':'' ?>" data-st="excused" title="Có phép"><i class="bi bi-journal-text"></i></button>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </form>

  <div class="sticky-bar d-flex gap-2 justify-content-between align-items-center">
    <div class="small text-muted d-none d-sm-block">
      <span class="text-success fw-bold" id="livePresent"><?= $cntPresent ?></span> có mặt ·
      <span class="text-danger fw-bold" id="liveAbsent"><?= $cntAbsent ?></span> vắng
    </div>
    <button type="submit" form="attForm" class="btn btn-nt flex-grow-1 flex-sm-grow-0 px-4">
      <i class="bi bi-save"></i> Lưu điểm danh
    </button>
  </div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  document.querySelectorAll('.stu-row').forEach(function(row){
    var valInput = row.querySelector('.status-val');
    var btns = row.querySelectorAll('.st-btn');
    btns.forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var st = btn.getAttribute('data-st');
        valInput.value = st;
        btns.forEach(function(b){
          b.className = 'st-btn';
          if (b.getAttribute('data-st') === st) b.classList.add('on-' + st);
        });
        recount();
      });
    });
  });
  function recount(){
    var p=0,a=0;
    document.querySelectorAll('.status-val').forEach(function(inp){
      if(inp.value==='present') p++; else a++;
    });
    var lp=document.getElementById('livePresent'), la=document.getElementById('liveAbsent');
    if(lp) lp.textContent=p;
    if(la) la.textContent=a;
  }
  var si = document.getElementById('searchInput');
  if(si){
    var t;
    si.addEventListener('input', function(){
      clearTimeout(t);
      t = setTimeout(function(){ document.getElementById('filterForm').submit(); }, 500);
    });
  }
})();
</script>
</body>
</html>
