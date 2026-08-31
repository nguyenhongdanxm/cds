<?php
/**
 * CSDL dùng chung – lớp lưu trữ (JSON)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database_shadow.php';
require_once __DIR__ . '/database_read_verify.php';
require_once __DIR__ . '/database_sql_read.php';
require_once __DIR__ . '/database_sql_write.php';
require_once __DIR__ . '/school_week_calendar.php';

define('CSDL_TEACHERS', DATA_PATH . '/teachers.json');
define('CSDL_CLASSES', DATA_PATH . '/classes.json');
define('CSDL_STUDENTS', DATA_PATH . '/students.json');
define('CSDL_YEARS', DATA_PATH . '/school_years.json');
define('CSDL_SUBJECTS', DATA_PATH . '/subjects.json');

function csdl_uid($prefix = 'id') {
    return $prefix . '_' . bin2hex(random_bytes(4));
}

function csdl_now() {
    return date('c');
}

/**
 * Khóa sắp xếp lớp dùng chung: khối 6 → 12, sau đó A → B → ...
 * Chấp nhận cả cách ghi "6A", "6A1", "Lớp 6A" và đẩy lớp không nhận diện
 * được xuống cuối danh sách.
 */
function csdl_class_sort_key($className) {
    $name = trim((string)$className);
    $compact = preg_replace('/\s+/u', '', $name);
    if (preg_match('/(?:^|[^0-9])(6|7|8|9|10|11|12)([^0-9]*)?(\d*)$/iu', $compact, $m)) {
        $grade = (int)$m[1];
        $suffix = function_exists('mb_strtoupper')
            ? mb_strtoupper((string)($m[2] ?? ''), 'UTF-8')
            : strtoupper((string)($m[2] ?? ''));
        return sprintf('%02d|%s|%06d|%s', $grade, $suffix, (int)($m[3] ?? 0), $name);
    }
    return '99|' . csdl_text_sort_key($name);
}

function csdl_compare_class_names($left, $right) {
    return strnatcasecmp(csdl_class_sort_key($left), csdl_class_sort_key($right));
}

/** Chuẩn hóa chữ Việt để so sánh ổn định kể cả khi máy chủ không có intl. */
function csdl_text_sort_key($text) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    $map = [
        'Đ'=>'D','đ'=>'d','Ă'=>'A','ă'=>'a','Â'=>'A','â'=>'a','Ê'=>'E','ê'=>'e',
        'Ô'=>'O','ô'=>'o','Ơ'=>'O','ơ'=>'o','Ư'=>'U','ư'=>'u',
    ];
    $text = strtr($text, $map);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii) && $ascii !== '') $text = $ascii;
    }
    return strtolower($text);
}

/**
 * Khóa tên học sinh theo tên gọi cuối cùng, rồi tên đệm và họ.
 * Ví dụ "Hoàng Văn Anh" được xếp ở vần Anh, không phải vần Hoàng.
 */
function csdl_person_name_sort_key($fullName) {
    $name = trim(preg_replace('/\s+/u', ' ', (string)$fullName));
    if ($name === '') return '';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $givenName = array_pop($parts);
    return csdl_text_sort_key($givenName) . '|' . csdl_text_sort_key(implode(' ', $parts)) . '|' . csdl_text_sort_key($name);
}

function csdl_compare_person_names($left, $right) {
    return strnatcasecmp(csdl_person_name_sort_key($left), csdl_person_name_sort_key($right));
}

function csdl_sort_classes(array &$rows) {
    usort($rows, static function ($left, $right) {
        return csdl_compare_class_names($left['name'] ?? '', $right['name'] ?? '');
    });
}

function csdl_sort_students(array &$rows, array $classMap = []) {
    usort($rows, static function ($left, $right) use ($classMap) {
        $leftClass = $left['class_name'] ?? ($classMap[(string)($left['class_id'] ?? '')] ?? '');
        $rightClass = $right['class_name'] ?? ($classMap[(string)($right['class_id'] ?? '')] ?? '');
        $classCompare = csdl_compare_class_names($leftClass, $rightClass);
        if ($classCompare !== 0) return $classCompare;
        return csdl_compare_person_names($left['name'] ?? '', $right['name'] ?? '');
    });
}

