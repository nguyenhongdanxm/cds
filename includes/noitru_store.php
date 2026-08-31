<?php
/** Quản lý nội trú – dữ liệu vận hành (HS từ CSDL). */
require_once __DIR__ . '/csdl_store.php';
require_once __DIR__ . '/database_meals.php';
require_once __DIR__ . '/database_meal_read.php';

define('NOITRU_DIR', DATA_PATH . '/noitru');
define('NOITRU_META', NOITRU_DIR . '/meta.json');
define('NOITRU_BOARDERS', NOITRU_DIR . '/boarders_cache.json');
define('NOITRU_EXITS', NOITRU_DIR . '/exits.json');
define('NOITRU_MEALS', NOITRU_DIR . '/meals_daily.json');
define('NOITRU_MEAL_REPORTS', NOITRU_DIR . '/meal_reports.json');
define('NOITRU_ATT', NOITRU_DIR . '/attendance.json');
define('NOITRU_ATT_REPORTS', NOITRU_DIR . '/attendance_reports.json');
define('NOITRU_DUTY', NOITRU_DIR . '/duty.json');
define('NOITRU_DUTY_SETTINGS', NOITRU_DIR . '/duty_settings.json');
define('NOITRU_DUTY_MANAGERS', NOITRU_DIR . '/duty_managers.json');
define('NOITRU_DUTY_GROUPS', NOITRU_DIR . '/duty_groups.json');
define('NOITRU_DUTY_ROSTER', NOITRU_DIR . '/duty_roster.json');
define('NOITRU_DUTY_REPORTS', NOITRU_DIR . '/duty_reports.json');
define('NOITRU_HEALTH', NOITRU_DIR . '/health.json');
define('NOITRU_MEDICINES', NOITRU_DIR . '/medicines.json');
define('NOITRU_MEDICINE_TX', NOITRU_DIR . '/medicine_transactions.json');
define('NOITRU_MENUS', NOITRU_DIR . '/menus.json');
define('NOITRU_MENU_CONFIG', NOITRU_DIR . '/menu_config.json');
define('NOITRU_RICE', NOITRU_DIR . '/rice.json');

function noitru_ensure_dir() {
    if (!is_dir(NOITRU_DIR)) @mkdir(NOITRU_DIR, 0755, true);
}
function noitru_uid($p = 'nt') { return $p . '_' . bin2hex(random_bytes(4)); }
function noitru_now() { return date('c'); }

function noitru_meta() {
    noitru_ensure_dir();
    return load_json(NOITRU_META, ['last_sync_at' => null, 'last_sync_count' => 0]);
}
function noitru_meta_save(array $m) {
    noitru_ensure_dir();
    save_json(NOITRU_META, $m);
}

function noitru_class_name($classId) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (csdl_classes_all() as $c) $map[$c['id'] ?? ''] = $c['name'] ?? '';
    }
    return $map[$classId] ?? '';
}

/** Lấy giá trị đầu tiên có tồn tại để tương thích dữ liệu CSDL cũ/mới. */
function noitru_student_value(array $student, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (array_key_exists($key, $student)) return $student[$key];
    }
    return $default;
}

function noitru_student_bool($value, bool $default = false): bool {
    if ($value === null || $value === '') return $default;
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return (int)$value !== 0;
    $text = trim((string)$value);
    if (function_exists('mb_strtolower')) $text = mb_strtolower($text, 'UTF-8');
    else $text = strtolower($text);
    return in_array($text, ['1', 'true', 'yes', 'y', 'on', 'x', 'có', 'co', 'nội trú', 'noi tru'], true);
}

function noitru_student_is_active(array $student): bool {
    // Bản ghi CSDL cũ không có trường active được hiểu là vẫn đang học.
    $value = noitru_student_value($student, ['active', 'is_active', 'dang_hoc'], null);
    return noitru_student_bool($value, $value === null);
}

function noitru_student_is_boarder(array $student): bool {
    return noitru_student_bool(noitru_student_value(
        $student,
        ['boarder', 'is_boarder', 'noi_tru', 'nội_trú'],
        false
    ));
}

function noitru_student_class_name(array $student): string {
    $classId = trim((string)noitru_student_value($student, ['class_id', 'lop_id'], ''));
    $name = $classId !== '' ? noitru_class_name($classId) : '';
    if ($name === '') {
        $name = (string)noitru_student_value($student, ['class_name', 'class', 'lop', 'ten_lop'], '');
    }
    return trim($name);
}

function noitru_boarders_live() {
    $out = [];
    foreach (csdl_students_all() as $s) {
        if (!noitru_student_is_active($s) || !noitru_student_is_boarder($s)) continue;
        $out[] = [
            'id' => $s['id'] ?? '',
            'code' => $s['code'] ?? '',
            'name' => $s['name'] ?? '',
            'cccd' => $s['cccd'] ?? '',
            'class_id' => noitru_student_value($s, ['class_id', 'lop_id'], ''),
            'class_name' => noitru_student_class_name($s),
            'gender' => noitru_student_value($s, ['gender', 'gioi_tinh', 'sex', 'gt'], ''),
            'dob' => $s['dob'] ?? '',
            'ethnicity' => $s['ethnicity'] ?? '',
            'hometown' => $s['hometown'] ?? '',
            'address' => $s['address'] ?? '',
            'phone' => $s['phone'] ?? '',
            'parent_name' => $s['parent_name'] ?? '',
            'parent_phone' => $s['parent_phone'] ?? '',
            'room_ktx' => noitru_student_value($s, ['room_ktx', 'dorm_room', 'phong_o', 'phong'], ''),
            'meal_group' => noitru_student_value($s, ['meal_group', 'mam_an', 'nhom_an'], ''),
            'note' => $s['note'] ?? '',
        ];
    }
    csdl_sort_students($out);
    return $out;
}

function noitru_sync_from_csdl() {
    noitru_ensure_dir();
    $list = noitru_boarders_live();
    save_json(NOITRU_BOARDERS, $list);
    $meta = noitru_meta();
    $meta['last_sync_at'] = noitru_now();
    $meta['last_sync_count'] = count($list);
    noitru_meta_save($meta);
    $sourceTotal = $activeTotal = $notBoarder = 0;
    foreach (csdl_students_all() as $student) {
        $sourceTotal++;
        if (!noitru_student_is_active($student)) continue;
        $activeTotal++;
        if (!noitru_student_is_boarder($student)) $notBoarder++;
    }
    $message = 'Đã đồng bộ ' . count($list) . ' HS nội trú từ CSDL.';
    if ($notBoarder > 0) {
        $message .= ' Có ' . $notBoarder . ' HS đang học chưa được đưa vào vì chưa đánh dấu “Nội trú” trong CSDL.';
    }
    return [
        'ok' => true,
        'count' => count($list),
        'source_total' => $sourceTotal,
        'active_total' => $activeTotal,
        'skipped_not_boarder' => $notBoarder,
        'message' => $message,
    ];
}

