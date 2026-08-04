<?php
/**
 * CSDL dùng chung – lớp lưu trữ (JSON)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database_shadow.php';
require_once __DIR__ . '/database_read_verify.php';

define('CSDL_TEACHERS', DATA_PATH . '/teachers.json');
define('CSDL_CLASSES', DATA_PATH . '/classes.json');
define('CSDL_STUDENTS', DATA_PATH . '/students.json');
define('CSDL_YEARS', DATA_PATH . '/school_years.json');
define('CSDL_SUBJECTS', DATA_PATH . '/subjects.json');

function csdl_uid($prefix = 'id') {
    return $prefix . '_' . bin2hex(random_bytes(4));
}

function csdl_now() {
    return date('c');
}

/* —— Năm học —— */
function csdl_years_all() {
    $years = load_json(CSDL_YEARS, []);
    if (!$years) {
        $years = [[
            'id' => 'y_2025',
            'label' => '2025–2026',
            'start' => '2025-09-01',
            'end' => '2026-05-31',
            'is_current' => true,
        ]];
        save_json(CSDL_YEARS, $years);
    }
    cds_read_verify_rows('years', $years);
    return $years;
}

function csdl_year_current() {
    foreach (csdl_years_all() as $y) {
        if (!empty($y['is_current'])) return $y;
    }
    $all = csdl_years_all();
    return $all[0] ?? null;
}

function csdl_year_find($id) {
    foreach (csdl_years_all() as $year) {
        if (($year['id'] ?? '') === $id) return $year;
    }
    return null;
}

