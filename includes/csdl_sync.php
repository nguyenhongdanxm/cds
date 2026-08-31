<?php
/**
 * Đồng bộ 2 chiều CSDL (CDS) ↔ PCCM
 *
 * PCCM:
 *   teachers.json, teacher_meta.json, classes.json
 *   roles_{version}.json  = kiêm nhiệm [{teacher, role, class, periods}]
 *   active_version.json
 */
require_once __DIR__ . '/csdl_store.php';

function csdl_sync_pccm_ready() {
    return PCCM_DATA_PATH !== '' && is_dir(PCCM_DATA_PATH);
}

function csdl_sync_pccm_path_info() {
    $vid = csdl_pccm_active_version_id();
    return [
        'ready' => csdl_sync_pccm_ready(),
        'path' => PCCM_DATA_PATH,
        'teachers' => csdl_sync_pccm_ready() && file_exists(PCCM_DATA_PATH . '/teachers.json'),
        'meta' => csdl_sync_pccm_ready() && file_exists(PCCM_DATA_PATH . '/teacher_meta.json'),
        'classes' => csdl_sync_pccm_ready() && file_exists(PCCM_DATA_PATH . '/classes.json'),
        'roles' => $vid && file_exists(csdl_pccm_roles_file($vid)),
        'version' => $vid,
    ];
}

function csdl_norm_name($name) {
    $name = trim(preg_replace('/\s+/u', ' ', $name ?? ''));
    return mb_strtolower($name, 'UTF-8');
}

function csdl_pccm_active_version_id() {
    if (!csdl_sync_pccm_ready()) return null;
    $data = load_json(PCCM_DATA_PATH . '/active_version.json', []);
    if (!empty($data['id'])) return $data['id'];
    $versions = load_json(PCCM_DATA_PATH . '/versions.json', []);
    if ($versions) {
        $last = end($versions);
        return $last['id'] ?? null;
    }
    return null;
}

function csdl_pccm_roles_file($vid) {
    return PCCM_DATA_PATH . '/roles_' . $vid . '.json';
}

/** Đọc toàn bộ kiêm nhiệm theo tên GV */
function csdl_pccm_load_roles_by_teacher() {
    $vid = csdl_pccm_active_version_id();
    if (!$vid) return [];
    $items = load_json(csdl_pccm_roles_file($vid), []);
    if (!$items && file_exists(PCCM_DATA_PATH . '/role_assignments.json')) {
        $items = load_json(PCCM_DATA_PATH . '/role_assignments.json', []);
    }
    $by = [];
    foreach ($items as $a) {
        $t = trim($a['teacher'] ?? '');
        if ($t === '') continue;
        $key = csdl_norm_name($t);
        if (!isset($by[$key])) $by[$key] = [];
        $by[$key][] = [
            'role' => trim($a['role'] ?? ''),
            'class' => trim($a['class'] ?? ''),
            'periods' => isset($a['periods']) ? floatval($a['periods']) : null,
        ];
    }
    return $by;
}

/** Chuỗi hiển thị: GVCN (6A); TTCM */
function csdl_format_kiem_nhiem($items) {
    if (!is_array($items) || !$items) return '';
    $parts = [];
    foreach ($items as $a) {
        $role = trim($a['role'] ?? '');
        if ($role === '') continue;
        $cls = trim($a['class'] ?? '');
        $parts[] = $cls !== '' ? ($role . ' (' . $cls . ')') : $role;
    }
    return implode('; ', $parts);
}

/* ========== PCCM → CDS ========== */