/** Thống kê trực tiếp trên danh sách đã áp dụng chia phòng/chia mâm. */
function noitru_boarders_stats(array $boarders): array {
    $male = $female = 0;
    $rooms = $meals = [];
    foreach ($boarders as $student) {
        $gender = trim((string)noitru_student_value($student, ['gender', 'gioi_tinh', 'sex', 'gt'], ''));
        $genderLower = function_exists('mb_strtolower')
            ? mb_strtolower($gender, 'UTF-8')
            : strtolower($gender);
        if (in_array($genderLower, ['nam', 'male', 'm', '1'], true)) $male++;
        elseif (in_array($genderLower, ['nữ', 'nu', 'female', 'f', '0'], true)) $female++;

        $room = trim((string)noitru_student_value($student, ['room_ktx', 'dorm_room', 'phong_o', 'phong'], ''));
        $meal = trim((string)noitru_student_value($student, ['meal_group', 'mam_an', 'nhom_an'], ''));
        if ($room !== '') $rooms[$room] = true;
        if ($meal !== '') $meals[$meal] = true;
    }
    return [
        'total' => count($boarders),
        'male' => $male,
        'female' => $female,
        'rooms' => count($rooms),
        'meals' => count($meals),
    ];
}

function noitru_stats() {
    $list = noitru_boarders_live();
    $byClass = $byRoom = $byMeal = [];
    foreach ($list as $s) {
        $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
        $byClass[$cn] = ($byClass[$cn] ?? 0) + 1;
        $rm = trim($s['room_ktx'] ?? '') !== '' ? $s['room_ktx'] : '(Chưa phòng)';
        $byRoom[$rm] = ($byRoom[$rm] ?? 0) + 1;
        $mg = trim($s['meal_group'] ?? '') !== '' ? $s['meal_group'] : '(Chưa nhóm ăn)';
        $byMeal[$mg] = ($byMeal[$mg] ?? 0) + 1;
    }
    uksort($byClass, 'csdl_compare_class_names');
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

/* —— Exits —— */
function noitru_exits_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_EXITS, []);
}
function noitru_exit_save(array $data) {
    $rows = noitru_exits_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$r) {
        if (($r['id'] ?? '') === $id) {
            $r = array_merge($r, $data);
            $r['updated_at'] = noitru_now();
            $found = true;
            break;
        }
    }
    unset($r);
    if (!$found) {
        $id = $id ?: noitru_uid('ex');
        $data['id'] = $id;
        $data['created_at'] = noitru_now();
        $data['status'] = $data['status'] ?? 'pending';
        $rows[] = $data;
    }
    save_json(NOITRU_EXITS, $rows);
    return $id;
}
function noitru_exit_delete($id) {
    save_json(NOITRU_EXITS, array_values(array_filter(noitru_exits_all(), fn($r) => ($r['id'] ?? '') !== $id)));
}
function noitru_student_away_on_date($studentId, $date) {
    foreach (noitru_exits_all() as $r) {
        if (($r['student_id'] ?? '') !== $studentId) continue;
        if (($r['status'] ?? '') !== 'approved') continue;
        $from = substr($r['from_date'] ?? '', 0, 10);
        $to = substr($r['to_date'] ?? $from, 0, 10);
        if ($from && $to && $date >= $from && $date <= $to) return $r;
    }
    return null;
}

