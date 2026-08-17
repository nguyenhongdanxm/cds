<?php
$page_title = 'Kết quả phân công';
require_once 'includes/functions.php';

// Khách chưa đăng nhập → trang tra cứu gọn
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'tracuu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $note = trim($_POST['note'] ?? '');
        $copy = $_POST['copy_from'] ?? '';
        if ($copy === '') $copy = null;
        if (!$name) {
            flash('Vui lòng nhập tên phiên bản.', 'danger');
        } else {
            $id = create_version($name, $date, $note, $copy);
            flash('Đã tạo phiên bản: ' . $name, 'success');
            header('Location: ' . BASE_URL . 'ketqua.php?v=' . urlencode($id));
            exit;
        }
    }

    if ($action === 'activate') {
        $id = $_POST['id'] ?? '';
        if (get_version($id)) {
            set_active_version_id($id);
            flash('Đã chọn phiên bản để làm việc.', 'success');
        }
        header('Location: ' . BASE_URL . 'ketqua.php?v=' . urlencode($id));
        exit;
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $versions = get_versions();
        if (count($versions) <= 1) {
            flash('Phải giữ ít nhất 1 phiên bản.', 'warning');
        } else {
            $versions = array_values(array_filter($versions, fn($v) => $v['id'] !== $id));
            save_versions($versions);
            @unlink(assignments_file($id));
            @unlink(role_assignments_file($id));
            if (get_active_version_id() === $id) {
                set_active_version_id($versions[0]['id']);
            }
            flash('Đã xóa phiên bản.', 'success');
        }
        header('Location: ' . BASE_URL . 'ketqua.php');
        exit;
    }
}

require_once 'includes/header.php';

