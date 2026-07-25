<?php
/**
 * CSDL dùng chung – lớp lưu trữ (JSON)
 * Sau này có thể thay bằng MySQL mà giữ cùng API hàm.
 */
require_once __DIR__ . '/auth.php';

define('CSDL_TEACHERS', DATA_PATH . '/teachers.json');
define('CSDL_CLASSES', DATA_PATH . '/classes.json');
define('CSDL_STUDENTS', DATA_PATH . '/students.json');
define('CSDL_YEARS', DATA_PATH . '/school_years.json');
define('CSDL_SUBJECTS', DATA_PATH . '/subjects.json');

/* —— Helpers —— */
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
    return $years;
}

function csdl_year_current() {
    foreach (csdl_years_all() as $y) {
        if (!empty($y['is_current'])) return $y;
    }
    $all = csdl_years_all();
    return $all[0] ?? null;
}

function csdl_year_set_current($id) {
    $years = csdl_years_all();
    foreach ($years as &$y) {
        $y['is_current'] = (($y['id'] ?? '') === $id);
    }
    unset($y);
    save_json(CSDL_YEARS, $years);
}

function csdl_year_save($data) {
    $years = csdl_years_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($years as &$y) {
        if (($y['id'] ?? '') === $id) {
            $y = array_merge($y, $data);
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
    return $data['id'] ?? $id;
}

/* —— Lớp / khối —— */
function csdl_classes_all() {
    $rows = load_json(CSDL_CLASSES, []);
    if (!$rows) {
        // Seed mẫu THCS + THPT
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
        return $seed;
    }
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
    return $id;
}

function csdl_class_delete($id) {
    $rows = array_values(array_filter(csdl_classes_all(), fn($c) => ($c['id'] ?? '') !== $id));
    save_json(CSDL_CLASSES, $rows);
}

/* —— Giáo viên —— */
function csdl_teachers_all() {
    return load_json(CSDL_TEACHERS, []);
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
    return $id;
}

function csdl_teacher_delete($id) {
    $rows = array_values(array_filter(csdl_teachers_all(), fn($t) => ($t['id'] ?? '') !== $id));
    save_json(CSDL_TEACHERS, $rows);
}

/* —— Học sinh —— */
function csdl_students_all() {
    return load_json(CSDL_STUDENTS, []);
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
    return $id;
}

function csdl_student_delete($id) {
    $rows = array_values(array_filter(csdl_students_all(), fn($s) => ($s['id'] ?? '') !== $id));
    save_json(CSDL_STUDENTS, $rows);
}

/* —— Thống kê nhanh —— */
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
