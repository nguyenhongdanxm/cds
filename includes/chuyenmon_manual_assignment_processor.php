<?php
/** Xử lý POST phân công thủ công trước khi Chuyên môn xuất HTML. */
if (!isset($current) || !in_array($current, ['them', 'danhsach'], true)) return;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !isset($_POST['cds_manual_action'])) return;

$manualCanEdit = function_exists('cds_can_feature')
    ? cds_can_feature('cm.pccm', 'edit')
    : !empty($_SESSION['pccm_admin']);
if (!$manualCanEdit) {
    http_response_code(403);
    exit('Bạn không có quyền sửa phân công chuyên môn.');
}

$manualFile = dirname(__DIR__) . '/data/manual_assignments.json';
$loadRows = static function () use ($manualFile): array {
    if (!is_file($manualFile)) return [];
    $rows = json_decode((string)file_get_contents($manualFile), true);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
};
$saveRows = static function (array $rows) use ($manualFile): void {
    $dir = dirname($manualFile);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents(
        $manualFile,
        json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
};
$number = static function ($value): float {
    $value = str_replace(',', '.', trim((string)$value));
    return round(max(0, (float)$value), 1);
};

$rows = $loadRows();
$action = (string)$_POST['cds_manual_action'];
if ($action === 'save') {
    $teacher = trim((string)($_POST['manual_teacher'] ?? ''));
    $subject = trim((string)($_POST['manual_subject'] ?? ''));
    $className = trim((string)($_POST['manual_class'] ?? ''));
    $periods = $number($_POST['manual_periods'] ?? 0);
    $note = trim((string)($_POST['manual_note'] ?? ''));

    if ($teacher === '' || $subject === '' || $className === '' || $periods <= 0) {
        $_SESSION['pccm_flash'] = [
            'type' => 'danger',
            'message' => 'Vui lòng chọn giáo viên, lớp, nhập môn và số tiết lớn hơn 0.',
        ];
    } else {
        $rows[] = [
            'id' => 'ma_' . bin2hex(random_bytes(5)),
            'version_id' => (string)(function_exists('get_active_version_id') ? get_active_version_id() : ''),
            'teacher' => $teacher,
            'subject' => $subject,
            'class_name' => $className,
            'periods' => $periods,
            'note' => $note,
            'created_by' => (string)($_SESSION['cds_user']['name'] ?? $_SESSION['cds_user']['username'] ?? ''),
            'created_at' => date('c'),
        ];
        $saveRows($rows);
        $_SESSION['pccm_flash'] = ['type' => 'success', 'message' => 'Đã thêm phân công thủ công vào giáo viên.'];
    }
} elseif ($action === 'delete') {
    $id = (string)($_POST['manual_id'] ?? '');
    $rows = array_values(array_filter($rows, static fn($row) => (string)($row['id'] ?? '') !== $id));
    $saveRows($rows);
    $_SESSION['pccm_flash'] = ['type' => 'warning', 'message' => 'Đã xóa phân công thủ công.'];
}

$returnPage = in_array((string)($_POST['manual_return_page'] ?? ''), ['them', 'danhsach'], true)
    ? (string)$_POST['manual_return_page']
    : $current;
header('Location: ' . $returnPage . '.php#cdsManualAssignments');
exit;