function csdl_sync_from_pccm() {
    if (!csdl_sync_pccm_ready()) {
        return ['ok' => false, 'message' => 'Không tìm thấy thư mục data PCCM. Kiểm tra PCCM_DATA_PATH trong config.'];
    }

    return cds_shadow_batch_run(function () {
    $pccm_teachers = load_json(PCCM_DATA_PATH . '/teachers.json', []);
    $pccm_meta = load_json(PCCM_DATA_PATH . '/teacher_meta.json', []);
    $pccm_classes = load_json(PCCM_DATA_PATH . '/classes.json', []);
    $roles_by = csdl_pccm_load_roles_by_teacher();

    $cds = csdl_teachers_all();
    $by_name = [];
    foreach ($cds as $t) {
        $by_name[csdl_norm_name($t['name'] ?? '')] = $t;
    }

    $added_t = 0;
    $updated_t = 0;
    $roles_count = 0;
    $name_to_id = [];

    foreach ($pccm_teachers as $name) {
        if (!is_string($name) || trim($name) === '') continue;
        $name = trim($name);
        $meta = $pccm_meta[$name] ?? [];
        $cm = $meta['chuyen_mon'] ?? [];
        if (is_string($cm)) $cm = $cm !== '' ? [$cm] : [];
        if (!is_array($cm)) $cm = [];
        $specialty = implode(', ', array_filter(array_map('strval', $cm)));

        $key = csdl_norm_name($name);
        $kiem = $roles_by[$key] ?? [];
        $roles_count += count($kiem);

        $payload = [
            'name' => $name,
            'specialty' => $specialty,
            'to_chuyen_mon' => trim($meta['group'] ?? ''),
            'kiem_nhiem' => $kiem,
            'role_flags' => [
                'is_probation' => !empty($meta['tap_su']),
                'is_principal' => !empty($meta['hieu_truong']),
                'is_vice' => !empty($meta['pho_hieu_truong']),
            ],
            'pccm_group' => $meta['group'] ?? '',
            'pccm_thcs' => array_key_exists('thcs', $meta)
                ? !empty($meta['thcs'])
                : (($meta['level'] ?? '') !== 'THPT'),
            'pccm_thpt' => array_key_exists('thpt', $meta)
                ? !empty($meta['thpt'])
                : (($meta['level'] ?? '') === 'THPT'),
            'active' => true,
            'source' => 'pccm',
        ];

        if (isset($by_name[$key])) {
            $payload['id'] = $by_name[$key]['id'];
            if (!empty($by_name[$key]['phone'])) $payload['phone'] = $by_name[$key]['phone'];
            if (!empty($by_name[$key]['email'])) $payload['email'] = $by_name[$key]['email'];
            if (!empty($by_name[$key]['code'])) $payload['code'] = $by_name[$key]['code'];
            if (!empty($by_name[$key]['note'])) $payload['note'] = $by_name[$key]['note'];
            $id = csdl_teacher_save($payload);
            $updated_t++;
        } else {
            $id = csdl_teacher_save($payload);
            $added_t++;
        }
        $name_to_id[$key] = $id;
    }

    $cds_cls = csdl_classes_all();
    $cls_by_name = [];
    foreach ($cds_cls as $c) {
        $cls_by_name[csdl_norm_name($c['name'] ?? '')] = $c;
    }

    $gvcn_map = [];
    foreach ($roles_by as $tkey => $items) {
        foreach ($items as $a) {
            $role = mb_strtoupper(trim($a['role'] ?? ''), 'UTF-8');
            if ($role === 'GVCN' && !empty($a['class']) && isset($name_to_id[$tkey])) {
                $gvcn_map[csdl_norm_name($a['class'])] = $name_to_id[$tkey];
            }
        }
    }

    $added_c = 0;
    $updated_c = 0;

    foreach ($pccm_classes as $cname) {
        if (!is_string($cname) || trim($cname) === '') continue;
        $cname = trim($cname);
        $grade = (int)preg_replace('/\D/', '', $cname);
        if ($grade < 1) $grade = 6;
        $ckey = csdl_norm_name($cname);

        $payload = [
            'name' => $cname,
            'grade' => $grade,
            'level' => $grade >= 10 ? 'THPT' : 'THCS',
            'active' => true,
            'source' => 'pccm',
        ];

        if (isset($gvcn_map[$ckey])) {
            $payload['homeroom_teacher_id'] = $gvcn_map[$ckey];
        }

        if (isset($cls_by_name[$ckey])) {
            $payload['id'] = $cls_by_name[$ckey]['id'];
            if (empty($payload['homeroom_teacher_id']) && !empty($cls_by_name[$ckey]['homeroom_teacher_id'])) {
                $payload['homeroom_teacher_id'] = $cls_by_name[$ckey]['homeroom_teacher_id'];
            }
            if (!empty($cls_by_name[$ckey]['room'])) {
                $payload['room'] = $cls_by_name[$ckey]['room'];
            }
            csdl_class_save($payload);
            $updated_c++;
        } else {
            csdl_class_save($payload);
            $added_c++;
        }
    }

    $vid = csdl_pccm_active_version_id();
    return [
        'ok' => true,
        'message' => sprintf(
            'Đã kéo từ PCCM → CDS: GV +%d / cập nhật %d · Lớp +%d / cập nhật %d · %d kiêm nhiệm (phiên bản %s)',
            $added_t, $updated_t, $added_c, $updated_c, $roles_count, $vid ?: '—'
        ),
        'teachers_added' => $added_t,
        'teachers_updated' => $updated_t,
        'classes_added' => $added_c,
        'classes_updated' => $updated_c,
        'roles' => $roles_count,
    ];
    });
}

/* ========== CDS → PCCM ========== */

