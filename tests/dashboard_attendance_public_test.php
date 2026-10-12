<?php
function can_module($module, $level = 'view') { return false; }
function noitru_boarders_live() {
    return [
        ['id'=>'s1','class_name'=>'6A'],
        ['id'=>'s2','class_name'=>'6B'],
        ['id'=>'s3','class_name'=>'6A'],
    ];
}
function noitru_att_recent_reports($limit, $studentIds, $scopeTotal) {
    $GLOBALS['attendance_call'] = [$limit, $studentIds, $scopeTotal];
    return [['present'=>2,'absent'=>1,'date'=>'2026-10-12']];
}
require __DIR__ . '/../includes/dashboard_attendance.php';

function check($condition) {
    if (!$condition) throw new RuntimeException('Public dashboard attendance regression.');
}

$reports = cds_dashboard_recent_attendance(['6A']);
check(!can_module('noitru', 'view'));
check(count($reports) === 1 && $reports[0]['present'] === 2);
check($GLOBALS['attendance_call'] === [3, ['s1','s3'], 2]);

cds_dashboard_recent_attendance(null);
check($GLOBALS['attendance_call'] === [3, null, 3]);
echo "PASS: recent attendance is fetched without noitru permission and respects class scope.\n";
