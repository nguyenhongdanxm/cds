<?php
/**
 * Đồng bộ 2 chiều CSDL (CDS) ↔ PCCM
 *
 * PCCM format:
 *   teachers.json     = ["Họ tên 1", "Họ tên 2", ...]
 *   teacher_meta.json = { "Họ tên": { chuyen_mon, tap_su, hieu_truong, ... } }
 *   classes.json      = ["6A", "6B", ...]
 *
 * CDS format: objects with id (teachers.json / classes.json trong data/ CDS)
 */
require_once __DIR__ . '/csdl_store.php';

function csdl_sync_pccm_ready() {
    return PCCM_DATA_PATH !== '' && is_dir(PCCM_DATA_PATH);
}

function csdl_sync_pccm_path_info() {
    return [
        'ready' => csdl_sync_pccm_ready(),
        'path' => PCCM_DATA_PATH,
        'teachers' => csdl_sync_pccm_ready() && file_exists(PCCM_DATA_PATH . '/teachers.json'),
        'meta' => csdl_sync_pccm_ready() && file_exists(PCCM_DATA_PATH . '/teacher_meta.json'),
        'classes' => csdl_sync_pccm_ready() && file_exists(PCCM_DATA_PATH . '/classes.json'),
    ];
}

function csdl_norm_name($name) {
    $name = trim(preg_replace('/\s+/u', ' ', $name ?? ''));
    return mb_strtolower($name, 'UTF-8');
}

/* ========== PCCM → CDS ========== */

