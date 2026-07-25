<?php
require_once 'includes/auth.php';
require_once 'includes/csdl_store.php';
require_once 'includes/csdl_sync.php';
require_login();
$user = current_user();
$tab = $_GET['tab'] ?? 'overview';
$allowed = ['overview', 'teachers', 'classes', 'students', 'years', 'sync'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sync_from_pccm') {
        $r = csdl_sync_from_pccm();
        flash($r['message'], $r['ok'] ? 'success' : 'danger');
        header('Location: ' . BASE_URL . 'csdl.php?tab=sync');
        exit;
    }
    if ($action === 'sync_to_pccm') {
        $r = csdl_sync_to_pccm();
        flash($r['message'], $r['ok'] ? 'success' : 'danger');
        header('Location: ' . BASE_URL . 'csdl.php?tab=sync');
        exit;
    }
    if ($action === 'sync_from_qlhs') {
        $r = csdl_sync_from_qlhs();
        flash($r['message'], $r['ok'] ? 'success' : 'danger');
        header('Location: ' . BASE_URL . 'csdl.php?tab=sync');
        exit;
    }

    if ($action === 'teacher_save') {
        $group = trim($_POST['to_chuyen_mon'] ?? '');
        csdl_teacher_save([
            'id' => trim($_POST['id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'code' => trim($_POST['code'] ?? ''),
            'specialty' => trim($_POST['specialty'] ?? ''),
            'to_chuyen_mon' => $group,
            'pccm_group' => $group,
            'kiem_nhiem' => csdl_parse_kiem_nhiem_text($_POST['kiem_nhiem_text'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role_flags' => [
                'is_probation' => !empty($_POST['is_probation']),
                'is_principal' => !empty($_POST['is_principal']),
                'is_vice' => !empty($_POST['is_vice']),
            ],
            'note' => trim($_POST['note'] ?? ''),
            'active' => !empty($_POST['active']),
        ]);
        flash('Đã lưu giáo viên.');
        header('Location: ' . BASE_URL . 'csdl.php?tab=teachers');
        exit;
    }
    if ($action === 'teacher_delete') {
        csdl_teacher_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa giáo viên.', 'warning');
        header('Location: ' . BASE_URL . 'csdl.php?tab=teachers');
        exit;
    }

    if ($action === 'class_save') {
        $grade = (int)($_POST['grade'] ?? 0);
        csdl_class_save([
            'id' => trim($_POST['id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'grade' => $grade,
            'level' => $grade <= 9 ? 'THCS' : 'THPT',
            'homeroom_teacher_id' => trim($_POST['homeroom_teacher_id'] ?? ''),
            'room' => trim($_POST['room'] ?? ''),
            'active' => !empty($_POST['active']),
        ]);
        flash('Đã lưu lớp.');
        header('Location: ' . BASE_URL . 'csdl.php?tab=classes');
        exit;
    }
    if ($action === 'class_delete') {
        csdl_class_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa lớp.', 'warning');
        header('Location: ' . BASE_URL . 'csdl.php?tab=classes');
        exit;
    }

    if ($action === 'student_save') {
        csdl_student_save([
            'id' => trim($_POST['id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'code' => trim($_POST['code'] ?? ''),
            'class_id' => trim($_POST['class_id'] ?? ''),
            'gender' => trim($_POST['gender'] ?? ''),
            'dob' => trim($_POST['dob'] ?? ''),
            'boarder' => !empty($_POST['boarder']),
            'phone' => trim($_POST['phone'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
            'active' => !empty($_POST['active']),
        ]);
        flash('Đã lưu học sinh.');
        header('Location: ' . BASE_URL . 'csdl.php?tab=students');
        exit;
    }
    if ($action === 'student_delete') {
        csdl_student_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa học sinh.', 'warning');
        header('Location: ' . BASE_URL . 'csdl.php?tab=students');
        exit;
    }

    if ($action === 'year_set_current') {
        csdl_year_set_current(trim($_POST['id'] ?? ''));
        flash('Đã đặt năm học hiện hành.');
        header('Location: ' . BASE_URL . 'csdl.php?tab=years');
        exit;
    }
    if ($action === 'year_save') {
        csdl_year_save([
            'id' => trim($_POST['id'] ?? ''),
            'label' => trim($_POST['label'] ?? ''),
            'start' => trim($_POST['start'] ?? ''),
            'end' => trim($_POST['end'] ?? ''),
            'is_current' => !empty($_POST['is_current']),
        ]);
        if (!empty($_POST['is_current'])) {
            $id = trim($_POST['id'] ?? '');
            if ($id) csdl_year_set_current($id);
        }
        flash('Đã lưu năm học.');
        header('Location: ' . BASE_URL . 'csdl.php?tab=years');
        exit;
    }
}

$stats = csdl_stats();
$teachers = csdl_teachers_all();
$classes = csdl_classes_all();
$students = csdl_students_all();
$years = csdl_years_all();
$edit_id = $_GET['edit'] ?? '';
$sync_info = csdl_sync_pccm_path_info();

function teacher_name_by_id($id, $teachers) {
    foreach ($teachers as $t) {
        if (($t['id'] ?? '') === $id) return $t['name'] ?? '';
    }
    return '—';
}
function class_name_by_id($id, $classes) {
    foreach ($classes as $c) {
        if (($c['id'] ?? '') === $id) return $c['name'] ?? '';
    }
    return '—';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cơ sở dữ liệu dùng chung – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#1F4E79}
body{background:#f0f4f8}
.navbar{background:var(--primary)!important}
.stat{background:#fff;border-radius:12px;padding:1.1rem;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center}
.stat .n{font-size:1.6rem;font-weight:800;color:var(--primary)}
.nav-pills .nav-link{border-radius:999px;font-weight:600;color:#334}
.nav-pills .nav-link.active{background:var(--primary)}
.card-soft{background:#fff;border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.table thead th{font-size:.75rem;text-transform:uppercase;letter-spacing:.03em;color:#667}
.sync-arrow{font-size:1.5rem;color:var(--primary)}
.badge-kn{font-size:.7rem;font-weight:600;background:#e8f0fe;color:#1a56a8;margin:.1rem;display:inline-block}
</style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>csdl.php"><i class="bi bi-database"></i> Cơ sở dữ liệu</a>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>" class="btn btn-outline-light btn-sm">Hệ sinh thái</a>
      <a href="<?= BASE_URL ?>admin.php" class="btn btn-outline-light btn-sm">Quản trị</a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark">Thoát</a>
    </div>
  </div>
</nav>

<div class="container pb-5">
  <?php show_flash(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h3 class="mb-0">Cơ sở dữ liệu dùng chung</h3>
      <div class="text-muted small">Nguồn chuẩn cho Chuyên môn · Nội trú · Thi đua · các module khác</div>
    </div>
    <div class="text-muted small">Năm học: <strong><?= e($stats['year']) ?></strong></div>
  </div>

  <ul class="nav nav-pills gap-1 mb-4 flex-wrap">
    <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?tab=overview"><i class="bi bi-grid"></i> Tổng quan</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='teachers'?'active':'' ?>" href="?tab=teachers"><i class="bi bi-people"></i> Giáo viên</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='classes'?'active':'' ?>" href="?tab=classes"><i class="bi bi-building"></i> Lớp / khối</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='students'?'active':'' ?>" href="?tab=students"><i class="bi bi-mortarboard"></i> Học sinh</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='years'?'active':'' ?>" href="?tab=years"><i class="bi bi-calendar3"></i> Năm học</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='sync'?'active':'' ?>" href="?tab=sync"><i class="bi bi-arrow-left-right"></i> Đồng bộ</a></li>
  </ul>

<?php if ($tab === 'sync'): ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <!-- ===== PCCM ===== -->
      <div class="card card-soft mb-3">
        <div class="card-body">
          <h5 class="mb-3"><i class="bi bi-arrow-left-right text-primary"></i> Đồng bộ 2 chiều với Phân công chuyên môn (PCCM)</h5>
          <?php if (!$sync_info['ready']): ?>
            <div class="alert alert-warning">
              <strong>Chưa kết nối được thư mục data PCCM.</strong>
              <div class="small mt-2">Path: <code><?= e($sync_info['path'] ?: '(trống)') ?></code></div>
              <pre class="bg-light p-2 rounded small mt-2 mb-0">define('PCCM_DATA_PATH', '/home/capnachi/public_html/pccm/data');</pre>
            </div>
          <?php else: ?>
            <div class="alert alert-success py-2 small mb-3">
              <i class="bi bi-check-circle"></i> Data PCCM: <code><?= e($sync_info['path']) ?></code>
              · GV <?= $sync_info['teachers']?'✓':'—' ?>
              · meta <?= $sync_info['meta']?'✓':'—' ?>
              · lớp <?= $sync_info['classes']?'✓':'—' ?>
              · kiêm nhiệm <?= $sync_info['roles']?'✓':'—' ?>
              <?php if (!empty($sync_info['version'])): ?>· phiên bản <code><?= e($sync_info['version']) ?></code><?php endif; ?>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 bg-light">
                  <div class="fw-bold mb-1"><i class="bi bi-download text-success"></i> PCCM → CDS</div>
                  <p class="small text-muted mb-3">Kéo GV, lớp, <strong>tổ chuyên môn</strong>, <strong>kiêm nhiệm / chức vụ</strong> (GVCN, TTCM…).</p>
                  <form method="post" onsubmit="return confirm('Kéo từ PCCM vào CDS?')">
                    <input type="hidden" name="action" value="sync_from_pccm">
                    <button class="btn btn-success w-100" type="submit"><i class="bi bi-cloud-download"></i> Kéo từ PCCM</button>
                  </form>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 bg-light">
                  <div class="fw-bold mb-1"><i class="bi bi-upload text-primary"></i> CDS → PCCM</div>
                  <p class="small text-muted mb-3">Đẩy GV, lớp, tổ, kiêm nhiệm. <em>Không</em> ghi đè phân công tiết dạy.</p>
                  <form method="post" onsubmit="return confirm('Đẩy CDS sang PCCM?')">
                    <input type="hidden" name="action" value="sync_to_pccm">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cloud-upload"></i> Đẩy sang PCCM</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ===== QLHS ===== -->
      <?php include __DIR__ . '/includes/csdl_sync_tab_qlhs.php'; ?>

      <!-- ===== Ánh xạ ===== -->
      <div class="card card-soft">
        <div class="card-body">
          <h6 class="mb-2">Ánh xạ dữ liệu</h6>
          <table class="table table-sm mb-0">
            <thead><tr><th>CDS</th><th></th><th>Nguồn</th></tr></thead>
            <tbody>
              <tr><td>Họ tên GV</td><td class="text-center sync-arrow">↔</td><td><code>PCCM teachers.json</code></td></tr>
              <tr><td>Chuyên môn / Tổ</td><td class="text-center sync-arrow">↔</td><td><code>teacher_meta</code></td></tr>
              <tr><td>Kiêm nhiệm / chức vụ</td><td class="text-center sync-arrow">↔</td><td><code>roles_{version}.json</code></td></tr>
              <tr><td>Tập sự / HT / PHT</td><td class="text-center sync-arrow">↔</td><td><code>tap_su / hieu_truong / pho_hieu_truong</code></td></tr>
              <tr><td>Lớp + GVCN</td><td class="text-center sync-arrow">↔</td><td><code>PCCM classes + role GVCN</code></td></tr>
              <tr><td>Lớp + Học sinh</td><td class="text-center sync-arrow">←</td><td><code>QLHS Supabase (classes / students)</code></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card card-soft mb-3">
        <div class="card-body">
          <h6>Hiện trạng CDS</h6>
          <ul class="mb-3">
            <li>Giáo viên: <strong><?= (int)$stats['teachers'] ?></strong></li>
            <li>Lớp: <strong><?= (int)$stats['classes'] ?></strong></li>
            <li>Học sinh: <strong><?= (int)$stats['students'] ?></strong></li>
          </ul>
          <p class="small text-muted mb-0">
            Sau khi kéo từ <strong>PCCM</strong> → mở tab <em>Giáo viên</em> xem Tổ & Kiêm nhiệm.<br>
            Sau khi kéo từ <strong>QLHS</strong> → mở tab <em>Học sinh</em> / <em>Lớp</em>.
          </p>
        </div>
      </div>
      <div class="card card-soft">
        <div class="card-body">
          <h6 class="mb-2">Thứ tự khuyến nghị</h6>
          <ol class="small mb-0 ps-3">
            <li>Kéo <strong>PCCM → CDS</strong> (GV + lớp + kiêm nhiệm)</li>
            <li>Kéo <strong>QLHS → CDS</strong> (học sinh + bổ sung lớp)</li>
            <li>Kiểm tra / chỉnh tay trên các tab</li>
            <li>Nếu cần: <strong>CDS → PCCM</strong> để đẩy ngược</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'overview'): ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$stats['teachers'] ?></div><div class="text-muted small">Giáo viên</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-success"><?= (int)$stats['classes'] ?></div><div class="text-muted small">Lớp học</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-info"><?= (int)$stats['students'] ?></div><div class="text-muted small">Học sinh</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= e($stats['year']) ?></div><div class="text-muted small">Năm học</div></div></div>
  </div>
  <div class="card card-soft"><div class="card-body">
    <h5 class="mb-3">Vai trò CSDL</h5>
    <ul class="mb-0">
      <li>Giáo viên (chuyên môn, tổ, kiêm nhiệm) → PCCM, Thi đua</li>
      <li>Lớp / khối → TKB, nội trú, phân công</li>
      <li>Học sinh → Nội trú (QLHS), thống kê</li>
      <li>Đồng bộ 2 chiều với PCCM · Kéo 1 chiều từ QLHS (Supabase)</li>
    </ul>
  </div></div>

<?php elseif ($tab === 'teachers'): ?>
  <?php
    $editing = null;
    if ($edit_id) {
      foreach ($teachers as $t) if (($t['id'] ?? '') === $edit_id) { $editing = $t; break; }
    }
    $kn_text = '';
    if ($editing && !empty($editing['kiem_nhiem']) && is_array($editing['kiem_nhiem'])) {
      foreach ($editing['kiem_nhiem'] as $a) {
        $role = trim($a['role'] ?? '');
        if ($role === '') continue;
        $cls = trim($a['class'] ?? '');
        $per = $a['periods'] ?? null;
        if ($cls !== '' && $per !== null && $per !== '') $kn_text .= $role . '|' . $cls . '|' . $per . "\n";
        elseif ($cls !== '') $kn_text .= $role . ' (' . $cls . ")\n";
        else $kn_text .= $role . "\n";
      }
    }
  ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-3"><?= $editing ? 'Sửa giáo viên' : 'Thêm giáo viên' ?></h5>
        <form method="post">
          <input type="hidden" name="action" value="teacher_save">
          <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
          <div class="mb-2"><label class="form-label small">Họ và tên *</label>
            <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Mã GV</label>
            <input type="text" name="code" class="form-control" value="<?= e($editing['code'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Chuyên môn</label>
            <input type="text" name="specialty" class="form-control" placeholder="Toán, Ngữ văn…" value="<?= e($editing['specialty'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Tổ chuyên môn</label>
            <input type="text" name="to_chuyen_mon" class="form-control" placeholder="Tổ Toán, Tổ Ngữ văn…"
              value="<?= e($editing['to_chuyen_mon'] ?? $editing['pccm_group'] ?? '') ?>"></div>
          <div class="mb-2">
            <label class="form-label small">Kiêm nhiệm / chức vụ</label>
            <textarea name="kiem_nhiem_text" class="form-control form-control-sm" rows="4" placeholder="Mỗi dòng một chức vụ, ví dụ:&#10;GVCN (6A)&#10;TTCM&#10;Tổng phụ trách Đội"><?= e(rtrim($kn_text)) ?></textarea>
            <div class="form-text">Một dòng / chức vụ. Có lớp: <code>GVCN (6A)</code> hoặc <code>GVCN|6A|3</code></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Điện thoại</label>
              <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($editing['email'] ?? '') ?>"></div>
          </div>
          <div class="mb-2">
            <label class="form-label small d-block">Cờ đặc biệt</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="is_probation" id="isp" <?= !empty($editing['role_flags']['is_probation'])?'checked':'' ?>>
              <label class="form-check-label small" for="isp">Tập sự</label></div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="is_principal" id="iht" <?= !empty($editing['role_flags']['is_principal'])?'checked':'' ?>>
              <label class="form-check-label small" for="iht">Hiệu trưởng</label></div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="is_vice" id="ivc" <?= !empty($editing['role_flags']['is_vice'])?'checked':'' ?>>
              <label class="form-check-label small" for="ivc">Phó HT</label></div>
          </div>
          <div class="mb-2"><label class="form-label small">Ghi chú</label>
            <input type="text" name="note" class="form-control" value="<?= e($editing['note'] ?? '') ?>"></div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" id="tact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>>
            <label class="form-check-label" for="tact">Đang công tác</label></div>
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save"></i> Lưu</button>
          <?php if ($editing): ?><a href="?tab=teachers" class="btn btn-outline-secondary w-100 mt-2">Hủy</a><?php endif; ?>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-2">Danh sách giáo viên (<?= count($teachers) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>STT</th><th>Họ tên</th><th>Chuyên môn</th><th>Tổ</th><th>Kiêm nhiệm</th><th>Cờ</th><th>TT</th><th></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$teachers): ?>
              <tr><td colspan="8" class="text-muted text-center py-4">Chưa có — Đồng bộ PCCM hoặc thêm bên trái.</td></tr>
            <?php else: foreach ($teachers as $i => $t):
              $flags = [];
              if (!empty($t['role_flags']['is_principal'])) $flags[] = 'HT';
              if (!empty($t['role_flags']['is_vice'])) $flags[] = 'PHT';
              if (!empty($t['role_flags']['is_probation'])) $flags[] = 'Tập sự';
              $kn = csdl_format_kiem_nhiem($t['kiem_nhiem'] ?? []);
              $to = $t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '';
            ?>
              <tr class="<?= empty($t['active'])?'table-secondary':'' ?>">
                <td><?= $i+1 ?></td>
                <td>
                  <strong><?= e($t['name'] ?? '') ?></strong>
                  <?php if (!empty($t['code'])): ?><div class="small text-muted"><?= e($t['code']) ?></div><?php endif; ?>
                </td>
                <td class="small"><?= e($t['specialty'] ?? '—') ?></td>
                <td class="small"><?= $to !== '' ? e($to) : '—' ?></td>
                <td class="small">
                  <?php if ($kn === ''): ?>—
                  <?php else:
                    foreach (explode('; ', $kn) as $piece):
                      if ($piece === '') continue;
                  ?>
                    <span class="badge badge-kn"><?= e($piece) ?></span>
                  <?php endforeach; endif; ?>
                </td>
                <td class="small"><?= $flags ? e(implode(', ', $flags)) : '—' ?></td>
                <td><?= !empty($t['active']) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Nghỉ</span>' ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="?tab=teachers&edit=<?= urlencode($t['id']) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Xóa giáo viên này?')">
                    <input type="hidden" name="action" value="teacher_delete">
                    <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'classes'): ?>
  <?php
    $editing = null;
    if ($edit_id) { foreach ($classes as $c) if (($c['id'] ?? '') === $edit_id) { $editing = $c; break; } }
    usort($classes, fn($a,$b) => ($a['grade'] ?? 0) <=> ($b['grade'] ?? 0) ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));
  ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-3"><?= $editing ? 'Sửa lớp' : 'Thêm lớp' ?></h5>
        <form method="post">
          <input type="hidden" name="action" value="class_save">
          <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
          <div class="mb-2"><label class="form-label small">Tên lớp *</label>
            <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Khối *</label>
            <input type="number" name="grade" class="form-control" min="6" max="12" required value="<?= e((string)($editing['grade'] ?? 6)) ?>"></div>
          <div class="mb-2"><label class="form-label small">GVCN</label>
            <select name="homeroom_teacher_id" class="form-select">
              <option value="">—</option>
              <?php foreach ($teachers as $t): if (empty($t['active'])) continue; ?>
                <option value="<?= e($t['id']) ?>" <?= (($editing['homeroom_teacher_id'] ?? '') === $t['id'])?'selected':'' ?>><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="mb-2"><label class="form-label small">Phòng</label>
            <input type="text" name="room" class="form-control" value="<?= e($editing['room'] ?? '') ?>"></div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" id="cact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>>
            <label class="form-check-label" for="cact">Đang hoạt động</label></div>
          <button class="btn btn-primary w-100" type="submit">Lưu</button>
          <?php if ($editing): ?><a href="?tab=classes" class="btn btn-outline-secondary w-100 mt-2">Hủy</a><?php endif; ?>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-2">Danh sách lớp (<?= count($classes) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>STT</th><th>Lớp</th><th>Cấp</th><th>GVCN</th><th>Phòng</th><th>TT</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($classes as $i => $c): ?>
              <tr class="<?= empty($c['active'])?'table-secondary':'' ?>">
                <td><?= $i+1 ?></td>
                <td><strong><?= e($c['name'] ?? '') ?></strong></td>
                <td><span class="badge <?= ($c['level']??'')==='THPT'?'bg-primary':'bg-success' ?>"><?= e($c['level'] ?? '') ?></span></td>
                <td class="small"><?= e(teacher_name_by_id($c['homeroom_teacher_id'] ?? '', $teachers)) ?></td>
                <td><?= e($c['room'] ?? '—') ?></td>
                <td><?= !empty($c['active']) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Ẩn</span>' ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="?tab=classes&edit=<?= urlencode($c['id']) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Xóa lớp?')">
                    <input type="hidden" name="action" value="class_delete">
                    <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'students'): ?>
  <?php
    $editing = null;
    if ($edit_id) { foreach ($students as $s) if (($s['id'] ?? '') === $edit_id) { $editing = $s; break; } }
  ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-3"><?= $editing ? 'Sửa học sinh' : 'Thêm học sinh' ?></h5>
        <form method="post">
          <input type="hidden" name="action" value="student_save">
          <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
          <div class="mb-2"><label class="form-label small">Họ và tên *</label>
            <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Mã HS</label>
            <input type="text" name="code" class="form-control" value="<?= e($editing['code'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Lớp</label>
            <select name="class_id" class="form-select">
              <option value="">—</option>
              <?php foreach ($classes as $c): if (empty($c['active'])) continue; ?>
                <option value="<?= e($c['id']) ?>" <?= (($editing['class_id'] ?? '') === $c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Giới tính</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="Nam" <?= (($editing['gender'] ?? '')==='Nam')?'selected':'' ?>>Nam</option>
                <option value="Nữ" <?= (($editing['gender'] ?? '')==='Nữ')?'selected':'' ?>>Nữ</option>
              </select></div>
            <div class="col-6"><label class="form-label small">Ngày sinh</label>
              <input type="date" name="dob" class="form-control" value="<?= e($editing['dob'] ?? '') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label small">SĐT</label>
            <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="boarder" id="brd" <?= !empty($editing['boarder'])?'checked':'' ?>>
            <label class="form-check-label" for="brd">Nội trú</label></div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" id="sact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>>
            <label class="form-check-label" for="sact">Đang học</label></div>
          <button class="btn btn-primary w-100" type="submit">Lưu</button>
          <?php if ($editing): ?><a href="?tab=students" class="btn btn-outline-secondary w-100 mt-2">Hủy</a><?php endif; ?>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-2">Học sinh (<?= count($students) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>STT</th><th>Họ tên</th><th>Lớp</th><th>GT</th><th>Nội trú</th><th>TT</th><th></th></tr></thead>
            <tbody>
            <?php if (!$students): ?>
              <tr><td colspan="7" class="text-muted text-center py-4">Chưa có học sinh — Kéo từ QLHS hoặc thêm bên trái.</td></tr>
            <?php else: foreach ($students as $i => $s): ?>
              <tr class="<?= empty($s['active'])?'table-secondary':'' ?>">
                <td><?= $i+1 ?></td>
                <td><strong><?= e($s['name'] ?? '') ?></strong><?php if (!empty($s['code'])): ?><div class="small text-muted"><?= e($s['code']) ?></div><?php endif; ?></td>
                <td><?= e(class_name_by_id($s['class_id'] ?? '', $classes)) ?></td>
                <td><?= e($s['gender'] ?? '—') ?></td>
                <td><?= !empty($s['boarder']) ? '<span class="badge bg-info">Có</span>' : '—' ?></td>
                <td><?= !empty($s['active']) ? '<span class="badge bg-success">Học</span>' : '<span class="badge bg-secondary">Nghỉ</span>' ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="?tab=students&edit=<?= urlencode($s['id']) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Xóa?')">
                    <input type="hidden" name="action" value="student_delete">
                    <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>

<?php elseif ($tab === 'years'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-3">Thêm năm học</h5>
        <form method="post">
          <input type="hidden" name="action" value="year_save">
          <input type="hidden" name="id" value="">
          <div class="mb-2"><label class="form-label small">Nhãn *</label>
            <input type="text" name="label" class="form-control" required placeholder="2025–2026"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Bắt đầu</label><input type="date" name="start" class="form-control"></div>
            <div class="col-6"><label class="form-label small">Kết thúc</label><input type="date" name="end" class="form-control"></div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_current" id="yc">
            <label class="form-check-label" for="yc">Năm hiện hành</label></div>
          <button class="btn btn-primary w-100" type="submit">Lưu</button>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-2">Các năm học</h5>
        <table class="table align-middle mb-0">
          <thead><tr><th>Nhãn</th><th>Thời gian</th><th>TT</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($years as $y): ?>
            <tr>
              <td><strong><?= e($y['label'] ?? '') ?></strong></td>
              <td class="small"><?= e(($y['start'] ?? '') . ' → ' . ($y['end'] ?? '')) ?></td>
              <td><?= !empty($y['is_current']) ? '<span class="badge bg-success">Hiện hành</span>' : '—' ?></td>
              <td class="text-end">
                <?php if (empty($y['is_current'])): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="year_set_current">
                  <input type="hidden" name="id" value="<?= e($y['id']) ?>">
                  <button class="btn btn-sm btn-outline-success" type="submit">Đặt hiện hành</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
