<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_login();
require_perm('nt.buaan.tonghop');

header('Content-Type: application/json; charset=utf-8');

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$students = noitru_boarders_live();
$classes = [];
foreach ($students as $student) {
    $class = trim((string)($student['class_name'] ?? ''));
    if ($class === '') $class = '(Chưa lớp)';
    $classes[$class][] = $student;
}
if (function_exists('csdl_compare_class_names')) {
    uksort($classes, 'csdl_compare_class_names');
} else {
    uksort($classes, static fn($a, $b) => strnatcasecmp((string)$a, (string)$b));
}

$mealMap = noitru_meals_for_date($date);
$reports = noitru_meal_reports_for_date($date);
$reportMap = [];
foreach ($reports as $report) {
    $reportMap[(string)($report['class_name'] ?? '') . '|' . (string)($report['meal'] ?? '')] = $report;
}

$mealLabels = ['sang' => 'Sáng', 'trua' => 'Trưa', 'toi' => 'Tối'];
$rows = [];
$totals = ['students' => 0, 'sang' => 0, 'trua' => 0, 'toi' => 0];

foreach ($classes as $class => $classStudents) {
    $row = [
        'class' => $class,
        'students' => count($classStudents),
        'meals' => [],
    ];
    $totals['students'] += count($classStudents);

    foreach ($mealLabels as $meal => $label) {
        $state = noitru_meal_state($date, $meal)['status'] ?? 'open';
        $report = $reportMap[$class . '|' . $meal] ?? null;
        $eat = null;
        $absentNames = [];

        if ($state === 'off') {
            $eat = 0;
        } elseif ($report) {
            $eat = 0;
            foreach ($classStudents as $student) {
                $sid = (string)($student['id'] ?? '');
                $value = (string)($mealMap[$sid][$meal] ?? 'no');
                if ($value === 'no') {
                    $name = trim((string)($student['name'] ?? ''));
                    if ($name !== '') $absentNames[] = $name;
                } else {
                    $eat++;
                }
            }
            $totals[$meal] += $eat;
        }

        $row['meals'][$meal] = [
            'label' => $label,
            'reported' => (bool)$report,
            'state' => $state,
            'eat' => $eat,
            'absent' => count($absentNames),
            'absent_names' => array_values($absentNames),
        ];
    }
    $rows[] = $row;
}

$user = current_user() ?? [];
echo json_encode([
    'ok' => true,
    'school' => defined('SCHOOL_NAME') ? SCHOOL_NAME : 'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN',
    'date' => $date,
    'date_label' => date('d/m/Y', strtotime($date)),
    'reporter' => (string)($user['name'] ?? ''),
    'rows' => $rows,
    'totals' => $totals,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
