<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/noitru_store.php';
require_login();
require_module('noitru','view');
require_perm_level('nt.yte','delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$ids = $_POST['medicine_ids'] ?? [];
if (!is_array($ids)) $ids = [$ids];
$ids = array_values(array_unique(array_filter(array_map(static fn($id) => trim((string)$id), $ids))));

if (!$ids) {
    flash('Chưa chọn thuốc cần xóa.', 'warning');
    header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=inventory');
    exit;
}

$active = [];
foreach (noitru_medicines_all() as $row) {
    $id = (string)($row['id'] ?? '');
    if ($id !== '') $active[$id] = true;
}

$deleted = 0;
foreach ($ids as $id) {
    if (!isset($active[$id])) continue;
    noitru_medicine_delete($id); // Xóa mềm: giữ nguyên lịch sử giao dịch nhập/xuất.
    $deleted++;
}

if ($deleted > 0) {
    flash('Đã xóa ' . $deleted . ' loại thuốc khỏi danh sách. Lịch sử nhập/xuất vẫn được giữ nguyên.', 'warning');
} else {
    flash('Không có thuốc hợp lệ để xóa.', 'warning');
}
header('Location: ' . BASE_URL . 'noitru.php?tab=health&health_view=inventory');
exit;
