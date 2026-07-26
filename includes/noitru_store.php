<?php
/**
 * Quản lý nội trú – lớp dữ liệu.
 * HS nội trú lấy từ CSDL (boarder=true); dữ liệu vận hành lưu data/noitru/.
 */
require_once __DIR__ . '/csdl_store.php';

define('NOITRU_DIR', DATA_PATH . '/noitru');
define('NOITRU_META', NOITRU_DIR . '/meta.json');
define('NOITRU_BOARDERS', NOITRU_DIR . '/boarders_cache.json');

function noitru_ensure_dir() {
    if (!is_dir(NOITRU_DIR)) {
        @mkdir(NOITRU_DIR, 0755, true);
    }
}

function noitru_meta() {
    noitru_ensure_dir();
    return load_json(NOITRU_META, [
        'last_sync_at' => null,
        'last_sync_count' => 0,
    ]);
}

function noitru_meta_save(array $meta) {
    noitru_ensure_dir();
    save_json(NOITRU_META, $meta);
}

/** Lớp name lookup */
function noitru_class_name($classId) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (csdl_classes_all() as $c) {
            $map[$c['id'] ?? ''] = $c['name'] ?? '';
        }
    }
    return $map[$classId] ?? '';
}

/**
 * HS nội trú trực tiếp từ CSDL (nguồn chuẩn).
 * @return array
 */
function noitru_boarders_live() {
    $out = [];
    foreach (csdl_students_all() as $s) {
        if (empty($s['active'])) continue;
        if (empty($s['boarder'])) continue;
        $out[] = [
            'id' => $s['id'] ?? '',
            'code' => $s['code'] ?? '',
            'name' => $s['name'] ?? '',
            'cccd' => $s['cccd'] ?? '',
            'class_id' => $s['class_id'] ?? '',
            'class_name' => noitru_class_name($s['class_id'] ?? ''),
            'gender' => $s['gender'] ?? '',
            'dob' => $s['dob'] ?? '',
            'phone' => $s['phone'] ?? '',
            'parent_name' => $s['parent_name'] ?? '',
            'parent_phone' => $s['parent_phone'] ?? '',
            'room_ktx' => $s['room_ktx'] ?? '',
            'meal_group' => $s['meal_group'] ?? '',
            'note' => $s['note'] ?? '',
        ];
    }
    usort($out, function ($a, $b) {
        $c = strcmp($a['class_name'], $b['class_name']);
        return $c !== 0 ? $c : strcmp($a['name'], $b['name']);
    });
    return $out;
}

/**
 * Đồng bộ snapshot từ CSDL → cache nội trú (để thống kê / offline nhẹ).
 * Luôn lấy lại từ CSDL khi xem danh sách; cache chỉ ghi nhận lần đồng bộ.
 */
function noitru_sync_from_csdl() {
    noitru_ensure_dir();
    $list = noitru_boarders_live();
    save_json(NOITRU_BOARDERS, $list);
    $meta = noitru_meta();
    $meta['last_sync_at'] = date('c');
    $meta['last_sync_count'] = count($list);
    noitru_meta_save($meta);
    return [
        'ok' => true,
        'count' => count($list),
        'message' => 'Đã đồng bộ ' . count($list) . ' học sinh nội trú từ CSDL.',
    ];
}

function noitru_stats() {
    $list = noitru_boarders_live();
    $byClass = [];
    $byRoom = [];
    $byMeal = [];
    foreach ($list as $s) {
        $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa xếp lớp)';
        $byClass[$cn] = ($byClass[$cn] ?? 0) + 1;
        $rm = trim($s['room_ktx'] ?? '');
        if ($rm === '') $rm = '(Chưa xếp phòng)';
        $byRoom[$rm] = ($byRoom[$rm] ?? 0) + 1;
        $mg = trim($s['meal_group'] ?? '');
        if ($mg === '') $mg = '(Chưa có nhóm ăn)';
        $byMeal[$mg] = ($byMeal[$mg] ?? 0) + 1;
    }
    ksort($byClass, SORT_NATURAL);
    ksort($byRoom, SORT_NATURAL);
    ksort($byMeal, SORT_NATURAL);
    $meta = noitru_meta();
    return [
        'total' => count($list),
        'by_class' => $byClass,
        'by_room' => $byRoom,
        'by_meal' => $byMeal,
        'last_sync_at' => $meta['last_sync_at'] ?? null,
        'last_sync_count' => (int)($meta['last_sync_count'] ?? 0),
    ];
}
