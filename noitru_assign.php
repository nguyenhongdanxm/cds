<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_assignment_store.php';

require_login();
require_perm_level('nt.danhsach', 'edit');

$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'rooms') === 'meals' ? 'meals' : 'rooms';
$label = $mode === 'rooms' ? 'phòng' : 'mâm';
$field = $mode === 'rooms' ? 'room_ktx' : 'meal_group';
$data = noitru_assignments_data();
$boarders = noitru_boarders_live();
$boarders = array_values(array_filter($boarders, static fn($s) => function_exists('can_class') ? can_class($s['class_name'] ?? '') : true));
$boarders = noitru_assignment_apply($boarders);
$user = current_user();
$by = (string)($user['name'] ?? $user['username'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create_groups') {
        $count = max(1, (int)($_POST['group_count'] ?? 1));
        $prefix = trim((string)($_POST['prefix'] ?? ''));
        $names = noitru_assignment_names($mode, $count, $prefix);
        $data[$mode === 'rooms' ? 'room_names' : 'meal_names'] = $names;
        noitru_assignments_save($data, $by);
        flash('Đã tạo danh sách ' . $label . '.', 'success');
    } elseif ($action === 'auto_assign') {
        $namesKey = $mode === 'rooms' ? 'room_names' : 'meal_names';
        $names = $data[$namesKey] ?? [];
        $capacity = max(1, (int)($_POST['capacity'] ?? 8));
        if (!$names) {
            flash('Hãy tạo danh sách ' . $label . ' trước.', 'danger');
        } else {
            $map = $mode === 'rooms'
                ? noitru_assignment_auto_rooms($boarders, $names, $capacity)
                : noitru_assignment_auto_meals($boarders, $names, $capacity);
            $data[$mode] = array_merge($data[$mode] ?? [], $map);
            $data['history'][] = ['mode'=>$mode, 'action'=>'auto', 'by'=>$by, 'at'=>date('c'), 'count'=>count($map)];
            noitru_assignments_save($data, $by);
            flash('Đã tự động chia ' . count($map) . ' học sinh vào ' . $label . '.', 'success');
        }
    } elseif ($action === 'manual_assign') {
        $target = trim((string)($_POST['target'] ?? ''));
        $ids = array_values(array_filter(array_map('strval', $_POST['student_ids'] ?? [])));
        if ($target === '' || !$ids) {
            flash('Hãy chọn ' . $label . ' và ít nhất một học sinh.', 'danger');
        } else {
            foreach ($ids as $id) $data[$mode][$id] = $target;
            $namesKey = $mode === 'rooms' ? 'room_names' : 'meal_names';
            if (!in_array($target, $data[$namesKey] ?? [], true)) $data[$namesKey][] = $target;
            $data['history'][] = ['mode'=>$mode, 'action'=>'manual', 'by'=>$by, 'at'=>date('c'), 'count'=>count($ids), 'target'=>$target];
            noitru_assignments_save($data, $by);
            flash('Đã gán ' . count($ids) . ' học sinh vào ' . $target . '.', 'success');
        }
    } elseif ($action === 'remove_students') {
        $ids = array_values(array_filter(array_map('strval', $_POST['student_ids'] ?? [])));
        foreach ($ids as $id) unset($data[$mode][$id]);
        noitru_assignments_save($data, $by);
        flash('Đã bỏ phân ' . $label . ' cho ' . count($ids) . ' học sinh.', 'warning');
    } elseif ($action === 'clear_all') {
        $data[$mode] = [];
        noitru_assignments_save($data, $by);
        flash('Đã xóa toàn bộ kết quả chia ' . $label . '.', 'warning');
    }
    header('Location: ' . BASE_URL . 'noitru_assign.php?mode=' . $mode);
    exit;
}

$data = noitru_assignments_data();
$boarders = noitru_assignment_apply(noitru_boarders_live());
$boarders = array_values(array_filter($boarders, static fn($s) => function_exists('can_class') ? can_class($s['class_name'] ?? '') : true));
$names = $data[$mode === 'rooms' ? 'room_names' : 'meal_names'] ?? [];
$grouped = [];
$unassigned = [];
foreach ($boarders as $student) {
    $name = trim((string)($student[$field] ?? ''));
    if ($name === '') $unassigned[] = $student;
    else $grouped[$name][] = $student;
}
foreach ($names as $name) if (!isset($grouped[$name])) $grouped[$name] = [];
ksort($grouped, SORT_NATURAL);

