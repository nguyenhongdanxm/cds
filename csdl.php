<?php
require_once 'includes/auth.php';
require_once 'includes/csdl_store.php';
require_once 'includes/csdl_sync.php'; // API cho module khác kéo 1 chiều từ CSDL
require_once 'includes/csdl_import_teachers.php';
require_once 'includes/csdl_io.php';
require_login();
require_module('csdl', 'view');
$user = current_user();
$tab = $_GET['tab'] ?? 'overview';
$allowed = ['overview', 'teachers', 'classes', 'students', 'years'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $editActions = ['bulk_delete','io_import','teacher_save','teacher_delete','class_save','class_delete','student_save','student_delete'];
    $yearActions = ['year_set_current','year_delete','year_save'];
    if (in_array($action, $editActions, true)) require_perm_level('csdl.edit', 'edit');
    if (in_array($action, $yearActions, true)) require_perm_level('csdl.year', 'edit');
    if ($action === 'student_save') {
        $targetClass = csdl_class_find(trim($_POST['class_id'] ?? ''));
        if (!$targetClass || !can_class($targetClass['name'] ?? '')) {
            flash('Bạn không có quyền sửa học sinh ngoài lớp được giao.', 'danger');
            header('Location: ' . BASE_URL . 'csdl.php?tab=students'); exit;
        }
    }
    if ($action === 'student_delete') {
        $targetStudent = csdl_student_find(trim($_POST['id'] ?? ''));
        $targetClass = $targetStudent ? csdl_class_find($targetStudent['class_id'] ?? '') : null;
        if (!$targetClass || !can_class($targetClass['name'] ?? '')) {
            flash('Bạn không có quyền xóa học sinh ngoài lớp được giao.', 'danger');
            header('Location: ' . BASE_URL . 'csdl.php?tab=students'); exit;
        }
    }

    if ($action === 'bulk_delete') {
        require __DIR__ . '/includes/csdl_post_extra.php';
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

    if ($action === 'teacher_save') {
        $group = trim($_POST['to_chuyen_mon'] ?? '');
        csdl_teacher_save([
            'id' => trim($_POST['id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'code' => trim($_POST['code'] ?? ''),
            'cccd' => trim($_POST['cccd'] ?? ''),
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
            'teaching_level' => trim($_POST['teaching_level'] ?? ''),
            'join_date' => trim($_POST['join_date'] ?? ''),
            'bac' => trim($_POST['bac'] ?? ''),
            'hang' => trim($_POST['hang'] ?? ''),
            'cap_luong' => trim($_POST['cap_luong'] ?? ''),
            'he_so' => trim($_POST['he_so'] ?? ''),
            'he_so_from' => trim($_POST['he_so_from'] ?? ''),
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
            'cccd' => trim($_POST['cccd'] ?? ''),
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
    if ($action === 'year_delete') {
        csdl_year_delete(trim($_POST['id'] ?? ''));
        flash('Đã xóa năm học.', 'warning');
        header('Location: ' . BASE_URL . 'csdl.php?tab=years');
        exit;
    }
    if ($action === 'year_save') {
        $id = trim($_POST['id'] ?? '');
        $saved = csdl_year_save([
            'id' => $id,
            'label' => trim($_POST['label'] ?? ''),
            'start' => trim($_POST['start'] ?? ''),
            'end' => trim($_POST['end'] ?? ''),
            'is_current' => !empty($_POST['is_current']),
        ]);
        if (!empty($_POST['is_current'])) {
            $nid = $id ?: $saved;
            if ($nid) csdl_year_set_current($nid);
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
$allowedClassNames = allowed_classes();
if ($allowedClassNames !== null) {
    $allowedClassIds = [];
    foreach ($classes as $classRow) {
        if (in_array((string)($classRow['name'] ?? ''), $allowedClassNames, true)) {
            $allowedClassIds[] = (string)($classRow['id'] ?? '');
        }
    }
    $classes = array_values(array_filter($classes, fn($row) => in_array((string)($row['id'] ?? ''), $allowedClassIds, true)));
    $students = array_values(array_filter($students, fn($row) => in_array((string)($row['class_id'] ?? ''), $allowedClassIds, true)));
}
$years = csdl_years_all();
$edit_id = $_GET['edit'] ?? '';
$canCsdlEdit = can_edit_perm('csdl.edit');
$canYearEdit = can_edit_perm('csdl.year');
$canEditCurrent = $tab === 'years' ? $canYearEdit : $canCsdlEdit;

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
function kn_text_from($editing) {
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
    return rtrim($kn_text);
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
.table thead th{font-size:.72rem;text-transform:uppercase;letter-spacing:.02em;color:#667;white-space:nowrap;background:#eef3f8}
.badge-kn{font-size:.7rem;font-weight:600;background:#e8f0fe;color:#1a56a8;margin:.1rem;display:inline-block}
.table-full td{font-size:.85rem;vertical-align:middle}
.modal-xl .modal-body{max-height:70vh;overflow-y:auto}
.form-label.small{font-weight:600;color:#445}
<?php if (!$canEditCurrent): ?>
form[method="post"],button[data-bs-toggle="modal"],a[href*="edit="],.row-chk{display:none!important}
<?php endif; ?>
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
      <div class="text-muted small">Nguồn chuẩn hệ sinh thái — module khác đồng bộ <strong>một chiều từ đây</strong></div>
    </div>
    <div class="text-muted small">Năm học: <strong><?= e($stats['year']) ?></strong></div>
  </div>

  <ul class="nav nav-pills gap-1 mb-4 flex-wrap">
    <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?tab=overview"><i class="bi bi-grid"></i> Tổng quan</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='teachers'?'active':'' ?>" href="?tab=teachers"><i class="bi bi-people"></i> Giáo viên</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='classes'?'active':'' ?>" href="?tab=classes"><i class="bi bi-building"></i> Lớp / khối</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='students'?'active':'' ?>" href="?tab=students"><i class="bi bi-mortarboard"></i> Học sinh</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='years'?'active':'' ?>" href="?tab=years"><i class="bi bi-calendar3"></i> Năm học</a></li>
  </ul>

<?php if ($tab === 'overview'): ?>
  <?php include __DIR__ . '/includes/csdl_tab_overview.php'; ?>

<?php elseif ($tab === 'teachers'): ?>
  <?php
    $editing = null;
    if ($edit_id) {
      foreach ($teachers as $t) if (($t['id'] ?? '') === $edit_id) { $editing = $t; break; }
    }
    $kn_text = kn_text_from($editing);
    $io_entity = 'teachers';
    include __DIR__ . '/includes/csdl_io_panel.php';
    $bulk_entity = 'teachers';
    include __DIR__ . '/includes/csdl_bulk_bar.php';
  ?>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Bảng giáo viên (<?= count($teachers) ?>)</h5>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTeacher" onclick="resetTeacherForm()">
      <i class="bi bi-plus-lg"></i> Thêm giáo viên
    </button>
  </div>
  <div class="card card-soft"><div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-full table-sm align-middle mb-0">
        <thead>
          <tr>
            <th style="width:2rem"></th>
            <th>STT</th><th>Mã</th><th>Họ tên</th><th>CCCD</th><th>GT</th><th>Ngày sinh</th>
            <th>SĐT</th><th>Chuyên môn</th><th>Tổ</th><th>Kiêm nhiệm</th><th>TT</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$teachers): ?>
          <tr><td colspan="13" class="text-muted text-center py-4">Chưa có — nhập CSV hoặc thêm mới.</td></tr>
        <?php else: foreach ($teachers as $i => $t):
          $kn = csdl_format_kiem_nhiem($t['kiem_nhiem'] ?? []);
          $to = $t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '';
        ?>
          <tr class="<?= empty($t['active'])?'table-secondary':'' ?>">
            <td><input type="checkbox" class="form-check-input row-chk row-chk-teachers" value="<?= e($t['id']) ?>"></td>
            <td><?= $i+1 ?></td>
            <td class="small"><?= e($t['code'] ?? '') ?></td>
            <td><strong><?= e($t['name'] ?? '') ?></strong></td>
            <td class="small"><?= e($t['cccd'] ?? '') ?></td>
            <td><?= e($t['gender'] ?? '') ?></td>
            <td class="small"><?= e($t['dob'] ?? '') ?></td>
            <td class="small"><?= e($t['phone'] ?? '') ?></td>
            <td class="small"><?= e($t['specialty'] ?? '') ?></td>
            <td class="small"><?= e($to) ?></td>
            <td class="small"><?php if ($kn): foreach (explode('; ', $kn) as $p): if($p==='')continue; ?><span class="badge badge-kn"><?= e($p) ?></span><?php endforeach; else: ?>—<?php endif; ?></td>
            <td><?= !empty($t['active']) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Nghỉ</span>' ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="?tab=teachers&edit=<?= urlencode($t['id']) ?>" title="Sửa"><i class="bi bi-pencil"></i></a>
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
  <?php include __DIR__ . '/includes/csdl_modal_teacher.php'; ?>

<?php elseif ($tab === 'classes'): ?>
  <?php
    $editing = null;
    if ($edit_id) { foreach ($classes as $c) if (($c['id'] ?? '') === $edit_id) { $editing = $c; break; } }
    usort($classes, fn($a,$b) => ($a['grade'] ?? 0) <=> ($b['grade'] ?? 0) ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));
    $io_entity = 'classes';
    include __DIR__ . '/includes/csdl_io_panel.php';
    $bulk_entity = 'classes';
    include __DIR__ . '/includes/csdl_bulk_bar.php';
  ?>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Bảng lớp (<?= count($classes) ?>)</h5>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalClass" onclick="document.getElementById('c_id').value='';document.getElementById('modalClassTitle').textContent='Thêm lớp'">
      <i class="bi bi-plus-lg"></i> Thêm lớp
    </button>
  </div>
  <div class="card card-soft"><div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-full table-sm align-middle mb-0">
        <thead><tr><th></th><th>STT</th><th>Lớp</th><th>Khối</th><th>Cấp</th><th>GVCN</th><th>Phòng</th><th>Định mức</th><th>TT</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($classes as $i => $c): ?>
          <tr class="<?= empty($c['active'])?'table-secondary':'' ?>">
            <td><input type="checkbox" class="form-check-input row-chk row-chk-classes" value="<?= e($c['id']) ?>"></td>
            <td><?= $i+1 ?></td>
            <td><strong><?= e($c['name'] ?? '') ?></strong></td>
            <td><?= e((string)($c['grade'] ?? '')) ?></td>
            <td><span class="badge <?= ($c['level']??'')==='THPT'?'bg-primary':'bg-success' ?>"><?= e($c['level'] ?? '') ?></span></td>
            <td class="small"><?= e(teacher_name_by_id($c['homeroom_teacher_id'] ?? '', $teachers)) ?></td>
            <td><?= e($c['room'] ?? '') ?></td>
            <td><?= e((string)($c['capacity'] ?? '')) ?></td>
            <td><?= !empty($c['active']) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Ẩn</span>' ?></td>
            <td class="text-nowrap">
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
  <?php include __DIR__ . '/includes/csdl_modal_class.php'; ?>

<?php elseif ($tab === 'students'): ?>
  <?php
    $editing = null;
    if ($edit_id) { foreach ($students as $s) if (($s['id'] ?? '') === $edit_id) { $editing = $s; break; } }
    $io_entity = 'students';
    include __DIR__ . '/includes/csdl_io_panel.php';
    $bulk_entity = 'students';
    include __DIR__ . '/includes/csdl_bulk_bar.php';
  ?>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Bảng học sinh (<?= count($students) ?>)</h5>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalStudent" onclick="resetStudentForm()">
      <i class="bi bi-plus-lg"></i> Thêm học sinh
    </button>
  </div>
  <div class="card card-soft"><div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-full table-sm align-middle mb-0">
        <thead>
          <tr>
            <th></th><th>STT</th><th>Mã</th><th>Họ tên</th><th>CCCD</th><th>Lớp</th><th>GT</th><th>Ngày sinh</th>
            <th>SĐT</th><th>PH</th><th>Nội trú</th><th>P.KTX</th><th>TT</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$students): ?>
          <tr><td colspan="14" class="text-muted text-center py-4">Chưa có — tải mẫu CSV hoặc thêm mới.</td></tr>
        <?php else: foreach ($students as $i => $s): ?>
          <tr class="<?= empty($s['active'])?'table-secondary':'' ?>">
            <td><input type="checkbox" class="form-check-input row-chk row-chk-students" value="<?= e($s['id']) ?>"></td>
            <td><?= $i+1 ?></td>
            <td class="small"><?= e($s['code'] ?? '') ?></td>
            <td><strong><?= e($s['name'] ?? '') ?></strong></td>
            <td class="small"><?= e($s['cccd'] ?? '') ?></td>
            <td><?= e(class_name_by_id($s['class_id'] ?? '', $classes)) ?></td>
            <td><?= e($s['gender'] ?? '') ?></td>
            <td class="small"><?= e($s['dob'] ?? '') ?></td>
            <td class="small"><?= e($s['phone'] ?? '') ?></td>
            <td class="small"><?= e($s['parent_name'] ?? '') ?></td>
            <td><?= !empty($s['boarder']) ? '<span class="badge bg-info">Có</span>' : '' ?></td>
            <td class="small"><?= e($s['room_ktx'] ?? '') ?></td>
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
  <?php include __DIR__ . '/includes/csdl_modal_student.php'; ?>

<?php elseif ($tab === 'years'): ?>
  <?php include __DIR__ . '/includes/csdl_tab_years.php'; ?>

<?php endif; ?>

</div>
<script>window.CSDL_BASE = <?= json_encode(BASE_URL) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/csdl-bulk.js"></script>
<script>
function resetTeacherForm(){
  var f=document.querySelector('#modalTeacher form');
  if(!f)return;
  f.reset();
  var id=document.getElementById('t_id'); if(id) id.value='';
  var t=document.getElementById('modalTeacherTitle'); if(t) t.textContent='Thêm giáo viên';
  var a=document.getElementById('tact'); if(a) a.checked=true;
}
function resetStudentForm(){
  var f=document.querySelector('#modalStudent form');
  if(!f)return;
  f.reset();
  var id=document.getElementById('s_id'); if(id) id.value='';
  var t=document.getElementById('modalStudentTitle'); if(t) t.textContent='Thêm học sinh';
  var a=document.getElementById('sact'); if(a) a.checked=true;
}
</script>
</body>
</html>
