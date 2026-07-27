<?php
/** Cấu hình buổi điểm danh nội trú */
if (!defined('NOITRU_DIR')) {
    // fallback if loaded standalone
    if (!defined('DATA_PATH')) require_once __DIR__ . '/config.php';
    define('NOITRU_DIR', DATA_PATH . '/noitru');
}
if (!defined('NOITRU_SHIFTS')) {
    define('NOITRU_SHIFTS', NOITRU_DIR . '/att_shifts.json');
}

function noitru_att_shifts_default() {
    return [
        ['id' => 'the_duc_sang', 'label' => 'Thể dục buổi sáng', 'active' => true, 'sort' => 10],
        ['id' => 'sang',         'label' => 'Điểm danh sáng',    'active' => true, 'sort' => 20],
        ['id' => 'trua',         'label' => 'Buổi trưa',         'active' => true, 'sort' => 30],
        ['id' => 'toi',          'label' => 'Điểm danh tối',     'active' => true, 'sort' => 40],
        ['id' => 'hoc_toi',      'label' => 'Học tối',           'active' => true, 'sort' => 50],
        ['id' => 'dem',          'label' => 'Điểm danh đêm',     'active' => true, 'sort' => 60],
    ];
}

if (!function_exists('noitru_att_shifts_all')) {
function noitru_att_shifts_all() {
    if (function_exists('noitru_ensure_dir')) noitru_ensure_dir();
    elseif (!is_dir(NOITRU_DIR)) @mkdir(NOITRU_DIR, 0755, true);
    $rows = function_exists('load_json') ? load_json(NOITRU_SHIFTS, null) : null;
    if (!is_array($rows) || !$rows) {
        $rows = noitru_att_shifts_default();
        if (function_exists('save_json')) save_json(NOITRU_SHIFTS, $rows);
    }
    usort($rows, fn($a, $b) => ($a['sort'] ?? 99) <=> ($b['sort'] ?? 99));
    return $rows;
}
}

if (!function_exists('noitru_att_shifts_active')) {
function noitru_att_shifts_active() {
    $out = [];
    foreach (noitru_att_shifts_all() as $s) {
        if (!empty($s['active'])) $out[$s['id']] = $s['label'];
    }
    return $out;
}
}

if (!function_exists('noitru_att_shifts_save')) {
function noitru_att_shifts_save(array $rows) {
    if (function_exists('noitru_ensure_dir')) noitru_ensure_dir();
    $clean = [];
    foreach ($rows as $r) {
        $id = trim($r['id'] ?? '');
        $label = trim($r['label'] ?? '');
        if ($id === '' || $label === '') continue;
        $id = preg_replace('/[^a-z0-9_]/', '', strtolower($id));
        if ($id === '') continue;
        $clean[] = [
            'id' => $id,
            'label' => $label,
            'active' => !empty($r['active']),
            'sort' => (int)($r['sort'] ?? 99),
        ];
    }
    if (!$clean) $clean = noitru_att_shifts_default();
    usort($clean, fn($a, $b) => $a['sort'] <=> $b['sort']);
    if (function_exists('save_json')) save_json(NOITRU_SHIFTS, $clean);
    return $clean;
}
}

if (!function_exists('noitru_att_bulk')) {
function noitru_att_bulk(array $studentIds, $date, $shift, $status, $by = '') {
    foreach ($studentIds as $sid) {
        $sid = trim($sid);
        if ($sid === '') continue;
        noitru_att_upsert([
            'date' => $date,
            'shift' => $shift,
            'student_id' => $sid,
            'status' => $status,
            'by' => $by,
        ]);
    }
}
}
