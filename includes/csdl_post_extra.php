<?php
/** Xử lý xóa hàng loạt — gọi từ csdl.php khi action=bulk_delete */
if (($_POST['action'] ?? '') !== 'bulk_delete') return;

$entity = $_POST['entity'] ?? '';
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$n = 0;
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
flash("Đã xóa $n mục đã chọn.", 'warning');
$back = in_array($entity, ['teachers', 'classes', 'students'], true) ? $entity : 'overview';
header('Location: ' . BASE_URL . 'csdl.php?tab=' . $back);
exit;
