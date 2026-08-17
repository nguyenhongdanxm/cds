<?php
$page_title = 'Danh sách phân công';
require_once 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_day_id'])) {
        $id = $_POST['delete_day_id'];
        save_assignments(array_values(array_filter(get_assignments(), fn($a) => $a['id'] !== $id)));
        flash('Đã xóa phân công dạy.', 'success');
    }
    if (isset($_POST['delete_role_id'])) {
        $id = $_POST['delete_role_id'];
        save_role_assignments(array_values(array_filter(get_role_assignments(), fn($a) => $a['id'] !== $id)));
        flash('Đã xóa kiêm nhiệm.', 'success');
    }
    header('Location: ' . BASE_URL . 'danhsach.php' . (!empty($_POST['keep_q']) ? '?q=' . urlencode($_POST['keep_q']) : ''));
    exit;
}

require_once 'includes/header.php';

$assignments = get_assignments();
$role_items = get_role_assignments();
$teachers_all = get_teachers_sorted();
$loads = get_teacher_loads();

$f_q = trim($_GET['q'] ?? '');
$f_cm = trim($_GET['cm'] ?? '');
$f_level = trim($_GET['level'] ?? '');
$f_group = trim($_GET['group'] ?? ''); // khxh | khtn

// Gom theo giáo viên
$day_by = [];
foreach ($assignments as $a) $day_by[$a['teacher']][] = $a;
$role_by = [];
foreach ($role_items as $a) $role_by[$a['teacher']][] = $a;

// Tập GV có phân công + toàn bộ danh sách (để hiện cả người chưa giao)
$names = sort_teachers_by_ten(array_unique(array_merge(
    $teachers_all,
    array_keys($day_by),
    array_keys($role_by)
)));

// Lọc
$names = array_values(array_filter($names, function($t) use ($f_q, $f_cm, $f_level, $f_group, $day_by, $role_by) {
    if ($f_q && mb_stripos($t, $f_q) === false) return false;
    $flags = get_teacher_flags($t);
    if ($f_cm !== '') {
        $cm = $flags['chuyen_mon'] ?? [];
        if (!in_array($f_cm, $cm, true)) return false;
    }
    if ($f_level === 'THCS' && empty($flags['thcs'])) return false;
    if ($f_level === 'THPT' && empty($flags['thpt'])) return false;
    if ($f_group === 'khxh' && empty($flags['khxh'])) return false;
    if ($f_group === 'khtn' && empty($flags['khtn'])) return false;
    return true;
}));

$subject_names = array_keys(get_subjects()); sort($subject_names);

// Format phân công dạy: "Ngữ văn (6A, 7A); Toán (8B)"
function fmt_day_items($items) {
    if (!$items) return '';
    $by_sub = [];
    foreach ($items as $a) {
        $s = $a['subject'] ?? '';
        $cls = $a['class'] ?? '';
        $p = $a['periods'] ?? '';
        if ($s === '') continue;
        if (!isset($by_sub[$s])) $by_sub[$s] = [];
        $by_sub[$s][] = $cls . ($p !== '' && $p !== null ? '' : '');
        // store class only, group later
    }
    // Better: Ngữ văn (6A), Ngữ văn (7A) → Ngữ văn (6A, 7A)
    $by_sub = [];
    foreach ($items as $a) {
        $s = $a['subject'] ?? '';
        if ($s === '') continue;
        $by_sub[$s][] = $a['class'] ?? '';
    }
    $parts = [];
    foreach ($by_sub as $s => $classes) {
        $classes = array_filter($classes, fn($c) => $c !== '');
        $parts[] = $s . ($classes ? ' (' . implode(', ', $classes) . ')' : '');
    }
    return implode('; ', $parts);
}

