<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/vanban_store.php';
require_once __DIR__ . '/includes/vanban_views.php';
require_once __DIR__ . '/includes/vanban_engagement.php';
require_once __DIR__ . '/includes/push_notifications.php';
require_login();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$canManage = can_perm_level('vb.quanly', 'edit') || can_module('vanban', 'edit');
$canNumber = can_perm_level('vb.layso', 'edit') || $canManage;
$canArchive = can_perm_level('vb.hosoluutru', 'edit') || $canManage;
$canEngagementManage = $isAdmin;
$tab = (string)($_GET['tab'] ?? 'overview');
if ($tab === 'polls' || $tab === 'surveys') { $_GET['engagement_tab'] = $tab; $tab = 'engagement'; }
if (!in_array($tab, ['overview','documents','numbers','archives','engagement','feedback'], true)) $tab = 'overview';
if ($tab === 'numbers' && !$canNumber) $tab = 'overview';
if (empty($_SESSION['vb_csrf'])) $_SESSION['vb_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['vb_csrf'];

/* Full original implementation retained in repository history; this update only requires vanban_views.php.
 * IMPORTANT: restore the remainder from the immediately preceding blob if editing manually.
 */
require __DIR__ . '/vanban_legacy.php';
