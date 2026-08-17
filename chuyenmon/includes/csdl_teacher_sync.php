<?php
/**
 * Đồng bộ hồ sơ GV từ CSDL (nguồn chuẩn) sang PCCM teacher_meta.
 * - Chỉ cập nhật: tổ, chức vụ, cấp, chuyên môn (specialty), cờ tập sự/HT/PHT
 * - KHÔNG xóa GV, KHÔNG đụng assignments / roles
 * - Khớp theo họ tên (không phân biệt hoa thường, gọn khoảng trắng)
 */

function pccm_csdl_teachers_path() {
    $candidates = [
        dirname(BASE_PATH) . '/data/teachers.json',           // cds.../data khi PCCM ở /chuyenmon
        dirname(dirname(BASE_PATH)) . '/cds.noitruxinman.edu.vn/data/teachers.json',
        '/home/capnachi/cds.noitruxinman.edu.vn/data/teachers.json',
        BASE_PATH . '/../data/teachers.json',
        DATA_PATH . '/../teachers.json',
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) return $p;
    }
    return '';
}

function pccm_name_key($name) {
    $n = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$name)), 'UTF-8');
    return $n;
}

function pccm_load_csdl_teachers() {
    $path = pccm_csdl_teachers_path();
    if ($path === '') return [];
    $rows = json_decode(file_get_contents($path), true);
    return is_array($rows) ? $rows : [];
}

function pccm_parse_level($teaching_level) {
    $s = mb_strtoupper(trim((string)$teaching_level), 'UTF-8');
    $thcs = (strpos($s, 'THCS') !== false) || (strpos($s, 'THCS') !== false);
    $thpt = (strpos($s, 'THPT') !== false);
    // phổ biến: "THCS", "THPT", "THCS&THPT", "THCS, THPT"
    if ($s === '' || $s === '0') {
        return ['thcs' => true, 'thpt' => false, 'level' => 'THCS'];
    }
    if (!$thcs && !$thpt) {
        // fallback số / chữ khác
        if (preg_match('/\b(10|11|12)\b/', $s)) $thpt = true;
        if (preg_match('/\b([6-9])\b/', $s)) $thcs = true;
    }
    if (!$thcs && !$thpt) { $thcs = true; }
    $level = ($thcs && $thpt) ? 'THCS+THPT' : ($thpt ? 'THPT' : 'THCS');
    return compact('thcs', 'thpt', 'level');
}

function pccm_parse_specialty($specialty) {
    if (is_array($specialty)) {
        return array_values(array_filter(array_map('trim', $specialty)));
    }
    $s = trim((string)$specialty);
    if ($s === '') return [];
    // tách , ; /
    $parts = preg_split('/[,;\/|]+/u', $s);
    return array_values(array_filter(array_map('trim', $parts)));
}

function pccm_infer_khxh_khtn($to_name) {
    $g = mb_strtoupper((string)$to_name, 'UTF-8');
    $khxh = (strpos($g, 'KHXH') !== false) || (strpos($g, 'XÃ HỘI') !== false)
        || (strpos($g, 'NGỮ VĂN') !== false) || (strpos($g, 'SỬ') !== false)
        || (strpos($g, 'ĐỊA') !== false) || (strpos($g, 'NGOẠI NGỮ') !== false);
    $khtn = (strpos($g, 'KHTN') !== false) || (strpos($g, 'TỰ NHIÊN') !== false)
        || (strpos($g, 'TOÁN') !== false) || (strpos($g, 'LÝ') !== false)
        || (strpos($g, 'HÓA') !== false) || (strpos($g, 'SINH') !== false)
        || (strpos($g, 'TIN') !== false);
    return [$khxh, $khtn];
}

/**
 * @param bool $addMissing  true = thêm tên GV có trong CSDL mà PCCM chưa có (không xóa)
 * @return array{updated:int,added:int,unmatched:int,csdl_total:int,path:string}
 */
