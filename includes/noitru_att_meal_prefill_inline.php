<?php
/** Dữ liệu báo ăn truyền thẳng vào trang điểm danh, không gọi endpoint AJAX. */
$ntMealPrefillData = [
    'ok' => true,
    'applied' => false,
    'reason' => 'not_meal_attendance',
    'date' => (string)($date ?? ''),
    'shift' => (string)($shift ?? ''),
    'shift_label' => (string)($shiftLabel ?? ''),
    'meal' => '',
    'meal_label' => '',
    'student_ids' => [],
    'count' => 0,
];

try {
    $ntDate = (string)($date ?? '');
    $ntShift = (string)($shift ?? '');
    $ntShiftLabel = (string)($shiftLabel ?? '');
    $ntMeal = function_exists('noitru_att_meal_for_shift') ? noitru_att_meal_for_shift($ntShift, $ntShiftLabel) : '';
    $ntMealPrefillData['meal'] = $ntMeal;
    $ntMealLabels = ['sang'=>'Ăn sáng','trua'=>'Ăn trưa','toi'=>'Ăn tối'];
    $ntMealPrefillData['meal_label'] = $ntMealLabels[$ntMeal] ?? '';

    if ($ntMeal !== '') {
        if (function_exists('noitru_att_report_for') && noitru_att_report_for($ntDate, $ntShift)) {
            $ntMealPrefillData['reason'] = 'attendance_saved';
        } else {
            $ntState = noitru_meal_state($ntDate, $ntMeal);
            $ntMealPrefillData['meal_state'] = (string)($ntState['status'] ?? 'open');
            $ntMealPrefillData['deadline'] = (string)($ntState['deadline'] ?? '');
            if (($ntState['status'] ?? 'open') !== 'locked') {
                $ntMealPrefillData['reason'] = 'meal_not_locked';
            } else {
                $ntReportedClasses = [];
                foreach (noitru_meal_reports_for_date($ntDate) as $ntReport) {
                    if (($ntReport['meal'] ?? '') !== $ntMeal) continue;
                    if (($ntReport['status'] ?? 'submitted') !== 'submitted') continue;
                    $ntClass = trim((string)($ntReport['class_name'] ?? ''));
                    if ($ntClass !== '') $ntReportedClasses[$ntClass] = true;
                }

                $ntStudentClass = [];
                foreach ((isset($boarders) && is_array($boarders)) ? $boarders : noitru_boarders_live() as $ntStudent) {
                    $ntSid = (string)($ntStudent['id'] ?? '');
                    if ($ntSid !== '') $ntStudentClass[$ntSid] = trim((string)($ntStudent['class_name'] ?? ''));
                }

                $ntIds = [];
                foreach (noitru_meals_for_date($ntDate) as $ntSid => $ntMealRow) {
                    $ntSid = (string)$ntSid;
                    $ntClass = $ntStudentClass[$ntSid] ?? '';
                    if ($ntClass === '' || empty($ntReportedClasses[$ntClass])) continue;
                    if (($ntMealRow[$ntMeal] ?? 'yes') === 'no') $ntIds[] = $ntSid;
                }
                $ntIds = array_values(array_unique($ntIds));
                $ntMealPrefillData['applied'] = true;
                $ntMealPrefillData['reason'] = 'ready';
                $ntMealPrefillData['student_ids'] = $ntIds;
                $ntMealPrefillData['count'] = count($ntIds);
                $ntMealPrefillData['reported_class_count'] = count($ntReportedClasses);
            }
        }
    }
} catch (Throwable $e) {
    $ntMealPrefillData['ok'] = false;
    $ntMealPrefillData['reason'] = 'server_error';
    $ntMealPrefillData['message'] = $e->getMessage();
    error_log('[CDS attendance meal prefill] ' . $e->getMessage());
}
?>
<script>window.NT_ATT_MEAL_PREFILL_DATA=<?= json_encode($ntMealPrefillData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
