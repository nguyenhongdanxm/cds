<?php
/**
 * Đồng bộ CDS ↔ QLHS (Supabase REST)
 * Kéo lớp + học sinh vào CSDL dùng chung.
 */
if (!function_exists('csdl_norm_name')) {
    function csdl_norm_name($name) {
        $name = trim(preg_replace('/\s+/u', ' ', $name ?? ''));
        return mb_strtolower($name, 'UTF-8');
    }
}

/* ========== QLHS / Supabase (học sinh · lớp) ========== */

function csdl_supabase_request($path, $params = []) {
    if (!defined('SUPABASE_URL') || !SUPABASE_URL || !defined('SUPABASE_KEY') || !SUPABASE_KEY) {
        return ['ok' => false, 'error' => 'Chưa cấu hình SUPABASE_URL / SUPABASE_KEY', 'data' => [], 'http' => 0];
    }
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($path, '/');
    if ($params) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Accept: application/json',
        'Content-Type: application/json',
        'Prefer: return=representation',
    ];
    $raw = null;
    $http = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'cURL: ' . $cerr, 'data' => [], 'http' => $http];
        }
    } else {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($opts));
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $http = (int)$m[1];
        }
        if ($raw === false) {
            return ['ok' => false, 'error' => 'Không kết nối được Supabase (file_get_contents)', 'data' => [], 'http' => $http];
        }
    }
    $data = json_decode($raw, true);
    if ($http >= 400 || (is_array($data) && (isset($data['message']) || isset($data['code']) || isset($data['error'])))) {
        $msg = is_array($data)
            ? ($data['message'] ?? $data['error'] ?? $data['code'] ?? substr($raw, 0, 240))
            : substr((string)$raw, 0, 240);
        return ['ok' => false, 'error' => "HTTP $http: $msg", 'data' => [], 'http' => $http];
    }
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Phản hồi không phải JSON', 'data' => [], 'http' => $http];
    }
    return ['ok' => true, 'data' => $data, 'http' => $http];
}

function csdl_qlhs_resolve_school_id() {
    if (defined('QLHS_SCHOOL_ID') && QLHS_SCHOOL_ID !== '') {
        return QLHS_SCHOOL_ID;
    }
    $r = csdl_supabase_request('schools', [
        'select' => 'id,name,code,is_active',
        'is_active' => 'eq.true',
        'limit' => '5',
    ]);
    if (!$r['ok'] || empty($r['data'])) {
        $r = csdl_supabase_request('schools', ['select' => 'id,name,code', 'limit' => '5']);
    }
    if (!$r['ok'] || empty($r['data'])) return null;
    return $r['data'][0]['id'] ?? null;
}

function csdl_sync_qlhs_info() {
    $school_id = csdl_qlhs_resolve_school_id();
    $ok = defined('SUPABASE_URL') && SUPABASE_URL && defined('SUPABASE_KEY') && SUPABASE_KEY;
    $ping = null;
    if ($ok) {
        $ping = csdl_supabase_request('schools', ['select' => 'id', 'limit' => '1']);
    }
    return [
        'ready' => $ok && $ping && !empty($ping['ok']),
        'url' => defined('SUPABASE_URL') ? SUPABASE_URL : '',
        'school_id' => $school_id ?: '',
        'ping_error' => ($ping && empty($ping['ok'])) ? ($ping['error'] ?? '') : '',
        'qlhs_app' => defined('URL_QLHS') ? URL_QLHS : '',
    ];
}

function csdl_map_gender_from_qlhs($g) {
    $g = strtolower(trim((string)$g));
    if ($g === 'male' || $g === 'nam' || $g === 'm') return 'Nam';
    if ($g === 'female' || $g === 'nữ' || $g === 'nu' || $g === 'f') return 'Nữ';
    return $g !== '' ? $g : '';
}

/**
 * Kéo lớp + học sinh từ QLHS (Supabase) → CDS
 * Khớp lớp theo tên; học sinh theo mã HS (ưu tiên) hoặc tên+lớp.
 */
