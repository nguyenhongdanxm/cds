<?php
$page_title = 'Quản lý Lớp';
require_once 'includes/functions.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classes = get_classes();
    if ($_POST['action'] === 'add') {
        $name = strtoupper(trim($_POST['name'] ?? ''));
        if ($name && !in_array($name, $classes)) {
            $classes[] = $name;
            usort($classes, function($a, $b) {
                $ga = intval(preg_replace('/[^0-9]/', '', $a));
                $gb = intval(preg_replace('/[^0-9]/', '', $b));
                return $ga === $gb ? strcmp($a, $b) : $ga - $gb;
            });
            save_json(CLASSES_FILE, $classes);
            flash("Đã thêm lớp: $name", 'success');
        }
    }
    if ($_POST['action'] === 'delete') {
        $name = trim($_POST['name'] ?? '');
        $classes = array_values(array_filter($classes, fn($c) => $c !== $name));
        save_json(CLASSES_FILE, $classes);
        flash("Đã xóa lớp: $name", 'success');
    }
    header('Location: ' . BASE_URL . 'lop.php'); exit;
}
require_once 'includes/header.php';
$classes = get_classes();
?>
<h3 class="mb-4"><i class="bi bi-building"></i> Quản lý Lớp</h3>
<div class="row">
<div class="col-md-4 mb-4"><div class="card"><div class="card-header">Thêm lớp mới</div>
<div class="card-body"><form method="post"><input type="hidden" name="action" value="add">
<div class="mb-3"><input type="text" name="name" class="form-control" placeholder="Ví dụ: 8D, 10C" required></div>
<button type="submit" class="btn btn-primary w-100">Thêm lớp</button></form></div></div></div>
<div class="col-md-8"><div class="card"><div class="card-header">Danh sách lớp (<?= count($classes) ?>)</div>
<div class="card-body"><div class="d-flex flex-wrap gap-2">
<?php foreach ($classes as $c): ?>
<div class="badge bg-primary d-flex align-items-center gap-2 p-2" style="font-size:1rem"><?= e($c) ?>
<form method="post" class="d-inline" onsubmit="return confirm('Xóa lớp <?= e($c) ?>?')">
<input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($c) ?>">
<button type="submit" class="btn-close btn-close-white" style="font-size:.6rem"></button></form></div>
<?php endforeach; ?>
</div></div></div></div></div>
<?php require_once 'includes/footer.php'; ?>