/* —— Meals —— */
function noitru_meal_read_cache_clear($date = null) {
    unset($GLOBALS['noitru_meals_all_cache'], $GLOBALS['noitru_meal_reports_cache']);
    if ($date === null) unset($GLOBALS['noitru_meals_date_cache']);
    else unset($GLOBALS['noitru_meals_date_cache'][(string)$date]);
}
function noitru_meals_all() {
    noitru_ensure_dir();
    if (isset($GLOBALS['noitru_meals_all_cache'])) return $GLOBALS['noitru_meals_all_cache'];
    if (cds_meal_sql_read_effective()) {
        try { return $GLOBALS['noitru_meals_all_cache'] = cds_meal_sql_daily_all(); }
        catch (Throwable $e) { cds_meal_sql_fallback('daily_all', $e); }
    }
    return $GLOBALS['noitru_meals_all_cache'] = load_json(NOITRU_MEALS, []);
}
function noitru_meal_target_key($date, $class, $meal) {
    return trim((string)$date) . '|' . trim((string)$class) . '|' . trim((string)$meal);
}
function noitru_meal_deleted_targets() {
    $rows = load_json(NOITRU_DIR . '/meal_history_deleted_targets.json', []);
    return is_array($rows) ? array_fill_keys(array_map('strval', $rows), true) : [];
}
function noitru_meal_deleted_targets_save(array $keys) {
    save_json(NOITRU_DIR . '/meal_history_deleted_targets.json', array_keys($keys));
}
function noitru_meal_reports_data() {
    noitru_ensure_dir();
    if (isset($GLOBALS['noitru_meal_reports_cache'])) return $GLOBALS['noitru_meal_reports_cache'];
    if (cds_meal_sql_read_effective()) {
        try { $data = cds_meal_sql_reports_data(); }
        catch (Throwable $e) { cds_meal_sql_fallback('reports', $e); $data = load_json(NOITRU_MEAL_REPORTS, ['reports'=>[], 'states'=>[], 'settings'=>[]]); }
    } else {
        $data = load_json(NOITRU_MEAL_REPORTS, ['reports'=>[], 'states'=>[], 'settings'=>[]]);
    }
    $data['reports'] = $data['reports'] ?? [];
    /* Các lượt đã xóa khỏi lịch sử không được tiếp tục tham gia tổng hợp. */
    $deletedReportIds = load_json(NOITRU_DIR . '/meal_history_deleted.json', []);
    if (is_array($deletedReportIds) && $deletedReportIds) {
        $deletedReportIds = array_fill_keys(array_map('strval', $deletedReportIds), true);
        $data['reports'] = array_values(array_filter($data['reports'], fn($row) => !isset($deletedReportIds[(string)($row['id'] ?? '')])));
    }
    $deletedTargets = noitru_meal_deleted_targets();
    if ($deletedTargets) {
        $data['reports'] = array_values(array_filter($data['reports'], function ($row) use ($deletedTargets) {
            return !isset($deletedTargets[noitru_meal_target_key($row['date'] ?? '', $row['class_name'] ?? '', $row['meal'] ?? '')]);
        }));
    }
    $data['states'] = $data['states'] ?? [];
    $data['settings'] = array_merge([
        'sang_lock_time'=>'20:00',
        'trua_lock_time'=>'09:00',
        'toi_lock_time'=>'15:00',
    ], $data['settings'] ?? []);
    return $GLOBALS['noitru_meal_reports_cache'] = $data;
}
function noitru_meal_reports_save(array $data) {
    noitru_ensure_dir();
    $data['reports'] = array_values($data['reports'] ?? []);
    $data['states'] = array_values($data['states'] ?? []);
    $data['settings'] = $data['settings'] ?? [];
    if (!save_json(NOITRU_MEAL_REPORTS, $data)) return false;
    noitru_meal_read_cache_clear();
    if (!cds_meal_shadow_reports_data($data)) cds_meal_shadow_notify_failure();
    return true;
}
function noitru_meal_settings() {
    return noitru_meal_reports_data()['settings'];
}
function noitru_meal_settings_save(array $settings) {
    $data = noitru_meal_reports_data();
    foreach (['sang','trua','toi'] as $meal) {
        $value = trim($settings[$meal . '_lock_time'] ?? '');
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) $data['settings'][$meal . '_lock_time'] = $value;
    }
    noitru_meal_reports_save($data);
}
function noitru_meal_report_for($date, $class, $meal) {
    foreach (noitru_meal_reports_data()['reports'] ?? [] as $row) {
        if (($row['date'] ?? '') === $date && ($row['class_name'] ?? '') === $class && ($row['meal'] ?? '') === $meal) return $row;
    }
    return null;
}
function noitru_meal_report_upsert(array $row) {
    $data = noitru_meal_reports_data();
    /* Báo lại sau khi đã xóa là một phiếu mới hợp lệ, nên mở lại đúng khóa này. */
    $deletedTargets = noitru_meal_deleted_targets();
    $targetKey = noitru_meal_target_key($row['date'] ?? '', $row['class_name'] ?? '', $row['meal'] ?? '');
    if (isset($deletedTargets[$targetKey])) {
        unset($deletedTargets[$targetKey]);
        noitru_meal_deleted_targets_save($deletedTargets);
    }
    $found = false;
    foreach ($data['reports'] as &$saved) {
        if (($saved['date'] ?? '') === ($row['date'] ?? '') && ($saved['class_name'] ?? '') === ($row['class_name'] ?? '') && ($saved['meal'] ?? '') === ($row['meal'] ?? '')) {
            $saved = array_merge($saved, $row, ['updated_at'=>noitru_now()]);
            $found = true;
            break;
        }
    }
    unset($saved);
    if (!$found) $data['reports'][] = array_merge(['id'=>noitru_uid('mr'), 'created_at'=>noitru_now()], $row);
    noitru_meal_reports_save($data);
}
function noitru_meal_state($date, $meal) {
    $saved = null;
    foreach (noitru_meal_reports_data()['states'] ?? [] as $row) {
        if (($row['date'] ?? '') === $date && ($row['meal'] ?? '') === $meal) { $saved = $row; break; }
    }
    if ($saved && in_array($saved['status'] ?? '', ['locked','off'], true)) return $saved;
    if ($saved && ($saved['status'] ?? '') === 'open_override') return array_merge($saved, ['status'=>'open']);

    $settings = noitru_meal_settings();
    $lockTime = $settings[$meal . '_lock_time'] ?? '23:59';
    $lockDate = $meal === 'sang' ? date('Y-m-d', strtotime($date . ' -1 day')) : $date;
    $deadline = strtotime($lockDate . ' ' . $lockTime . ':00');
    if ($deadline !== false && time() >= $deadline) {
        return ['date'=>$date, 'meal'=>$meal, 'status'=>'locked', 'auto_locked'=>true, 'deadline'=>date('c', $deadline)];
    }
    return ['date'=>$date, 'meal'=>$meal, 'status'=>'open', 'deadline'=>$deadline ? date('c', $deadline) : ''];
}
function noitru_meal_state_set($date, $meal, $status, $by = '') {
    if (!in_array($status, ['open','locked','off'], true)) $status = 'open';
    if ($status === 'open') $status = 'open_override';
    $data = noitru_meal_reports_data();
    $found = false;
    foreach ($data['states'] as &$row) {
        if (($row['date'] ?? '') === $date && ($row['meal'] ?? '') === $meal) {
            $row = array_merge($row, ['status'=>$status, 'by'=>$by, 'updated_at'=>noitru_now()]);
            $found = true;
            break;
        }
    }
    unset($row);
    if (!$found) $data['states'][] = ['date'=>$date, 'meal'=>$meal, 'status'=>$status, 'by'=>$by, 'updated_at'=>noitru_now()];
    noitru_meal_reports_save($data);
}
function noitru_meal_reports_for_date($date) {
    return array_values(array_filter(noitru_meal_reports_data()['reports'] ?? [], fn($row) => ($row['date'] ?? '') === $date));
}
function noitru_meals_for_date($date) {
    if (isset($GLOBALS['noitru_meals_date_cache'][(string)$date])) return $GLOBALS['noitru_meals_date_cache'][(string)$date];
    $out = [];
    $rows = null;
    if (cds_meal_sql_read_effective()) {
        try { $rows = cds_meal_sql_daily_for_date($date); }
        catch (Throwable $e) { cds_meal_sql_fallback('daily_date', $e); }
    }
    if (!is_array($rows)) $rows = load_json(NOITRU_MEALS, []);
    foreach ($rows as $m) {
        if (($m['date'] ?? '') === $date) $out[$m['student_id'] ?? ''] = $m;
    }
    $GLOBALS['noitru_meals_date_cache'][(string)$date] = $out;
    return $out;
}
function noitru_meals_for_range($from, $to) {
    if (cds_meal_sql_read_effective()) {
        try { return cds_meal_sql_daily_for_range($from, $to); }
        catch (Throwable $e) { cds_meal_sql_fallback('daily_range', $e); }
    }
    return array_values(array_filter(load_json(NOITRU_MEALS, []), function ($row) use ($from, $to) {
        $date = (string)($row['date'] ?? '');
        return $date >= $from && $date <= $to;
    }));
}
function noitru_meal_upsert(array $row) {
    $rows = noitru_meals_all();
    $date = $row['date'] ?? '';
    $sid = $row['student_id'] ?? '';
    $found = false;
    $savedRow = null;
    foreach ($rows as &$m) {
        if (($m['date'] ?? '') === $date && ($m['student_id'] ?? '') === $sid) {
            if (!empty($m['locked']) && empty($row['force'])) return false;
            $m = array_merge($m, $row);
            unset($m['force']);
            $m['updated_at'] = noitru_now();
            $savedRow = $m;
            $found = true;
            break;
        }
    }
    unset($m);
    if (!$found) {
        $row['id'] = $row['id'] ?? noitru_uid('ml');
        $row['created_at'] = noitru_now();
        unset($row['force']);
        $rows[] = $row;
        $savedRow = $row;
    }
    if (!save_json(NOITRU_MEALS, $rows)) return false;
    noitru_meal_read_cache_clear($date);
    if ($savedRow && !cds_meal_shadow_daily_row($savedRow)) cds_meal_shadow_notify_failure();
    return true;
}
function noitru_meals_generate_day($date) {
    $n = 0;
    $rows = noitru_meals_all();
    $positions = [];
    foreach ($rows as $index => $saved) {
        if (($saved['date'] ?? '') === $date) $positions[(string)($saved['student_id'] ?? '')] = $index;
    }
    foreach (noitru_boarders_live() as $s) {
        $sid = (string)($s['id'] ?? '');
        $index = $positions[$sid] ?? null;
        if ($index !== null && !empty($rows[$index]['locked'])) continue;
        $away = noitru_student_away_on_date($s['id'], $date);
        $val = $away ? 'no' : 'yes';
        $next = [
            'date' => $date,
            'student_id' => $sid,
            'sang' => $val,
            'trua' => $val,
            'toi' => $val,
            'source' => $away ? 'auto_exit' : 'auto',
            'locked' => false,
            'note' => $away ? ('Theo phiếu: ' . ($away['reason'] ?? '')) : '',
        ];
        if ($index !== null) {
            $rows[$index] = array_merge($rows[$index], $next, ['updated_at'=>noitru_now()]);
        } else {
            $next['id'] = noitru_uid('ml');
            $next['created_at'] = noitru_now();
            $rows[] = $next;
            $positions[$sid] = count($rows) - 1;
        }
        $n++;
    }
    if (!save_json(NOITRU_MEALS, $rows)) return 0;
    noitru_meal_read_cache_clear($date);
    if (!cds_meal_shadow_daily_rows($rows, $date)) cds_meal_shadow_notify_failure();
    return $n;
}
function noitru_meals_lock_day($date, $lock = true) {
    $rows = noitru_meals_all();
    foreach ($rows as &$m) {
        if (($m['date'] ?? '') === $date) $m['locked'] = $lock;
    }
    unset($m);
    if (!save_json(NOITRU_MEALS, $rows)) return false;
    noitru_meal_read_cache_clear($date);
    if (!cds_meal_shadow_daily_rows($rows, $date)) cds_meal_shadow_notify_failure();
    return true;
}
function noitru_meals_count_day($date) {
    $c = ['sang' => 0, 'trua' => 0, 'toi' => 0, 'students' => 0];
    foreach (noitru_meals_for_date($date) as $m) {
        $c['students']++;
        foreach (['sang', 'trua', 'toi'] as $b) {
            if (in_array($m[$b] ?? '', ['yes', 'sick', 'guest'], true)) $c[$b]++;
        }
    }
    foreach (['sang','trua','toi'] as $meal) {
        if ((noitru_meal_state($date, $meal)['status'] ?? 'open') === 'off') $c[$meal] = 0;
    }
    return $c;
}