$versions = get_versions();
usort($versions, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

$view_id = $_GET['v'] ?? get_active_version_id();
if (!get_version($view_id) && $versions) $view_id = $versions[0]['id'];
$view = get_version($view_id);
$active_id = get_active_version_id();

$assignments = $view_id ? get_assignments($view_id) : [];
$role_assignments = $view_id ? get_role_assignments($view_id) : [];
$loads = $view_id ? get_teacher_loads($view_id) : [];
$stats = $view_id ? get_assignment_stats($view_id) : null;

$by_teacher = [];
foreach ($assignments as $a) { $by_teacher[$a['teacher']][] = $a; }
$roles_by_teacher = [];
foreach ($role_assignments as $a) { $roles_by_teacher[$a['teacher']][] = $a; }
$all_names = sort_teachers_by_ten(array_unique(array_merge(array_keys($by_teacher), array_keys($roles_by_teacher))));

$total_all = array_sum(array_column($loads, 'total'));
$total_day = array_sum(array_column($loads, 'day'));
$total_role = array_sum(array_column($loads, 'role'));
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h3 class="mb-0"><i class="bi bi-clipboard-data"></i> Kết quả phân công</h3>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($view_id): ?>
        <a href="<?= BASE_URL ?>thongke.php?v=<?= e($view_id) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-bar-chart-line"></i> Thống kê
        </a>
        <a href="<?= BASE_URL ?>tracuu.php?v=<?= e($view_id) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-search"></i> Tra cứu GV
        </a>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
            <i class="bi bi-plus-lg"></i> Tạo phiên bản mới
        </button>
    </div>
</div>

<div class="card mb-4">
<div class="card-header">Các phiên bản phân công</div>
<div class="card-body p-0">
<?php if ($versions): ?>
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>Tên</th><th>Ngày phân công</th><th>Ghi chú</th><th class="text-center">Trạng thái</th><th></th></tr></thead>
<tbody>
<?php foreach ($versions as $v): ?>
<tr class="<?= $v['id'] === $view_id ? 'table-primary' : '' ?>">
    <td><strong><?= e($v['name']) ?></strong></td>
    <td><?= e($v['date'] ?? '') ?></td>
    <td class="text-muted small"><?= e($v['note'] ?? '') ?></td>
    <td class="text-center">
        <?php if ($v['id'] === $active_id): ?>
        <span class="badge bg-success">Đang làm việc</span>
        <?php endif; ?>
        <?php if ($v['id'] === $view_id): ?>
        <span class="badge bg-primary">Đang xem</span>
        <?php endif; ?>
    </td>
    <td class="text-nowrap">
        <a href="<?= BASE_URL ?>ketqua.php?v=<?= e($v['id']) ?>" class="btn btn-outline-primary btn-sm">Xem</a>
        <a href="<?= BASE_URL ?>thongke.php?v=<?= e($v['id']) ?>" class="btn btn-outline-secondary btn-sm" title="Thống kê"><i class="bi bi-bar-chart-line"></i></a>
        <?php if ($v['id'] !== $active_id): ?>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="id" value="<?= e($v['id']) ?>">
            <button class="btn btn-outline-success btn-sm">Chọn làm việc</button>
        </form>
        <?php endif; ?>
        <?php if (count($versions) > 1): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Xóa phiên bản này? Dữ liệu phân công sẽ mất.')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($v['id']) ?>">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="p-3 text-muted">Chưa có phiên bản nào.</div>
<?php endif; ?>
</div></div>

<?php if ($view): ?>
<div class="alert alert-light border mb-3">
    <strong><?= e($view['name']) ?></strong>
    · Ngày phân công: <strong><?= e($view['date'] ?? '') ?></strong>
    <?php if ($view['id'] === $active_id): ?>
    <span class="badge bg-success ms-2">Đang làm việc (Thêm/Sửa sẽ ghi vào phiên bản này)</span>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>thongke.php?v=<?= e($view_id) ?>" class="ms-2">Xem thống kê chi tiết →</a>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3"><div class="card stat-card py-2"><div class="number fs-4"><?= number_format($total_day, 1) ?></div><div class="label">Tổng tiết dạy môn</div></div></div>
    <div class="col-md-3"><div class="card stat-card py-2"><div class="number fs-4 text-info"><?= number_format($total_role, 1) ?></div><div class="label">Tổng tiết kiêm nhiệm</div></div></div>
    <div class="col-md-3"><div class="card stat-card py-2"><div class="number fs-4 text-primary"><?= number_format($total_all, 1) ?></div><div class="label">Tổng cộng tiết</div></div></div>
    <?php if ($stats): ?>
    <div class="col-md-3"><div class="card stat-card py-2">
        <div class="number fs-4 <?= $stats['slots_missing']?'text-danger':'text-success' ?>"><?= (int)$stats['slots_missing'] ?></div>
        <div class="label">Ô thiếu môn+lớp · <a href="<?= BASE_URL ?>thongke.php?v=<?= e($view_id) ?>" class="small">Chi tiết</a></div>
    </div></div>
    <?php endif; ?>
</div>

<?php if ($all_names): ?>
<div class="accordion" id="acc">
<?php $idx = 0; foreach ($all_names as $teacher):
    $idx++;
    $items = $by_teacher[$teacher] ?? [];
    $roles = $roles_by_teacher[$teacher] ?? [];
    $load = $loads[$teacher] ?? ['day'=>0,'role'=>0,'total'=>0,'class_count'=>0];
    $by_subject = [];
    foreach ($items as $a) $by_subject[$a['subject']][] = $a['class'].'('.$a['periods'].')';
    ksort($by_subject);
    $lines = [];
    foreach ($by_subject as $s => $parts) $lines[] = $s . ': ' . implode(', ', $parts);
?>
<div class="accordion-item mb-2 border-0 shadow-sm rounded">
    <h2 class="accordion-header">
        <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#t<?= $idx ?>">
            <strong class="me-2"><?= e($teacher) ?></strong>
            <?php if ($roles): ?><span class="badge bg-info text-dark me-1"><?= count($roles) ?> KN</span><?php endif; ?>
            <span class="badge bg-secondary me-1"><?= $load['class_count'] ?> lớp</span>
            <span class="badge bg-primary ms-auto me-2"><?= number_format($load['total'], 1) ?> tiết</span>
            <small class="text-muted me-2 d-none d-md-inline">(dạy <?= number_format($load['day'],1) ?> + KN <?= number_format($load['role'],1) ?>)</small>
        </button>
    </h2>
    <div id="t<?= $idx ?>" class="accordion-collapse collapse" data-bs-parent="#acc">
        <div class="accordion-body">
            <?php if ($roles): ?>
            <strong><i class="bi bi-person-badge"></i> Kiêm nhiệm</strong>
            <table class="table table-sm table-bordered mt-2 mb-3">
            <thead><tr><th>Chức vụ</th><th>Lớp</th><th class="text-center">Tiết</th><th>Ghi chú</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
            <tr><td><span class="badge bg-info text-dark"><?= e($r['role']) ?></span></td><td><?= e($r['class']??'') ?></td><td class="text-center"><?= e($r['periods']??0) ?></td><td class="text-muted"><?= e($r['note']??'') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
            <?php if ($lines): ?>
            <strong><i class="bi bi-book"></i> Dạy môn</strong>
            <pre class="mt-2 mb-2 p-2 bg-light border rounded small"><?= e(implode("\n", $lines)) ?></pre>
            <table class="table table-sm table-bordered">
            <thead><tr><th>Môn</th><th>Lớp</th><th class="text-center">Tiết</th><th>Ghi chú</th></tr></thead>
            <tbody>
            <?php foreach ($items as $a): ?>
            <tr><td><?= e($a['subject']) ?></td><td><?= e($a['class']) ?></td><td class="text-center"><?= e($a['periods']) ?></td><td class="text-muted"><?= e($a['note']??'') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
            <div class="text-end">
                <span class="badge bg-light text-dark border">Dạy: <?= number_format($load['day'],1) ?></span>
                <span class="badge bg-info text-dark">KN: <?= number_format($load['role'],1) ?></span>
                <span class="badge bg-primary">Tổng: <?= number_format($load['total'],1) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-info">Phiên bản này chưa có dữ liệu phân công.
 <a href="<?= BASE_URL ?>them.php">Thêm phân công</a>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="modalCreate" tabindex="-1">
<div class="modal-dialog">
<form method="post" class="modal-content">
<input type="hidden" name="action" value="create">
<div class="modal-header"><h5 class="modal-title">Tạo phiên bản phân công mới</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label fw-semibold">Tên phiên bản *</label>
        <input type="text" name="name" class="form-control" placeholder="VD: Phân công lần 2" required
               value="Phân công lần <?= count($versions) + 1 ?>">
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Ngày phân công *</label>
        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Sao chép từ phiên bản</label>
        <select name="copy_from" class="form-select">
            <option value="">— Không sao chép (trống) —</option>
            <?php foreach ($versions as $v): ?>
            <option value="<?= e($v['id']) ?>"><?= e($v['name']) ?> (<?= e($v['date'] ?? '') ?>)</option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Chọn để copy toàn bộ phân công dạy + kiêm nhiệm từ phiên bản cũ.</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Ghi chú</label>
        <input type="text" name="note" class="form-control" placeholder="Tùy chọn">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-primary">Tạo phiên bản</button>
</div>
</form>
</div></div>

<?php require_once 'includes/footer.php'; ?>
