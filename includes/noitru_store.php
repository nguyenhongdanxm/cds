<?php
/** Quản lý nội trú – dữ liệu vận hành (HS từ CSDL). */
require_once __DIR__ . '/csdl_store.php';

define('NOITRU_DIR', DATA_PATH . '/noitru');
define('NOITRU_META', NOITRU_DIR . '/meta.json');
define('NOITRU_BOARDERS', NOITRU_DIR . '/boarders_cache.json');
define('NOITRU_EXITS', NOITRU_DIR . '/exits.json');
define('NOITRU_MEALS', NOITRU_DIR . '/meals_daily.json');
define('NOITRU_MEAL_REPORTS', NOITRU_DIR . '/meal_reports.json');
define('NOITRU_ATT', NOITRU_DIR . '/attendance.json');
define('NOITRU_DUTY', NOITRU_DIR . '/duty.json');
define('NOITRU_HEALTH', NOITRU_DIR . '/health.json');
define('NOITRU_MENUS', NOITRU_DIR . '/menus.json');
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

function noitru_boarders_live() {
    $out = [];
    foreach (csdl_students_all() as $s) {
        if (empty($s['active']) || empty($s['boarder'])) continue;
        $out[] = [
            'id' => $s['id'] ?? '',
            'code' => $s['code'] ?? '',
            'name' => $s['name'] ?? '',
            'cccd' => $s['cccd'] ?? '',
            'class_id' => $s['class_id'] ?? '',
            'class_name' => noitru_class_name($s['class_id'] ?? ''),
            'gender' => $s['gender'] ?? '',
            'dob' => $s['dob'] ?? '',
            'ethnicity' => $s['ethnicity'] ?? '',
            'hometown' => $s['hometown'] ?? '',
            'address' => $s['address'] ?? '',
            'phone' => $s['phone'] ?? '',
            'parent_name' => $s['parent_name'] ?? '',
            'parent_phone' => $s['parent_phone'] ?? '',
            'room_ktx' => $s['room_ktx'] ?? '',
            'meal_group' => $s['meal_group'] ?? '',
            'note' => $s['note'] ?? '',
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['class_name'], $b['class_name']) ?: strcmp($a['name'], $b['name']));
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
    return ['ok' => true, 'count' => count($list), 'message' => 'Đã đồng bộ ' . count($list) . ' HS nội trú từ CSDL.'];
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
    ksort($byClass, SORT_NATURAL);
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
function noitru_meals_all() {
    noitru_ensure_dir();
    return load_json(NOITRU_MEALS, []);
}
function noitru_meal_reports_data() {
    noitru_ensure_dir();
    $data = load_json(NOITRU_MEAL_REPORTS, ['reports'=>[], 'states'=>[], 'settings'=>[]]);
    $data['reports'] = $data['reports'] ?? [];
    $data['states'] = $data['states'] ?? [];
    $data['settings'] = array_merge([
        'sang_lock_time'=>'20:00',
        'trua_lock_time'=>'09:00',
        'toi_lock_time'=>'15:00',
    ], $data['settings'] ?? []);
    return $data;
}
function noitru_meal_reports_save(array $data) {
    noitru_ensure_dir();
    $data['reports'] = array_values($data['reports'] ?? []);
    $data['states'] = array_values($data['states'] ?? []);
    $data['settings'] = $data['settings'] ?? [];
    save_json(NOITRU_MEAL_REPORTS, $data);
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
    $out = [];
    foreach (noitru_meals_all() as $m) {
        if (($m['date'] ?? '') === $date) $out[$m['student_id'] ?? ''] = $m;
    }
    return $out;
}
function noitru_meal_upsert(array $row) {
    $rows = noitru_meals_all();
    $date = $row['date'] ?? '';
    $sid = $row['student_id'] ?? '';
    $found = false;
    foreach ($rows as &$m) {
        if (($m['date'] ?? '') === $date && ($m['student_id'] ?? '') === $sid) {
            if (!empty($m['locked']) && empty($row['force'])) return false;
            $m = array_merge($m, $row);
            unset($m['force']);
            $m['updated_at'] = noitru_now();
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
    }
    save_json(NOITRU_MEALS, $rows);
    return true;
}
function noitru_meals_generate_day($date) {
    $n = 0;
    $exist = noitru_meals_for_date($date);
    foreach (noitru_boarders_live() as $s) {
        if (isset($exist[$s['id']]) && !empty($exist[$s['id']]['locked'])) continue;
        $away = noitru_student_away_on_date($s['id'], $date);
        $val = $away ? 'no' : 'yes';
        noitru_meal_upsert([
            'date' => $date,
            'student_id' => $s['id'],
            'sang' => $val,
            'trua' => $val,
            'toi' => $val,
            'source' => $away ? 'auto_exit' : 'auto',
            'locked' => false,
            'note' => $away ? ('Theo phiếu: ' . ($away['reason'] ?? '')) : '',
            'force' => true,
        ]);
        $n++;
    }
    return $n;
}
function noitru_meals_lock_day($date, $lock = true) {
    $rows = noitru_meals_all();
    foreach ($rows as &$m) {
        if (($m['date'] ?? '') === $date) $m['locked'] = $lock;
    }
    unset($m);
    save_json(NOITRU_MEALS, $rows);
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
    $out = ['classes'=>[], 'groups'=>[], 'days'=>[], 'total'=>['sang'=>0,'trua'=>0,'toi'=>0]];
    foreach (noitru_meals_all() as $m) {
        $date = $m['date'] ?? '';
        if ($date < $from || $date > $to) continue;
        $student = $students[$m['student_id'] ?? ''] ?? [];
        $class = trim($student['class_name'] ?? '') ?: '(Chưa lớp)';
        $group = trim($student['meal_group'] ?? '') ?: '(Chưa mâm)';
        foreach (['sang','trua','toi'] as $meal) {
            if (!in_array($m[$meal] ?? '', ['yes','sick','guest'], true)) continue;
            $out['total'][$meal]++;
            $out['days'][$date][$meal] = ($out['days'][$date][$meal] ?? 0) + 1;
            $out['classes'][$class][$meal] = ($out['classes'][$class][$meal] ?? 0) + 1;
            $out['groups'][$group][$meal] = ($out['groups'][$group][$meal] ?? 0) + 1;
        }
    }
    ksort($out['days']); ksort($out['classes'], SORT_NATURAL); ksort($out['groups'], SORT_NATURAL);
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
    return $deleted;
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
    foreach (noitru_att_all() as $a) {
        $d = $a['date'] ?? '';
        if ($d < $from || $d > $to) continue;
        $st = $a['status'] ?? 'present';
        if (isset($attSum[$st])) $attSum[$st]++;
    }
    $exitSum = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach (noitru_exits_all() as $e) {
        $d = substr($e['from_date'] ?? '', 0, 10);
        if ($d && ($d < $from || $d > $to)) continue;
        $st = $e['status'] ?? 'pending';
        if (isset($exitSum[$st])) $exitSum[$st]++;
    }
    $healthN = 0;
    foreach (noitru_health_all() as $h) {
        $d = substr($h['date'] ?? '', 0, 10);
        if ($d >= $from && $d <= $to) $healthN++;
    }
    return [
        'from' => $from, 'to' => $to,
        'boarders' => count(noitru_boarders_live()),
        'meals' => $mealSum,
        'attendance' => $attSum,
        'exits' => $exitSum,
        'health' => $healthN,
    ];
}
