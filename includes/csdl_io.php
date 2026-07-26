<?php
/**
 * Nhập / xuất / mẫu CSV theo schema CSDL chuẩn.
 * Module khác tái sử dụng cùng hàm + cùng nhãn cột.
 */
require_once __DIR__ . '/csdl_store.php';
require_once __DIR__ . '/csdl_schema.php';
require_once __DIR__ . '/csdl_sync.php';

function csdl_io_parse_date($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) return $s;
    return $s;
}

function csdl_io_fmt_date($s) {
    $s = trim((string)$s);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $s;
}

function csdl_io_bool_out($v) {
    return !empty($v) ? 'Có' : '';
}

function csdl_io_bool_in($v) {
    $v = mb_strtolower(trim((string)$v), 'UTF-8');
    return in_array($v, ['1', 'x', 'có', 'co', 'yes', 'true', 'y'], true);
}

function csdl_io_csv_escape($v) {
    $v = (string)$v;
    if (strpos($v, '"') !== false || strpos($v, ',') !== false || strpos($v, "\n") !== false || strpos($v, "\r") !== false) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

function csdl_io_send_csv($filename, array $headers, array $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // BOM UTF-8 cho Excel
    $line = [];
    foreach ($headers as $h) $line[] = csdl_io_csv_escape($h);
    echo implode(',', $line) . "\r\n";
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $cell) $line[] = csdl_io_csv_escape($cell);
        echo implode(',', $line) . "\r\n";
    }
    exit;
}

/* ========== Flat row helpers ========== */

function csdl_io_teacher_flat(array $t) {
    $kn = '';
    if (function_exists('csdl_format_kiem_nhiem')) {
        $kn = csdl_format_kiem_nhiem($t['kiem_nhiem'] ?? []);
    }
    $flags = $t['role_flags'] ?? [];
    return [
        'code' => $t['code'] ?? '',
        'name' => $t['name'] ?? '',
        'dob' => csdl_io_fmt_date($t['dob'] ?? ''),
        'gender' => $t['gender'] ?? '',
        'ethnicity' => $t['ethnicity'] ?? '',
        'phone' => $t['phone'] ?? '',
        'email' => $t['email'] ?? '',
        'hometown' => $t['hometown'] ?? '',
        'address' => $t['address'] ?? '',
        'teaching_level' => $t['teaching_level'] ?? '',
        'specialty' => $t['specialty'] ?? '',
        'to_chuyen_mon' => $t['to_chuyen_mon'] ?? $t['pccm_group'] ?? '',
        'chuc_vu' => $t['chuc_vu'] ?? '',
        'kiem_nhiem_text' => $kn,
        'join_date' => csdl_io_fmt_date($t['join_date'] ?? ''),
        'bac' => $t['bac'] ?? '',
        'hang' => $t['hang'] ?? '',
        'cap_luong' => $t['cap_luong'] ?? '',
        'he_so' => $t['he_so'] ?? '',
        'he_so_from' => csdl_io_fmt_date($t['he_so_from'] ?? ''),
        'is_probation' => csdl_io_bool_out($flags['is_probation'] ?? false),
        'is_principal' => csdl_io_bool_out($flags['is_principal'] ?? false),
        'is_vice' => csdl_io_bool_out($flags['is_vice'] ?? false),
        'active' => csdl_io_bool_out($t['active'] ?? true),
        'note' => $t['note'] ?? '',
    ];
}

function csdl_io_class_flat(array $c, array $teachers) {
    $tn = '';
    $hid = $c['homeroom_teacher_id'] ?? '';
    foreach ($teachers as $t) {
        if (($t['id'] ?? '') === $hid) { $tn = $t['name'] ?? ''; break; }
    }
    return [
        'name' => $c['name'] ?? '',
        'grade' => $c['grade'] ?? '',
        'level' => $c['level'] ?? '',
        'homeroom_teacher_name' => $tn,
        'room' => $c['room'] ?? '',
        'capacity' => $c['capacity'] ?? '',
        'active' => csdl_io_bool_out($c['active'] ?? true),
        'note' => $c['note'] ?? '',
    ];
}

