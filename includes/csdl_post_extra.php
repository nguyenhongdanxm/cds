<?php
/** Xử lý xóa hàng loạt — gọi từ csdl.php khi action=bulk_delete */
if (($_POST['action'] ?? '') !== 'bulk_delete') return;

$entity = $_POST['entity'] ?? '';
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$n = 0;
$shadowSyncOk = true;
cds_shadow_batch_begin();
try {
foreach ($ids as $id) {
    $id = trim((string)$id);
    if ($id === '') continue;
    if ($entity === 'teachers') {
        csdl_teacher_delete($id);
        $n++;
    } elseif ($entity === 'classes') {
        csdl_class_delete($id);
        $n++;
    } elseif ($entity === 'students') {
        csdl_student_delete($id);
        $n++;
    }
}
} finally {
    $shadowSyncOk = cds_shadow_batch_end();
}
$message = "Đã xóa $n mục đã chọn.";
if (!$shadowSyncOk) $message .= ' JSON đã lưu; MySQL chưa đồng bộ được.';
flash($message, 'warning');
$back = in_array($entity, ['teachers', 'classes', 'students'], true) ? $entity : 'overview';
header('Location: ' . BASE_URL . 'csdl.php?tab=' . $back);
exit;
