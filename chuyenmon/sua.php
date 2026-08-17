<?php
$page_title = 'Sửa phân công';
require_once 'includes/functions.php';
require_login();

$type = $_GET['type'] ?? 'day';
$id = $_GET['id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'day';
    $id = $_POST['id'] ?? '';

    if ($type === 'day') {
        $assignments = get_assignments();
        $found = false;
        foreach ($assignments as &$a) {
            if ($a['id'] === $id) {
                $a['teacher'] = trim($_POST['teacher'] ?? $a['teacher']);
                $a['subject'] = trim($_POST['subject'] ?? $a['subject']);
                $a['class'] = trim($_POST['class_name'] ?? $a['class']);
                $a['note'] = trim($_POST['note'] ?? '');
                $pm = trim($_POST['periods'] ?? '');
                if ($pm !== '' && is_numeric($pm)) {
                    $a['periods'] = round(floatval($pm), 2);
                } else {
                    $p = get_periods($a['subject'], $a['class']);
                    if ($p !== null) $a['periods'] = $p;
                }
                $found = true;
                break;
            }
        }
        unset($a);
        if ($found) {
            save_assignments($assignments);
            flash('Đã cập nhật phân công dạy.', 'success');
        }
        header('Location: ' . BASE_URL . 'danhsach.php'); exit;
    }

    if ($type === 'role') {
        $items = get_role_assignments();
        $roles = get_roles();
        $found = false;
        foreach ($items as &$a) {
            if ($a['id'] === $id) {
                $a['teacher'] = trim($_POST['teacher'] ?? $a['teacher']);
                $a['role'] = trim($_POST['role'] ?? $a['role']);
                $a['class'] = trim($_POST['class_name'] ?? '');
                $a['note'] = trim($_POST['note'] ?? '');
                $pm = trim($_POST['periods'] ?? '');
                if ($pm !== '' && is_numeric($pm)) {
                    $a['periods'] = round(floatval($pm), 2);
                } else {
                    foreach ($roles as $r) {
                        if ($r['name'] === $a['role']) {
                            $a['periods'] = $r['periods'] ?? 0;
                            break;
                        }
                    }
                }
                $found = true;
                break;
            }
        }
        unset($a);
        if ($found) {
            save_role_assignments($items);
            flash('Đã cập nhật kiêm nhiệm.', 'success');
        }
        header('Location: ' . BASE_URL . 'danhsach.php?tab=kiemnhiem'); exit;
    }
}

$item = null;
if ($type === 'day') {
    foreach (get_assignments() as $a) {
        if ($a['id'] === $id) { $item = $a; break; }
    }
} else {
    foreach (get_role_assignments() as $a) {
        if ($a['id'] === $id) { $item = $a; break; }
    }
}

if (!$item) {
    flash('Không tìm thấy phân công.', 'danger');
    header('Location: ' . BASE_URL . 'danhsach.php');
    exit;
}

require_once 'includes/header.php';
$teachers = get_teachers_sorted();
$subjects = array_keys(get_subjects()); sort($subjects);
$classes = get_classes();
$roles = get_roles();
?>

<h3 class="mb-4"><i class="bi bi-pencil"></i> Sửa <?= $type === 'role' ? 'kiêm nhiệm' : 'phân công dạy' ?></h3>

<div class="row justify-content-center">
<div class="col-md-6">
<div class="card">
<div class="card-body">
<form method="post">
<input type="hidden" name="type" value="<?= e($type) ?>">
<input type="hidden" name="id" value="<?= e($id) ?>">

<div class="mb-3">
<label class="form-label fw-semibold">Giáo viên *</label>
<select name="teacher" class="form-select" required>
<?php foreach ($teachers as $t): ?>
<option value="<?= e($t) ?>" <?= ($item['teacher'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
<?php endforeach; ?>
</select>
</div>

<?php if ($type === 'day'): ?>
<div class="mb-3">
<label class="form-label fw-semibold">Môn học *</label>
<select name="subject" class="form-select" required>
<?php foreach ($subjects as $s): ?>
<option value="<?= e($s) ?>" <?= ($item['subject'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label fw-semibold">Lớp *</label>
<select name="class_name" class="form-select" required>
<?php foreach ($classes as $c): ?>
<option value="<?= e($c) ?>" <?= ($item['class'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
</select>
</div>
<?php else: ?>
<div class="mb-3">
<label class="form-label fw-semibold">Chức vụ *</label>
<select name="role" class="form-select" required>
<?php foreach ($roles as $r): ?>
<option value="<?= e($r['name']) ?>" <?= ($item['role'] ?? '') === $r['name'] ? 'selected' : '' ?>>
<?= e($r['name']) ?> (<?= e($r['periods'] ?? 0) ?> tiết)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label fw-semibold">Lớp</label>
<select name="class_name" class="form-select">
<option value="">-- Không --</option>
<?php foreach ($classes as $c): ?>
<option value="<?= e($c) ?>" <?= ($item['class'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<div class="mb-3">
<label class="form-label fw-semibold">Số tiết</label>
<input type="number" name="periods" class="form-control" step="0.01" min="0" max="40" value="<?= e($item['periods'] ?? '') ?>">
<div class="form-text">Cho phép số lẻ đến 0,01 (vd: 1,25 · 0,75)</div>
</div>
<div class="mb-3">
<label class="form-label">Ghi chú</label>
<input type="text" name="note" class="form-control" value="<?= e($item['note'] ?? '') ?>">
</div>

<div class="d-flex gap-2">
<button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Lưu</button>
<a href="<?= BASE_URL ?>danhsach.php<?= $type === 'role' ? '?tab=kiemnhiem' : '' ?>" class="btn btn-outline-secondary">Hủy</a>
</div>
</form>
</div></div></div></div>

<?php require_once 'includes/footer.php'; ?>
