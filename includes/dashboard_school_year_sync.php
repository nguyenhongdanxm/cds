<?php
/** Đồng bộ năm học trên trang chủ quản trị với năm học hiện hành trong CSDL. */
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'admin.php') return;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;

require_once __DIR__ . '/csdl_store.php';

function cds_dashboard_sync_school_year_html(string $html): string {
    $currentYear = csdl_year_current();
    $label = trim((string)($currentYear['label'] ?? ''));
    if ($label === '') $label = defined('SCHOOL_YEAR') ? (string)SCHOOL_YEAR : '';
    if ($label === '') return $html;

    $escaped = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    return preg_replace(
        '/(<div class="school-year">.*?<strong>).*?(<\/strong>)/s',
        '$1' . $escaped . '$2',
        $html,
        1
    ) ?? $html;
}

ob_start('cds_dashboard_sync_school_year_html');