function fmt_role_items($items) {
    if (!$items) return '';
    $parts = [];
    foreach ($items as $a) {
        $r = $a['role'] ?? '';
        $c = $a['class'] ?? '';
        if ($r === '') continue;
        $parts[] = $r . ($c !== '' ? ' (' . $c . ')' : '');
    }
    return implode('; ', $parts);
}
?>
<style>
.badge-periods{
  background:#e8f0fe!important;
  color:#1F4E79!important;
  font-weight:700;
  font-size:.95rem;
  min-width:2.2rem;
  display:inline-block;
}
.col-day{max-width:280px}
.col-role{max-width:200px}
.cm-badge{background:#d1e7dd;color:#0f5132;font-weight:500}
.table-pc td{vertical-align:middle}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
<h3 class="mb-0"><i class="bi bi-list-ul"></i> Danh sách phân công</h3>
<div class="d-flex gap-2">
<a href="<?= BASE_URL ?>them.php" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Thêm phân công</a>
<a href="<?= BASE_URL ?>xuat_bang.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Xuất bảng</a>
</div>
</div>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
<div class="col-md-3">
<label class="form-label small mb-0">Tìm giáo viên</label>
<input type="text" name="q" class="form-control form-control-sm" value="<?= e($f_q) ?>" placeholder="Họ tên...">
</div>
<div class="col-md-2">
<label class="form-label small mb-0">Chuyên môn</label>
<select name="cm" class="form-select form-select-sm">
<option value="">Tất cả</option>
<?php foreach ($subject_names as $s): ?>
<option value="<?= e($s) ?>" <?= $f_cm===$s?'selected':'' ?>><?= e($s) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<label class="form-label small mb-0">Cấp</label>
<select name="level" class="form-select form-select-sm">
<option value="">Tất cả</option>
<option value="THCS" <?= $f_level==='THCS'?'selected':'' ?>>THCS</option>
<option value="THPT" <?= $f_level==='THPT'?'selected':'' ?>>THPT</option>
</select>
</div>
<div class="col-md-2">
<label class="form-label small mb-0">Tổ</label>
<select name="group" class="form-select form-select-sm">
<option value="">Tất cả</option>
<option value="khxh" <?= $f_group==='khxh'?'selected':'' ?>>Tổ KHXH</option>
<option value="khtn" <?= $f_group==='khtn'?'selected':'' ?>>Tổ KHTN</option>
</select>
</div>
<div class="col-auto"><button class="btn btn-outline-primary btn-sm">Lọc</button>
<a href="<?= BASE_URL ?>danhsach.php" class="btn btn-outline-secondary btn-sm">Xóa lọc</a></div>
</form>
</div></div>

<div class="card">
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover table-striped table-pc mb-0">
<thead>
<tr class="align-middle">
<th style="width:4%">STT</th>
<th style="width:14%">Giáo viên</th>
<th style="width:12%">Chuyên môn</th>
<th style="width:28%">Phân công dạy</th>
<th style="width:18%">Kiêm nhiệm</th>
<th class="text-center" style="width:8%">Số tiết</th>
<th style="width:10%">Ghi chú</th>
<th style="width:6%"></th>
</tr>
</thead>
<tbody>
<?php $stt = 0; foreach ($names as $t):
    $days = $day_by[$t] ?? [];
    $roles = $role_by[$t] ?? [];
    // Chỉ ẩn người không có gì khi đang lọc rỗng? Hiện tất cả GV trong danh sách đã lọc.
    $load = $loads[$t] ?? null;
    $total = $load['total'] ?? 0;
    $quota = get_quota($t);
    $diff = $total - $quota;
    $cm = get_teacher_chuyen_mon($t);
    $day_txt = fmt_day_items($days);
    $role_txt = fmt_role_items($roles);
    $notes = [];
    foreach ($days as $a) if (!empty($a['note'])) $notes[] = $a['note'];
    foreach ($roles as $a) if (!empty($a['note'])) $notes[] = $a['note'];
    $note_txt = implode('; ', array_unique($notes));
    $stt++;
    $diffClass = abs($diff) < 0.05 ? 'text-success' : ($diff > 0 ? 'text-danger fw-bold' : 'text-warning fw-semibold');
?>
<tr>
<td><?= $stt ?></td>
<td>
<strong><?= e($t) ?></strong>
<div class="small text-muted"><?= e(get_teacher_level($t)) ?></div>
</td>
<td>
<?php if ($cm): foreach ($cm as $s): ?>
<span class="badge cm-badge me-1 mb-1"><?= e($s) ?></span>
<?php endforeach; else: ?>
<span class="text-muted small">—</span>
<?php endif; ?>
</td>
<td class="col-day small">
<?php if ($days): ?>
<?php foreach ($days as $a): ?>
<span class="d-inline-block me-1 mb-1">
<span class="badge bg-secondary-subtle text-dark border"><?= e($a['subject']) ?> (<?= e($a['class']) ?>)</span>
</span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-muted">—</span>
<?php endif; ?>
</td>
<td class="col-role small">
<?php if ($roles): ?>
<?php foreach ($roles as $a): ?>
<span class="badge bg-info-subtle text-dark border me-1 mb-1">
<?= e($a['role']) ?><?= !empty($a['class']) ? ' ('.e($a['class']).')' : '' ?>
</span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-muted">—</span>
<?php endif; ?>
</td>
<td class="text-center">
<span class="badge badge-periods"><?= number_format($total, 2) ?></span>
<div class="small <?= $diffClass ?>"><?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 2) ?> / <?= number_format($quota, 0) ?></div>
</td>
<td class="small text-muted"><?= e($note_txt) ?></td>
<td class="text-nowrap">
<a href="<?= BASE_URL ?>them.php" class="btn btn-outline-primary btn-sm" title="Sửa trên bàn làm việc"><i class="bi bi-pencil"></i></a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$names): ?>
<tr><td colspan="8" class="text-center text-muted py-4">Không có dữ liệu phù hợp.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<div class="card-footer d-flex flex-wrap justify-content-between gap-2 small text-muted">
<span>Tổng: <strong><?= count($names) ?></strong> giáo viên · Sắp theo tên</span>
<span>Số tiết = dạy + kiêm nhiệm · Dòng dưới là chênh lệch so với định mức</span>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
