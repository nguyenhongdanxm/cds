<?php
require_once 'includes/auth.php';
require_once 'includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
$user = current_user();

$tab = $_GET['tab'] ?? 'overview';
$allowed = ['overview','boarders','exits','meals','attendance','duty','health','menu','stats'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';
$tabPerms = [
    'overview'=>'nt.tongquan', 'boarders'=>'nt.danhsach', 'exits'=>'nt.ravao',
    'meals'=>'nt.baoan', 'attendance'=>'nt.diemdanh', 'duty'=>'nt.lichtruc',
    'health'=>'nt.yte', 'menu'=>'nt.thucdon', 'stats'=>'nt.thongke',
];
require_perm($tabPerms[$tab] ?? 'nt.tongquan');

function noitru_student_in_scope($studentId) {
    foreach (noitru_boarders_live() as $student) {
        if (($student['id'] ?? '') !== $studentId) continue;
        return can_class($student['class_name'] ?? '');
    }
    return false;
}

function noitru_require_student_scope($studentId) {
    if (noitru_student_in_scope($studentId)) return;
    flash('Bạn không có quyền thao tác với học sinh ngoài lớp được giao.', 'danger');
    header('Location: ' . BASE_URL . 'noitru.php');
    exit;
}

function noitru_require_global_scope() {
    if (allowed_classes() === null) return;
    flash('Chức năng này chỉ dành cho người có phạm vi toàn trường.', 'danger');
    header('Location: ' . BASE_URL . 'noitru.php');
    exit;
}

/* Danh sách → trang 4 tab riêng */
if ($tab === 'boarders') {
    header('Location: ' . BASE_URL . 'noitru_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $actionPerms = [
        'sync_from_csdl'=>'nt.danhsach',
        'exit_save'=>'nt.ravao', 'exit_status'=>'nt.ravao', 'exit_delete'=>'nt.ravao',
        'meals_generate'=>'nt.baoan', 'meals_save'=>'nt.baoan', 'meals_lock'=>'nt.baoan', 'meals_unlock'=>'nt.baoan',
        'att_save'=>'nt.diemdanh',
        'duty_save'=>'nt.lichtruc', 'duty_delete'=>'nt.lichtruc',
        'health_save'=>'nt.yte', 'health_delete'=>'nt.yte',
        'menu_save'=>'nt.thucdon',
    ];
    if (isset($actionPerms[$action])) {
        $requiredLevel = substr($action, -7) === '_delete' ? 'delete' : 'edit';
        require_perm_level($actionPerms[$action], $requiredLevel);
    }
    if (in_array($action, ['sync_from_csdl','meals_generate','meals_lock','meals_unlock','duty_save','duty_delete','menu_save'], true)) {
        noitru_require_global_scope();
    }
    if (in_array($action, ['exit_status','exit_delete'], true)) {
        $targetId = trim($_POST['id'] ?? '');
        foreach (noitru_exits_all() as $targetRow) {
            if (($targetRow['id'] ?? '') === $targetId) {
                noitru_require_student_scope($targetRow['student_id'] ?? '');
                break;
            }
        }
    }
    if ($action === 'health_delete') {
        $targetId = trim($_POST['id'] ?? '');
        foreach (noitru_health_all() as $targetRow) {
            if (($targetRow['id'] ?? '') === $targetId) {
                noitru_require_student_scope($targetRow['student_id'] ?? '');
                break;
            }
        }
    }

    if ($action === 'sync_from_csdl') {
        $r = noitru_sync_from_csdl();
        flash($r['message'], $r['ok'] ? 'success' : 'danger');
        header('Location: ' . BASE_URL . 'noitru.php?tab=' . urlencode($tab));
        exit;
    }

    /* Exits */
    if ($action === 'exit_save') {
        $sid = trim($_POST['student_id'] ?? '');
        noitru_require_student_scope($sid);
        $bmap = [];
        foreach (noitru_boarders_live() as $s) $bmap[$s['id']] = $s;
        $st = $bmap[$sid] ?? null;
        noitru_exit_save([
            'id' => trim($_POST['id'] ?? ''),
            'student_id' => $sid,
            'student_name' => $st['name'] ?? '',
            'class_name' => $st['class_name'] ?? '',
            'from_date' => trim($_POST['from_date'] ?? ''),
            'to_date' => trim($_POST['to_date'] ?? ''),
            'reason' => trim($_POST['reason'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
            'status' => trim($_POST['status'] ?? 'pending'),
            'created_by' => $user['name'] ?? '',
        ]);
        flash('Đã lưu phiếu xin ra/vào KTX.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=exits');
        exit;
    }
    if ($action === 'exit_status') {
        $id = trim($_POST['id'] ?? '');
        $st = trim($_POST['status'] ?? '');
        if (in_array($st, ['approved','rejected','pending'], true)) {
            foreach (noitru_exits_all() as $r) {
                if (($r['id'] ?? '') === $id) {
                    $r['status'] = $st;
                    $r['approved_by'] = $user['name'] ?? '';
                    $r['approved_at'] = noitru_now();
                    noitru_exit_save($r);
                    break;
                }
            }
            flash($st === 'approved' ? 'Đã duyệt phiếu.' : ($st === 'rejected' ? 'Đã từ chối.' : 'Đã cập nhật.'));
        }
        header('Location: ' . BASE_URL . 'noitru.php?tab=exits');
        exit;
    }
    if ($action === 'exit_delete') {
        noitru_exit_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa phiếu.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=exits');
        exit;
    }

    /* Meals */
    if ($action === 'meals_generate') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $n = noitru_meals_generate_day($date);
        flash("Đã tạo/cập nhật báo ăn $n HS cho ngày $date (theo phiếu KTX nếu có).");
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }
    if ($action === 'meals_save') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $ids = $_POST['sid'] ?? [];
        $sang = $_POST['sang'] ?? [];
        $trua = $_POST['trua'] ?? [];
        $toi = $_POST['toi'] ?? [];
        foreach ($ids as $i => $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_require_student_scope($sid);
            noitru_meal_upsert([
                'date' => $date,
                'student_id' => $sid,
                'sang' => $sang[$i] ?? 'yes',
                'trua' => $trua[$i] ?? 'yes',
                'toi' => $toi[$i] ?? 'yes',
                'source' => 'manual',
                'force' => !empty($_POST['force']),
            ]);
        }
        flash('Đã lưu báo ăn.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }
    if ($action === 'meals_lock') {
        $date = trim($_POST['date'] ?? '');
        noitru_meals_lock_day($date, true);
        flash("Đã chốt báo ăn ngày $date.");
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }
    if ($action === 'meals_unlock') {
        $date = trim($_POST['date'] ?? '');
        noitru_meals_lock_day($date, false);
        flash("Đã mở khóa báo ăn ngày $date.", 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=meals&date=' . urlencode($date));
        exit;
    }

    /* Attendance */
    if ($action === 'att_save') {
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $shift = trim($_POST['shift'] ?? 'toi');
        $ids = $_POST['sid'] ?? [];
        $sts = $_POST['status'] ?? [];
        foreach ($ids as $i => $sid) {
            $sid = trim($sid);
            if ($sid === '') continue;
            noitru_require_student_scope($sid);
            noitru_att_upsert([
                'date' => $date,
                'shift' => $shift,
                'student_id' => $sid,
                'status' => $sts[$i] ?? 'present',
                'by' => $user['name'] ?? '',
            ]);
        }
        flash('Đã lưu điểm danh.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=attendance&date=' . urlencode($date) . '&shift=' . urlencode($shift));
        exit;
    }

    /* Duty */
    if ($action === 'duty_save') {
        noitru_duty_save([
            'id' => trim($_POST['id'] ?? ''),
            'date' => trim($_POST['date'] ?? ''),
            'shift' => trim($_POST['shift'] ?? 'toi'),
            'teacher_id' => trim($_POST['teacher_id'] ?? ''),
            'teacher_name' => trim($_POST['teacher_name'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
        ]);
        flash('Đã lưu lịch trực.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=duty');
        exit;
    }
    if ($action === 'duty_delete') {
        noitru_duty_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa ca trực.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=duty');
        exit;
    }

    /* Health */
    if ($action === 'health_save') {
        $sid = trim($_POST['student_id'] ?? '');
        noitru_require_student_scope($sid);
        $name = '';
        foreach (noitru_boarders_live() as $s) if ($s['id'] === $sid) { $name = $s['name']; break; }
        noitru_health_save([
            'id' => trim($_POST['id'] ?? ''),
            'student_id' => $sid,
            'student_name' => $name,
            'date' => trim($_POST['date'] ?? date('Y-m-d')),
            'type' => trim($_POST['type'] ?? 'kham'),
            'diagnosis' => trim($_POST['diagnosis'] ?? ''),
            'treatment' => trim($_POST['treatment'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
            'by' => $user['name'] ?? '',
        ]);
        flash('Đã lưu hồ sơ y tế.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health');
        exit;
    }
    if ($action === 'health_delete') {
        noitru_health_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa hồ sơ.', 'warning');
        header('Location: ' . BASE_URL . 'noitru.php?tab=health');
        exit;
    }

    /* Menu */
    if ($action === 'menu_save') {
        $ws = trim($_POST['week_start'] ?? '');
        $days = ['mon','tue','wed','thu','fri','sat','sun'];
        $meals = [];
        foreach ($days as $d) {
            $meals[$d] = [
                'sang' => trim($_POST[$d . '_sang'] ?? ''),
                'trua' => trim($_POST[$d . '_trua'] ?? ''),
                'toi' => trim($_POST[$d . '_toi'] ?? ''),
            ];
        }
        noitru_menu_save(['week_start' => $ws, 'meals' => $meals]);
        flash('Đã lưu thực đơn tuần.');
        header('Location: ' . BASE_URL . 'noitru.php?tab=menu&week=' . urlencode($ws));
        exit;
    }
}

$boarders = array_values(array_filter(noitru_boarders_live(), fn($student) => can_class($student['class_name'] ?? '')));
$stats = noitru_stats();
if (allowed_classes() !== null) {
    $stats['total'] = count($boarders);
    $stats['by_class'] = $stats['by_room'] = $stats['by_meal'] = [];
    foreach ($boarders as $student) {
        $className = $student['class_name'] ?: '(Chưa lớp)';
        $room = $student['room_ktx'] ?: '(Chưa phòng)';
        $meal = $student['meal_group'] ?: '(Chưa nhóm ăn)';
        $stats['by_class'][$className] = ($stats['by_class'][$className] ?? 0) + 1;
        $stats['by_room'][$room] = ($stats['by_room'][$room] ?? 0) + 1;
        $stats['by_meal'][$meal] = ($stats['by_meal'][$meal] ?? 0) + 1;
    }
}
$teachers = array_values(array_filter(csdl_teachers_all(), fn($t) => !empty($t['active'])));

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
$tabs = array_filter($tabs, fn($info, $key) => can_perm($tabPerms[$key] ?? ''), ARRAY_FILTER_USE_BOTH);
$canEditCurrent = can_edit_perm($tabPerms[$tab] ?? '');
$canDeleteCurrent = can_delete_perm($tabPerms[$tab] ?? '');

function nt_meal_label($v) {
    return ['yes'=>'Có','no'=>'Không','sick'=>'Bệnh','guest'=>'Khách'][$v] ?? $v;
}
function nt_att_label($v) {
    return ['present'=>'Có mặt','absent'=>'Vắng','late'=>'Muộn','excused'=>'Có phép'][$v] ?? $v;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản lý nội trú – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>includes/noitru_layout.css" rel="stylesheet">
<style>
:root{--primary:#d63384;--pd:#a61e5c}
body{background:#f8f0f4}
.navbar{background:var(--pd)!important}
.stat{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center}
.stat .n{font-size:1.5rem;font-weight:800;color:var(--primary)}
.nav-pills .nav-link{border-radius:999px;font-weight:600;color:#445;font-size:.85rem}
.nav-pills .nav-link.active{background:var(--primary)}
.card-soft{background:#fff;border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.table thead th{font-size:.7rem;text-transform:uppercase;color:#667;background:#fce8f0;white-space:nowrap}
.btn-nt{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-nt:hover{background:var(--pd);color:#fff}
.badge-room{background:#fce8f0;color:#a61e5c}
.badge-meal{background:#e8f5e9;color:#2e7d32}
<?php if (!$canEditCurrent): ?>
form[method="post"]{display:none!important}
<?php endif; ?>
</style>
</head>
<body class="nt-body">
<?php $nt_sec = $tab; require __DIR__ . '/includes/noitru_shell.php'; ?>
<main class="nt-main"><div class="nt-content">
<?php show_flash(); ?>

<div class="nt-page-head">
  <div>
    <h3 class="mb-0">Quản lý nội trú</h3>
    <div class="text-muted small">Nguồn HS: <strong>CSDL</strong> · <?= e(SCHOOL_NAME) ?></div>
  </div>
  <?php if (allowed_classes() === null && can_edit_perm('nt.danhsach')): ?>
  <form method="post" class="m-0">
    <input type="hidden" name="action" value="sync_from_csdl">
    <button class="btn btn-nt btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Đồng bộ từ CSDL</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($tab === 'overview'): ?>
  <?php $st = $stats; ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$st['total'] ?></div><div class="text-muted small">HS nội trú</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n" style="font-size:.95rem;padding-top:.4rem"><?= $st['last_sync_at'] ? e(date('d/m H:i', strtotime($st['last_sync_at']))) : 'Chưa' ?></div><div class="text-muted small">Đồng bộ CSDL</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count($st['by_room']) ?></div><div class="text-muted small">Phòng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count(array_filter(noitru_exits_all(), fn($x)=>($x['status']??'')==='pending' && noitru_student_in_scope($x['student_id'] ?? ''))) ?></div><div class="text-muted small">Phiếu chờ duyệt</div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body"><h6>Theo lớp</h6>
      <?php foreach ($st['by_class'] as $k=>$n): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
      <?php if (!$st['by_class']): ?><p class="text-muted small mb-0">Chưa có HS nội trú trong CSDL.</p><?php endif; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body"><h6>Theo phòng</h6>
      <?php foreach ($st['by_room'] as $k=>$n): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body"><h6>Theo nhóm ăn</h6>
      <?php foreach ($st['by_meal'] as $k=>$n): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
    </div></div></div>
  </div>

<?php elseif ($tab === 'exits'): ?>
  <?php
    $exits = array_values(array_filter(noitru_exits_all(), fn($row) => noitru_student_in_scope($row['student_id'] ?? '')));
    usort($exits, fn($a,$b) => strcmp($b['from_date']??'', $a['from_date']??''));
  ?>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h6 class="mb-3">Thêm phiếu xin ra KTX</h6>
        <form method="post">
          <input type="hidden" name="action" value="exit_save">
          <div class="mb-2"><label class="form-label small">Học sinh</label>
            <select name="student_id" class="form-select form-select-sm" required>
              <option value="">— Chọn —</option>
              <?php foreach ($boarders as $s): ?>
                <option value="<?= e($s['id']) ?>"><?= e($s['name']) ?> (<?= e($s['class_name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Từ ngày</label><input type="date" name="from_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6"><label class="form-label small">Đến ngày</label><input type="date" name="to_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label small">Lý do</label><input type="text" name="reason" class="form-control form-control-sm" required></div>
          <div class="mb-2"><label class="form-label small">Ghi chú</label><input type="text" name="note" class="form-control form-control-sm"></div>
          <button class="btn btn-nt btn-sm w-100" type="submit">Lưu phiếu</button>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-soft"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead><tr><th>HS</th><th>Thời gian</th><th>Lý do</th><th>TT</th><th></th></tr></thead>
          <tbody>
          <?php if (!$exits): ?><tr><td colspan="5" class="text-muted text-center py-3">Chưa có phiếu.</td></tr>
          <?php else: foreach ($exits as $x):
            $st = $x['status']??'pending';
            $badge = $st==='approved'?'success':($st==='rejected'?'danger':'warning');
          ?>
            <tr>
              <td><strong><?= e($x['student_name']??'') ?></strong><br><span class="small text-muted"><?= e($x['class_name']??'') ?></span></td>
              <td class="small"><?= e($x['from_date']??'') ?> → <?= e($x['to_date']??'') ?></td>
              <td class="small"><?= e($x['reason']??'') ?></td>
              <td><span class="badge bg-<?= $badge ?>"><?= e($st) ?></span></td>
              <td class="text-nowrap">
                <?php if ($st==='pending'): ?>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="exit_status"><input type="hidden" name="id" value="<?= e($x['id']) ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success" title="Duyệt">✓</button></form>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="exit_status"><input type="hidden" name="id" value="<?= e($x['id']) ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-danger" title="Từ chối">✗</button></form>
                <?php endif; ?>
                <?php if ($canDeleteCurrent): ?><form method="post" class="d-inline" onsubmit="return confirm('Xóa?')"><input type="hidden" name="action" value="exit_delete"><input type="hidden" name="id" value="<?= e($x['id']) ?>"><button class="btn btn-sm btn-outline-secondary">🗑</button></form><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'meals'): ?>
  <?php
    $date = $_GET['date'] ?? date('Y-m-d');
    $mealMap = noitru_meals_for_date($date);
    $cnt = noitru_meals_count_day($date);
    $locked = false;
    foreach ($mealMap as $m) if (!empty($m['locked'])) { $locked = true; break; }
  ?>
  <div class="card card-soft mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="meals">
      <div class="col-auto"><label class="form-label small mb-1">Ngày</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3">
      <form method="post"><input type="hidden" name="action" value="meals_generate"><input type="hidden" name="date" value="<?= e($date) ?>"><button class="btn btn-nt btn-sm" type="submit">Tạo báo ăn từ CSDL + phiếu KTX</button></form>
      <?php if ($locked): ?>
        <form method="post"><input type="hidden" name="action" value="meals_unlock"><input type="hidden" name="date" value="<?= e($date) ?>"><button class="btn btn-outline-warning btn-sm">Mở khóa</button></form>
        <span class="badge bg-success align-self-center">Đã chốt</span>
      <?php else: ?>
        <form method="post"><input type="hidden" name="action" value="meals_lock"><input type="hidden" name="date" value="<?= e($date) ?>"><button class="btn btn-outline-success btn-sm">Chốt ngày</button></form>
      <?php endif; ?>
      <span class="align-self-center small text-muted">Suất: Sáng <strong><?= $cnt['sang'] ?></strong> · Trưa <strong><?= $cnt['trua'] ?></strong> · Tối <strong><?= $cnt['toi'] ?></strong></span>
    </div>
  </div></div>
  <form method="post">
    <input type="hidden" name="action" value="meals_save">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <?php if ($locked): ?><input type="hidden" name="force" value="1"><?php endif; ?>
    <div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>HS</th><th>Lớp</th><th>Sáng</th><th>Trưa</th><th>Tối</th><th>Nguồn</th></tr></thead>
        <tbody>
        <?php if (!$boarders): ?><tr><td colspan="6" class="text-muted text-center py-3">Chưa có HS nội trú.</td></tr>
        <?php else: foreach ($boarders as $s):
          $m = $mealMap[$s['id']] ?? null;
          $opts = ['yes'=>'Có','no'=>'Không','sick'=>'Bệnh','guest'=>'Khách'];
        ?>
          <tr>
            <td><input type="hidden" name="sid[]" value="<?= e($s['id']) ?>"><strong><?= e($s['name']) ?></strong></td>
            <td class="small"><?= e($s['class_name']) ?></td>
            <?php foreach (['sang','trua','toi'] as $b): ?>
            <td>
              <select name="<?= $b ?>[]" class="form-select form-select-sm">
                <?php foreach ($opts as $ov=>$ol): ?>
                  <option value="<?= $ov ?>" <?= (($m[$b]??'yes')===$ov)?'selected':'' ?>><?= $ol ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <?php endforeach; ?>
            <td class="small text-muted"><?= e($m['source'] ?? '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($boarders): ?><div class="card-body border-top"><button class="btn btn-nt" type="submit">Lưu báo ăn</button></div><?php endif; ?>
    </div>
  </form>

<?php elseif ($tab === 'attendance'): ?>
  <?php
    $date = $_GET['date'] ?? date('Y-m-d');
    $shift = $_GET['shift'] ?? 'toi';
    $attMap = noitru_att_for($date, $shift);
    $shifts = ['sang'=>'Sáng','toi'=>'Tối','hoc_toi'=>'Học tối'];
  ?>
  <form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="tab" value="attendance">
    <div class="col-auto"><label class="form-label small mb-1">Ngày</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
    <div class="col-auto"><label class="form-label small mb-1">Ca</label>
      <select name="shift" class="form-select" onchange="this.form.submit()">
        <?php foreach ($shifts as $k=>$v): ?><option value="<?= $k ?>" <?= $shift===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
  </form>
  <form method="post">
    <input type="hidden" name="action" value="att_save">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <input type="hidden" name="shift" value="<?= e($shift) ?>">
    <div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead><tr><th>HS</th><th>Lớp</th><th>Phòng</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($boarders as $s):
          $a = $attMap[$s['id']] ?? null;
          $cur = $a['status'] ?? 'present';
        ?>
          <tr>
            <td><input type="hidden" name="sid[]" value="<?= e($s['id']) ?>"><strong><?= e($s['name']) ?></strong></td>
            <td><?= e($s['class_name']) ?></td>
            <td><?= e($s['room_ktx']) ?></td>
            <td>
              <select name="status[]" class="form-select form-select-sm" style="max-width:140px">
                <?php foreach (['present'=>'Có mặt','absent'=>'Vắng','late'=>'Muộn','excused'=>'Có phép'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$boarders): ?><tr><td colspan="4" class="text-muted text-center py-3">Chưa có HS.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($boarders): ?><div class="card-body border-top"><button class="btn btn-nt" type="submit">Lưu điểm danh</button></div><?php endif; ?>
    </div>
  </form>

<?php elseif ($tab === 'duty'): ?>
  <?php
    $duties = noitru_duty_all();
    usort($duties, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
  ?>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Thêm ca trực</h6>
      <form method="post">
        <input type="hidden" name="action" value="duty_save">
        <div class="mb-2"><label class="form-label small">Ngày</label><input type="date" name="date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small">Ca</label>
          <select name="shift" class="form-select form-select-sm"><option value="sang">Sáng</option><option value="toi" selected>Tối</option><option value="dem">Đêm</option></select>
        </div>
        <div class="mb-2"><label class="form-label small">Giáo viên (CSDL)</label>
          <select name="teacher_id" class="form-select form-select-sm" onchange="var o=this.options[this.selectedIndex];document.getElementById('tn').value=o.getAttribute('data-name')||''">
            <option value="">—</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= e($t['id']) ?>" data-name="<?= e($t['name']??'') ?>"><?= e($t['name']??'') ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="teacher_name" id="tn" value="">
        </div>
        <div class="mb-2"><label class="form-label small">Ghi chú</label><input type="text" name="note" class="form-control form-control-sm"></div>
        <button class="btn btn-nt btn-sm w-100">Lưu</button>
      </form>
    </div></div></div>
    <div class="col-md-8"><div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>Ca</th><th>GV</th><th>Ghi chú</th><th></th></tr></thead><tbody>
      <?php foreach ($duties as $d): ?>
        <tr>
          <td><?= e($d['date']??'') ?></td>
          <td><?= e($d['shift']??'') ?></td>
          <td><?= e($d['teacher_name']??'') ?></td>
          <td class="small"><?= e($d['note']??'') ?></td>
          <td><?php if ($canDeleteCurrent): ?><form method="post" onsubmit="return confirm('Xóa?')"><input type="hidden" name="action" value="duty_delete"><input type="hidden" name="id" value="<?= e($d['id']) ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$duties): ?><tr><td colspan="5" class="text-muted text-center py-3">Chưa có lịch.</td></tr><?php endif; ?>
      </tbody></table>
    </div></div></div>
  </div>

<?php elseif ($tab === 'health'): ?>
  <?php
    $health = array_values(array_filter(noitru_health_all(), fn($row) => noitru_student_in_scope($row['student_id'] ?? '')));
    usort($health, fn($a,$b) => strcmp($b['date']??'', $a['date']??''));
  ?>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Ghi nhận y tế</h6>
      <form method="post">
        <input type="hidden" name="action" value="health_save">
        <div class="mb-2"><label class="form-label small">HS</label>
          <select name="student_id" class="form-select form-select-sm" required>
            <option value="">—</option>
            <?php foreach ($boarders as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label small">Ngày</label><input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small">Loại</label>
          <select name="type" class="form-select form-select-sm"><option value="kham">Khám</option><option value="thuoc">Thuốc</option><option value="theo_doi">Theo dõi</option></select>
        </div>
        <div class="mb-2"><label class="form-label small">Chẩn đoán / tình trạng</label><input type="text" name="diagnosis" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small">Xử trí</label><input type="text" name="treatment" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">Ghi chú</label><input type="text" name="note" class="form-control form-control-sm"></div>
        <button class="btn btn-nt btn-sm w-100">Lưu</button>
      </form>
    </div></div></div>
    <div class="col-md-8"><div class="card card-soft"><div class="table-responsive">
      <table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>HS</th><th>Loại</th><th>Tình trạng</th><th>Xử trí</th><th></th></tr></thead><tbody>
      <?php foreach ($health as $h): ?>
        <tr>
          <td class="small"><?= e($h['date']??'') ?></td>
          <td><?= e($h['student_name']??'') ?></td>
          <td><?= e($h['type']??'') ?></td>
          <td class="small"><?= e($h['diagnosis']??'') ?></td>
          <td class="small"><?= e($h['treatment']??'') ?></td>
          <td><?php if ($canDeleteCurrent): ?><form method="post" onsubmit="return confirm('Xóa?')"><input type="hidden" name="action" value="health_delete"><input type="hidden" name="id" value="<?= e($h['id']) ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$health): ?><tr><td colspan="6" class="text-muted text-center py-3">Chưa có hồ sơ.</td></tr><?php endif; ?>
      </tbody></table>
    </div></div></div>
  </div>

<?php elseif ($tab === 'menu'): ?>
  <?php
    $week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
    $menu = noitru_menu_for_week($week);
    $meals = $menu['meals'] ?? [];
    $dayLabels = ['mon'=>'Thứ 2','tue'=>'Thứ 3','wed'=>'Thứ 4','thu'=>'Thứ 5','fri'=>'Thứ 6','sat'=>'Thứ 7','sun'=>'CN'];
    $groups = $stats['by_meal'];
  ?>
  <div class="row g-3 mb-3">
    <div class="col-md-8">
      <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="menu">
        <div class="col-auto"><label class="form-label small mb-1">Tuần (thứ 2)</label><input type="date" name="week" class="form-control" value="<?= e($week) ?>" onchange="this.form.submit()"></div>
      </form>
      <form method="post" class="card card-soft"><div class="card-body">
        <input type="hidden" name="action" value="menu_save">
        <input type="hidden" name="week_start" value="<?= e($week) ?>">
        <h6 class="mb-3">Thực đơn tuần <?= e($week) ?></h6>
        <?php foreach ($dayLabels as $dk => $dl):
          $row = $meals[$dk] ?? ['sang'=>'','trua'=>'','toi'=>''];
        ?>
          <div class="border rounded p-2 mb-2">
            <div class="fw-semibold small mb-1"><?= $dl ?></div>
            <div class="row g-1">
              <div class="col-md-4"><input type="text" name="<?= $dk ?>_sang" class="form-control form-control-sm" placeholder="Sáng" value="<?= e($row['sang']??'') ?>"></div>
              <div class="col-md-4"><input type="text" name="<?= $dk ?>_trua" class="form-control form-control-sm" placeholder="Trưa" value="<?= e($row['trua']??'') ?>"></div>
              <div class="col-md-4"><input type="text" name="<?= $dk ?>_toi" class="form-control form-control-sm" placeholder="Tối" value="<?= e($row['toi']??'') ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-nt" type="submit">Lưu thực đơn</button>
      </div></form>
    </div>
    <div class="col-md-4">
      <div class="card card-soft"><div class="card-body">
        <h6>Nhóm ăn (từ CSDL)</h6>
        <p class="small text-muted">Sửa nhóm ăn trên hồ sơ HS ở CSDL, rồi đồng bộ.</p>
        <?php foreach ($groups as $g=>$n): ?>
          <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= e($g) ?></span><strong><?= $n ?></strong></div>
        <?php endforeach; ?>
        <?php if (!$groups): ?><p class="text-muted small mb-0">Chưa có.</p><?php endif; ?>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'stats'): ?>
  <?php
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $full = noitru_stats_full($from, $to);
  ?>
  <form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="tab" value="stats">
    <div class="col-auto"><label class="form-label small mb-1">Từ</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-auto"><label class="form-label small mb-1">Đến</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-auto"><button class="btn btn-nt">Xem</button></div>
  </form>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['boarders'] ?></div><div class="text-muted small">HS nội trú</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['meals']['sang'] ?></div><div class="text-muted small">Suất sáng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['meals']['trua'] ?></div><div class="text-muted small">Suất trưa</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$full['meals']['toi'] ?></div><div class="text-muted small">Suất tối</div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Điểm danh</h6>
      <?php foreach ($full['attendance'] as $k=>$v): ?>
        <div class="d-flex justify-content-between small border-bottom py-1"><span><?= e(nt_att_label($k)) ?></span><strong><?= (int)$v ?></strong></div>
      <?php endforeach; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Phiếu KTX</h6>
      <?php foreach ($full['exits'] as $k=>$v): ?>
        <div class="d-flex justify-content-between small border-bottom py-1"><span><?= e($k) ?></span><strong><?= (int)$v ?></strong></div>
      <?php endforeach; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card card-soft"><div class="card-body">
      <h6>Y tế</h6>
      <div class="d-flex justify-content-between small"><span>Hồ sơ trong kỳ</span><strong><?= (int)$full['health'] ?></strong></div>
    </div></div></div>
  </div>
  <?php if ($full['meals']['days']): ?>
  <div class="card card-soft mt-3"><div class="card-body">
    <h6>Suất ăn theo ngày</h6>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Ngày</th><th>Sáng</th><th>Trưa</th><th>Tối</th></tr></thead><tbody>
    <?php foreach ($full['meals']['days'] as $d=>$c): ?>
      <tr><td><?= e($d) ?></td><td><?= (int)$c['sang'] ?></td><td><?= (int)$c['trua'] ?></td><td><?= (int)$c['toi'] ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div></div>
  <?php endif; ?>

<?php endif; ?>
</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