function csdl_io_student_flat(array $s, array $classes) {
    $cn = '';
    $cid = $s['class_id'] ?? '';
    foreach ($classes as $c) {
        if (($c['id'] ?? '') === $cid) { $cn = $c['name'] ?? ''; break; }
    }
    return [
        'code' => $s['code'] ?? '',
        'name' => $s['name'] ?? '',
        'class_name' => $cn,
        'dob' => csdl_io_fmt_date($s['dob'] ?? ''),
        'gender' => $s['gender'] ?? '',
        'ethnicity' => $s['ethnicity'] ?? '',
        'hometown' => $s['hometown'] ?? '',
        'address' => $s['address'] ?? '',
        'phone' => $s['phone'] ?? '',
        'parent_name' => $s['parent_name'] ?? '',
        'parent_phone' => $s['parent_phone'] ?? '',
        'boarder' => csdl_io_bool_out($s['boarder'] ?? false),
        'room_ktx' => $s['room_ktx'] ?? '',
        'meal_group' => $s['meal_group'] ?? '',
        'active' => csdl_io_bool_out($s['active'] ?? true),
        'note' => $s['note'] ?? '',
    ];
}

/* ========== Template & Export ========== */

function csdl_io_template($entity) {
    $schema = csdl_schema_entity($entity);
    $headers = array_values(array_map(fn($m) => $m['label'], $schema));
    $keys = array_keys($schema);
    $sample = array_fill(0, count($keys), '');
    // vài gợi ý mẫu
    if ($entity === 'teachers') {
        $sample = ['GV001', 'Nguyễn Văn A', '15/03/1985', 'Nam', 'Kinh', '0901234567', 'a@example.com', 'Hà Giang', '', 'THCS&THPT', 'Toán', 'Tổ Toán', 'Giáo viên', '', '01/09/2010', '3', 'II', 'THCS', '3', '01/01/2024', '', '', '', 'Có', ''];
    } elseif ($entity === 'classes') {
        $sample = ['6A', '6', 'THCS', 'Nguyễn Văn A', 'P101', '35', 'Có', ''];
    } elseif ($entity === 'students') {
        $sample = ['HS001', 'Lý Thị B', '6A', '01/01/2012', 'Nữ', 'Tày', 'Xín Mần', '', '', 'Phạm Văn C', '0909999999', 'Có', 'A1', '1', 'Có', ''];
    }
    // căn độ dài
    while (count($sample) < count($headers)) $sample[] = '';
    $sample = array_slice($sample, 0, count($headers));
    $names = ['teachers' => 'mau-giao-vien', 'classes' => 'mau-lop', 'students' => 'mau-hoc-sinh'];
    csdl_io_send_csv(($names[$entity] ?? 'mau') . '-csdl.csv', $headers, [$sample]);
}

function csdl_io_export($entity, array $fieldKeys) {
    $schema = csdl_schema_entity($entity);
    if (!$fieldKeys) $fieldKeys = array_keys($schema);
    $fieldKeys = array_values(array_filter($fieldKeys, fn($k) => isset($schema[$k])));
    if (!$fieldKeys) $fieldKeys = array_keys($schema);

    $headers = [];
    foreach ($fieldKeys as $k) $headers[] = $schema[$k]['label'];

    $rows = [];
    if ($entity === 'teachers') {
        foreach (csdl_teachers_all() as $t) {
            $flat = csdl_io_teacher_flat($t);
            $row = [];
            foreach ($fieldKeys as $k) $row[] = $flat[$k] ?? '';
            $rows[] = $row;
        }
        $fname = 'csdl-giao-vien-' . date('Ymd') . '.csv';
    } elseif ($entity === 'classes') {
        $teachers = csdl_teachers_all();
        $list = csdl_classes_all();
        usort($list, fn($a, $b) => ($a['grade'] ?? 0) <=> ($b['grade'] ?? 0) ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));
        foreach ($list as $c) {
            $flat = csdl_io_class_flat($c, $teachers);
            $row = [];
            foreach ($fieldKeys as $k) $row[] = $flat[$k] ?? '';
            $rows[] = $row;
        }
        $fname = 'csdl-lop-' . date('Ymd') . '.csv';
    } else {
        $classes = csdl_classes_all();
        foreach (csdl_students_all() as $s) {
            $flat = csdl_io_student_flat($s, $classes);
            $row = [];
            foreach ($fieldKeys as $k) $row[] = $flat[$k] ?? '';
            $rows[] = $row;
        }
        $fname = 'csdl-hoc-sinh-' . date('Ymd') . '.csv';
    }
    csdl_io_send_csv($fname, $headers, $rows);
}

