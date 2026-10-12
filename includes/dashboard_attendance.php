<?php
function cds_dashboard_recent_attendance($allowedClasses): array {
    if (!function_exists('noitru_att_recent_reports') || !function_exists('noitru_boarders_live')) {
        require_once __DIR__ . '/noitru_store.php';
    }
    if (!function_exists('noitru_att_recent_reports') || !function_exists('noitru_boarders_live')) return [];

    $boarders = array_values(array_filter(noitru_boarders_live(), static function ($student) use ($allowedClasses) {
        return $allowedClasses === null || in_array((string)($student['class_name'] ?? ''), $allowedClasses, true);
    }));
    $studentIds = $allowedClasses === null ? null : array_column($boarders, 'id');
    return noitru_att_recent_reports(3, $studentIds, count($boarders));
}
