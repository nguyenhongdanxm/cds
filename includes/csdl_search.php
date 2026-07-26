<?php
/**
 * Tìm kiếm & lọc liên kết trong CSDL (nguồn chuẩn).
 */
require_once __DIR__ . '/csdl_store.php';
require_once __DIR__ . '/csdl_sync.php'; // csdl_norm_name, csdl_format_kiem_nhiem

function csdl_search_hay($text) {
    $text = mb_strtolower(trim((string)$text), 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return $text;
}

function csdl_search_match($haystack, $q) {
    if ($q === '') return true;
    return mb_strpos(csdl_search_hay($haystack), $q) !== false;
}

/**
 * @param string $q từ khóa
 * @param string $scope all|teachers|students|classes|to
 * @return array{teachers:array,students:array,classes:array,to_groups:array,q:string,scope:string}
 */
function csdl_search($q = '', $scope = 'all') {
    $q = csdl_search_hay($q);
    $scope = in_array($scope, ['all', 'teachers', 'students', 'classes', 'to'], true) ? $scope : 'all';

    $teachers = csdl_teachers_all();
    $classes = csdl_classes_all();
    $students = csdl_students_all();

    $classById = [];
    foreach ($classes as $c) $classById[$c['id'] ?? ''] = $c;
    $teacherById = [];
    foreach ($teachers as $t) $teacherById[$t['id'] ?? ''] = $t;

    $outT = [];
    $outS = [];
    $outC = [];
    $toMap = []; // tổ => [teachers]

    if ($scope === 'all' || $scope === 'teachers' || $scope === 'to') {
        foreach ($teachers as $t) {
            $to = trim($t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '');
            $kn = function_exists('csdl_format_kiem_nhiem') ? csdl_format_kiem_nhiem($t['kiem_nhiem'] ?? []) : '';
            $blob = implode(' ', [
                $t['name'] ?? '', $t['code'] ?? '', $t['cccd'] ?? '',
                $t['phone'] ?? '', $t['email'] ?? '', $t['specialty'] ?? '',
                $to, $t['chuc_vu'] ?? '', $kn, $t['hometown'] ?? '', $t['address'] ?? '',
            ]);
            $ok = ($q === '') || csdl_search_match($blob, $q);
            if ($scope === 'to' && $to === '') $ok = false;
            if ($ok && ($scope === 'all' || $scope === 'teachers')) {
                $outT[] = $t;
            }
            if ($to !== '' && (($q === '') || csdl_search_match($to, $q) || csdl_search_match($blob, $q))) {
                if (!isset($toMap[$to])) $toMap[$to] = [];
                $toMap[$to][] = $t;
            }
        }
        ksort($toMap, SORT_NATURAL | SORT_FLAG_CASE);
    }

    if ($scope === 'all' || $scope === 'classes') {
        foreach ($classes as $c) {
            $gvcn = '';
            $hid = $c['homeroom_teacher_id'] ?? '';
            if ($hid && isset($teacherById[$hid])) $gvcn = $teacherById[$hid]['name'] ?? '';
            $blob = implode(' ', [
                $c['name'] ?? '', (string)($c['grade'] ?? ''), $c['level'] ?? '',
                $c['room'] ?? '', $gvcn, $c['note'] ?? '',
            ]);
            if (($q === '') || csdl_search_match($blob, $q)) $outC[] = $c;
        }
        usort($outC, fn($a, $b) => ($a['grade'] ?? 0) <=> ($b['grade'] ?? 0) ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));
    }

    if ($scope === 'all' || $scope === 'students') {
        foreach ($students as $s) {
            $cn = '';
            $cid = $s['class_id'] ?? '';
            if ($cid && isset($classById[$cid])) $cn = $classById[$cid]['name'] ?? '';
            $blob = implode(' ', [
                $s['name'] ?? '', $s['code'] ?? '', $s['cccd'] ?? '',
                $cn, $s['phone'] ?? '', $s['parent_name'] ?? '', $s['parent_phone'] ?? '',
                $s['hometown'] ?? '', $s['address'] ?? '', $s['room_ktx'] ?? '',
            ]);
            if (($q === '') || csdl_search_match($blob, $q)) $outS[] = $s;
        }
    }

    return [
        'q' => $q,
        'scope' => $scope,
        'teachers' => $outT,
        'students' => $outS,
        'classes' => $outC,
        'to_groups' => $toMap,
        'class_by_id' => $classById,
        'teacher_by_id' => $teacherById,
    ];
}