/* ========== Import ========== */

function csdl_io_read_csv_file($tmpPath) {
    $raw = file_get_contents($tmpPath);
    if ($raw === false || $raw === '') return [null, 'Không đọc được file.'];
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
    if (count($lines) < 1) return [null, 'File trống.'];
    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
    $headers = str_getcsv($lines[0], $delimiter);
    $rows = [];
    for ($i = 1; $i < count($lines); $i++) {
        $rows[] = str_getcsv($lines[$i], $delimiter);
    }
    return [['headers' => $headers, 'rows' => $rows, 'delimiter' => $delimiter], null];
}

function csdl_io_map_headers(array $headers, array $schema) {
    $labelToKey = [];
    foreach ($schema as $key => $meta) {
        $labelToKey[mb_strtolower(trim($meta['label']), 'UTF-8')] = $key;
    }
    // alias thêm
    $extra = [
        'họ tên' => 'name', 'ho va ten' => 'name', 'tên' => 'name',
        'mã' => 'code', 'ma hs' => 'code', 'mã hs' => 'code', 'ma gv' => 'code',
        'lớp' => 'class_name', 'lop' => 'class_name',
        'gvcn' => 'homeroom_teacher_name',
        'môn dạy' => 'specialty', 'chuyên môn' => 'specialty',
        'tổ' => 'to_chuyen_mon',
        'kiêm nhiệm' => 'kiem_nhiem_text',
        'nội trú' => 'boarder',
    ];
    foreach ($extra as $a => $k) {
        if (!isset($labelToKey[$a]) && isset($schema[$k])) $labelToKey[$a] = $k;
    }

    $map = [];
    foreach ($headers as $i => $h) {
        $h = mb_strtolower(trim((string)$h), 'UTF-8');
        $h = preg_replace('/\s+/u', ' ', $h);
        if (isset($labelToKey[$h])) $map[$labelToKey[$h]] = $i;
    }
    return $map;
}

function csdl_io_cell(array $row, array $map, $key) {
    if (!isset($map[$key])) return '';
    $i = $map[$key];
    return isset($row[$i]) ? trim((string)$row[$i]) : '';
}

