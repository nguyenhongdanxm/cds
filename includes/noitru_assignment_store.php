<?php
/** Công cụ chia phòng và chia mâm cho học sinh nội trú. */
require_once __DIR__ . '/noitru_store.php';

if (!defined('NOITRU_ASSIGNMENTS')) {
    define('NOITRU_ASSIGNMENTS', NOITRU_DIR . '/assignments.json');
}

function noitru_assignments_data(): array {
    noitru_ensure_dir();
    $data = load_json(NOITRU_ASSIGNMENTS, []);
    return array_merge([
        'rooms' => [],
        'meals' => [],
        'room_names' => [],
        'meal_names' => [],
        'history' => [],
        'updated_at' => null,
        'updated_by' => '',
    ], is_array($data) ? $data : []);
}

function noitru_assignments_save(array $data, string $by = ''): void {
    noitru_ensure_dir();
    $data['rooms'] = is_array($data['rooms'] ?? null) ? $data['rooms'] : [];
    $data['meals'] = is_array($data['meals'] ?? null) ? $data['meals'] : [];
    $data['room_names'] = array_values(array_unique(array_filter(array_map('strval', $data['room_names'] ?? []))));
    $data['meal_names'] = array_values(array_unique(array_filter(array_map('strval', $data['meal_names'] ?? []))));
    sort($data['room_names'], SORT_NATURAL);
    sort($data['meal_names'], SORT_NATURAL);
    $data['updated_at'] = date('c');
    $data['updated_by'] = $by;
    save_json(NOITRU_ASSIGNMENTS, $data);
}

function noitru_assignment_apply(array $boarders): array {
    $data = noitru_assignments_data();
    foreach ($boarders as &$student) {
        $id = (string)($student['id'] ?? '');
        if ($id === '') continue;
        if (array_key_exists($id, $data['rooms'])) $student['room_ktx'] = (string)$data['rooms'][$id];
        if (array_key_exists($id, $data['meals'])) $student['meal_group'] = (string)$data['meals'][$id];
    }
    unset($student);
    return $boarders;
}

function noitru_assignment_grade(array $student): string {
    $class = (string)($student['class_name'] ?? '');
    if (preg_match('/(?<!\d)(1[0-2]|[6-9])(?!\d)/u', $class, $m)) return $m[1];
    if (preg_match('/^(1[0-2]|[6-9])/u', $class, $m)) return $m[1];
    return 'Khác';
}

function noitru_assignment_gender(array $student): string {
    $g = mb_strtolower(trim((string)($student['gender'] ?? '')), 'UTF-8');
    if (in_array($g, ['nam', 'male', 'm', '1'], true)) return 'Nam';
    if (in_array($g, ['nữ', 'nu', 'female', 'f', '0'], true)) return 'Nữ';
    return 'Khác';
}

function noitru_assignment_summary(array $students): array {
    $summary = ['total'=>count($students), 'male'=>0, 'female'=>0, 'other'=>0, 'grades'=>[], 'classes'=>[]];
    foreach ($students as $student) {
        $gender = noitru_assignment_gender($student);
        if ($gender === 'Nam') $summary['male']++;
        elseif ($gender === 'Nữ') $summary['female']++;
        else $summary['other']++;
        $grade = noitru_assignment_grade($student);
        $class = trim((string)($student['class_name'] ?? '')) ?: 'Chưa lớp';
        $summary['grades'][$grade] = ($summary['grades'][$grade] ?? 0) + 1;
        $summary['classes'][$class] = ($summary['classes'][$class] ?? 0) + 1;
    }
    ksort($summary['grades'], SORT_NATURAL);
    ksort($summary['classes'], SORT_NATURAL);
    return $summary;
}

function noitru_assignment_names(string $mode, int $count, string $prefix = ''): array {
    $count = max(1, min(200, $count));
    $prefix = trim($prefix);
    if ($prefix === '') $prefix = $mode === 'rooms' ? 'Phòng ' : 'Mâm ';
    $names = [];
    for ($i = 1; $i <= $count; $i++) $names[] = $prefix . $i;
    return $names;
}

function noitru_assignment_auto_rooms(array $students, array $names, int $capacity): array {
    $capacity = max(1, $capacity);
    usort($students, static function ($a, $b) {
        return [noitru_assignment_gender($a), (string)($a['class_name'] ?? ''), noitru_assignment_grade($a), (string)($a['name'] ?? '')]
            <=> [noitru_assignment_gender($b), (string)($b['class_name'] ?? ''), noitru_assignment_grade($b), (string)($b['name'] ?? '')];
    });
    $result = [];
    $index = 0;
    foreach ($students as $student) {
        if (!isset($names[$index])) $index = count($names) - 1;
        $result[(string)$student['id']] = (string)$names[$index];
        $used = count(array_filter($result, static fn($v) => $v === $names[$index]));
        if ($used >= $capacity && $index < count($names) - 1) $index++;
    }
    return $result;
}

function noitru_assignment_auto_meals(array $students, array $names, int $capacity): array {
    $capacity = max(1, $capacity);
    usort($students, static function ($a, $b) {
        return [(string)($a['class_name'] ?? ''), noitru_assignment_grade($a), noitru_assignment_gender($a), (string)($a['name'] ?? '')]
            <=> [(string)($b['class_name'] ?? ''), noitru_assignment_grade($b), noitru_assignment_gender($b), (string)($b['name'] ?? '')];
    });
    $slots = array_fill_keys($names, []);
    $result = [];
    foreach ($students as $student) {
        $gender = noitru_assignment_gender($student);
        $class = (string)($student['class_name'] ?? '');
        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ($names as $name) {
            $members = $slots[$name];
            if (count($members) >= $capacity) continue;
            $sameClass = count(array_filter($members, static fn($s) => (string)($s['class_name'] ?? '') === $class));
            $sameGender = count(array_filter($members, static fn($s) => noitru_assignment_gender($s) === $gender));
            $score = count($members) * 100 - $sameClass * 20 + $sameGender * 5;
            if ($score < $bestScore) { $bestScore = $score; $best = $name; }
        }
        if ($best === null) $best = $names[array_key_last($names)];
        $slots[$best][] = $student;
        $result[(string)$student['id']] = (string)$best;
    }
    return $result;
}
