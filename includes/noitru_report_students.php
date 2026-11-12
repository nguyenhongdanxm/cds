<?php
/** Same roster transformation as the interactive boarding page; standalone Word export. */
function noitru_attendance_students_all($date = null) {
    $classMap = [];
    foreach (csdl_classes_all() as $class) {
        $classMap[(string)($class['id'] ?? '')] = (string)($class['name'] ?? '');
    }
    $students = [];
    foreach (csdl_students_all() as $student) {
        if (!csdl_student_is_active_on($student, $date ?? date('Y-m-d'))) continue;
        $student['class_name'] = trim((string)($student['class_name'] ?? ''));
        if ($student['class_name'] === '') {
            $student['class_name'] = $classMap[(string)($student['class_id'] ?? '')] ?? '';
        }
        $student['room_ktx'] = (string)($student['room_ktx'] ?? '');
        $students[] = $student;
    }
    csdl_sort_students($students);
    return $students;
}
