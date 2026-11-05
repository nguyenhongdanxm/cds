<?php
if (empty($_SESSION['pccm_release_csrf'])) $_SESSION['pccm_release_csrf'] = bin2hex(random_bytes(32));
$releaseAction = $_POST['pccm_release_action'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
if ($releaseAction === '') {
    pccm_lock_request(DATA_PATH);
    if (!empty(pccm_release_state(DATA_PATH)['locked'][(string)get_active_version_id()])) {
        flash('Phiên bản đã khóa. Mở khóa trước khi sửa phân công.','warning');
        header('Location: '.BASE_URL.'them.php');
        exit;
    }
    return;
}

try {
    if (!cds_can_feature('cm.pccm','edit') || !hash_equals($_SESSION['pccm_release_csrf'], (string)($_POST['pccm_token'] ?? ''))) {
        throw new RuntimeException('Không có quyền hoặc phiên thao tác đã hết hạn.');
    }
    $version = (string)($_POST['pccm_version'] ?? '');
    $versionInfo = $version !== '' ? get_version($version) : null;
    if (!$versionInfo || $version !== (string)get_active_version_id()) throw new RuntimeException('Phiên bản đang làm việc đã đổi. Hãy tải lại trang.');
    if (in_array($releaseAction,['publish','unlock'],true)) {
        pccm_publish_version(DATA_PATH,$version,(string)$versionInfo['name'],$releaseAction === 'unlock');
        flash($releaseAction === 'publish' ? 'Đã khóa và công bố. TKB sẽ lấy bản vừa công bố.' : 'Đã mở khóa bản làm việc. TKB tiếp tục lấy bản đã công bố.', 'success');
    } elseif ($releaseAction === 'delete_manual') {
        $ids = array_values(array_unique(array_filter((array)($_POST['manual_ids'] ?? []),static fn($id)=>is_string($id)&&$id!=='')));
        if (!$ids) throw new RuntimeException('Hãy chọn phân công thủ công cần xóa.');
        pccm_delete_manual_rows(DATA_PATH,$version,$ids);
        flash('Đã xóa các phân công thủ công đã chọn.', 'success');
    }
} catch (RuntimeException $error) {
    flash($error->getMessage(),'danger');
}
header('Location: '.BASE_URL.'them.php'.($releaseAction==='delete_manual'?'#cdsManualAssignments':''));
exit;
