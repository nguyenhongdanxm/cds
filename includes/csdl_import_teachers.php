<?php
/**
 * Nhập danh sách cán bộ / GV / NV từ CSV (Excel → Lưu dạng CSV UTF-8)
 *
 * Chiến lược MERGE theo họ tên (không phân biệt hoa thường, gộp khoảng trắng):
 *  - Cập nhật hồ sơ hành chính (ngày sinh, GT, SĐT, email, địa chỉ…)
 *  - KHÔNG ghi đè: chuyên môn (nếu đã có), tổ chuyên môn, kiêm nhiệm, cờ HT/PHT/tập sự
 *  - Môn dạy từ Excel chỉ đổ vào specialty khi specialty đang trống
 *  - Chức vụ Excel → trường chuc_vu riêng (không đụng kiem_nhiem PCCM)
 */

require_once __DIR__ . '/csdl_store.php';
require_once __DIR__ . '/csdl_sync.php'; // csdl_norm_name

function csdl_import_parse_date($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    // d/m/Y hoặc d-m-Y
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    // Y-m-d
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) return $s;
    return $s;
}

function csdl_import_header_map($headers) {
    $map = [];
    foreach ($headers as $i => $h) {
        $h = mb_strtolower(trim((string)$h), 'UTF-8');
        $h = preg_replace('/\s+/u', ' ', $h);
        $aliases = [
            'name' => ['họ và tên', 'ho va ten', 'họ tên', 'hoten', 'họ tên giáo viên', 'tên'],
            'dob' => ['ngày sinh', 'ngay sinh'],
            'gender' => ['giới tính', 'gioi tinh'],
            'ethnicity' => ['dân tộc', 'dan toc'],
            'phone' => ['sđt', 'sdt', 'điện thoại', 'dien thoai', 'phone'],
            'email' => ['email', 'e-mail'],
            'hometown' => ['quê quán', 'que quan'],
            'address' => ['địa chỉ', 'dia chi'],
            'teaching_level' => ['cấp học', 'cap hoc'],
            'specialty' => ['môn dạy', 'mon day', 'chuyên môn', 'chuyen mon'],
            'chuc_vu' => ['chức vụ', 'chuc vu'],
            'join_date' => ['ngày vào ngành', 'ngay vao nganh'],
            'bac' => ['bậc', 'bac'],
            'hang' => ['hạng', 'hang'],
            'cap_luong' => ['cấp', 'cap'],
            'he_so' => ['hệ số', 'he so'],
            'he_so_from' => ['hưởng từ ngày', 'huong tu ngay'],
            'note' => ['ghi chú', 'ghi chu', 'note'],
            'code' => ['mã gv', 'ma gv', 'mã', 'code'],
        ];
        foreach ($aliases as $key => $list) {
            if (in_array($h, $list, true)) {
                $map[$key] = $i;
                break;
            }
        }
    }
    return $map;
}

function csdl_import_row_get($row, $map, $key) {
    if (!isset($map[$key])) return '';
    $i = $map[$key];
    return isset($row[$i]) ? trim((string)$row[$i]) : '';
}

/**
 * @return array{ok:bool,message:string,added:int,updated:int,skipped:int,errors:array}
 */
