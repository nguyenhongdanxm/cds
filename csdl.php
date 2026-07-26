<?php
require_once 'includes/auth.php';
require_once 'includes/csdl_store.php';
require_once 'includes/csdl_sync.php';
require_once 'includes/csdl_import_teachers.php';
require_once 'includes/csdl_io.php';
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

    if ($action === 'io_import') {
        $entity = $_POST['entity'] ?? '';
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            flash('Chưa chọn file CSV.', 'danger');
        } else {
            $tmp = $_FILES['csv']['tmp_name'];
            if ($entity === 'teachers') $r = csdl_io_import_teachers($tmp);
            elseif ($entity === 'classes') $r = csdl_io_import_classes($tmp);
            elseif ($entity === 'students') $r = csdl_io_import_students($tmp);
            else $r = ['ok' => false, 'message' => 'Entity không hợp lệ'];
            flash($r['message'], !empty($r['ok']) ? 'success' : 'danger');
        }
        $back = in_array($entity, ['teachers','classes','students'], true) ? $entity : 'overview';
        header('Location: ' . BASE_URL . 'csdl.php?tab=' . $back);
        exit;
    }

    if ($action === 'teacher_import_csv') {
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            flash('Chưa chọn file CSV.', 'danger');
        } else {
            $r = csdl_import_teachers_from_csv_file($_FILES['csv']['tmp_name']);
            flash($r['message'], $r['ok'] ? 'success' : 'danger');
        }
        header('Location: ' . BASE_URL . 'csdl.php?tab=teachers');
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
            'dob' => trim($_POST['dob'] ?? ''),
            'gender' => trim($_POST['gender'] ?? ''),
            'ethnicity' => trim($_POST['ethnicity'] ?? ''),
            'hometown' => trim($_POST['hometown'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'chuc_vu' => trim($_POST['chuc_vu'] ?? ''),
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
            'capacity' => trim($_POST['capacity'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
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
            'ethnicity' => trim($_POST['ethnicity'] ?? ''),
            'hometown' => trim($_POST['hometown'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'boarder' => !empty($_POST['boarder']),
            'room_ktx' => trim($_POST['room_ktx'] ?? ''),
            'meal_group' => trim($_POST['meal_group'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'parent_name' => trim($_POST['parent_name'] ?? ''),
            'parent_phone' => trim($_POST['parent_phone'] ?? ''),
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
.table thead th{font-size:.72rem;text-transform:uppercase;letter-spacing:.02em;color:#667;white-space:nowrap}
.sync-arrow{font-size:1.5rem;color:var(--primary)}
.badge-kn{font-size:.7rem;font-weight:600;background:#e8f0fe;color:#1a56a8;margin:.1rem;display:inline-block}
.table-full td{font-size:.85rem;vertical-align:middle}
</style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>csdl.php"><i class="bi bi-database"></i> Cơ sở dữ liệu</a>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>" class="btn btn-outline-light btn-sm">Hệ sinh thái</a>
      <a href="<?= BASE_URL ?>admin.php" class="btn btn-outline-light btn-sm">Quản trị</a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark">Thoát</a>
    </div>
  </div>
</nav>

<div class="container-fluid px-3 px-lg-4 pb-5">
  <?php show_flash(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h3 class="mb-0">Cơ sở dữ liệu dùng chung</h3>
      <div class="text-muted small">Nguồn chuẩn hệ sinh thái — module khác chỉ trích / nhập theo mẫu xuất từ đây</div>
    </div>
    <div class="text-muted small">Năm học: <strong><?= e($stats['year']) ?></strong></div>
  </div>

  <ul class="nav nav-pills gap-1 mb-4 flex-wrap">
    <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?tab=overview"><i class="bi bi-grid"></i> Tổng quan</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='teachers'?'active':'' ?>" href="?tab=teachers"><i class="bi bi-people"></i> Giáo viên</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='classes'?'active':'' ?>" href="?tab=classes"><i class="bi bi-building"></i> Lớp / khối</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='students'?'active':'' ?>" href="?tab=students"><i class="bi bi-mortarboard"></i> Học sinh</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='years'?'active':'' ?>" href="?tab=years"><i class="bi bi-calendar3"></i> Năm học</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='sync'?'active':'' ?>" href="?tab=sync"><i class="bi bi-arrow-left-right"></i> Đồng bộ PCCM</a></li>
  </ul>

<?php if ($tab === 'sync'): ?>
  <?php include __DIR__ . '/includes/csdl_tab_sync.php'; ?>

<?php elseif ($tab === 'overview'): ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= (int)$stats['teachers'] ?></div><div class="text-muted small">Giáo viên</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-success"><?= (int)$stats['classes'] ?></div><div class="text-muted small">Lớp học</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n text-info"><?= (int)$stats['students'] ?></div><div class="text-muted small">Học sinh</div></div></div>
    <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= e($stats['year']) ?></div><div class="text-muted small">Năm học</div></div></div>
  </div>
  <div class="card card-soft"><div class="card-body">
    <h5 class="mb-3">CSDL là nguồn chuẩn</h5>
    <ul class="mb-0">
      <li>Bảng đầy đủ GV · Lớp · HS — nhập/xuất CSV theo <strong>cùng schema</strong></li>
      <li>Module khác (PCCM, Nội trú, Thi đua) <strong>trích</strong> từ đây hoặc nhập lại đúng mẫu xuất</li>
      <li>Đồng bộ 2 chiều với PCCM (chuyên môn, tổ, kiêm nhiệm)</li>
      <li>Xuất: chọn cột cần → file CSV dùng chung toàn hệ</li>
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
    $io_entity = 'teachers';
    include __DIR__ . '/includes/csdl_io_panel.php';
  ?>

  <div class="row g-4">
    <div class="col-xl-3 col-lg-4">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-3"><?= $editing ? 'Sửa giáo viên' : 'Thêm giáo viên' ?></h5>
        <form method="post">
          <input type="hidden" name="action" value="teacher_save">
          <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
          <div class="mb-2"><label class="form-label small">Họ và tên *</label>
            <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Mã GV</label>
            <input type="text" name="code" class="form-control" value="<?= e($editing['code'] ?? '') ?>"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Ngày sinh</label>
              <input type="date" name="dob" class="form-control" value="<?= e($editing['dob'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small">Giới tính</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="Nam" <?= (($editing['gender'] ?? '')==='Nam')?'selected':'' ?>>Nam</option>
                <option value="Nữ" <?= (($editing['gender'] ?? '')==='Nữ')?'selected':'' ?>>Nữ</option>
              </select></div>
          </div>
          <div class="mb-2"><label class="form-label small">Dân tộc</label>
            <input type="text" name="ethnicity" class="form-control" value="<?= e($editing['ethnicity'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Chuyên môn</label>
            <input type="text" name="specialty" class="form-control" value="<?= e($editing['specialty'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Tổ chuyên môn</label>
            <input type="text" name="to_chuyen_mon" class="form-control" value="<?= e($editing['to_chuyen_mon'] ?? $editing['pccm_group'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Chức vụ (hành chính)</label>
            <input type="text" name="chuc_vu" class="form-control" value="<?= e($editing['chuc_vu'] ?? '') ?>"></div>
          <div class="mb-2">
            <label class="form-label small">Kiêm nhiệm</label>
            <textarea name="kiem_nhiem_text" class="form-control form-control-sm" rows="3"><?= e(rtrim($kn_text)) ?></textarea>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">SĐT</label>
              <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($editing['email'] ?? '') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label small">Quê quán</label>
            <input type="text" name="hometown" class="form-control" value="<?= e($editing['hometown'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Địa chỉ</label>
            <input type="text" name="address" class="form-control" value="<?= e($editing['address'] ?? '') ?>"></div>
          <div class="mb-2">
            <label class="form-label small d-block">Cờ</label>
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_probation" id="isp" <?= !empty($editing['role_flags']['is_probation'])?'checked':'' ?>><label class="form-check-label small" for="isp">Tập sự</label></div>
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_principal" id="iht" <?= !empty($editing['role_flags']['is_principal'])?'checked':'' ?>><label class="form-check-label small" for="iht">HT</label></div>
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_vice" id="ivc" <?= !empty($editing['role_flags']['is_vice'])?'checked':'' ?>><label class="form-check-label small" for="ivc">PHT</label></div>
          </div>
          <div class="mb-2"><label class="form-label small">Ghi chú</label>
            <input type="text" name="note" class="form-control" value="<?= e($editing['note'] ?? '') ?>"></div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" id="tact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>>
            <label class="form-check-label" for="tact">Đang công tác</label></div>
          <button class="btn btn-primary w-100" type="submit">Lưu</button>
          <?php if ($editing): ?><a href="?tab=teachers" class="btn btn-outline-secondary w-100 mt-2">Hủy</a><?php endif; ?>
        </form>
      </div></div>
    </div>
    <div class="col-xl-9 col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-2">Bảng giáo viên đầy đủ (<?= count($teachers) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover table-full table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>STT</th><th>Mã</th><th>Họ tên</th><th>GT</th><th>Ngày sinh</th><th>Dân tộc</th>
                <th>SĐT</th><th>Email</th><th>Chuyên môn</th><th>Tổ</th><th>Chức vụ</th><th>Kiêm nhiệm</th><th>Cờ</th><th>TT</th><th></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$teachers): ?>
              <tr><td colspan="15" class="text-muted text-center py-4">Chưa có dữ liệu.</td></tr>
            <?php else: foreach ($teachers as $i => $t):
              $flags = [];
              if (!empty($t['role_flags']['is_principal'])) $flags[] = 'HT';
              if (!empty($t['role_flags']['is_vice'])) $flags[] = 'PHT';
              if (!empty($t['role_flags']['is_probation'])) $flags[] = 'TS';
              $kn = csdl_format_kiem_nhiem($t['kiem_nhiem'] ?? []);
              $to = $t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '';
            ?>
              <tr class="<?= empty($t['active'])?'table-secondary':'' ?>">
                <td><?= $i+1 ?></td>
                <td class="small"><?= e($t['code'] ?? '') ?></td>
                <td><strong><?= e($t['name'] ?? '') ?></strong></td>
                <td><?= e($t['gender'] ?? '') ?></td>
                <td class="small"><?= e($t['dob'] ?? '') ?></td>
                <td class="small"><?= e($t['ethnicity'] ?? '') ?></td>
                <td class="small"><?= e($t['phone'] ?? '') ?></td>
                <td class="small"><?= e($t['email'] ?? '') ?></td>
                <td class="small"><?= e($t['specialty'] ?? '') ?></td>
                <td class="small"><?= e($to) ?></td>
                <td class="small"><?= e($t['chuc_vu'] ?? '') ?></td>
                <td class="small"><?php if ($kn): foreach (explode('; ', $kn) as $piece): if ($piece==='') continue; ?><span class="badge badge-kn"><?= e($piece) ?></span><?php endforeach; else: ?>—<?php endif; ?></td>
                <td class="small"><?= e(implode(',', $flags)) ?></td>
                <td><?= !empty($t['active']) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Nghỉ</span>' ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="?tab=teachers&edit=<?= urlencode($t['id']) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Xóa?')">
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
    $io_entity = 'classes';
    include __DIR__ . '/includes/csdl_io_panel.php';
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
          <div class="mb-2"><label class="form-label small">Sĩ số định mức</label>
            <input type="text" name="capacity" class="form-control" value="<?= e((string)($editing['capacity'] ?? '')) ?>"></div>
          <div class="mb-2"><label class="form-label small">Ghi chú</label>
            <input type="text" name="note" class="form-control" value="<?= e($editing['note'] ?? '') ?>"></div>
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
        <h5 class="mb-2">Bảng lớp đầy đủ (<?= count($classes) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover table-full table-sm align-middle mb-0">
            <thead><tr><th>STT</th><th>Lớp</th><th>Khối</th><th>Cấp</th><th>GVCN</th><th>Phòng</th><th>Định mức</th><th>Ghi chú</th><th>TT</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($classes as $i => $c): ?>
              <tr class="<?= empty($c['active'])?'table-secondary':'' ?>">
                <td><?= $i+1 ?></td>
                <td><strong><?= e($c['name'] ?? '') ?></strong></td>
                <td><?= e((string)($c['grade'] ?? '')) ?></td>
                <td><span class="badge <?= ($c['level']??'')==='THPT'?'bg-primary':'bg-success' ?>"><?= e($c['level'] ?? '') ?></span></td>
                <td class="small"><?= e(teacher_name_by_id($c['homeroom_teacher_id'] ?? '', $teachers)) ?></td>
                <td><?= e($c['room'] ?? '') ?></td>
                <td><?= e((string)($c['capacity'] ?? '')) ?></td>
                <td class="small"><?= e($c['note'] ?? '') ?></td>
                <td><?= !empty($c['active']) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Ẩn</span>' ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="?tab=classes&edit=<?= urlencode($c['id']) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Xóa?')">
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
    $io_entity = 'students';
    include __DIR__ . '/includes/csdl_io_panel.php';
  ?>
  <div class="row g-4">
    <div class="col-xl-3 col-lg-4">
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
          <div class="mb-2"><label class="form-label small">Dân tộc</label>
            <input type="text" name="ethnicity" class="form-control" value="<?= e($editing['ethnicity'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Quê quán</label>
            <input type="text" name="hometown" class="form-control" value="<?= e($editing['hometown'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Địa chỉ</label>
            <input type="text" name="address" class="form-control" value="<?= e($editing['address'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">SĐT HS</label>
            <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">Họ tên PH</label>
            <input type="text" name="parent_name" class="form-control" value="<?= e($editing['parent_name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label small">SĐT PH</label>
            <input type="text" name="parent_phone" class="form-control" value="<?= e($editing['parent_phone'] ?? '') ?>"></div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="boarder" id="brd" <?= !empty($editing['boarder'])?'checked':'' ?>>
            <label class="form-check-label" for="brd">Nội trú</label></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Phòng KTX</label>
              <input type="text" name="room_ktx" class="form-control" value="<?= e($editing['room_ktx'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small">Nhóm ăn</label>
              <input type="text" name="meal_group" class="form-control" value="<?= e($editing['meal_group'] ?? '') ?>"></div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" id="sact" <?= ($editing === null || !empty($editing['active']))?'checked':'' ?>>
            <label class="form-check-label" for="sact">Đang học</label></div>
          <button class="btn btn-primary w-100" type="submit">Lưu</button>
          <?php if ($editing): ?><a href="?tab=students" class="btn btn-outline-secondary w-100 mt-2">Hủy</a><?php endif; ?>
        </form>
      </div></div>
    </div>
    <div class="col-xl-9 col-lg-8">
      <div class="card card-soft"><div class="card-body">
        <h5 class="mb-2">Bảng học sinh đầy đủ (<?= count($students) ?>)</h5>
        <div class="table-responsive">
          <table class="table table-hover table-full table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>STT</th><th>Mã</th><th>Họ tên</th><th>Lớp</th><th>GT</th><th>Ngày sinh</th><th>Dân tộc</th>
                <th>SĐT</th><th>PH</th><th>SĐT PH</th><th>Nội trú</th><th>P.KTX</th><th>Nhóm ăn</th><th>TT</th><th></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$students): ?>
              <tr><td colspan="15" class="text-muted text-center py-4">Chưa có — dùng mẫu CSV hoặc thêm bên trái.</td></tr>
            <?php else: foreach ($students as $i => $s): ?>
              <tr class="<?= empty($s['active'])?'table-secondary':'' ?>">
                <td><?= $i+1 ?></td>
                <td class="small"><?= e($s['code'] ?? '') ?></td>
                <td><strong><?= e($s['name'] ?? '') ?></strong></td>
                <td><?= e(class_name_by_id($s['class_id'] ?? '', $classes)) ?></td>
                <td><?= e($s['gender'] ?? '') ?></td>
                <td class="small"><?= e($s['dob'] ?? '') ?></td>
                <td class="small"><?= e($s['ethnicity'] ?? '') ?></td>
                <td class="small"><?= e($s['phone'] ?? '') ?></td>
                <td class="small"><?= e($s['parent_name'] ?? '') ?></td>
                <td class="small"><?= e($s['parent_phone'] ?? '') ?></td>
                <td><?= !empty($s['boarder']) ? '<span class="badge bg-info">Có</span>' : '' ?></td>
                <td class="small"><?= e($s['room_ktx'] ?? '') ?></td>
                <td class="small"><?= e($s['meal_group'] ?? '') ?></td>
                <td><?= !empty($s['active']) ? '<span class="badge bg-success">Học</span>' : '<span class="badge bg-secondary">Nghỉ</span>' ?></td>
                <td class="text-nowrap">
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