function csdl_sync_from_pccm() {
    if (!csdl_sync_pccm_ready()) {
        return ['ok' => false, 'message' => 'Không tìm thấy thư mục data PCCM. Kiểm tra PCCM_DATA_PATH trong config.'];
    }

    $pccm_teachers = load_json(PCCM_DATA_PATH . '/teachers.json', []);
    $pccm_meta = load_json(PCCM_DATA_PATH . '/teacher_meta.json', []);
    $pccm_classes = load_json(PCCM_DATA_PATH . '/classes.json', []);

    // —— Giáo viên ——
    $cds = csdl_teachers_all();
    $by_name = [];
    foreach ($cds as $t) {
        $by_name[csdl_norm_name($t['name'] ?? '')] = $t;
    }

    $added_t = 0;
    $updated_t = 0;

    foreach ($pccm_teachers as $name) {
        if (!is_string($name) || trim($name) === '') continue;
        $name = trim($name);
        $meta = $pccm_meta[$name] ?? [];
        $cm = $meta['chuyen_mon'] ?? [];
        if (is_string($cm)) $cm = $cm !== '' ? [$cm] : [];
        if (!is_array($cm)) $cm = [];
        $specialty = implode(', ', array_filter(array_map('strval', $cm)));

        $payload = [
            'name' => $name,
            'specialty' => $specialty,
            'role_flags' => [
                'is_probation' => !empty($meta['tap_su']),
                'is_principal' => !empty($meta['hieu_truong']),
                'is_vice' => !empty($meta['pho_hieu_truong']),
            ],
            'pccm_group' => $meta['group'] ?? '',
            'pccm_thcs' => !empty($meta['thcs']) || (($meta['level'] ?? '') !== 'THPT'),
            'pccm_thpt' => !empty($meta['thpt']) || (($meta['level'] ?? '') === 'THPT'),
            'active' => true,
            'source' => 'pccm',
        ];

        $key = csdl_norm_name($name);
        if (isset($by_name[$key])) {
            $payload['id'] = $by_name[$key]['id'];
            // giữ phone/email/code nếu đã có trên CDS
            if (!empty($by_name[$key]['phone'])) $payload['phone'] = $by_name[$key]['phone'];
            if (!empty($by_name[$key]['email'])) $payload['email'] = $by_name[$key]['email'];
            if (!empty($by_name[$key]['code'])) $payload['code'] = $by_name[$key]['code'];
            csdl_teacher_save($payload);
            $updated_t++;
        } else {
            csdl_teacher_save($payload);
            $added_t++;
        }
    }

    // —— Lớp ——
    $cds_cls = csdl_classes_all();
    $cls_by_name = [];
    foreach ($cds_cls as $c) {
        $cls_by_name[csdl_norm_name($c['name'] ?? '')] = $c;
    }

    $added_c = 0;
    $updated_c = 0;

    foreach ($pccm_classes as $cname) {
        if (!is_string($cname) || trim($cname) === '') continue;
        $cname = trim($cname);
        $grade = (int)preg_replace('/\D/', '', $cname);
        if ($grade < 1) $grade = 6;
        $payload = [
            'name' => $cname,
            'grade' => $grade,
            'level' => $grade >= 10 ? 'THPT' : 'THCS',
            'active' => true,
            'source' => 'pccm',
        ];
        $key = csdl_norm_name($cname);
        if (isset($cls_by_name[$key])) {
            $payload['id'] = $cls_by_name[$key]['id'];
            if (!empty($cls_by_name[$key]['homeroom_teacher_id'])) {
                $payload['homeroom_teacher_id'] = $cls_by_name[$key]['homeroom_teacher_id'];
            }
            if (!empty($cls_by_name[$key]['room'])) {
                $payload['room'] = $cls_by_name[$key]['room'];
            }
            csdl_class_save($payload);
            $updated_c++;
        } else {
            csdl_class_save($payload);
            $added_c++;
        }
    }

    return [
        'ok' => true,
        'message' => sprintf(
            'Đã kéo từ PCCM → CDS: GV +%d / cập nhật %d · Lớp +%d / cập nhật %d',
            $added_t, $updated_t, $added_c, $updated_c
        ),
        'teachers_added' => $added_t,
        'teachers_updated' => $updated_t,
        'classes_added' => $added_c,
        'classes_updated' => $updated_c,
    ];
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

    // Danh sách tên (chỉ active)
    $names = [];
    $meta = load_json(PCCM_DATA_PATH . '/teacher_meta.json', []);

    foreach ($teachers as $t) {
        if (empty($t['active'])) continue;
        $name = trim($t['name'] ?? '');
        if ($name === '') continue;
        $names[] = $name;

        if (!isset($meta[$name]) || !is_array($meta[$name])) $meta[$name] = [];

        // chuyên môn: chuỗi "Toán, Văn" → mảng
        $cm = [];
        if (!empty($t['specialty'])) {
            $cm = array_values(array_filter(array_map('trim', preg_split('/[,;|\/]+/u', $t['specialty']))));
        }
        $meta[$name]['chuyen_mon'] = $cm;

        $flags = $t['role_flags'] ?? [];
        $meta[$name]['tap_su'] = !empty($flags['is_probation']);
        $meta[$name]['hieu_truong'] = !empty($flags['is_principal']);
        $meta[$name]['pho_hieu_truong'] = !empty($flags['is_vice']) && empty($flags['is_principal']);

        if (isset($t['pccm_group'])) $meta[$name]['group'] = $t['pccm_group'];
        if (isset($t['pccm_thcs'])) $meta[$name]['thcs'] = !empty($t['pccm_thcs']);
        if (isset($t['pccm_thpt'])) $meta[$name]['thpt'] = !empty($t['pccm_thpt']);

        // mặc định cấp nếu chưa có
        if (empty($meta[$name]['thcs']) && empty($meta[$name]['thpt'])) {
            $meta[$name]['thcs'] = true;
            $meta[$name]['thpt'] = true;
        }
    }

    // Lớp active
    $class_names = [];
    foreach ($classes as $c) {
        if (empty($c['active'])) continue;
        $n = trim($c['name'] ?? '');
        if ($n !== '') $class_names[] = $n;
    }
    // sắp xếp khối rồi tên
    usort($class_names, function ($a, $b) {
        $ga = (int)preg_replace('/\D/', '', $a);
        $gb = (int)preg_replace('/\D/', '', $b);
        if ($ga !== $gb) return $ga <=> $gb;
        return strcmp($a, $b);
    });

    save_json(PCCM_DATA_PATH . '/teachers.json', array_values($names));
    save_json(PCCM_DATA_PATH . '/teacher_meta.json', $meta);
    save_json(PCCM_DATA_PATH . '/classes.json', array_values($class_names));

    return [
        'ok' => true,
        'message' => sprintf(
            'Đã đẩy CDS → PCCM: %d giáo viên · %d lớp',
            count($names),
            count($class_names)
        ),
        'teachers' => count($names),
        'classes' => count($class_names),
    ];
}