function csdl_import_teachers_from_csv_file($tmpPath) {
    $raw = file_get_contents($tmpPath);
    if ($raw === false || $raw === '') {
        return ['ok' => false, 'message' => 'Không đọc được file.', 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
    // Bỏ BOM UTF-8
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);

    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
    if (count($lines) < 2) {
        return ['ok' => false, 'message' => 'File trống hoặc thiếu dòng dữ liệu.', 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    // Tách CSV (hỗ trợ dấu phẩy / chấm phẩy)
    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
    $headers = str_getcsv($lines[0], $delimiter);
    $map = csdl_import_header_map($headers);
    if (!isset($map['name'])) {
        return [
            'ok' => false,
            'message' => 'Không tìm thấy cột «Họ và tên». Kiểm tra dòng tiêu đề (STT, Họ và tên, …).',
            'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [],
        ];
    }

    $existing = csdl_teachers_all();
    $by_name = [];
    foreach ($existing as $t) {
        $by_name[csdl_norm_name($t['name'] ?? '')] = $t;
    }

    $added = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    for ($r = 1; $r < count($lines); $r++) {
        $row = str_getcsv($lines[$r], $delimiter);
        $name = csdl_import_row_get($row, $map, 'name');
        if ($name === '') {
            $skipped++;
            continue;
        }

        $key = csdl_norm_name($name);
        $old = $by_name[$key] ?? null;

        $payload = ['name' => $name, 'active' => true];

        // Hồ sơ hành chính — chỉ ghi nếu Excel có giá trị
        $dob = csdl_import_parse_date(csdl_import_row_get($row, $map, 'dob'));
        if ($dob !== '') $payload['dob'] = $dob;

        $gender = csdl_import_row_get($row, $map, 'gender');
        if ($gender !== '') $payload['gender'] = $gender;

        $eth = csdl_import_row_get($row, $map, 'ethnicity');
        if ($eth !== '') $payload['ethnicity'] = $eth;

        $phone = csdl_import_row_get($row, $map, 'phone');
        if ($phone !== '') $payload['phone'] = $phone;

        $email = csdl_import_row_get($row, $map, 'email');
        if ($email !== '') $payload['email'] = $email;

        $hometown = csdl_import_row_get($row, $map, 'hometown');
        if ($hometown !== '') $payload['hometown'] = $hometown;

        $address = csdl_import_row_get($row, $map, 'address');
        if ($address !== '') $payload['address'] = $address;

        $code = csdl_import_row_get($row, $map, 'code');
        if ($code !== '') $payload['code'] = $code;

        $level = csdl_import_row_get($row, $map, 'teaching_level');
        if ($level !== '') {
            $payload['teaching_level'] = $level;
            $up = mb_strtoupper($level, 'UTF-8');
            if (strpos($up, 'THCS') !== false && strpos($up, 'THPT') !== false) {
                $payload['pccm_thcs'] = true;
                $payload['pccm_thpt'] = true;
            } elseif (strpos($up, 'THPT') !== false) {
                $payload['pccm_thcs'] = false;
                $payload['pccm_thpt'] = true;
            } elseif (strpos($up, 'THCS') !== false) {
                $payload['pccm_thcs'] = true;
                $payload['pccm_thpt'] = false;
            }
        }

        // Môn dạy → specialty CHỈ khi chưa có (giữ PCCM)
        $mon = csdl_import_row_get($row, $map, 'specialty');
        if ($mon !== '') {
            if (!$old || trim($old['specialty'] ?? '') === '') {
                $payload['specialty'] = $mon;
            }
            // nếu đã có specialty PCCM → bỏ qua, không đè
        }

        // Chức vụ Excel → chuc_vu (không đụng kiem_nhiem)
        $cv = csdl_import_row_get($row, $map, 'chuc_vu');
        if ($cv !== '') $payload['chuc_vu'] = $cv;

        $jd = csdl_import_parse_date(csdl_import_row_get($row, $map, 'join_date'));
        if ($jd !== '') $payload['join_date'] = $jd;

        foreach (['bac', 'hang', 'cap_luong', 'he_so'] as $f) {
            $v = csdl_import_row_get($row, $map, $f);
            if ($v !== '') $payload[$f] = $v;
        }
        $hsf = csdl_import_parse_date(csdl_import_row_get($row, $map, 'he_so_from'));
        if ($hsf !== '') $payload['he_so_from'] = $hsf;

        $note_ex = csdl_import_row_get($row, $map, 'note');
        if ($note_ex !== '') {
            if ($old && trim($old['note'] ?? '') !== '' && trim($old['note']) !== $note_ex) {
                // giữ note cũ, thêm note Excel nếu khác
                $payload['note'] = trim($old['note']) . ' | ' . $note_ex;
            } else {
                $payload['note'] = $note_ex;
            }
        }

        if ($old) {
            $payload['id'] = $old['id'];
            // Bảo toàn tuyệt đối các trường PCCM nếu payload không cố ý sửa
            // (specialty đã xử lý ở trên; các trường dưới không nằm trong payload → array_merge giữ cũ trong csdl_teacher_save)
            csdl_teacher_save($payload);
            $updated++;
            // refresh index
            $by_name[$key] = array_merge($old, $payload);
        } else {
            $payload['source'] = 'excel';
            $payload['kiem_nhiem'] = [];
            $payload['role_flags'] = [
                'is_probation' => false,
                'is_principal' => false,
                'is_vice' => false,
            ];
            $id = csdl_teacher_save($payload);
            $payload['id'] = $id;
            $by_name[$key] = $payload;
            $added++;
        }
    }

    return [
        'ok' => true,
        'message' => sprintf(
            'Nhập xong: thêm mới %d · cập nhật %d · bỏ qua %d dòng. Dữ liệu PCCM (chuyên môn đã có, tổ, kiêm nhiệm, cờ HT/PHT) được giữ nguyên.',
            $added, $updated, $skipped
        ),
        'added' => $added,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}