function noitru_meals_summary($from, $to) {
    $students = [];
    foreach (noitru_boarders_live() as $s) $students[$s['id']] = $s;
    $reported = [];
    foreach (noitru_meal_reports_data()['reports'] ?? [] as $report) {
        $reported[(string)($report['date'] ?? '') . '|' . (string)($report['class_name'] ?? '') . '|' . (string)($report['meal'] ?? '')] = true;
    }
    $out = ['classes'=>[], 'groups'=>[], 'days'=>[], 'total'=>['sang'=>0,'trua'=>0,'toi'=>0]];
    $mealStates = [];
    foreach (noitru_meals_for_range($from, $to) as $m) {
        $date = $m['date'] ?? '';
        if ($date < $from || $date > $to) continue;
        $student = $students[$m['student_id'] ?? ''] ?? [];
        $class = trim($student['class_name'] ?? '') ?: '(Chưa lớp)';
        $group = trim($student['meal_group'] ?? '') ?: '(Chưa mâm)';
        foreach (['sang','trua','toi'] as $meal) {
            /* Trạng thái nghỉ của toàn bữa luôn ưu tiên hơn dữ liệu báo ăn từng học sinh. */
            $stateKey = $date . '|' . $meal;
            if (!array_key_exists($stateKey, $mealStates)) $mealStates[$stateKey] = noitru_meal_state($date, $meal)['status'] ?? 'open';
            if ($mealStates[$stateKey] === 'off') continue;
            /* Không có phiếu báo của lớp/bữa thì không được mặc định là có ăn. */
            if (empty($reported[$date . '|' . $class . '|' . $meal])) continue;
            if (!in_array($m[$meal] ?? '', ['yes','sick','guest'], true)) continue;
            $out['total'][$meal]++;
            $out['days'][$date][$meal] = ($out['days'][$date][$meal] ?? 0) + 1;
            $out['classes'][$class][$meal] = ($out['classes'][$class][$meal] ?? 0) + 1;
            $out['groups'][$group][$meal] = ($out['groups'][$group][$meal] ?? 0) + 1;
        }
    }
    ksort($out['days']); uksort($out['classes'], 'csdl_compare_class_names'); ksort($out['groups'], SORT_NATURAL);
    return $out;
}

function noitru_rice_data() {
    noitru_ensure_dir();
    return load_json(NOITRU_RICE, [
        'settings'=>['sang_grams'=>0,'trua_grams'=>180,'toi_grams'=>180],
        'transactions'=>[],
    ]);
}
function noitru_rice_save(array $data) {
    noitru_ensure_dir();
    save_json(NOITRU_RICE, $data);
}
function noitru_rice_balance(array $data = null) {
    $data = $data ?? noitru_rice_data();
    $balance = 0.0;
    foreach ($data['transactions'] ?? [] as $row) {
        $qty = (float)($row['kg'] ?? 0);
        $balance += ($row['type'] ?? '') === 'in' ? $qty : -$qty;
    }
    $auto = noitru_rice_usage_summary('0000-01-01', '9999-12-31', $data);
    $balance -= (float)($auto['total_kg'] ?? 0);
    return round($balance, 3);
}
function noitru_rice_usage_summary($from, $to, array $riceData = null) {
    $riceData = $riceData ?? noitru_rice_data();
    $settings = array_merge(['sang_grams'=>0,'trua_grams'=>180,'toi_grams'=>180], $riceData['settings'] ?? []);
    $reports = noitru_meal_reports_data()['reports'] ?? [];
    $stateCache = [];
    $out = [
        'days'=>[],
        'meals'=>[
            'sang'=>['students'=>0,'kg'=>0.0],
            'trua'=>['students'=>0,'kg'=>0.0],
            'toi'=>['students'=>0,'kg'=>0.0],
        ],
        'total_students'=>0,
        'total_kg'=>0.0,
    ];
    foreach ($reports as $report) {
        $date = $report['date'] ?? '';
        $meal = $report['meal'] ?? '';
        if ($date < $from || $date > $to || !isset($out['meals'][$meal])) continue;
        $stateKey = $date . '|' . $meal;
        if (!isset($stateCache[$stateKey])) $stateCache[$stateKey] = noitru_meal_state($date, $meal)['status'] ?? 'open';
        if ($stateCache[$stateKey] !== 'locked') continue;
        $students = max(0, (int)($report['eat_count'] ?? 0));
        $kg = round($students * (float)($settings[$meal . '_grams'] ?? 0) / 1000, 3);
        if (!isset($out['days'][$date])) {
            $out['days'][$date] = [
                'sang'=>['students'=>0,'kg'=>0.0],
                'trua'=>['students'=>0,'kg'=>0.0],
                'toi'=>['students'=>0,'kg'=>0.0],
                'students'=>0,
                'kg'=>0.0,
            ];
        }
        $out['days'][$date][$meal]['students'] += $students;
        $out['days'][$date][$meal]['kg'] = round($out['days'][$date][$meal]['kg'] + $kg, 3);
        $out['days'][$date]['students'] += $students;
        $out['days'][$date]['kg'] = round($out['days'][$date]['kg'] + $kg, 3);
        $out['meals'][$meal]['students'] += $students;
        $out['meals'][$meal]['kg'] = round($out['meals'][$meal]['kg'] + $kg, 3);
        $out['total_students'] += $students;
        $out['total_kg'] = round($out['total_kg'] + $kg, 3);
    }
    ksort($out['days']);
    return $out;
}

/* —— Attendance —— */
function noitru_att_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_ATT, []);
}
function noitru_att_for($date, $shift) {
    $out = [];
    foreach (noitru_att_all() as $a) {
        if (($a['date'] ?? '') === $date && ($a['shift'] ?? '') === $shift)
            $out[$a['student_id'] ?? ''] = $a;
    }
    return $out;
}
function noitru_att_upsert(array $row) {
    $rows = noitru_att_all();
    $date = $row['date'] ?? '';
    $shift = $row['shift'] ?? '';
    $sid = $row['student_id'] ?? '';
    $found = false;
    foreach ($rows as &$a) {
        if (($a['date'] ?? '') === $date && ($a['shift'] ?? '') === $shift && ($a['student_id'] ?? '') === $sid) {
            $a = array_merge($a, $row);
            $a['updated_at'] = noitru_now();
            $found = true;
            break;
        }
    }
    unset($a);
    if (!$found) {
        $row['id'] = noitru_uid('at');
        $row['created_at'] = noitru_now();
        $rows[] = $row;
    }
    save_json(NOITRU_ATT, $rows);
}
function noitru_att_reports_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_ATT_REPORTS, []);
}

