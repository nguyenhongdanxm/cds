<?php
function can_module($module, $level = 'view') { return false; }
function noitru_boarders_live() {
    return [
        ['id'=>'s1','class_name'=>'6A'],
        ['id'=>'s2','class_name'=>'6B'],
        ['id'=>'s3','class_name'=>'6A'],
    ];
}
function noitru_att_ensure_legacy_reports($schoolTotal) {
    $GLOBALS['legacy_total'] = $schoolTotal;
}
function noitru_att_recent_reports($limit, $studentIds, $scopeTotal) {
    $GLOBALS['attendance_call'] = [$limit, $studentIds, $scopeTotal];
    if (!empty($GLOBALS['attendance_should_fail'])) throw new RuntimeException('Simulated report failure');
    return [['present'=>2,'absent'=>1,'date'=>'2026-10-12']];
}
require __DIR__ . '/../includes/dashboard_attendance.php';

function check($condition) {
    if (!$condition) throw new RuntimeException('Public dashboard attendance regression.');
}

$reports = cds_dashboard_recent_attendance([]);
check(!can_module('noitru', 'view'));
check(count($reports) === 1 && $reports[0]['present'] === 2);
check($GLOBALS['attendance_call'] === [3, null, null]);
check($GLOBALS['legacy_total'] === 3);

cds_dashboard_recent_attendance(null);
check($GLOBALS['attendance_call'] === [3, null, null]);
$GLOBALS['attendance_should_fail'] = true;
check(cds_dashboard_recent_attendance([]) === [] && cds_dashboard_attendance_error());
echo "PASS: recent attendance is fetched school-wide, independent of permissions/classes, and reports logged errors.\n";
