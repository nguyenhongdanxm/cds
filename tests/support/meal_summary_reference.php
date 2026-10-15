<?php
/** Unmodified summary algorithms from main 67f488361201f018e2ad544a2f2e4e454fb92830; names isolated for parity checks. */
function reference_noitru_meal_period_summary($from, $to, array $students) {
    $classes = [];
    foreach ($students as $student) {
        $class = trim((string)($student['class_name'] ?? '')) ?: '(Chưa lớp)';
        $classes[$class][] = $student;
    }
    ksort($classes, SORT_NATURAL);

    $reports = [];
    foreach (noitru_meal_reports_data()['reports'] ?? [] as $report) {
        $date = (string)($report['date'] ?? '');
        $class = trim((string)($report['class_name'] ?? '')) ?: '(Chưa lớp)';
        $meal = (string)($report['meal'] ?? '');
        if ($date >= $from && $date <= $to && isset($classes[$class]) && in_array($meal, ['sang', 'trua', 'toi'], true)) {
            $reports[$date][$meal][$class] = $report;
        }
    }

    $meals = [];
    foreach (noitru_meals_all() as $row) {
        $date = (string)($row['date'] ?? '');
        $studentId = (string)($row['student_id'] ?? '');
        if ($date >= $from && $date <= $to && $studentId !== '') $meals[$date][$studentId] = $row;
    }

    $out = [
        'days' => [],
        'total' => ['sang'=>0, 'trua'=>0, 'toi'=>0, 'reported'=>0, 'missing'=>0],
    ];
    for ($date = $from; $date <= $to; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
        $day = ['sang'=>0, 'trua'=>0, 'toi'=>0, 'reported'=>0, 'missing'=>0, 'states'=>[]];
        foreach (['sang', 'trua', 'toi'] as $meal) {
            $state = noitru_meal_state($date, $meal)['status'] ?? 'open';
            $reportedClasses = $reports[$date][$meal] ?? [];
            $day['reported'] += count($reportedClasses);
            $day['missing'] += count($classes) - count($reportedClasses);
            $day['states'][$meal] = $state;
            if ($state === 'off') continue;

            foreach ($reportedClasses as $class => $_) {
                $report = $reportedClasses[$class];
                $counts = noitru_meal_report_effective_counts($report, $date, $meal, $meals[$date] ?? []);
                $day[$meal] += $counts['eat'];
            }
            $out['total'][$meal] += $day[$meal];
        }
        $out['total']['reported'] += $day['reported'];
        $out['total']['missing'] += $day['missing'];
        $out['days'][$date] = $day;
    }
    return $out;
}

function reference_noitru_rice_usage_summary($from, $to, array $riceData = null) {
    $riceData = $riceData ?? noitru_rice_data();
    $settings = array_merge(['sang_grams'=>0,'trua_grams'=>180,'toi_grams'=>180], $riceData['settings'] ?? []);
    $reports = noitru_meal_reports_data()['reports'] ?? [];
    $stateCache = [];
    $mealDayCache = [];
    $out = [
        'days'=>[],
        'meals'=>[
            'sang'=>['students'=>0,'kg'=>0.0],
            'trua'=>['students'=>0,'kg'=>0.0],
            'toi'=>['students'=>0,'kg'=>0.0],
        ],
        'total_students'=>0,
        'total_kg'=>0.0,
    ];
    foreach ($reports as $report) {
        $date = $report['date'] ?? '';
        $meal = $report['meal'] ?? '';
        if ($date < $from || $date > $to || !isset($out['meals'][$meal])) continue;
        $stateKey = $date . '|' . $meal;
        if (!isset($stateCache[$stateKey])) $stateCache[$stateKey] = noitru_meal_state($date, $meal)['status'] ?? 'open';
        if ($stateCache[$stateKey] !== 'locked') continue;
        if (!isset($mealDayCache[$date])) $mealDayCache[$date] = noitru_meals_for_date($date);
        $counts = noitru_meal_report_effective_counts($report, $date, $meal, $mealDayCache[$date]);
        $students = $counts['eat'];
        $kg = round($students * (float)($settings[$meal . '_grams'] ?? 0) / 1000, 3);
        if (!isset($out['days'][$date])) {
            $out['days'][$date] = [
                'sang'=>['students'=>0,'kg'=>0.0],
                'trua'=>['students'=>0,'kg'=>0.0],
                'toi'=>['students'=>0,'kg'=>0.0],
                'students'=>0,
                'kg'=>0.0,
            ];
        }
        $out['days'][$date][$meal]['students'] += $students;
        $out['days'][$date][$meal]['kg'] = round($out['days'][$date][$meal]['kg'] + $kg, 3);
        $out['days'][$date]['students'] += $students;
        $out['days'][$date]['kg'] = round($out['days'][$date]['kg'] + $kg, 3);
        $out['meals'][$meal]['students'] += $students;
        $out['meals'][$meal]['kg'] = round($out['meals'][$meal]['kg'] + $kg, 3);
        $out['total_students'] += $students;
        $out['total_kg'] = round($out['total_kg'] + $kg, 3);
    }
    ksort($out['days']);
    return $out;
}

/* —— Attendance —— */