$page_title = $mode === 'rooms' ? 'Chia phòng nội trú' : 'Chia mâm ăn';
$tab = 'boarders';
$nt_sec = 'boarders';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($page_title) ?> – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>includes/noitru_layout.css?v=20260731-4" rel="stylesheet">
<style>
.assign-card{border:1px solid #dbe5ee;border-radius:14px;background:#fff;box-shadow:0 3px 12px rgba(15,23,42,.05)}
.assign-summary{font-size:.78rem;color:#64748b}.assign-student{display:flex;gap:.45rem;align-items:flex-start;padding:.42rem .5rem;border-bottom:1px solid #edf2f7}.assign-student:last-child{border-bottom:0}.assign-student label{cursor:pointer;flex:1}.assign-name{font-weight:700;color:#173f65}.assign-meta{font-size:.75rem;color:#64748b}.assign-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.8rem}.assign-group-head{padding:.7rem .85rem;background:#eef5fb;border-bottom:1px solid #dbe5ee}.assign-scroll{max-height:350px;overflow:auto}.sticky-tools{position:sticky;top:.5rem;z-index:5}
</style>
</head>
<body class="nt-body">
<?php require __DIR__ . '/includes/noitru_shell.php'; ?>
<main class="nt-main"><div class="nt-content">
<div class="nt-page-head">
  <div><h4 class="mb-0 fw-bold"><i class="bi <?= $mode === 'rooms' ? 'bi-door-closed' : 'bi-egg-fried' ?>"></i> <?= e($page_title) ?></h4><div class="text-muted small mt-1">Tự động hoặc thủ công; có thể gán nhanh nhiều học sinh, đổi và xóa kết quả.</div></div>
  <div class="d-flex gap-2"><a class="btn btn-sm <?= $mode === 'rooms' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?mode=rooms">Chia phòng</a><a class="btn btn-sm <?= $mode === 'meals' ? 'btn-warning' : 'btn-outline-warning' ?>" href="?mode=meals">Chia mâm</a><a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>noitru_list.php?view=<?= $mode ?>">Trở lại danh sách</a></div>
</div>
<?php show_flash(); ?>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="assign-card p-3 h-100">
      <h6 class="fw-bold">1. Tạo danh sách <?= e($label) ?></h6>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="mode" value="<?= e($mode) ?>"><input type="hidden" name="action" value="create_groups">
        <div class="col-4"><label class="form-label small">Số <?= e($label) ?></label><input class="form-control form-control-sm" type="number" min="1" max="200" name="group_count" value="<?= max(1,count($names)) ?>" required></div>
        <div class="col-5"><label class="form-label small">Tiền tố</label><input class="form-control form-control-sm" name="prefix" placeholder="<?= $mode === 'rooms' ? 'Phòng ' : 'Mâm ' ?>"></div>
        <div class="col-3"><button class="btn btn-sm btn-primary w-100">Tạo</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="assign-card p-3 h-100">
      <h6 class="fw-bold">2. Chia tự động</h6>
      <div class="small text-muted mb-2"><?= $mode === 'rooms' ? 'Ưu tiên cùng giới tính → cùng lớp → cùng khối.' : 'Ưu tiên cùng lớp → cùng khối → cân bằng nam nữ trong từng mâm.' ?></div>
      <form method="post" class="row g-2 align-items-end" onsubmit="return confirm('Tự động chia lại toàn bộ học sinh theo thiết lập này?')">
        <input type="hidden" name="mode" value="<?= e($mode) ?>"><input type="hidden" name="action" value="auto_assign">
        <div class="col-5"><label class="form-label small">Số HS tối đa / <?= e($label) ?></label><input class="form-control form-control-sm" type="number" min="1" max="100" name="capacity" value="<?= $mode === 'rooms' ? 8 : 10 ?>" required></div>
        <div class="col-7"><button class="btn btn-sm btn-success w-100"><i class="bi bi-magic"></i> Chia tự động</button></div>
      </form>
    </div>
  </div>
</div>

<div class="assign-card p-3 mb-3 sticky-tools">
  <h6 class="fw-bold">3. Gán nhanh / đổi thủ công</h6>
  <form method="post" id="bulkAssignForm" class="row g-2 align-items-end">
    <input type="hidden" name="mode" value="<?= e($mode) ?>"><input type="hidden" name="action" value="manual_assign">
    <div class="col-md-5"><label class="form-label small">Chọn <?= e($label) ?></label><input class="form-control form-control-sm" name="target" list="assignNames" required placeholder="Chọn hoặc nhập <?= e($label) ?>"><datalist id="assignNames"><?php foreach ($names as $name): ?><option value="<?= e($name) ?>"><?php endforeach; ?></datalist></div>
    <div class="col-md-4"><div class="small text-muted">Tích nhiều học sinh ở danh sách bên dưới rồi bấm gán.</div></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-person-plus"></i> Gán học sinh đã chọn</button></div>
  </form>
</div>

<div class="mb-3 d-flex justify-content-between align-items-center">
  <div><strong><?= count($boarders) ?></strong> HS · <span class="text-success"><?= count($boarders)-count($unassigned) ?> đã chia</span> · <span class="text-danger"><?= count($unassigned) ?> chưa chia</span></div>
  <form method="post" onsubmit="return confirm('Xóa toàn bộ kết quả chia <?= e($label) ?>?')"><input type="hidden" name="mode" value="<?= e($mode) ?>"><input type="hidden" name="action" value="clear_all"><button class="btn btn-sm btn-outline-danger">Xóa toàn bộ kết quả</button></form>
</div>

<div class="assign-groups">
<?php foreach ($grouped as $groupName => $students): $sum=noitru_assignment_summary($students); ?>
  <section class="assign-card overflow-hidden">
    <div class="assign-group-head"><div class="d-flex justify-content-between"><strong><?= e($groupName) ?></strong><span class="badge bg-primary"><?= $sum['total'] ?> HS</span></div><div class="assign-summary">Nam <?= $sum['male'] ?> · Nữ <?= $sum['female'] ?> · Khối <?= e(implode(', ', array_keys($sum['grades']))) ?: '—' ?></div></div>
    <div class="assign-scroll">
      <?php foreach ($students as $student): ?>
      <div class="assign-student"><input form="bulkAssignForm" class="form-check-input mt-1" type="checkbox" name="student_ids[]" value="<?= e($student['id']) ?>" id="s_<?= e($student['id']) ?>"><label for="s_<?= e($student['id']) ?>"><div class="assign-name"><?= e($student['name']) ?></div><div class="assign-meta"><?= e($student['class_name']) ?> · <?= e(noitru_assignment_gender($student)) ?> · Khối <?= e(noitru_assignment_grade($student)) ?></div></label></div>
      <?php endforeach; ?>
      <?php if (!$students): ?><div class="text-muted text-center py-3 small">Chưa có học sinh.</div><?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>

<section class="assign-card overflow-hidden">
  <div class="assign-group-head"><div class="d-flex justify-content-between"><strong>Chưa chia <?= e($label) ?></strong><span class="badge bg-danger"><?= count($unassigned) ?> HS</span></div><div class="assign-summary">Có thể tích nhiều học sinh và gán nhanh.</div></div>
  <div class="assign-scroll">
    <?php foreach ($unassigned as $student): ?>
    <div class="assign-student"><input form="bulkAssignForm" class="form-check-input mt-1" type="checkbox" name="student_ids[]" value="<?= e($student['id']) ?>" id="u_<?= e($student['id']) ?>"><label for="u_<?= e($student['id']) ?>"><div class="assign-name"><?= e($student['name']) ?></div><div class="assign-meta"><?= e($student['class_name']) ?> · <?= e(noitru_assignment_gender($student)) ?> · Khối <?= e(noitru_assignment_grade($student)) ?></div></label></div>
    <?php endforeach; ?>
    <?php if (!$unassigned): ?><div class="text-success text-center py-3 small">Tất cả học sinh đã được chia.</div><?php endif; ?>
  </div>
</section>
</div>
</div></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
