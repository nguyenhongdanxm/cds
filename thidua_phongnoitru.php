<?php
ob_start();
require __DIR__ . '/includes/thidua_phongnoitru_v2.php';
$html = ob_get_clean();
$canDeleteRoomScore = function_exists('can_perm_level') && can_perm_level('td.student_room_input', 'delete');
$patch = '<script>window.TD_ROOM_CAN_DELETE=' . ($canDeleteRoomScore ? 'true' : 'false') . ';window.TD_ROOM_BASE_URL=' . json_encode(defined('BASE_URL') ? BASE_URL : '/', JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . ';</script>'
       . '<script src="' . (defined('BASE_URL') ? BASE_URL : '/') . 'assets/thidua_phongnoitru_patch.js?v=20260826-1"></script>';
if (stripos($html, '</body>') !== false) $html = preg_replace('/<\/body>/i', $patch . '</body>', $html, 1);
else $html .= $patch;
echo $html;