/**
 * Các lần điểm danh đã chốt gần nhất theo đúng thứ tự buổi được cấu hình.
 * Không suy đoán bằng tên/mã ca cố định vì quản trị có thể đổi hoặc thêm buổi.
 */
function noitru_att_recent_reports($limit = 3, $studentIds = null, $scopeTotal = null, $untilDate = null) {
    require_once __DIR__ . '/noitru_att_shifts.php';
    $limit = max(1, (int)$limit);
    $untilDate = $untilDate ?: date('Y-m-d');
    $shiftMeta = [];
    foreach (noitru_att_shifts_all() as $shift) {
        $id = (string)($shift['id'] ?? '');
        if ($id === '') continue;
        $shiftMeta[$id] = [
            'label'=>(string)($shift['label'] ?? $id),
            'sort'=>(int)($shift['sort'] ?? 0),
        ];
    }
    $shiftMeta['dot_xuat'] = $shiftMeta['dot_xuat'] ?? ['label'=>'Điểm danh đột xuất','sort'=>999];

    $allReports = noitru_att_reports_all();
    if (!$allReports && $scopeTotal !== null && (int)$scopeTotal > 0 && noitru_att_all()) {
        noitru_att_ensure_legacy_reports((int)$scopeTotal);
        $allReports = noitru_att_reports_all();
    }
    $reports = array_values(array_filter($allReports, static function ($row) use ($untilDate) {
        $date = (string)($row['date'] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= $untilDate && trim((string)($row['shift'] ?? '')) !== '';
    }));
    usort($reports, static function ($a, $b) use ($shiftMeta) {
        $dateCompare = strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
        if ($dateCompare !== 0) return $dateCompare;
        $aShift = (string)($a['shift'] ?? '');
        $bShift = (string)($b['shift'] ?? '');
        $sortCompare = ($shiftMeta[$bShift]['sort'] ?? 0) <=> ($shiftMeta[$aShift]['sort'] ?? 0);
        if ($sortCompare !== 0) return $sortCompare;
        return strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? ''));
    });

    $studentMap = is_array($studentIds) ? array_fill_keys(array_map('strval', $studentIds), true) : null;
    $scopeTotal = $scopeTotal === null ? null : max(0, (int)$scopeTotal);
    $exceptions = $studentMap === null ? [] : noitru_att_all();
    $out = [];
    $seen = [];
    foreach ($reports as $report) {
        $date = (string)$report['date'];
        $shift = (string)$report['shift'];
        $key = $date . '|' . $shift;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $counts = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
        if ($studentMap === null) {
            foreach ($counts as $status => $_) $counts[$status] = max(0, (int)($report[$status] ?? 0));
            $total = max(0, (int)($report['total'] ?? array_sum($counts)));
            $present = $counts['present'] + $counts['late'];
        } else {
            foreach ($exceptions as $row) {
                if (($row['date'] ?? '') !== $date || ($row['shift'] ?? '') !== $shift) continue;
                if (!isset($studentMap[(string)($row['student_id'] ?? '')])) continue;
                $status = (string)($row['status'] ?? 'present');
                if (isset($counts[$status])) $counts[$status]++;
            }
            $total = $scopeTotal ?? count($studentMap);
            $present = max(0, $total - $counts['absent'] - $counts['excused']);
            $counts['present'] = max(0, $present - $counts['late']);
        }
        $out[] = [
            'date'=>$date,
            'shift'=>$shift,
            'shift_label'=>$shiftMeta[$shift]['label'] ?? $shift,
            'present'=>$present,
            'absent'=>$counts['absent'],
            'excused'=>$counts['excused'],
            'late'=>$counts['late'],
            'total'=>$total,
            'by'=>trim((string)($report['by'] ?? $report['saved_by'] ?? '')),
            'saved_by'=>trim((string)($report['saved_by'] ?? '')),
            'updated_at'=>(string)($report['updated_at'] ?? $report['created_at'] ?? ''),
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}
function noitru_att_report_for($date, $shift) {
    foreach (noitru_att_reports_all() as $report) {
        if (($report['date'] ?? '') === $date && ($report['shift'] ?? '') === $shift) return $report;
    }
    return null;
}
function noitru_att_ensure_legacy_reports($schoolTotal) {
    $schoolTotal=max(0,(int)$schoolTotal);if($schoolTotal===0)return 0;
    $reports=noitru_att_reports_all();$known=[];foreach($reports as $report)$known[($report['date']??'').'|'.($report['shift']??'')]=true;
    $groups=[];foreach(noitru_att_all() as $row){$date=(string)($row['date']??'');$shift=(string)($row['shift']??'');if($date===''||$shift==='')continue;$key=$date.'|'.$shift;if(isset($known[$key]))continue;if(!isset($groups[$key]))$groups[$key]=['date'=>$date,'shift'=>$shift,'present'=>0,'absent'=>0,'late'=>0,'excused'=>0,'by'=>$row['by']??'','saved_by'=>$row['saved_by']??'','created_at'=>$row['created_at']??noitru_now()];$status=$row['status']??'present';if(isset($groups[$key][$status]))$groups[$key][$status]++;}
    $added=0;foreach($groups as $group){$nonPresent=(int)$group['absent']+(int)$group['late']+(int)$group['excused'];$group['present']=max((int)$group['present'],$schoolTotal-$nonPresent);$group['total']=$group['present']+$nonPresent;$group['id']=noitru_uid('atr');$group['updated_at']=noitru_now();$reports[]=$group;$added++;}
    if($added)save_json(NOITRU_ATT_REPORTS,$reports);return $added;
}
function noitru_att_save_report($date, $shift, array $records, array $meta = []) {
    $date = trim((string)$date); $shift = trim((string)$shift);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $shift === '') return false;
    noitru_ensure_dir();$lock=fopen(NOITRU_DIR.'/.attendance.lock','c');if($lock===false||!flock($lock,LOCK_EX)){if(is_resource($lock))fclose($lock);return false;}
    $now = noitru_now(); $counts = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    $exceptions = [];
    foreach ($records as $record) {
        $sid = trim((string)($record['student_id'] ?? ''));
        $status = (string)($record['status'] ?? 'present');
        if ($sid === '' || !isset($counts[$status])) continue;
        $counts[$status]++;
        if ($status === 'present') continue;
        $record['date']=$date; $record['shift']=$shift; $record['student_id']=$sid;
        $record['id']=$record['id']??noitru_uid('at'); $record['updated_at']=$now;
        $record['created_at']=$record['created_at']??$now; $exceptions[]=$record;
    }
    $rows = array_values(array_filter(noitru_att_all(), fn($row) => ($row['date']??'') !== $date || ($row['shift']??'') !== $shift));
    $savedAttendance=save_json(NOITRU_ATT, array_merge($rows, $exceptions));
    $reports = noitru_att_reports_all(); $found = false;
    foreach ($reports as &$report) {
        if (($report['date']??'') !== $date || ($report['shift']??'') !== $shift) continue;
        $createdAt=$report['created_at']??$now;
        $report=array_merge($report,$meta,$counts,['date'=>$date,'shift'=>$shift,'total'=>array_sum($counts),'created_at'=>$createdAt,'updated_at'=>$now]);
        $found=true; break;
    }
    unset($report);
    if (!$found) $reports[]=array_merge($meta,$counts,['id'=>noitru_uid('atr'),'date'=>$date,'shift'=>$shift,'total'=>array_sum($counts),'created_at'=>$now,'updated_at'=>$now]);
    $savedReport=save_json(NOITRU_ATT_REPORTS, $reports);
    flock($lock,LOCK_UN);fclose($lock);
    return $savedAttendance&&$savedReport;
}
function noitru_att_delete(array $dates, $shift = null) {
    $dates = array_values(array_unique(array_filter(array_map('trim', $dates), function ($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    })));
    if (!$dates) return 0;

    $dateMap = array_fill_keys($dates, true);
    $shift = $shift !== null ? trim((string)$shift) : null;
    $rows = noitru_att_all();
    $kept = [];
    $deleted = 0;
    foreach ($rows as $row) {
        $matchesDate = isset($dateMap[$row['date'] ?? '']);
        $matchesShift = $shift === null || $shift === '' || ($row['shift'] ?? '') === $shift;
        if ($matchesDate && $matchesShift) {
            $deleted++;
            continue;
        }
        $kept[] = $row;
    }
    if ($deleted > 0) save_json(NOITRU_ATT, $kept);
    $reports=noitru_att_reports_all();$keptReports=[];$deletedReports=0;
    foreach($reports as $report){$matchesDate=isset($dateMap[$report['date']??'']);$matchesShift=$shift===null||$shift===''||($report['shift']??'')===$shift;if($matchesDate&&$matchesShift){$deletedReports++;continue;}$keptReports[]=$report;}
    if($deletedReports>0)save_json(NOITRU_ATT_REPORTS,$keptReports);
    return max($deleted,$deletedReports);
}