/* —— Năm học —— */
function csdl_years_all() {
    $sqlRows = cds_core_sql_rows('years');
    if (is_array($sqlRows)) return $sqlRows;
    $years = load_json(CSDL_YEARS, []);
    if (!$years) {
        $years = [[
            'id' => 'y_2025',
            'label' => '2025–2026',
            'start' => '2025-09-01',
            'end' => '2026-05-31',
            'is_current' => true,
        ]];
        save_json(CSDL_YEARS, $years);
    }
    cds_read_verify_rows('years', $years);
    return $years;
}

function csdl_year_current() {
    foreach (csdl_years_all() as $y) {
        if (!empty($y['is_current'])) return $y;
    }
    $all = csdl_years_all();
    return $all[0] ?? null;
}

function csdl_year_find($id) {
    foreach (csdl_years_all() as $year) {
        if (($year['id'] ?? '') === $id) return $year;
    }
    return null;
}

function csdl_date_valid($value) {
    if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

/**
 * Lịch tuần chuẩn của một năm học.
 * Mỗi module dùng hàm này thay vì tự tính "monday this week".
 */
function csdl_year_weeks($year = null) {
    // Một nguồn tuần duy nhất cho toàn hệ sinh thái. Hàm lịch dùng chung bao
    // gồm cả Tuần học trước 1/2 và các tuần chính khóa đã điều chỉnh trong CSDL.
    if (function_exists('cds_school_week_calendar')) {
        return cds_school_week_calendar($year);
    }
    if (is_string($year)) $year = csdl_year_find($year);
    if (!is_array($year)) $year = csdl_year_current();
    if (!$year) return [];

    $start = $year['start'] ?? '';
    $end = $year['end'] ?? '';
    if (!csdl_date_valid($start) || !csdl_date_valid($end) || $end < $start) return [];

    $saved = [];
    foreach (($year['weeks'] ?? []) as $row) {
        $number = (int)($row['number'] ?? 0);
        $weekStart = $row['start'] ?? '';
        if ($number > 0 && csdl_date_valid($weekStart)) $saved[$number] = $weekStart;
    }

    $weeks = [];
    $cursor = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    for ($number = 1; $number <= 60 && $cursor <= $endDate; $number++) {
        if (isset($saved[$number])) $cursor = new DateTimeImmutable($saved[$number]);
        $weekEnd = $cursor->modify('+6 days');
        $weeks[] = [
            'number' => $number,
            'start' => $cursor->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
            'label' => 'Tuần ' . $number,
        ];
        $cursor = $cursor->modify('+7 days');
    }
    return $weeks;
}

/** Tên tuần kèm đúng khoảng thời gian để các module hiển thị thống nhất. */
function csdl_week_display($week) {
    if (!is_array($week)) return '';
    $label = trim((string)($week['label'] ?? '')) ?: 'Tuần';
    $start = (string)($week['start'] ?? '');
    $end = (string)($week['end'] ?? '');
    if (csdl_date_valid($start) && csdl_date_valid($end)) {
        return $label . ' · ' . date('d/m/Y', strtotime($start)) . ' – ' . date('d/m/Y', strtotime($end));
    }
    return $label;
}

function csdl_year_week_adjust($yearId, $weekNumber, $newStart) {
    $weekNumber = (int)$weekNumber;
    if ($weekNumber < 1 || !csdl_date_valid($newStart)) {
        return ['ok' => false, 'message' => 'Tuần hoặc ngày bắt đầu không hợp lệ.'];
    }

    $years = csdl_years_all();
    foreach ($years as &$year) {
        if (($year['id'] ?? '') !== $yearId) continue;
        // Chỉ điều chỉnh các tuần chính khóa; Tuần học trước 1/2 có biểu mẫu
        // cấu hình riêng và không được làm thay đổi số Tuần 1, 2, 3...
        $weeks = array_values(array_filter(csdl_year_weeks($year), static function ($week) {
            return empty($week['is_pre']);
        }));
        if (!$weeks || $weekNumber > count($weeks)) {
            unset($year);
            return ['ok' => false, 'message' => 'Không tìm thấy tuần trong năm học.'];
        }

        $newCursor = new DateTimeImmutable($newStart);
        foreach ($weeks as &$week) {
            if ((int)$week['number'] < $weekNumber) continue;
            $offset = ((int)$week['number'] - $weekNumber) * 7;
            $startDate = $newCursor->modify('+' . $offset . ' days');
            $week['start'] = $startDate->format('Y-m-d');
            $week['end'] = $startDate->modify('+6 days')->format('Y-m-d');
        }
        unset($week);
        $year['weeks'] = array_map(fn($week) => [
            'number' => (int)$week['number'],
            'start' => $week['start'],
            'end' => $week['end'],
        ], $weeks);
        $year['weeks_updated_at'] = csdl_now();
        save_json(CSDL_YEARS, $years);
        cds_shadow_refresh_core('school_year', $yearId);
        unset($year);
        return ['ok' => true, 'message' => 'Đã cập nhật lịch tuần dùng chung.'];
    }
    unset($year);
    return ['ok' => false, 'message' => 'Không tìm thấy năm học.'];
}

function csdl_week_for_date($date = null, $year = null) {
    $date = $date ?: date('Y-m-d');
    if (!csdl_date_valid($date)) $date = date('Y-m-d');
    foreach (csdl_shared_weeks($year) as $week) {
        if ($date >= $week['start'] && $date <= $week['end']) return $week;
    }
    return null;
}

/**
 * Toàn bộ lịch tuần dùng chung, gồm cả Tuần học trước 1/2 và tuần chính khóa.
 * Các module hiển thị/chọn tuần phải dùng hàm này. Hàm csdl_year_weeks()
 * vẫn được giữ như tên tương thích cho các màn hình cũ.
 */
function csdl_shared_weeks($year = null) {
    return function_exists('cds_school_week_calendar')
        ? cds_school_week_calendar($year)
        : csdl_year_weeks($year);
}

function csdl_week_by_key($key, $year = null) {
    $key = trim((string)$key);
    foreach (csdl_shared_weeks($year) as $week) {
        if ((string)($week['key'] ?? $week['number'] ?? '') === $key) return $week;
    }
    return null;
}

/** Nhận khóa tuần, ngày bắt đầu hoặc một ngày bất kỳ thuộc tuần. */
function csdl_week_resolve($value = null, $year = null) {
    $value = trim((string)($value ?? ''));
    if ($value !== '') {
        $byKey = csdl_week_by_key($value, $year);
        if ($byKey) return $byKey;
        if (csdl_date_valid($value)) {
            $byDate = csdl_week_for_date($value, $year);
            if ($byDate) return $byDate;
        }
    }
    return csdl_current_week(date('Y-m-d'));
}

function csdl_current_week($date = null) {
    return csdl_week_for_date($date ?: date('Y-m-d'), csdl_year_current());
}

function csdl_year_set_current($id) {
    $years = csdl_years_all();
    foreach ($years as &$y) {
        $y['is_current'] = (($y['id'] ?? '') === $id);
    }
    unset($y);
    save_json(CSDL_YEARS, $years);
    cds_shadow_refresh_core('school_year', $id);
}

function csdl_year_save($data) {
    $years = csdl_years_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($years as &$y) {
        if (($y['id'] ?? '') === $id) {
            $oldStart = $y['start'] ?? '';
            $oldEnd = $y['end'] ?? '';
            $y = array_merge($y, $data);
            if (($y['start'] ?? '') !== $oldStart || ($y['end'] ?? '') !== $oldEnd) {
                unset($y['weeks'], $y['weeks_updated_at']);
            }
            $found = true;
            break;
        }
    }
    unset($y);
    if (!$found) {
        if (!$id) $data['id'] = csdl_uid('y');
        $years[] = $data;
    }
    save_json(CSDL_YEARS, $years);
    cds_shadow_refresh_core('school_year', $data['id'] ?? $id);
    return $data['id'] ?? $id;
}

function csdl_year_delete($id) {
    $years = csdl_years_all();
    $years = array_values(array_filter($years, fn($y) => ($y['id'] ?? '') !== $id));
    // Nếu xóa năm hiện hành → đặt năm đầu tiên làm hiện hành
    $hasCurrent = false;
    foreach ($years as $y) {
        if (!empty($y['is_current'])) { $hasCurrent = true; break; }
    }
    if (!$hasCurrent && $years) {
        $years[0]['is_current'] = true;
    }
    save_json(CSDL_YEARS, $years);
    cds_shadow_refresh_core('school_year', $id);
}

/* —— Lớp / khối —— */
function csdl_classes_all() {
    $sqlRows = cds_core_sql_rows('classes');
    if (is_array($sqlRows)) {
        csdl_sort_classes($sqlRows);
        return $sqlRows;
    }
    $rows = load_json(CSDL_CLASSES, []);
    if (!$rows) {
        $seed = [];
        $map = [
            6 => ['A','B'], 7 => ['A','B'], 8 => ['A','B'], 9 => ['A','B'],
            10 => ['A','B'], 11 => ['A','B'], 12 => ['A','B'],
        ];
        foreach ($map as $k => $suffixes) {
            $cap = $k <= 9 ? 'THCS' : 'THPT';
            foreach ($suffixes as $s) {
                $seed[] = [
                    'id' => csdl_uid('cl'),
                    'name' => $k . $s,
                    'grade' => $k,
                    'level' => $cap,
                    'homeroom_teacher_id' => '',
                    'room' => '',
                    'active' => true,
                    'created_at' => csdl_now(),
                ];
            }
        }
        save_json(CSDL_CLASSES, $seed);
        cds_read_verify_rows('classes', $seed);
        csdl_sort_classes($seed);
        return $seed;
    }
    cds_read_verify_rows('classes', $rows);
    csdl_sort_classes($rows);
    return $rows;
}

function csdl_class_find($id) {
    foreach (csdl_classes_all() as $c) {
        if (($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

function csdl_class_save($data) {
    $rows = csdl_classes_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$c) {
        if (($c['id'] ?? '') === $id) {
            $c = array_merge($c, $data);
            $c['updated_at'] = csdl_now();
            $found = true;
            break;
        }
    }
    unset($c);
    if (!$found) {
        $id = $id ?: csdl_uid('cl');
        $data['id'] = $id;
        $data['created_at'] = csdl_now();
        $data['active'] = $data['active'] ?? true;
        $rows[] = $data;
    }
    if (!cds_core_sql_primary_save('class', $id, $rows, CSDL_CLASSES)) {
        save_json(CSDL_CLASSES, $rows);
        cds_shadow_refresh_core('class', $id);
    }
    return $id;
}

function csdl_class_delete($id) {
    $rows = array_values(array_filter(csdl_classes_all(), fn($c) => ($c['id'] ?? '') !== $id));
    if (!cds_core_sql_primary_save('class', $id, $rows, CSDL_CLASSES)) {
        save_json(CSDL_CLASSES, $rows);
        cds_shadow_refresh_core('class', $id);
    }
}

/* —— Giáo viên —— */
function csdl_teachers_all() {
    $sqlRows = cds_core_sql_rows('teachers');
    if (is_array($sqlRows)) return $sqlRows;
    $rows = load_json(CSDL_TEACHERS, []);
    cds_read_verify_rows('teachers', $rows);
    return $rows;
}

function csdl_teacher_find($id) {
    foreach (csdl_teachers_all() as $t) {
        if (($t['id'] ?? '') === $id) return $t;
    }
    return null;
}

function csdl_teacher_save($data) {
    $rows = csdl_teachers_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$t) {
        if (($t['id'] ?? '') === $id) {
            $t = array_merge($t, $data);
            $t['updated_at'] = csdl_now();
            $found = true;
            break;
        }
    }
    unset($t);
    if (!$found) {
        $id = $id ?: csdl_uid('gv');
        $data['id'] = $id;
        $data['created_at'] = csdl_now();
        $data['active'] = $data['active'] ?? true;
        $rows[] = $data;
    }
    if (!cds_core_sql_primary_save('teacher', $id, $rows, CSDL_TEACHERS)) {
        save_json(CSDL_TEACHERS, $rows);
        cds_shadow_refresh_core('teacher', $id);
    }
    return $id;
}

function csdl_teacher_delete($id) {
    $rows = array_values(array_filter(csdl_teachers_all(), fn($t) => ($t['id'] ?? '') !== $id));
    if (!cds_core_sql_primary_save('teacher', $id, $rows, CSDL_TEACHERS)) {
        save_json(CSDL_TEACHERS, $rows);
        cds_shadow_refresh_core('teacher', $id);
    }
}

/* —— Học sinh —— */
function csdl_students_recovery_normalize(array $rows) {
    $classesByName=[];foreach(csdl_classes_all() as $class)$classesByName[csdl_text_sort_key($class['name']??'')]=(string)($class['id']??'');
    $result=[];
    foreach($rows as $row){
        if(!is_array($row))continue;$name=trim((string)($row['name']??$row['ho_ten']??$row['hoten']??''));if($name==='')continue;
        $className=trim((string)($row['class_name']??$row['lop']??$row['class']??''));$id=trim((string)($row['id']??$row['student_id']??$row['ma_hs']??''));
        if($id==='')$id='hs_recovered_'.substr(hash('sha256',$name.'|'.$className.'|'.($row['dob']??'')),0,12);
        $row['id']=$id;$row['name']=$name;$row['class_name']=$className;$row['class_id']=$row['class_id']??($classesByName[csdl_text_sort_key($className)]??'');
        $row['gender']=$row['gender']??$row['gioi_tinh']??$row['gt']??'';$row['dob']=$row['dob']??$row['ngay_sinh']??'';$row['boarder']=array_key_exists('boarder',$row)?(bool)$row['boarder']:true;
        $row['room_ktx']=$row['room_ktx']??$row['dorm_room']??$row['phong']??$row['phong_o']??'';$row['meal_group']=$row['meal_group']??$row['mam']??$row['mam_an']??$row['nhom_an']??'';$row['active']=array_key_exists('active',$row)?(bool)$row['active']:true;
        $result[$id]=$row;
    }
    return array_values($result);
}

function csdl_students_recover_rows() {
    // Ưu tiên bản sao MySQL vì raw_json giữ nguyên hồ sơ CSDL đầy đủ.
    try{
        $rows=[];$stmt=cds_db()->query('SELECT * FROM cds_students ORDER BY name,id');
        while($dbRow=$stmt->fetch(PDO::FETCH_ASSOC)){
            $raw=json_decode((string)($dbRow['raw_json']??''),true);
            if(is_array($raw)&&trim((string)($raw['name']??''))!==''){$rows[]=$raw;continue;}
            $rows[]=['id'=>$dbRow['id']??'','class_id'=>$dbRow['class_id']??'','code'=>$dbRow['code']??'','name'=>$dbRow['name']??'','cccd'=>$dbRow['cccd']??'','dob'=>$dbRow['dob']??'','gender'=>$dbRow['gender']??'','ethnicity'=>$dbRow['ethnicity']??'','hometown'=>$dbRow['hometown']??'','address'=>$dbRow['address']??'','phone'=>$dbRow['phone']??'','parent_name'=>$dbRow['parent_name']??'','parent_phone'=>$dbRow['parent_phone']??'','boarder'=>!empty($dbRow['is_boarder']),'room_ktx'=>$dbRow['dorm_room']??'','meal_group'=>$dbRow['meal_group']??'','active'=>!isset($dbRow['active'])||!empty($dbRow['active']),'note'=>$dbRow['note']??''];
        }
        $rows=csdl_students_recovery_normalize($rows);if($rows)return ['rows'=>$rows,'source'=>'bản sao MySQL'];
    }catch(Throwable $e){error_log('[CDS student recovery mysql] '.$e->getMessage());}

    // Nếu MySQL đã bị đồng bộ rỗng, khôi phục tối thiểu từ cache Nội trú còn trên host.
    foreach([DATA_PATH.'/noitru/boarders_cache.json',DATA_PATH.'/noitru_boarders_live.json'] as $path){
        if(!is_file($path)||!is_readable($path))continue;$decoded=json_decode((string)file_get_contents($path),true);if(!is_array($decoded))continue;$rows=csdl_students_recovery_normalize($decoded);if($rows)return ['rows'=>$rows,'source'=>basename($path)];
    }
    return ['rows'=>[],'source'=>''];
}

function csdl_students_all() {
    $sqlRows = cds_core_sql_rows('students');
    if (is_array($sqlRows)) {
        $classMap = [];
        foreach (csdl_classes_all() as $class) {
            $classMap[(string)($class['id'] ?? '')] = (string)($class['name'] ?? '');
        }
        csdl_sort_students($sqlRows, $classMap);
        return $sqlRows;
    }
    $rows = load_json(CSDL_STUDENTS, []);
    if(!$rows){
        $recovered=csdl_students_recover_rows();
        if(!empty($recovered['rows'])){
            if(is_file(CSDL_STUDENTS))@copy(CSDL_STUDENTS,CSDL_STUDENTS.'.before-recovery-'.date('Ymd-His').'.bak');
            if(save_json(CSDL_STUDENTS,$recovered['rows'])){
                $rows=$recovered['rows'];
                error_log('[CDS student recovery] Restored '.count($rows).' rows from '.$recovered['source']);
                if(function_exists('flash'))flash('Đã tự động khôi phục '.count($rows).' học sinh từ '.$recovered['source'].'.','warning');
            }
        }
    }
    cds_read_verify_rows('students', $rows);
    $classMap = [];
    foreach (csdl_classes_all() as $class) {
        $classMap[(string)($class['id'] ?? '')] = (string)($class['name'] ?? '');
    }
    csdl_sort_students($rows, $classMap);
    return $rows;
}

function csdl_student_find($id) {
    foreach (csdl_students_all() as $s) {
        if (($s['id'] ?? '') === $id) return $s;
    }
    return null;
}

function csdl_student_save($data) {
    $rows = csdl_students_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$s) {
        if (($s['id'] ?? '') === $id) {
            $s = array_merge($s, $data);
            $s['updated_at'] = csdl_now();
            $found = true;
            break;
        }
    }
    unset($s);
    if (!$found) {
        $id = $id ?: csdl_uid('hs');
        $data['id'] = $id;
        $data['created_at'] = csdl_now();
        $data['active'] = $data['active'] ?? true;
        $rows[] = $data;
    }
    if (!cds_core_sql_primary_save('student', $id, $rows, CSDL_STUDENTS)) {
        save_json(CSDL_STUDENTS, $rows);
        cds_shadow_refresh_core('student', $id);
    }
    return $id;
}

function csdl_student_delete($id) {
    $rows = array_values(array_filter(csdl_students_all(), fn($s) => ($s['id'] ?? '') !== $id));
    if (!cds_core_sql_primary_save('student', $id, $rows, CSDL_STUDENTS)) {
        save_json(CSDL_STUDENTS, $rows);
        cds_shadow_refresh_core('student', $id);
    }
}

function csdl_stats() {
    $teachers = csdl_teachers_all();
    $classes = csdl_classes_all();
    $students = csdl_students_all();
    return [
        'teachers' => count(array_filter($teachers, fn($t) => !empty($t['active']))),
        'teachers_total' => count($teachers),
        'classes' => count(array_filter($classes, fn($c) => !empty($c['active']))),
        'students' => count(array_filter($students, fn($s) => !empty($s['active']))),
        'students_total' => count($students),
        'year' => csdl_year_current()['label'] ?? SCHOOL_YEAR,
    ];
}
