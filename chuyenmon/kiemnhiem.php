<?php
$page_title = 'Quản lý chức vụ kiêm nhiệm';
require_once 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roles = get_roles();

    if (($_POST['action'] ?? '') === 'update') {
        $idx = intval($_POST['idx'] ?? -1);
        if (isset($roles[$idx])) {
            $roles[$idx]['name'] = trim($_POST['name'] ?? $roles[$idx]['name']);
            $roles[$idx]['need_class'] = isset($_POST['need_class']);
            $roles[$idx]['periods'] = is_numeric($_POST['periods'] ?? '') ? round(floatval($_POST['periods']), 2) : 0;
            $roles[$idx]['note'] = trim($_POST['note'] ?? '');
            save_json(ROLES_FILE, $roles);
            flash('Đã cập nhật chức vụ.', 'success');
        }
        header('Location: ' . BASE_URL . 'kiemnhiem.php'); exit;
    }

    if (($_POST['action'] ?? '') === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $exists = false;
            foreach ($roles as $r) { if ($r['name'] === $name) { $exists = true; break; } }
            if (!$exists) {
                $roles[] = [
                    'name' => $name,
                    'need_class' => isset($_POST['need_class']),
                    'periods' => is_numeric($_POST['periods'] ?? '') ? round(floatval($_POST['periods']), 2) : 0,
                    'note' => trim($_POST['note'] ?? ''),
                ];
                save_json(ROLES_FILE, $roles);
                flash("Đã thêm chức vụ: $name", 'success');
            } else {
                flash('Chức vụ đã tồn tại.', 'warning');
            }
        }
        header('Location: ' . BASE_URL . 'kiemnhiem.php'); exit;
    }

    if (($_POST['action'] ?? '') === 'delete') {
        $idx = intval($_POST['idx'] ?? -1);
        if (isset($roles[$idx])) {
            $name = $roles[$idx]['name'];
            array_splice($roles, $idx, 1);
            save_json(ROLES_FILE, $roles);
            flash("Đã xóa chức vụ: $name", 'success');
        }
        header('Location: ' . BASE_URL . 'kiemnhiem.php'); exit;
    }
}

require_once 'includes/header.php';
$roles = get_roles();
?>

<h3 class="mb-3"><i class="bi bi-person-badge"></i> Quản lý chức vụ Kiêm nhiệm & Số tiết</h3>
<div class="alert alert-info"><i class="bi bi-info-circle"></i> Tạo tên chức vụ và quy định số tiết chuẩn tại đây (cho phép lẻ đến <strong>0,01</strong>). Việc <strong>phân công</strong> kiêm nhiệm thực hiện ở trang <a href="<?= BASE_URL ?>them.php">Thêm phân công</a>.</div>

<div class="row mb-4">
<div class="col-md-5">
<div class="card">
<div class="card-header">Thêm chức vụ mới</div>
<div class="card-body">
<form method="post">
<input type="hidden" name="action" value="add">
<div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Tên chức vụ (VD: GVCN, TTCM)" required></div>
<div class="mb-2"><input type="number" name="periods" class="form-control" step="0.01" min="0" max="20" placeholder="Số tiết (vd: 1.25)" value="1"></div>
<div class="mb-2"><input type="text" name="note" class="form-control" placeholder="Mô tả (tùy chọn)"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="need_class" id="nc"><label class="form-check-label" for="nc">Cần chọn lớp (như GVCN)</label></div>
<button type="submit" class="btn btn-primary w-100">Thêm</button>
</form>
</div></div></div>
</div>

<?php foreach ($roles as $i => $r): ?>
<div class="card mb-2">
<div class="card-body py-2">
<form method="post" class="row g-2 align-items-end">
<input type="hidden" name="action" value="update">
<input type="hidden" name="idx" value="<?= $i ?>">
<div class="col-md-3"><label class="form-label small mb-0">Tên</label>
<input type="text" name="name" class="form-control form-control-sm" value="<?= e($r['name']) ?>" required></div>
<div class="col-md-2"><label class="form-label small mb-0">Số tiết</label>
<input type="number" name="periods" class="form-control form-control-sm" step="0.01" min="0" max="20" value="<?= e($r['periods'] ?? 0) ?>"></div>
<div class="col-md-3"><label class="form-label small mb-0">Mô tả</label>
<input type="text" name="note" class="form-control form-control-sm" value="<?= e($r['note'] ?? '') ?>"></div>
<div class="col-md-2"><div class="form-check mt-3">
<input class="form-check-input" type="checkbox" name="need_class" id="nc<?= $i ?>" <?= !empty($r['need_class']) ? 'checked' : '' ?>>
<label class="form-check-label small" for="nc<?= $i ?>">Cần lớp</label>
</div></div>
<div class="col-md-2 d-flex gap-1">
<button type="submit" class="btn btn-primary btn-sm">Lưu</button>
</div>
</form>
<form method="post" class="mt-1" onsubmit="return confirm('Xóa chức vụ này?')">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="idx" value="<?= $i ?>">
<button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
</form>
</div></div>
<?php endforeach; ?>

<?php require_once 'includes/footer.php'; ?>