/* —— Duty —— */
function noitru_duty_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_DUTY, []);
}
function noitru_duty_save(array $data) {
    $rows = noitru_duty_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$r) {
        if (($r['id'] ?? '') === $id) {
            $r = array_merge($r, $data);
            $found = true;
            break;
        }
    }
    unset($r);
    if (!$found) {
        $id = noitru_uid('du');
        $data['id'] = $id;
        $data['created_at'] = noitru_now();
        $rows[] = $data;
    }
    save_json(NOITRU_DUTY, $rows);
    return $id;
}
function noitru_duty_delete($id) {
    save_json(NOITRU_DUTY, array_values(array_filter(noitru_duty_all(), fn($r) => ($r['id'] ?? '') !== $id)));
}

/* —— Biên bản trực nội trú hằng ngày —— */
function noitru_duty_reports_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_DUTY_REPORTS, []);
}
function noitru_duty_report_for_date($date) {
    foreach (noitru_duty_reports_all() as $row) {
        if (($row['date'] ?? '') === $date) return $row;
    }
    return null;
}
function noitru_duty_report_save(array $data) {
    $rows = noitru_duty_reports_all();
    $date = trim((string)($data['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    $clean = ['date'=>$date];
    foreach (['location','shift_label','discipline','hygiene','safety','health','incidents','assessment','handover'] as $key) {
        $clean[$key] = trim((string)($data[$key] ?? ''));
    }
    $clean['updated_by'] = trim((string)($data['updated_by'] ?? ''));
    $clean['updated_at'] = noitru_now();
    $found = false;
    foreach ($rows as $index=>$row) {
        if (($row['date'] ?? '') !== $date) continue;
        $clean['created_at'] = $row['created_at'] ?? noitru_now();
        $rows[$index] = array_merge($row, $clean);
        $found = true;
        break;
    }
    if (!$found) {
        $clean['id'] = noitru_uid('bbtruc');
        $clean['created_at'] = noitru_now();
        $rows[] = $clean;
    }
    usort($rows, fn($a,$b) => strcmp((string)($b['date']??''), (string)($a['date']??'')));
    return save_json(NOITRU_DUTY_REPORTS, $rows);
}
function noitru_duty_for_month($month) {
    return array_values(array_filter(noitru_duty_all(), fn($row) =>
        str_starts_with((string)($row['date'] ?? ''), $month . '-')
    ));
}
function noitru_duty_delete_month($month) {
    $rows = noitru_duty_all();
    $kept = array_values(array_filter($rows, fn($row) =>
        !str_starts_with((string)($row['date'] ?? ''), $month . '-')
    ));
    $deleted = count($rows) - count($kept);
    if ($deleted > 0) save_json(NOITRU_DUTY, $kept);
    return $deleted;
}
function noitru_duty_settings() {
    noitru_ensure_dir();
    return array_merge([
        'people_per_day' => 3,
        'max_per_month' => 4,
        'start_time' => '06:00',
        'end_time' => '06:00',
    ], load_json(NOITRU_DUTY_SETTINGS, []));
}
function noitru_duty_settings_save(array $data) {
    $settings = noitru_duty_settings();
    $settings['people_per_day'] = max(1, min(20, (int)($data['people_per_day'] ?? 3)));
    $settings['max_per_month'] = max(1, min(31, (int)($data['max_per_month'] ?? 4)));
    foreach (['start_time','end_time'] as $key) {
        $value = trim((string)($data[$key] ?? $settings[$key]));
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) $settings[$key] = $value;
    }
    save_json(NOITRU_DUTY_SETTINGS, $settings);
    return $settings;
}
function noitru_duty_managers_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_DUTY_MANAGERS, []);
}
function noitru_duty_manager_for_date($date) {
    foreach (noitru_duty_managers_all() as $row) {
        if (($row['date'] ?? '') === $date) return $row;
    }
    return null;
}
function noitru_duty_manager_save($date, array $teacherIds, array $teacherMap, $note = '') {
    $rows = noitru_duty_managers_all();
    $teacherIds = array_values(array_unique(array_filter($teacherIds, fn($id) => isset($teacherMap[$id]))));
    $record = [
        'date' => $date,
        'teacher_ids' => $teacherIds,
        'teacher_names' => array_values(array_map(fn($id) => $teacherMap[$id], $teacherIds)),
        'note' => trim((string)$note),
        'updated_at' => noitru_now(),
    ];
    $found = false;
    foreach ($rows as $i=>$row) {
        if (($row['date'] ?? '') !== $date) continue;
        if ($teacherIds || $record['note'] !== '') $rows[$i] = $record;
        else unset($rows[$i]);
        $found = true;
        break;
    }
    if (!$found && ($teacherIds || $record['note'] !== '')) $rows[] = $record;
    save_json(NOITRU_DUTY_MANAGERS, array_values($rows));
}
function noitru_duty_groups_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_DUTY_GROUPS, []);
}
function noitru_duty_group_save($name, array $teacherIds, array $teacherMap) {
    $name = trim((string)$name);
    if ($name === '') throw new InvalidArgumentException('Tên nhóm trực không được để trống.');
    $teacherIds = array_values(array_unique(array_filter($teacherIds, fn($id) => isset($teacherMap[$id]))));
    $rows = noitru_duty_groups_all();
    $rows[] = [
        'id' => noitru_uid('dg'),
        'name' => $name,
        'teacher_ids' => $teacherIds,
        'teacher_names' => array_values(array_map(fn($id) => $teacherMap[$id], $teacherIds)),
        'created_at' => noitru_now(),
    ];
    save_json(NOITRU_DUTY_GROUPS, $rows);
}
function noitru_duty_group_delete($id) {
    save_json(NOITRU_DUTY_GROUPS, array_values(array_filter(noitru_duty_groups_all(), fn($row) => ($row['id'] ?? '') !== $id)));
}
function noitru_duty_roster_all(array $teacherMap = []) {
    noitru_ensure_dir();
    if (!is_file(NOITRU_DUTY_ROSTER)) {
        $rows = [];
        foreach ($teacherMap as $teacherId=>$teacherName) {
            $rows[] = ['teacher_id'=>$teacherId, 'teacher_name'=>$teacherName, 'max_per_month'=>0, 'note'=>''];
        }
        return $rows;
    }
    $rows = load_json(NOITRU_DUTY_ROSTER, []);
    $out = [];
    foreach ($rows as $row) {
        $teacherId = (string)($row['teacher_id'] ?? '');
        if ($teacherId === '' || ($teacherMap && !isset($teacherMap[$teacherId]))) continue;
        $row['teacher_name'] = $teacherMap[$teacherId] ?? ($row['teacher_name'] ?? '');
        $row['max_per_month'] = max(0, (int)($row['max_per_month'] ?? 0));
        $row['note'] = trim((string)($row['note'] ?? ''));
        $out[] = $row;
    }
    usort($out, fn($a,$b) => strcasecmp((string)($a['teacher_name'] ?? ''), (string)($b['teacher_name'] ?? '')));
    return $out;
}
function noitru_duty_roster_save($teacherId, array $teacherMap, $maxPerMonth = 0, $note = '') {
    $teacherId = trim((string)$teacherId);
    if (!isset($teacherMap[$teacherId])) throw new InvalidArgumentException('Giáo viên không hợp lệ.');
    $rows = noitru_duty_roster_all($teacherMap);
    $record = [
        'teacher_id'=>$teacherId,
        'teacher_name'=>$teacherMap[$teacherId],
        'max_per_month'=>max(0, min(31, (int)$maxPerMonth)),
        'note'=>trim((string)$note),
        'updated_at'=>noitru_now(),
    ];
    $found = false;
    foreach ($rows as $i=>$row) {
        if (($row['teacher_id'] ?? '') !== $teacherId) continue;
        $rows[$i] = array_merge($row, $record);
        $found = true;
        break;
    }
    if (!$found) $rows[] = $record;
    save_json(NOITRU_DUTY_ROSTER, array_values($rows));
}
function noitru_duty_roster_delete($teacherId, array $teacherMap = []) {
    $rows = noitru_duty_roster_all($teacherMap);
    save_json(NOITRU_DUTY_ROSTER, array_values(array_filter($rows, fn($row) => ($row['teacher_id'] ?? '') !== $teacherId)));
}