function csdl_date_valid($value) {
    if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

/**
 * Lịch tuần chuẩn của một năm học.
 * Mỗi module dùng hàm này thay vì tự tính "monday this week".
 */
function csdl_year_weeks($year = null) {
    if (is_string($year)) $year = csdl_year_find($year);
    if (!is_array($year)) $year = csdl_year_current();
    if (!$year) return [];

    $start = $year['start'] ?? '';
    $end = $year['end'] ?? '';
    if (!csdl_date_valid($start) || !csdl_date_valid($end) || $end < $start) return [];

    $saved = [];
    foreach (($year['weeks'] ?? []) as $row) {
        $number = (int)($row['number'] ?? 0);
        $weekStart = $row['start'] ?? '';
        if ($number > 0 && csdl_date_valid($weekStart)) $saved[$number] = $weekStart;
    }

    $weeks = [];
    $cursor = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    for ($number = 1; $number <= 60 && $cursor <= $endDate; $number++) {
        if (isset($saved[$number])) $cursor = new DateTimeImmutable($saved[$number]);
        $weekEnd = $cursor->modify('+6 days');
        $weeks[] = [
            'number' => $number,
            'start' => $cursor->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
            'label' => 'Tuần ' . $number,
        ];
        $cursor = $cursor->modify('+7 days');
    }
    return $weeks;
}

function csdl_year_week_adjust($yearId, $weekNumber, $newStart) {
    $weekNumber = (int)$weekNumber;
    if ($weekNumber < 1 || !csdl_date_valid($newStart)) {
        return ['ok' => false, 'message' => 'Tuần hoặc ngày bắt đầu không hợp lệ.'];
    }

    $years = csdl_years_all();
    foreach ($years as &$year) {
        if (($year['id'] ?? '') !== $yearId) continue;
        $weeks = csdl_year_weeks($year);
        if (!$weeks || $weekNumber > count($weeks)) {
            unset($year);
            return ['ok' => false, 'message' => 'Không tìm thấy tuần trong năm học.'];
        }

        $newCursor = new DateTimeImmutable($newStart);
        foreach ($weeks as &$week) {
            if ((int)$week['number'] < $weekNumber) continue;
            $offset = ((int)$week['number'] - $weekNumber) * 7;
            $startDate = $newCursor->modify('+' . $offset . ' days');
            $week['start'] = $startDate->format('Y-m-d');
            $week['end'] = $startDate->modify('+6 days')->format('Y-m-d');
        }
        unset($week);
        $year['weeks'] = array_map(fn($week) => [
            'number' => (int)$week['number'],
            'start' => $week['start'],
            'end' => $week['end'],
        ], $weeks);
        $year['weeks_updated_at'] = csdl_now();
        save_json(CSDL_YEARS, $years);
        cds_shadow_refresh_core('school_year', $yearId);
        unset($year);
        return ['ok' => true, 'message' => 'Đã cập nhật lịch tuần dùng chung.'];
    }
    unset($year);
    return ['ok' => false, 'message' => 'Không tìm thấy năm học.'];
}

function csdl_week_for_date($date = null, $year = null) {
    $date = $date ?: date('Y-m-d');
    if (!csdl_date_valid($date)) $date = date('Y-m-d');
    foreach (csdl_year_weeks($year) as $week) {
        if ($date >= $week['start'] && $date <= $week['end']) return $week;
    }
    return null;
}

function csdl_current_week($date = null) {
    return csdl_week_for_date($date ?: date('Y-m-d'), csdl_year_current());
}

function csdl_year_set_current($id) {
    $years = csdl_years_all();
    foreach ($years as &$y) {
        $y['is_current'] = (($y['id'] ?? '') === $id);
    }
    unset($y);
    save_json(CSDL_YEARS, $years);
    cds_shadow_refresh_core('school_year', $id);
}

function csdl_year_save($data) {
    $years = csdl_years_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($years as &$y) {
        if (($y['id'] ?? '') === $id) {
            $oldStart = $y['start'] ?? '';
            $oldEnd = $y['end'] ?? '';
            $y = array_merge($y, $data);
            if (($y['start'] ?? '') !== $oldStart || ($y['end'] ?? '') !== $oldEnd) {
                unset($y['weeks'], $y['weeks_updated_at']);
            }
            $found = true;
            break;
        }
    }
    unset($y);
    if (!$found) {
        if (!$id) $data['id'] = csdl_uid('y');
        $years[] = $data;
    }
    save_json(CSDL_YEARS, $years);
    cds_shadow_refresh_core('school_year', $data['id'] ?? $id);
    return $data['id'] ?? $id;
}

function csdl_year_delete($id) {
    $years = csdl_years_all();
    $years = array_values(array_filter($years, fn($y) => ($y['id'] ?? '') !== $id));
    // Nếu xóa năm hiện hành → đặt năm đầu tiên làm hiện hành
    $hasCurrent = false;
    foreach ($years as $y) {
        if (!empty($y['is_current'])) { $hasCurrent = true; break; }
    }
    if (!$hasCurrent && $years) {
        $years[0]['is_current'] = true;
    }
    save_json(CSDL_YEARS, $years);
    cds_shadow_refresh_core('school_year', $id);
}

/* —— Lớp / khối —— */
function csdl_classes_all() {
    $rows = load_json(CSDL_CLASSES, []);
    if (!$rows) {
        $seed = [];
        $map = [
            6 => ['A','B'], 7 => ['A','B'], 8 => ['A','B'], 9 => ['A','B'],
            10 => ['A','B'], 11 => ['A','B'], 12 => ['A','B'],
        ];
        foreach ($map as $k => $suffixes) {
            $cap = $k <= 9 ? 'THCS' : 'THPT';
            foreach ($suffixes as $s) {
                $seed[] = [
                    'id' => csdl_uid('cl'),
                    'name' => $k . $s,
                    'grade' => $k,
                    'level' => $cap,
                    'homeroom_teacher_id' => '',
                    'room' => '',
                    'active' => true,
                    'created_at' => csdl_now(),
                ];
            }
        }
        save_json(CSDL_CLASSES, $seed);
        cds_read_verify_rows('classes', $seed);
        return $seed;
    }
    cds_read_verify_rows('classes', $rows);
    return $rows;
}

function csdl_class_find($id) {
    foreach (csdl_classes_all() as $c) {
        if (($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

function csdl_class_save($data) {
    $rows = csdl_classes_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$c) {
        if (($c['id'] ?? '') === $id) {
            $c = array_merge($c, $data);
            $c['updated_at'] = csdl_now();
            $found = true;
            break;
        }
    }
    unset($c);
    if (!$found) {
        $id = $id ?: csdl_uid('cl');
        $data['id'] = $id;
        $data['created_at'] = csdl_now();
        $data['active'] = $data['active'] ?? true;
        $rows[] = $data;
    }
    save_json(CSDL_CLASSES, $rows);
    cds_shadow_refresh_core('class', $id);
    return $id;
}

function csdl_class_delete($id) {
    $rows = array_values(array_filter(csdl_classes_all(), fn($c) => ($c['id'] ?? '') !== $id));
    save_json(CSDL_CLASSES, $rows);
    cds_shadow_refresh_core('class', $id);
}

/* —— Giáo viên —— */
function csdl_teachers_all() {
    $rows = load_json(CSDL_TEACHERS, []);
    cds_read_verify_rows('teachers', $rows);
    return $rows;
}

function csdl_teacher_find($id) {
    foreach (csdl_teachers_all() as $t) {
        if (($t['id'] ?? '') === $id) return $t;
    }
    return null;
}

function csdl_teacher_save($data) {
    $rows = csdl_teachers_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$t) {
        if (($t['id'] ?? '') === $id) {
            $t = array_merge($t, $data);
            $t['updated_at'] = csdl_now();
            $found = true;
            break;
        }
    }
    unset($t);
    if (!$found) {
        $id = $id ?: csdl_uid('gv');
        $data['id'] = $id;
        $data['created_at'] = csdl_now();
        $data['active'] = $data['active'] ?? true;
        $rows[] = $data;
    }
    save_json(CSDL_TEACHERS, $rows);
    cds_shadow_refresh_core('teacher', $id);
    return $id;
}

function csdl_teacher_delete($id) {
    $rows = array_values(array_filter(csdl_teachers_all(), fn($t) => ($t['id'] ?? '') !== $id));
    save_json(CSDL_TEACHERS, $rows);
    cds_shadow_refresh_core('teacher', $id);
}

/* —— Học sinh —— */
function csdl_students_all() {
    $rows = load_json(CSDL_STUDENTS, []);
    cds_read_verify_rows('students', $rows);
    return $rows;
}

function csdl_student_find($id) {
    foreach (csdl_students_all() as $s) {
        if (($s['id'] ?? '') === $id) return $s;
    }
    return null;
}

function csdl_student_save($data) {
    $rows = csdl_students_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$s) {
        if (($s['id'] ?? '') === $id) {
            $s = array_merge($s, $data);
            $s['updated_at'] = csdl_now();
            $found = true;
            break;
        }
    }
    unset($s);
    if (!$found) {
        $id = $id ?: csdl_uid('hs');
        $data['id'] = $id;
        $data['created_at'] = csdl_now();
        $data['active'] = $data['active'] ?? true;
        $rows[] = $data;
    }
    save_json(CSDL_STUDENTS, $rows);
    cds_shadow_refresh_core('student', $id);
    return $id;
}

function csdl_student_delete($id) {
    $rows = array_values(array_filter(csdl_students_all(), fn($s) => ($s['id'] ?? '') !== $id));
    save_json(CSDL_STUDENTS, $rows);
    cds_shadow_refresh_core('student', $id);
}

function csdl_stats() {
    $teachers = csdl_teachers_all();
    $classes = csdl_classes_all();
    $students = csdl_students_all();
    return [
        'teachers' => count(array_filter($teachers, fn($t) => !empty($t['active']))),
        'teachers_total' => count($teachers),
        'classes' => count(array_filter($classes, fn($c) => !empty($c['active']))),
        'students' => count(array_filter($students, fn($s) => !empty($s['active']))),
        'students_total' => count($students),
        'year' => csdl_year_current()['label'] ?? SCHOOL_YEAR,
    ];
}
