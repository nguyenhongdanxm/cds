<?php
/**
 * Metadata môn học theo cấp:
 * - thcs_visible / thpt_visible: ẩn ở cấp này không ảnh hưởng cấp kia
 * - thcs_order / thpt_order: thứ tự hiển thị riêng từng cấp
 */

function get_subject_meta_all() {
    return load_json(SUBJECT_META_FILE, []);
}

function save_subject_meta_all($meta) {
    save_json(SUBJECT_META_FILE, $meta);
}

/** Suy luận cấp từ dữ liệu số tiết đã có */
function infer_subject_levels($periods) {
    $has_thcs = false;
    $has_thpt = false;
    if (!is_array($periods)) return [true, true];
    foreach ($periods as $k => $v) {
        if ($v === '' || $v === null || !is_numeric($v) || floatval($v) <= 0) continue;
        $g = intval(preg_replace('/[^0-9]/', '', (string)$k));
        if ($g >= 10) $has_thpt = true;
        elseif ($g >= 6 && $g <= 9) $has_thcs = true;
    }
    // Không có dữ liệu → hiện cả hai để người dùng tự ẩn
    if (!$has_thcs && !$has_thpt) return [true, true];
    return [$has_thcs, $has_thpt];
}

/**
 * Đảm bảo mọi môn có meta; khởi tạo từ dữ liệu tiết nếu chưa có.
 */
function ensure_subject_meta() {
    $subjects = get_subjects();
    $meta = get_subject_meta_all();
    $changed = false;
    $i = 0;
    foreach ($subjects as $name => $periods) {
        $i++;
        if (!isset($meta[$name]) || !is_array($meta[$name])) {
            [$thcs, $thpt] = infer_subject_levels($periods);
            $meta[$name] = [
                'thcs_visible' => $thcs,
                'thpt_visible' => $thpt,
                'thcs_order' => $i * 10,
                'thpt_order' => $i * 10,
            ];
            $changed = true;
        } else {
            $m = &$meta[$name];
            if (!array_key_exists('thcs_visible', $m)) { $m['thcs_visible'] = true; $changed = true; }
            if (!array_key_exists('thpt_visible', $m)) { $m['thpt_visible'] = true; $changed = true; }
            if (!isset($m['thcs_order'])) { $m['thcs_order'] = $i * 10; $changed = true; }
            if (!isset($m['thpt_order'])) { $m['thpt_order'] = $i * 10; $changed = true; }
            unset($m);
        }
    }
    // Xóa meta môn đã bị xóa
    foreach (array_keys($meta) as $n) {
        if (!isset($subjects[$n])) {
            unset($meta[$n]);
            $changed = true;
        }
    }
    if ($changed) save_subject_meta_all($meta);
    return $meta;
}

function get_subject_meta($name) {
    $meta = ensure_subject_meta();
    return $meta[$name] ?? [
        'thcs_visible' => true,
        'thpt_visible' => true,
        'thcs_order' => 9999,
        'thpt_order' => 9999,
    ];
}

function is_subject_visible_for_level($name, $level) {
    $m = get_subject_meta($name);
    if ($level === 'thpt') return !empty($m['thpt_visible']);
    return !empty($m['thcs_visible']);
}

function set_subject_visible($name, $level, $visible) {
    $meta = ensure_subject_meta();
    if (!isset($meta[$name])) $meta[$name] = [];
    if ($level === 'thpt') $meta[$name]['thpt_visible'] = (bool)$visible;
    else $meta[$name]['thcs_visible'] = (bool)$visible;
    save_subject_meta_all($meta);
}

/**
 * Danh sách môn theo cấp, đã sắp xếp.
 * @param string $level thcs|thpt
 * @param bool $only_visible
 * @return array name => periods
 */
function get_subjects_for_level($level, $only_visible = true) {
    $subjects = get_subjects();
    $meta = ensure_subject_meta();
    $vis_key = ($level === 'thpt') ? 'thpt_visible' : 'thcs_visible';
    $ord_key = ($level === 'thpt') ? 'thpt_order' : 'thcs_order';

    $rows = [];
    foreach ($subjects as $name => $periods) {
        $m = $meta[$name] ?? [];
        $vis = array_key_exists($vis_key, $m) ? !empty($m[$vis_key]) : true;
        if ($only_visible && !$vis) continue;
        $rows[] = [
            'name' => $name,
            'periods' => is_array($periods) ? $periods : [],
            'order' => intval($m[$ord_key] ?? 9999),
            'visible' => $vis,
        ];
    }
    usort($rows, function ($a, $b) {
        if ($a['order'] !== $b['order']) return $a['order'] <=> $b['order'];
        return strcmp(mb_strtolower($a['name'], 'UTF-8'), mb_strtolower($b['name'], 'UTF-8'));
    });

    $out = [];
    foreach ($rows as $r) $out[$r['name']] = $r['periods'];
    return $out;
}

/** Đổi thứ tự môn trong 1 cấp (up|down) — chỉ trong nhóm visible của cấp đó */
function move_subject_order($name, $level, $dir) {
    $meta = ensure_subject_meta();
    $subjects = get_subjects();
    if (!isset($subjects[$name])) return false;

    $vis_key = ($level === 'thpt') ? 'thpt_visible' : 'thcs_visible';
    $ord_key = ($level === 'thpt') ? 'thpt_order' : 'thcs_order';

    // Chỉ sắp trong các môn đang hiện của cấp
    $list = [];
    foreach ($subjects as $n => $_) {
        $m = $meta[$n] ?? [];
        $vis = array_key_exists($vis_key, $m) ? !empty($m[$vis_key]) : true;
        if (!$vis) continue;
        $list[] = ['name' => $n, 'order' => intval($m[$ord_key] ?? 9999)];
    }
    usort($list, fn($a, $b) => $a['order'] <=> $b['order'] ?: strcmp($a['name'], $b['name']));

    $idx = null;
    foreach ($list as $i => $row) {
        if ($row['name'] === $name) { $idx = $i; break; }
    }
    if ($idx === null) return false;

    $swap = ($dir === 'up') ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($list)) return false;

    $a = $list[$idx]['name'];
    $b = $list[$swap]['name'];
    $oa = intval($meta[$a][$ord_key] ?? ($idx + 1) * 10);
    $ob = intval($meta[$b][$ord_key] ?? ($swap + 1) * 10);
    $meta[$a][$ord_key] = $ob;
    $meta[$b][$ord_key] = $oa;

    // Chuẩn hóa lại order 10,20,30… theo thứ tự mới
    $list[$idx]['order'] = $ob;
    $list[$swap]['order'] = $oa;
    usort($list, fn($x, $y) => $x['order'] <=> $y['order'] ?: strcmp($x['name'], $y['name']));
    foreach ($list as $i => $row) {
        $meta[$row['name']][$ord_key] = ($i + 1) * 10;
    }

    save_subject_meta_all($meta);
    return true;
}

function delete_subject_meta($name) {
    $meta = get_subject_meta_all();
    if (isset($meta[$name])) {
        unset($meta[$name]);
        save_subject_meta_all($meta);
    }
}
