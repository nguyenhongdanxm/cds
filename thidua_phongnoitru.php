<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/thidua_room_lock.php';
require_login();

// Khóa phía máy chủ: dữ liệu chỉ được sửa trong ngày chấm và ngày kế tiếp.
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=(string)($_POST['action']??'');
    if ($action==='batch_save') {
        $date=trim((string)($_POST['date']??''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) tdr_assert_date_editable($date);
    }
    if ($action==='entry_delete' && !tdr_is_room_admin()) {
        http_response_code(403); exit('Xóa toàn bộ dữ liệu tuần chỉ dành cho Quản trị hệ thống.');
    }
}

ob_start();
require __DIR__ . '/includes/thidua_phongnoitru_v2.php';
$html = ob_get_clean();
$canDeleteRoomScore = function_exists('can_perm_level') && can_perm_level('td.student_room_input', 'delete');
$canRoomAdmin = tdr_is_room_admin();
$patch = '<script>window.TD_ROOM_CAN_DELETE=' . ($canDeleteRoomScore ? 'true' : 'false') . ';window.TD_ROOM_IS_ADMIN=' . ($canRoomAdmin ? 'true' : 'false') . ';window.TD_ROOM_BASE_URL=' . json_encode(defined('BASE_URL') ? BASE_URL : '/', JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . ';</script>'
       . '<script src="' . (defined('BASE_URL') ? BASE_URL : '/') . 'assets/thidua_phongnoitru_patch.js?v=20260826-2"></script>';
if (stripos($html, '</body>') !== false) $html = preg_replace('/<\/body>/i', $patch . '</body>', $html, 1);
else $html .= $patch;
echo $html;
