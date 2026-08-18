<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/school_week_calendar.php';
require_login();
require_perm_level('csdl.year', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'csdl.php?tab=years');
    exit;
}
$yearId = trim((string)($_POST['year_id'] ?? ''));
$result = cds_school_save_preweeks(
    $yearId,
    trim((string)($_POST['pre_week_1_start'] ?? '')),
    trim((string)($_POST['pre_week_2_start'] ?? ''))
);
flash((string)($result['message'] ?? ''), !empty($result['ok']) ? 'success' : 'danger');
header('Location: ' . BASE_URL . 'csdl.php?tab=years&weeks=' . urlencode($yearId));
exit;
