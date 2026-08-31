<?php
/**
 * Nhập GV/NV từ CSV — merge theo họ tên, giữ PCCM.
 */
require_once __DIR__ . '/csdl_store.php';
require_once __DIR__ . '/csdl_sync.php';

function csdl_import_parse_date($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) return $s;
    return $s;
}

function csdl_import_header_map($headers) {
    $map = [];
    foreach ($headers as $i => $h) {
        $h = mb_strtolower(trim((string)$h), 'UTF-8');
        $h = preg_replace('/\s+/u', ' ', $h);
        if ($h === 'stt') continue;
        $aliases = [
            'name' => ['họ và tên', 'ho va ten', 'họ tên', 'hoten', 'họ tên giáo viên', 'tên'],
            'cccd' => ['cccd', 'cmnd', 'số cccd', 'so cccd'],
            'dob' => ['ngày sinh', 'ngay sinh'],
            'gender' => ['giới tính', 'gioi tinh'],
            'ethnicity' => ['dân tộc', 'dan toc'],
            'phone' => ['sđt', 'sdt', 'điện thoại', 'dien thoai', 'phone'],
            'email' => ['email', 'e-mail'],
            'hometown' => ['quê quán', 'que quan'],
            'address' => ['địa chỉ', 'dia chi'],
            'teaching_level' => ['cấp học', 'cap hoc'],
            'specialty' => ['môn dạy', 'mon day', 'chuyên môn', 'chuyen mon', 'môn dạy / chuyên môn'],
            'chuc_vu' => ['chức vụ', 'chuc vu', 'chức vụ (hành chính)'],
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

function csdl_import_teachers_from_csv_file($tmpPath) {
    $raw = file_get_contents($tmpPath);
    if ($raw === false || $raw === '') {
        return ['ok' => false, 'message' => 'Không đọc được file.', 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);

    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
    if (count($lines) < 2) {
        return ['ok' => false, 'message' => 'File trống hoặc thiếu dòng dữ liệu.', 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
    $headers = str_getcsv($lines[0], $delimiter);
    $map = csdl_import_header_map($headers);
    if (!isset($map['name'])) {
        return [
            'ok' => false,
            'message' => 'Không tìm thấy cột «Họ và tên». Dòng 1 phải là tiêu đề cột chuẩn.',
            'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [],
        ];
    }

    return cds_shadow_batch_run(function () use ($lines, $delimiter, $map) {
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

        $dob = csdl_import_parse_date(csdl_import_row_get($row, $map, 'dob'));
        if ($dob !== '') $payload['dob'] = $dob;

        foreach (['gender', 'ethnicity', 'phone', 'email', 'hometown', 'address', 'code', 'chuc_vu', 'bac', 'hang', 'cap_luong', 'he_so'] as $f) {
            $v = csdl_import_row_get($row, $map, $f);
            if ($v !== '') $payload[$f] = $v;
        }

        $cccd = preg_replace('/\s+/', '', csdl_import_row_get($row, $map, 'cccd'));
        if ($cccd !== '') $payload['cccd'] = $cccd;

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

        $mon = csdl_import_row_get($row, $map, 'specialty');
        if ($mon !== '' && (!$old || trim($old['specialty'] ?? '') === '')) {
            $payload['specialty'] = $mon;
        }

        $jd = csdl_import_parse_date(csdl_import_row_get($row, $map, 'join_date'));
        if ($jd !== '') $payload['join_date'] = $jd;
        $hsf = csdl_import_parse_date(csdl_import_row_get($row, $map, 'he_so_from'));
        if ($hsf !== '') $payload['he_so_from'] = $hsf;

        $note_ex = csdl_import_row_get($row, $map, 'note');
        if ($note_ex !== '') {
            if ($old && trim($old['note'] ?? '') !== '' && trim($old['note']) !== $note_ex) {
                $payload['note'] = trim($old['note']) . ' | ' . $note_ex;
            } else {
                $payload['note'] = $note_ex;
            }
        }

        if ($old) {
            $payload['id'] = $old['id'];
            csdl_teacher_save($payload);
            $updated++;
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
            'Nhập xong: thêm mới %d · cập nhật %d · bỏ qua %d. Giữ nguyên chuyên môn/tổ/kiêm nhiệm PCCM đã có.',
            $added, $updated, $skipped
        ),
        'added' => $added,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
    });
}