function csdl_sync_from_qlhs() {
    $info = csdl_sync_qlhs_info();
    if (!$info['ready']) {
        return [
            'ok' => false,
            'message' => 'Không kết nối được QLHS/Supabase. '
                . ($info['ping_error'] ?: 'Kiểm tra SUPABASE_URL / KEY hoặc RLS cho phép đọc (anon).'),
        ];
    }
    $school_id = $info['school_id'];
    if (!$school_id) {
        return ['ok' => false, 'message' => 'Không xác định được school_id. Điền QLHS_SCHOOL_ID trong config.php.'];
    }

    $cls_params = [
        'select' => 'id,name,grade,school_year,is_active,school_id',
        'school_id' => 'eq.' . $school_id,
        'order' => 'grade.asc,name.asc',
        'limit' => '500',
    ];
    $cls_r = csdl_supabase_request('classes', $cls_params);
    if (!$cls_r['ok']) {
        return ['ok' => false, 'message' => 'Lỗi đọc lớp QLHS: ' . ($cls_r['error'] ?? '')];
    }
    $qlhs_classes = $cls_r['data'];

    $cds_cls = csdl_classes_all();
    $cls_by_name = [];
    foreach ($cds_cls as $c) {
        $cls_by_name[csdl_norm_name($c['name'] ?? '')] = $c;
    }

    $qlhs_cls_to_cds = [];
    $added_c = 0;
    $updated_c = 0;

    foreach ($qlhs_classes as $qc) {
        $cname = trim($qc['name'] ?? '');
        if ($cname === '') continue;
        $ckey = csdl_norm_name($cname);
        $grade = (int)($qc['grade'] ?? 0);
        if ($grade < 1) $grade = (int)preg_replace('/\D/', '', $cname) ?: 6;

        $payload = [
            'name' => $cname,
            'grade' => $grade,
            'level' => $grade >= 10 ? 'THPT' : 'THCS',
            'active' => array_key_exists('is_active', $qc) ? !empty($qc['is_active']) : true,
            'source' => 'qlhs',
            'qlhs_id' => $qc['id'] ?? '',
            'school_year' => $qc['school_year'] ?? '',
        ];

        if (isset($cls_by_name[$ckey])) {
            $payload['id'] = $cls_by_name[$ckey]['id'];
            if (!empty($cls_by_name[$ckey]['homeroom_teacher_id'])) {
                $payload['homeroom_teacher_id'] = $cls_by_name[$ckey]['homeroom_teacher_id'];
            }
            if (!empty($cls_by_name[$ckey]['room'])) {
                $payload['room'] = $cls_by_name[$ckey]['room'];
            }
            $id = csdl_class_save($payload);
            $updated_c++;
        } else {
            $id = csdl_class_save($payload);
            $added_c++;
        }
        $qlhs_cls_to_cds[$qc['id'] ?? ''] = $id;
        $cls_by_name[$ckey] = array_merge($cls_by_name[$ckey] ?? [], ['id' => $id, 'name' => $cname]);
    }

    $stu_params = [
        'select' => 'id,full_name,student_code,class_id,gender,date_of_birth,is_boarding,phone,parent_phone,notes,ethnicity,room_number,meal_group,is_active,school_id,address,cccd',
        'school_id' => 'eq.' . $school_id,
        'order' => 'full_name.asc',
        'limit' => '3000',
    ];
    $stu_r = csdl_supabase_request('students', $stu_params);
    if (!$stu_r['ok']) {
        return ['ok' => false, 'message' => 'Lỗi đọc học sinh QLHS: ' . ($stu_r['error'] ?? '')];
    }
    $qlhs_students = $stu_r['data'];

    $cds_stu = csdl_students_all();
    $by_code = [];
    $by_name_class = [];
    foreach ($cds_stu as $s) {
        $code = strtoupper(trim($s['code'] ?? ''));
        if ($code !== '') $by_code[$code] = $s;
        $nk = csdl_norm_name($s['name'] ?? '') . '|' . ($s['class_id'] ?? '');
        $by_name_class[$nk] = $s;
    }

    $added_s = 0;
    $updated_s = 0;

    foreach ($qlhs_students as $qs) {
        $name = trim($qs['full_name'] ?? '');
        if ($name === '') continue;
        $code = trim($qs['student_code'] ?? '');
        $qlhs_cid = $qs['class_id'] ?? '';
        $cds_class_id = $qlhs_cls_to_cds[$qlhs_cid] ?? '';

        if ($cds_class_id === '' && $qlhs_cid !== '') {
            foreach ($qlhs_classes as $qc) {
                if (($qc['id'] ?? '') === $qlhs_cid) {
                    $ck = csdl_norm_name($qc['name'] ?? '');
                    if (isset($cls_by_name[$ck])) $cds_class_id = $cls_by_name[$ck]['id'];
                    break;
                }
            }
        }

        $payload = [
            'name' => $name,
            'code' => $code,
            'class_id' => $cds_class_id,
            'gender' => csdl_map_gender_from_qlhs($qs['gender'] ?? ''),
            'dob' => $qs['date_of_birth'] ?? '',
            'boarder' => !empty($qs['is_boarding']),
            'phone' => $qs['phone'] ?? ($qs['parent_phone'] ?? ''),
            'parent_phone' => $qs['parent_phone'] ?? '',
            'note' => $qs['notes'] ?? '',
            'ethnicity' => $qs['ethnicity'] ?? '',
            'room_number' => $qs['room_number'] ?? '',
            'meal_group' => $qs['meal_group'] ?? '',
            'address' => $qs['address'] ?? '',
            'cccd' => $qs['cccd'] ?? '',
            'active' => array_key_exists('is_active', $qs) ? !empty($qs['is_active']) : true,
            'source' => 'qlhs',
            'qlhs_id' => $qs['id'] ?? '',
        ];

        $existing = null;
        $code_key = strtoupper($code);
        if ($code_key !== '' && isset($by_code[$code_key])) {
            $existing = $by_code[$code_key];
        } else {
            $nk = csdl_norm_name($name) . '|' . $cds_class_id;
            if (isset($by_name_class[$nk])) $existing = $by_name_class[$nk];
        }

        if ($existing) {
            $payload['id'] = $existing['id'];
            if ($payload['note'] === '' && !empty($existing['note'])) {
                $payload['note'] = $existing['note'];
            }
            csdl_student_save($payload);
            $updated_s++;
        } else {
            csdl_student_save($payload);
            $added_s++;
        }
    }

    return [
        'ok' => true,
        'message' => sprintf(
            'Đã kéo từ QLHS → CDS: Lớp +%d / cập nhật %d · Học sinh +%d / cập nhật %d (school %s)',
            $added_c, $updated_c, $added_s, $updated_s, substr($school_id, 0, 8) . '…'
        ),
        'classes_added' => $added_c,
        'classes_updated' => $updated_c,
        'students_added' => $added_s,
        'students_updated' => $updated_s,
        'school_id' => $school_id,
    ];
}