function csdl_io_import_teachers($tmpPath) {
    // Giữ logic merge an toàn PCCM (delegate file cũ nếu còn)
    if (function_exists('csdl_import_teachers_from_csv_file')) {
        return csdl_import_teachers_from_csv_file($tmpPath);
    }
    list($data, $err) = csdl_io_read_csv_file($tmpPath);
    if ($err) return ['ok' => false, 'message' => $err];
    $schema = csdl_schema_teachers();
    $map = csdl_io_map_headers($data['headers'], $schema);
    if (!isset($map['name'])) return ['ok' => false, 'message' => 'Thiếu cột Họ và tên.'];

    $by = [];
    foreach (csdl_teachers_all() as $t) $by[csdl_norm_name($t['name'] ?? '')] = $t;
    $added = 0; $updated = 0; $skipped = 0;

    foreach ($data['rows'] as $row) {
        $name = csdl_io_cell($row, $map, 'name');
        if ($name === '') { $skipped++; continue; }
        $key = csdl_norm_name($name);
        $old = $by[$key] ?? null;
        $p = ['name' => $name, 'active' => true];

        foreach (['code','gender','ethnicity','phone','email','hometown','address','teaching_level','chuc_vu','bac','hang','cap_luong','he_so','note'] as $f) {
            $v = csdl_io_cell($row, $map, $f);
            if ($v !== '') $p[$f] = $v;
        }
        foreach (['dob','join_date','he_so_from'] as $f) {
            $v = csdl_io_parse_date(csdl_io_cell($row, $map, $f));
            if ($v !== '') $p[$f] = $v;
        }
        $mon = csdl_io_cell($row, $map, 'specialty');
        if ($mon !== '' && (!$old || trim($old['specialty'] ?? '') === '')) $p['specialty'] = $mon;

        $to = csdl_io_cell($row, $map, 'to_chuyen_mon');
        if ($to !== '' && (!$old || trim($old['to_chuyen_mon'] ?? $old['pccm_group'] ?? '') === '')) {
            $p['to_chuyen_mon'] = $to;
            $p['pccm_group'] = $to;
        }

        if (isset($map['active'])) $p['active'] = csdl_io_bool_in(csdl_io_cell($row, $map, 'active')) || csdl_io_cell($row, $map, 'active') === '';

        if ($old) {
            $p['id'] = $old['id'];
            csdl_teacher_save($p);
            $updated++;
        } else {
            $p['source'] = 'csv';
            $p['kiem_nhiem'] = [];
            $p['role_flags'] = ['is_probation'=>false,'is_principal'=>false,'is_vice'=>false];
            $id = csdl_teacher_save($p);
            $p['id'] = $id;
            $by[$key] = $p;
            $added++;
        }
    }
    return ['ok'=>true,'message'=>"GV: +$added / cập nhật $updated / bỏ $skipped", 'added'=>$added,'updated'=>$updated,'skipped'=>$skipped];
}

function csdl_io_import_classes($tmpPath) {
    list($data, $err) = csdl_io_read_csv_file($tmpPath);
    if ($err) return ['ok' => false, 'message' => $err];
    $map = csdl_io_map_headers($data['headers'], csdl_schema_classes());
    if (!isset($map['name'])) return ['ok' => false, 'message' => 'Thiếu cột Tên lớp.'];

    $teachers = csdl_teachers_all();
    $tBy = [];
    foreach ($teachers as $t) $tBy[csdl_norm_name($t['name'] ?? '')] = $t['id'] ?? '';

    $classes = csdl_classes_all();
    $cBy = [];
    foreach ($classes as $c) $cBy[csdl_norm_name($c['name'] ?? '')] = $c;

    $added = 0; $updated = 0; $skipped = 0;
    foreach ($data['rows'] as $row) {
        $name = csdl_io_cell($row, $map, 'name');
        if ($name === '') { $skipped++; continue; }
        $key = csdl_norm_name($name);
        $old = $cBy[$key] ?? null;

        $grade = (int)csdl_io_cell($row, $map, 'grade');
        if ($grade < 1) $grade = (int)preg_replace('/\D/', '', $name);
        if ($grade < 1) $grade = 6;

        $level = csdl_io_cell($row, $map, 'level');
        if ($level === '') $level = $grade <= 9 ? 'THCS' : 'THPT';

        $p = [
            'name' => $name,
            'grade' => $grade,
            'level' => $level,
            'active' => true,
        ];
        $room = csdl_io_cell($row, $map, 'room');
        if ($room !== '') $p['room'] = $room;
        $cap = csdl_io_cell($row, $map, 'capacity');
        if ($cap !== '') $p['capacity'] = $cap;
        $note = csdl_io_cell($row, $map, 'note');
        if ($note !== '') $p['note'] = $note;
        if (isset($map['active'])) {
            $av = csdl_io_cell($row, $map, 'active');
            $p['active'] = ($av === '') ? true : csdl_io_bool_in($av);
        }

        $tn = csdl_io_cell($row, $map, 'homeroom_teacher_name');
        if ($tn !== '') {
            $tid = $tBy[csdl_norm_name($tn)] ?? '';
            if ($tid !== '') $p['homeroom_teacher_id'] = $tid;
        }

        if ($old) {
            $p['id'] = $old['id'];
            csdl_class_save($p);
            $updated++;
        } else {
            $id = csdl_class_save($p);
            $p['id'] = $id;
            $cBy[$key] = $p;
            $added++;
        }
    }
    return ['ok'=>true,'message'=>"Lớp: +$added / cập nhật $updated / bỏ $skipped", 'added'=>$added,'updated'=>$updated,'skipped'=>$skipped];
}