function csdl_sync_to_pccm() {
    if (!csdl_sync_pccm_ready()) {
        return ['ok' => false, 'message' => 'Không tìm thấy thư mục data PCCM. Kiểm tra PCCM_DATA_PATH trong config.'];
    }
    if (!is_writable(PCCM_DATA_PATH)) {
        return ['ok' => false, 'message' => 'Thư mục data PCCM không ghi được (chmod).'];
    }

    $teachers = csdl_teachers_all();
    $classes = csdl_classes_all();

    $names = [];
    $meta = load_json(PCCM_DATA_PATH . '/teacher_meta.json', []);
    $role_items = [];

    foreach ($teachers as $t) {
        if (empty($t['active'])) continue;
        $name = trim($t['name'] ?? '');
        if ($name === '') continue;
        $names[] = $name;

        if (!isset($meta[$name]) || !is_array($meta[$name])) $meta[$name] = [];

        $cm = [];
        if (!empty($t['specialty'])) {
            $cm = array_values(array_filter(array_map('trim', preg_split('/[,;|\/]+/u', $t['specialty']))));
        }
        $meta[$name]['chuyen_mon'] = $cm;

        $flags = $t['role_flags'] ?? [];
        $meta[$name]['tap_su'] = !empty($flags['is_probation']);
        $meta[$name]['hieu_truong'] = !empty($flags['is_principal']);
        $meta[$name]['pho_hieu_truong'] = !empty($flags['is_vice']) && empty($flags['is_principal']);

        $group = trim($t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '');
        if ($group !== '') $meta[$name]['group'] = $group;

        if (isset($t['pccm_thcs'])) $meta[$name]['thcs'] = !empty($t['pccm_thcs']);
        if (isset($t['pccm_thpt'])) $meta[$name]['thpt'] = !empty($t['pccm_thpt']);
        if (empty($meta[$name]['thcs']) && empty($meta[$name]['thpt'])) {
            $meta[$name]['thcs'] = true;
            $meta[$name]['thpt'] = true;
        }

        $kiem = $t['kiem_nhiem'] ?? [];
        if (is_array($kiem)) {
            foreach ($kiem as $a) {
                $role = trim($a['role'] ?? '');
                if ($role === '') continue;
                $role_items[] = [
                    'id' => 'r_' . bin2hex(random_bytes(4)),
                    'teacher' => $name,
                    'role' => $role,
                    'class' => trim($a['class'] ?? ''),
                    'periods' => isset($a['periods']) && $a['periods'] !== null && $a['periods'] !== ''
                        ? floatval($a['periods']) : 0,
                ];
            }
        }
    }

    $teacher_by_id = [];
    foreach ($teachers as $t) {
        $teacher_by_id[$t['id'] ?? ''] = $t;
    }
    $existing_gvcn = [];
    foreach ($role_items as $ri) {
        if (mb_strtoupper($ri['role'], 'UTF-8') === 'GVCN' && $ri['class'] !== '') {
            $existing_gvcn[csdl_norm_name($ri['class'])] = true;
        }
    }
    foreach ($classes as $c) {
        if (empty($c['active']) || empty($c['homeroom_teacher_id'])) continue;
        $cname = trim($c['name'] ?? '');
        if ($cname === '' || isset($existing_gvcn[csdl_norm_name($cname)])) continue;
        $ht = $teacher_by_id[$c['homeroom_teacher_id']] ?? null;
        if (!$ht || empty($ht['active'])) continue;
        $role_items[] = [
            'id' => 'r_' . bin2hex(random_bytes(4)),
            'teacher' => $ht['name'],
            'role' => 'GVCN',
            'class' => $cname,
            'periods' => 3,
        ];
    }

    $class_names = [];
    foreach ($classes as $c) {
        if (empty($c['active'])) continue;
        $n = trim($c['name'] ?? '');
        if ($n !== '') $class_names[] = $n;
    }
    usort($class_names, function ($a, $b) {
        $ga = (int)preg_replace('/\D/', '', $a);
        $gb = (int)preg_replace('/\D/', '', $b);
        if ($ga !== $gb) return $ga <=> $gb;
        return strcmp($a, $b);
    });

    save_json(PCCM_DATA_PATH . '/teachers.json', array_values($names));
    save_json(PCCM_DATA_PATH . '/teacher_meta.json', $meta);
    save_json(PCCM_DATA_PATH . '/classes.json', array_values($class_names));

    $vid = csdl_pccm_active_version_id();
    $roles_written = 0;
    if ($vid) {
        save_json(csdl_pccm_roles_file($vid), $role_items);
        $roles_written = count($role_items);
    }

    return [
        'ok' => true,
        'message' => sprintf(
            'Đã đẩy CDS → PCCM: %d GV · %d lớp · %d kiêm nhiệm%s',
            count($names),
            count($class_names),
            $roles_written,
            $vid ? " (phiên bản $vid)" : ' (chưa có phiên bản PCCM — bỏ qua roles)'
        ),
        'teachers' => count($names),
        'classes' => count($class_names),
        'roles' => $roles_written,
    ];
}

/** Parse textarea kiêm nhiệm → mảng
 *  Mỗi dòng: ROLE | ROLE|LỚP | ROLE|LỚP|TIẾT
 */
function csdl_parse_kiem_nhiem_text($text) {
    $out = [];
    $lines = preg_split('/\r\n|\r|\n/', $text ?? '');
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/u', $line, $m)) {
            $out[] = ['role' => trim($m[1]), 'class' => trim($m[2]), 'periods' => null];
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $role = $parts[0] ?? '';
        if ($role === '') continue;
        $out[] = [
            'role' => $role,
            'class' => $parts[1] ?? '',
            'periods' => isset($parts[2]) && $parts[2] !== '' ? floatval($parts[2]) : null,
        ];
    }
    return $out;
}