/* —— Health —— */
function noitru_health_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_HEALTH, []);
}
function noitru_health_save(array $data) {
    $rows = noitru_health_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$r) {
        if (($r['id'] ?? '') === $id) {
            $r = array_merge($r, $data);
            $r['updated_at'] = noitru_now();
            $found = true;
            break;
        }
    }
    unset($r);
    if (!$found) {
        $id = noitru_uid('he');
        $data['id'] = $id;
        $data['created_at'] = noitru_now();
        $rows[] = $data;
    }
    save_json(NOITRU_HEALTH, $rows);
    return $id;
}
function noitru_health_delete($id) {
    save_json(NOITRU_HEALTH, array_values(array_filter(noitru_health_all(), fn($r) => ($r['id'] ?? '') !== $id)));
}

function noitru_medicines_all() {
    noitru_ensure_dir();
    $rows = load_json(NOITRU_MEDICINES, []);
    return array_values(array_filter($rows, fn($row) => !isset($row['active']) || !empty($row['active'])));
}
function noitru_medicines_save_all(array $rows) {
    noitru_ensure_dir();
    save_json(NOITRU_MEDICINES, array_values($rows));
}
function noitru_medicine_find($id) {
    foreach (noitru_medicines_all() as $row) if (($row['id'] ?? '') === $id) return $row;
    return null;
}
function noitru_medicine_save(array $data) {
    $rows = load_json(NOITRU_MEDICINES, []);
    $id = trim($data['id'] ?? '');
    $found = false;
    foreach ($rows as &$row) {
        if (($row['id'] ?? '') !== $id || $id === '') continue;
        $row = array_merge($row, $data, ['updated_at'=>noitru_now()]);
        $found = true;
        break;
    }
    unset($row);
    if (!$found) {
        $data['id'] = $id ?: noitru_uid('med');
        $data['active'] = true;
        $data['created_at'] = noitru_now();
        $rows[] = $data;
    }
    noitru_medicines_save_all($rows);
    return $data['id'] ?? $id;
}
function noitru_medicine_adjust($id, $delta, $type, $note = '', $by = '') {
    $rows = load_json(NOITRU_MEDICINES, []);
    $changed = null;
    foreach ($rows as &$row) {
        if (($row['id'] ?? '') !== $id || empty($row['active'])) continue;
        $before = max(0, (int)($row['quantity'] ?? 0));
        $after = max(0, $before + (int)$delta);
        if ((int)$delta < 0 && $after !== $before + (int)$delta) throw new RuntimeException('Số lượng thuốc trong kho không đủ.');
        $row['quantity'] = $after;
        $row['updated_at'] = noitru_now();
        $changed = ['before'=>$before, 'after'=>$after, 'medicine'=>$row];
        break;
    }
    unset($row);
    if (!$changed) throw new RuntimeException('Không tìm thấy thuốc trong kho.');
    noitru_medicines_save_all($rows);
    $tx = load_json(NOITRU_MEDICINE_TX, []);
    $tx[] = ['id'=>noitru_uid('mtx'),'medicine_id'=>$id,'type'=>$type,'quantity'=>abs((int)$delta),'before'=>$changed['before'],'after'=>$changed['after'],'note'=>$note,'by'=>$by,'created_at'=>noitru_now()];
    save_json(NOITRU_MEDICINE_TX, $tx);
    return $changed['medicine'];
}
function noitru_medicine_delete($id) {
    $rows = load_json(NOITRU_MEDICINES, []);
    foreach ($rows as &$row) if (($row['id'] ?? '') === $id) { $row['active'] = false; $row['updated_at'] = noitru_now(); }
    unset($row);
    noitru_medicines_save_all($rows);
}
function noitru_medicine_transactions($medicineId = '') {
    noitru_ensure_dir();
    $rows = load_json(NOITRU_MEDICINE_TX, []);
    if ($medicineId !== '') $rows = array_values(array_filter($rows, fn($row) => ($row['medicine_id'] ?? '') === $medicineId));
    usort($rows, fn($a,$b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $rows;
}

/* —— Menus —— */
function noitru_menus_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_MENUS, []);
}
function noitru_menu_for_week($weekStart) {
    foreach (noitru_menus_all() as $m) {
        if (($m['week_start'] ?? '') === $weekStart) return $m;
    }
    return null;
}
function noitru_menu_save(array $data) {
    $rows = noitru_menus_all();
    $ws = $data['week_start'] ?? '';
    $found = false;
    foreach ($rows as &$m) {
        if (($m['week_start'] ?? '') === $ws) {
            $m = array_merge($m, $data);
            $m['updated_at'] = noitru_now();
            $found = true;
            break;
        }
    }
    unset($m);
    if (!$found) {
        $data['id'] = noitru_uid('mn');
        $data['created_at'] = noitru_now();
        $rows[] = $data;
    }
    save_json(NOITRU_MENUS, $rows);
}

