<?php
function cds_dashboard_attendance_error(): bool {
    return !empty($GLOBALS['cds_dashboard_attendance_error']);
}

function cds_dashboard_recent_attendance($allowedClasses = null): array {
    $GLOBALS['cds_dashboard_attendance_error'] = false;
    try {
        if (!function_exists('noitru_att_recent_reports') || !function_exists('noitru_boarders_live')) {
            require_once __DIR__ . '/noitru_store.php';
        }
        if (!function_exists('noitru_att_recent_reports') || !function_exists('noitru_boarders_live')) {
            throw new RuntimeException('Attendance report functions are unavailable.');
        }

        noitru_att_ensure_legacy_reports(count(noitru_boarders_live()));
        return noitru_att_recent_reports(3, null, null);
    } catch (Throwable $error) {
        $GLOBALS['cds_dashboard_attendance_error'] = true;
        error_log('CDS dashboard recent attendance failed: ' . $error->getMessage());
        return [];
    }
}
