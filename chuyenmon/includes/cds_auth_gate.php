<?php
/**
 * Cổng xác thực dùng chung cho phân hệ Chuyên môn.
 * File được cài vào chuyenmon/includes và gọi ngay sau functions.php,
 * trước khi trang xuất HTML.
 */
$cmGateScript = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$cmPublicPages = ['tracuu.php', 'ketqua.php'];

if (!function_exists('is_logged_in')) return;
if (is_logged_in() || in_array($cmGateScript, $cmPublicPages, true)) return;

$next = (string)($_SERVER['REQUEST_URI'] ?? '/chuyenmon/');
header('Location: /login.php?next=' . urlencode($next));
exit;