function noitru_menu_config() {
    noitru_ensure_dir();
    $data = load_json(NOITRU_MENU_CONFIG, ['dishes'=>[], 'template'=>[]]);
    $data['dishes'] = array_values($data['dishes'] ?? []);
    $data['template'] = $data['template'] ?? [];
    return $data;
}
function noitru_menu_config_save(array $data) {
    noitru_ensure_dir();
    $data['dishes'] = array_values($data['dishes'] ?? []);
    $data['template'] = $data['template'] ?? [];
    save_json(NOITRU_MENU_CONFIG, $data);
}
function noitru_menu_dish_add($name, $category) {
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    $allowed = ['breakfast','meat','vegetable'];
    if ($name === '' || !in_array($category, $allowed, true)) return false;
    $data = noitru_menu_config();
    foreach ($data['dishes'] as $dish) if (mb_strtolower($dish['name'] ?? '') === mb_strtolower($name)) return false;
    $data['dishes'][] = ['id'=>noitru_uid('dish'), 'name'=>$name, 'category'=>$category, 'created_at'=>noitru_now()];
    noitru_menu_config_save($data);
    return true;
}
function noitru_menu_dish_delete($id) {
    $data = noitru_menu_config();
    $data['dishes'] = array_values(array_filter($data['dishes'], fn($dish)=>($dish['id']??'')!==$id));
    noitru_menu_config_save($data);
}
function noitru_menu_template_save(array $template) {
    $data = noitru_menu_config();
    $data['template'] = $template;
    noitru_menu_config_save($data);
}

function noitru_stats_full($from, $to) {
    $mealSum = ['sang' => 0, 'trua' => 0, 'toi' => 0, 'days' => []];
    foreach (noitru_meals_all() as $m) {
        $d = $m['date'] ?? '';
        if ($d < $from || $d > $to) continue;
        if (!isset($mealSum['days'][$d])) $mealSum['days'][$d] = ['sang' => 0, 'trua' => 0, 'toi' => 0];
        foreach (['sang', 'trua', 'toi'] as $b) {
            if (in_array($m[$b] ?? '', ['yes', 'sick', 'guest'], true)) {
                $mealSum[$b]++;
                $mealSum['days'][$d][$b]++;
            }
        }
    }
    ksort($mealSum['days']);
    $attSum = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
    $attendanceReports=noitru_att_reports_all();$attendanceReportKeys=[];
    foreach ($attendanceReports as $a) {
        $d = $a['date'] ?? '';
        if ($d < $from || $d > $to) continue;
        $attendanceReportKeys[$d.'|'.($a['shift']??'')]=true;
        foreach($attSum as $st=>$_)$attSum[$st]+=(int)($a[$st]??0);
    }
    foreach(noitru_att_all() as $a){$d=$a['date']??'';if($d<$from||$d>$to||isset($attendanceReportKeys[$d.'|'.($a['shift']??'')]))continue;$st=$a['status']??'present';if(isset($attSum[$st]))$attSum[$st]++;}
    $exitSum = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach (noitru_exits_all() as $e) {
        $d = substr($e['from_date'] ?? '', 0, 10);
        if ($d && ($d < $from || $d > $to)) continue;
        $st = $e['status'] ?? 'pending';
        if (isset($exitSum[$st])) $exitSum[$st]++;
    }
    $healthN = 0;
    $healthTypes = ['medicine'=>0,'first_aid'=>0,'hospital'=>0,'family_pickup'=>0,'other'=>0];
    foreach (noitru_health_all() as $h) {
        $d = substr($h['date'] ?? '', 0, 10);
        if ($d >= $from && $d <= $to) {
            $healthN++;
            $type = $h['type'] ?? 'other';
            if (!isset($healthTypes[$type])) $type = 'other';
            $healthTypes[$type]++;
        }
    }
    $dutyN = 0;
    foreach (noitru_duty_all() as $duty) {
        $date = substr($duty['date'] ?? '', 0, 10);
        if ($date >= $from && $date <= $to) $dutyN++;
    }
    $riceUsage = noitru_rice_usage_summary($from, $to, noitru_rice_data());
    $medicineIssued = 0;
    foreach (noitru_medicine_transactions() as $transaction) {
        $date = substr($transaction['created_at'] ?? '', 0, 10);
        if ($date >= $from && $date <= $to && ($transaction['type'] ?? '') === 'issue') $medicineIssued += (int)($transaction['quantity'] ?? 0);
    }
    $daily = [];
    for ($cursor=$from; $cursor<=$to; $cursor=date('Y-m-d', strtotime($cursor.' +1 day'))) {
        $daily[$cursor] = ['meals'=>['sang'=>0,'trua'=>0,'toi'=>0], 'attendance'=>['present'=>0,'absent'=>0,'late'=>0,'excused'=>0], 'exits'=>0, 'health'=>0, 'duty'=>0, 'rice_kg'=>0.0];
    }
    foreach ($mealSum['days'] as $date=>$counts) if (isset($daily[$date])) $daily[$date]['meals'] = array_merge($daily[$date]['meals'], $counts);
    foreach ($attendanceReports as $row) { $date=$row['date']??'';if(!isset($daily[$date]))continue;foreach($daily[$date]['attendance'] as $status=>$_)$daily[$date]['attendance'][$status]+=(int)($row[$status]??0); }
    foreach(noitru_att_all() as $row){$date=$row['date']??'';if(isset($attendanceReportKeys[$date.'|'.($row['shift']??'')]))continue;$status=$row['status']??'present';if(isset($daily[$date]['attendance'][$status]))$daily[$date]['attendance'][$status]++;}
    foreach (noitru_exits_all() as $row) { $date=substr($row['from_date']??'',0,10); if(isset($daily[$date])) $daily[$date]['exits']++; }
    foreach (noitru_health_all() as $row) { $date=substr($row['date']??'',0,10); if(isset($daily[$date])) $daily[$date]['health']++; }
    foreach (noitru_duty_all() as $row) { $date=substr($row['date']??'',0,10); if(isset($daily[$date])) $daily[$date]['duty']++; }
    foreach ($riceUsage['days'] ?? [] as $date=>$row) if(isset($daily[$date])) $daily[$date]['rice_kg']=(float)($row['kg']??0);
    $classes = [];
    foreach (noitru_boarders_live() as $student) { $class=trim($student['class_name']??'') ?: '(Chưa lớp)'; $classes[$class]=($classes[$class]??0)+1; }
    uksort($classes, 'csdl_compare_class_names');
    return [
        'from' => $from, 'to' => $to,
        'boarders' => count(noitru_boarders_live()),
        'meals' => $mealSum,
        'attendance' => $attSum,
        'exits' => $exitSum,
        'health' => $healthN,
        'health_types'=>$healthTypes,
        'duty'=>$dutyN,
        'rice'=>$riceUsage,
        'medicine_issued'=>$medicineIssued,
        'daily'=>$daily,
        'classes'=>$classes,
    ];
}