function csdl_io_import_students($tmpPath) {
    list($data, $err) = csdl_io_read_csv_file($tmpPath);
    if ($err) return ['ok' => false, 'message' => $err];
    $map = csdl_io_map_headers($data['headers'], csdl_schema_students());
    if (!isset($map['name'])) return ['ok' => false, 'message' => 'Thiếu cột Họ và tên.'];

    $classes = csdl_classes_all();
    $cBy = [];
    foreach ($classes as $c) $cBy[csdl_norm_name($c['name'] ?? '')] = $c['id'] ?? '';

    $students = csdl_students_all();
    $byCode = [];
    $byNameClass = [];
    foreach ($students as $s) {
        if (!empty($s['code'])) $byCode[mb_strtolower(trim($s['code']), 'UTF-8')] = $s;
        $nk = csdl_norm_name($s['name'] ?? '') . '|' . ($s['class_id'] ?? '');
        $byNameClass[$nk] = $s;
    }

    $added = 0; $updated = 0; $skipped = 0;
    foreach ($data['rows'] as $row) {
        $name = csdl_io_cell($row, $map, 'name');
        if ($name === '') { $skipped++; continue; }

        $className = csdl_io_cell($row, $map, 'class_name');
        $classId = $className !== '' ? ($cBy[csdl_norm_name($className)] ?? '') : '';

        $code = csdl_io_cell($row, $map, 'code');
        $old = null;
        if ($code !== '' && isset($byCode[mb_strtolower($code, 'UTF-8')])) {
            $old = $byCode[mb_strtolower($code, 'UTF-8')];
        } elseif ($classId !== '') {
            $nk = csdl_norm_name($name) . '|' . $classId;
            $old = $byNameClass[$nk] ?? null;
        }

        $p = ['name' => $name, 'active' => true];
        if ($code !== '') $p['code'] = $code;
        if ($classId !== '') $p['class_id'] = $classId;

        foreach (['gender','ethnicity','hometown','address','phone','parent_name','parent_phone','room_ktx','meal_group','note'] as $f) {
            $v = csdl_io_cell($row, $map, $f);
            if ($v !== '') $p[$f] = $v;
        }
        $dob = csdl_io_parse_date(csdl_io_cell($row, $map, 'dob'));
        if ($dob !== '') $p['dob'] = $dob;

        if (isset($map['boarder'])) $p['boarder'] = csdl_io_bool_in(csdl_io_cell($row, $map, 'boarder'));
        if (isset($map['active'])) {
            $av = csdl_io_cell($row, $map, 'active');
            $p['active'] = ($av === '') ? true : csdl_io_bool_in($av);
        }

        if ($old) {
            $p['id'] = $old['id'];
            csdl_student_save($p);
            $updated++;
        } else {
            $p['source'] = 'csv';
            $id = csdl_student_save($p);
            $p['id'] = $id;
            if ($code !== '') $byCode[mb_strtolower($code, 'UTF-8')] = $p;
            $added++;
        }
    }
    return ['ok'=>true,'message'=>"HS: +$added / cập nhật $updated / bỏ $skipped", 'added'=>$added,'updated'=>$updated,'skipped'=>$skipped];
}