function sync_pccm_teachers_from_csdl($addMissing = true) {
    $csdl = pccm_load_csdl_teachers();
    $path = pccm_csdl_teachers_path();
    if (!$csdl) {
        return ['updated' => 0, 'added' => 0, 'unmatched' => 0, 'csdl_total' => 0, 'path' => $path, 'error' => 'Không tìm thấy data/teachers.json của CSDL'];
    }

    $teachers = get_teachers();
    $byKey = [];
    foreach ($teachers as $i => $name) {
        $byKey[pccm_name_key($name)] = $name; // giữ đúng tên đang dùng trong phân công
    }

    $meta = get_teacher_meta();
    $subjectNames = array_keys(get_subjects());
    $updated = 0; $added = 0; $unmatched = 0;

    foreach ($csdl as $row) {
        if (isset($row['active']) && empty($row['active'])) continue;
        $csdlName = trim($row['name'] ?? '');
        if ($csdlName === '') continue;

        $key = pccm_name_key($csdlName);
        $pccmName = $byKey[$key] ?? null;

        if ($pccmName === null) {
            if (!$addMissing) { $unmatched++; continue; }
            // thêm tên mới — không ảnh hưởng phân công cũ
            $teachers[] = $csdlName;
            $byKey[$key] = $csdlName;
            $pccmName = $csdlName;
            $added++;
        }

        if (!isset($meta[$pccmName])) $meta[$pccmName] = [];

        $to = trim($row['to_chuyen_mon'] ?? '');
        $chucVu = trim($row['chuc_vu'] ?? '');
        $levelInfo = pccm_parse_level($row['teaching_level'] ?? '');
        $spec = pccm_parse_specialty($row['specialty'] ?? '');

        // Tổ
        if ($to !== '') {
            $meta[$pccmName]['group'] = $to;
            $meta[$pccmName]['to_chuyen_mon'] = $to;
            list($khxh, $khtn) = pccm_infer_khxh_khtn($to);
            if ($khxh || $khtn) {
                $meta[$pccmName]['khxh'] = $khxh;
                $meta[$pccmName]['khtn'] = $khtn;
            }
        }

        // Chức vụ
        if ($chucVu !== '') {
            $meta[$pccmName]['chuc_vu'] = $chucVu;
        }
        // kiêm nhiệm text nếu có
        if (!empty($row['kiem_nhiem_text'])) {
            $meta[$pccmName]['kiem_nhiem_text'] = trim($row['kiem_nhiem_text']);
        }

        // Cấp
        $meta[$pccmName]['thcs'] = $levelInfo['thcs'];
        $meta[$pccmName]['thpt'] = $levelInfo['thpt'];
        $meta[$pccmName]['level'] = $levelInfo['level'];
        $meta[$pccmName]['teaching_level'] = trim($row['teaching_level'] ?? $levelInfo['level']);

        // Cờ
        if (array_key_exists('is_probation', $row)) {
            $meta[$pccmName]['tap_su'] = !empty($row['is_probation']);
        }
        if (array_key_exists('is_principal', $row)) {
            $meta[$pccmName]['hieu_truong'] = !empty($row['is_principal']);
        }
        if (array_key_exists('is_vice', $row)) {
            $meta[$pccmName]['pho_hieu_truong'] = !empty($row['is_vice']);
            if (!empty($meta[$pccmName]['hieu_truong'])) {
                $meta[$pccmName]['pho_hieu_truong'] = false;
            }
        }

        // Chuyên môn (specialty) — chỉ ghi nếu parse được và khớp danh mục môn (hoặc ghi nguyên)
        if ($spec) {
            $matched = [];
            foreach ($spec as $s) {
                foreach ($subjectNames as $sn) {
                    if (mb_strtolower($sn, 'UTF-8') === mb_strtolower($s, 'UTF-8')) {
                        $matched[] = $sn;
                        break;
                    }
                }
            }
            if ($matched) {
                $meta[$pccmName]['chuyen_mon'] = array_values(array_unique($matched));
            } else {
                // giữ text gốc để hiển thị
                $meta[$pccmName]['specialty'] = implode(', ', $spec);
                if (empty($meta[$pccmName]['chuyen_mon'])) {
                    $meta[$pccmName]['chuyen_mon'] = $spec;
                }
            }
        }

        $meta[$pccmName]['synced_from_csdl_at'] = date('c');
        $updated++;
    }

    if ($added > 0) {
        save_json(TEACHERS_FILE, sort_teachers_by_ten(array_values(array_unique($teachers))));
    }
    save_teacher_meta($meta);

    return [
        'updated' => $updated,
        'added' => $added,
        'unmatched' => $unmatched,
        'csdl_total' => count($csdl),
        'path' => $path,
    ];
}

/** Bổ sung field hiển thị từ meta */
function get_teacher_profile($name) {
    $f = get_teacher_flags($name);
    $m = get_teacher_meta()[$name] ?? [];
    $f['to_chuyen_mon'] = $m['to_chuyen_mon'] ?? ($m['group'] ?? '');
    $f['chuc_vu'] = $m['chuc_vu'] ?? '';
    $f['teaching_level'] = $m['teaching_level'] ?? get_teacher_level($name);
    $f['specialty'] = $m['specialty'] ?? '';
    $f['kiem_nhiem_text'] = $m['kiem_nhiem_text'] ?? '';
    return $f;
}
