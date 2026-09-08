<?php
/** Xử lý xóa hàng loạt — gọi từ csdl.php khi action=bulk_delete */
if (($_POST['action'] ?? '') !== 'bulk_delete') return;

$entity = $_POST['entity'] ?? '';
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$n = 0;
$shadowSyncOk = true;
$batchCommit = false;
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
        csdl_student_deactivate($id, date('Y-m-d'), 'Nghỉ học', 'Cập nhật hàng loạt');
        $n++;
    }
}
$batchCommit = true;
} finally {
    $shadowSyncOk = cds_shadow_batch_end($batchCommit);
}
$message = $entity === 'students'
    ? "Đã chuyển $n học sinh sang trạng thái nghỉ từ hôm nay; hồ sơ lịch sử được giữ nguyên."
    : "Đã xóa $n mục đã chọn.";
if (!$shadowSyncOk) $message .= ' Lô chưa hoàn tất hoặc bản dự phòng cần quản trị kiểm tra.';
flash($message, 'warning');
$back = in_array($entity, ['teachers', 'classes', 'students'], true) ? $entity : 'overview';
header('Location: ' . BASE_URL . 'csdl.php?tab=' . $back);
exit;
