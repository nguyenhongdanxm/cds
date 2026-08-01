<?php
require_once __DIR__ . '/csdl_store.php';

function cds_dashboard_gender_key($value): string {
    $value = trim((string)$value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    if (in_array($value, ['nam','male','m','1'], true)) return 'male';
    if (in_array($value, ['nữ','nu','female','f','2'], true)) return 'female';
    return 'other';
}

function cds_dashboard_gender_stats(array $rows): array {
    $out = ['total'=>count($rows),'male'=>0,'female'=>0,'other'=>0];
    foreach ($rows as $row) $out[cds_dashboard_gender_key($row['gender'] ?? '')]++;
    return $out;
}

/* Âm lịch Việt Nam (múi giờ UTC+7), tính trực tiếp để không phụ thuộc dịch vụ ngoài. */
function cds_dashboard_jd_from_date($day, $month, $year): int {
    $a = intdiv(14 - $month, 12);
    $y = $year + 4800 - $a;
    $m = $month + 12 * $a - 3;
    $jd = $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    if ($jd < 2299161) $jd = $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - 32083;
    return $jd;
}
function cds_dashboard_new_moon($k): float {
    $t = $k / 1236.85; $t2 = $t * $t; $t3 = $t2 * $t; $dr = M_PI / 180;
    $jd = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $t2 - 0.000000155 * $t3;
    $jd += 0.00033 * sin((166.56 + 132.87 * $t - 0.009173 * $t2) * $dr);
    $m = 359.2242 + 29.10535608 * $k - 0.0000333 * $t2 - 0.00000347 * $t3;
    $mpr = 306.0253 + 385.81691806 * $k + 0.0107306 * $t2 + 0.00001236 * $t3;
    $f = 21.2964 + 390.67050646 * $k - 0.0016528 * $t2 - 0.00000239 * $t3;
    $c1 = (0.1734 - 0.000393 * $t) * sin($m * $dr) + 0.0021 * sin(2 * $m * $dr);
    $c1 -= 0.4068 * sin($mpr * $dr) + 0.0161 * sin(2 * $mpr * $dr);
    $c1 -= 0.0004 * sin(3 * $mpr * $dr);
    $c1 += 0.0104 * sin(2 * $f * $dr) - 0.0051 * sin(($m + $mpr) * $dr);
    $c1 -= 0.0074 * sin(($m - $mpr) * $dr) + 0.0004 * sin((2 * $f + $m) * $dr);
    $c1 -= 0.0004 * sin((2 * $f - $m) * $dr) - 0.0006 * sin((2 * $f + $mpr) * $dr);
    $c1 += 0.0010 * sin((2 * $f - $mpr) * $dr) + 0.0005 * sin((2 * $mpr + $m) * $dr);
    $delta = $t < -11 ? 0.001 + 0.000839 * $t + 0.0002261 * $t2 - 0.00000845 * $t3 - 0.000000081 * $t * $t3 : -0.000278 + 0.000265 * $t + 0.000262 * $t2;
    return $jd + $c1 - $delta;
}
function cds_dashboard_new_moon_day($k, $timezone = 7): int { return (int)floor(cds_dashboard_new_moon($k) + 0.5 + $timezone / 24); }
function cds_dashboard_sun_longitude($jdn, $timezone = 7): int {
    $t = ($jdn - 2451545.5 - $timezone / 24) / 36525; $t2 = $t * $t; $dr = M_PI / 180;
    $m = 357.52910 + 35999.05030 * $t - 0.0001559 * $t2 - 0.00000048 * $t * $t2;
    $l0 = 280.46645 + 36000.76983 * $t + 0.0003032 * $t2;
    $dl = (1.914600 - 0.004817 * $t - 0.000014 * $t2) * sin($dr * $m);
    $dl += (0.019993 - 0.000101 * $t) * sin(2 * $dr * $m) + 0.000290 * sin(3 * $dr * $m);
    $l = fmod(($l0 + $dl) * $dr, 2 * M_PI); if ($l < 0) $l += 2 * M_PI;
    return (int)floor($l / M_PI * 6);
}
function cds_dashboard_lunar_month11($year, $timezone = 7): int {
    $off = cds_dashboard_jd_from_date(31, 12, $year) - 2415021;
    $k = (int)floor($off / 29.530588853); $nm = cds_dashboard_new_moon_day($k, $timezone);
    if (cds_dashboard_sun_longitude($nm, $timezone) >= 9) $nm = cds_dashboard_new_moon_day($k - 1, $timezone);
    return $nm;
}
function cds_dashboard_leap_month_offset($a11, $timezone = 7): int {
    $k = (int)floor(0.5 + ($a11 - 2415021.076998695) / 29.530588853);
    $last = 0;
    $i = 1;
    $arc = cds_dashboard_sun_longitude(cds_dashboard_new_moon_day($k + $i, $timezone), $timezone);
    do {
        $last = $arc;
        $i++;
        $arc = cds_dashboard_sun_longitude(cds_dashboard_new_moon_day($k + $i, $timezone), $timezone);
    } while ($arc !== $last && $i < 14);
    return $i - 1;
}
function cds_dashboard_solar_to_lunar($day, $month, $year, $timezone = 7): array {
    $dayNumber = cds_dashboard_jd_from_date($day, $month, $year);
    $k = (int)floor(($dayNumber - 2415021.076998695) / 29.530588853);
    $monthStart = cds_dashboard_new_moon_day($k + 1, $timezone);
    if ($monthStart > $dayNumber) $monthStart = cds_dashboard_new_moon_day($k, $timezone);
    $a11 = cds_dashboard_lunar_month11($year, $timezone); $b11 = $a11;
    if ($a11 >= $monthStart) { $lunarYear = $year; $a11 = cds_dashboard_lunar_month11($year - 1, $timezone); }
    else { $lunarYear = $year + 1; $b11 = cds_dashboard_lunar_month11($year + 1, $timezone); }
    $lunarDay = $dayNumber - $monthStart + 1; $diff = (int)floor(($monthStart - $a11) / 29); $lunarLeap = false; $lunarMonth = $diff + 11;
    if ($b11 - $a11 > 365) { $leapDiff = cds_dashboard_leap_month_offset($a11, $timezone); if ($diff >= $leapDiff) { $lunarMonth = $diff + 10; if ($diff === $leapDiff) $lunarLeap = true; } }
    if ($lunarMonth > 12) $lunarMonth -= 12; if ($lunarMonth >= 11 && $diff < 4) $lunarYear--;
    return ['day'=>$lunarDay,'month'=>$lunarMonth,'year'=>$lunarYear,'leap'=>$lunarLeap];
}

function cds_dashboard_preferences_file(): string { return DATA_PATH . '/dashboard_preferences.json'; }
function cds_dashboard_preferences(array $user): array {
    $all = load_json(cds_dashboard_preferences_file(), []); $key = (string)($user['id'] ?? $user['username'] ?? '');
    return array_merge(['muted_birthdays'=>[]], is_array($all[$key] ?? null) ? $all[$key] : []);
}
function cds_dashboard_mute_birthday(array $user, string $teacherId): void {
    $all = load_json(cds_dashboard_preferences_file(), []); $key = (string)($user['id'] ?? $user['username'] ?? '');
    $pref = array_merge(['muted_birthdays'=>[]], is_array($all[$key] ?? null) ? $all[$key] : []);
    $pref['muted_birthdays'] = array_values(array_unique(array_merge($pref['muted_birthdays'], [$teacherId])));
    $all[$key] = $pref; save_json(cds_dashboard_preferences_file(), $all);
}
function cds_dashboard_birthday(array $teachers, array $muted = []): ?array {
    $today = date('m-d'); $todayYear = (int)date('Y'); $found = [];
    foreach ($teachers as $teacher) {
        $id = (string)($teacher['id'] ?? ''); $dob = (string)($teacher['dob'] ?? '');
        if ($id === '' || in_array($id, $muted, true) || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $dob, $m)) continue;
        $md = $m[1] . '-' . $m[2]; $year = $todayYear; $ts = strtotime($year . '-' . $md);
        if ($ts < strtotime(date('Y-m-d'))) $ts = strtotime(($year + 1) . '-' . $md);
        $days = (int)floor(($ts - strtotime(date('Y-m-d'))) / 86400);
        if ($days <= 7) $found[] = ['id'=>$id,'name'=>$teacher['name'] ?? '','dob'=>$dob,'days'=>$days,'date'=>date('Y-m-d',$ts),'today'=>$md===$today];
    }
    usort($found, fn($a,$b)=>$a['days']<=>$b['days'] ?: strcasecmp($a['name'],$b['name']));
    return $found[0] ?? null;
}

function cds_dashboard_quote(): string {
    $quotes = [
        'Tri thức mở ra cánh cửa, sự tử tế dẫn ta đi đúng đường.',
        'Mỗi ngày học một điều mới là mỗi ngày tiến gần hơn tới phiên bản tốt đẹp của mình.',
        'Giáo dục không chỉ truyền đạt kiến thức, mà còn thắp lên khát vọng.',
        'Điều ta biết là hữu hạn; điều ta có thể học là vô hạn.',
        'Thành công bền vững bắt đầu từ những việc nhỏ được làm đều đặn.',
        'Một người thầy tốt gieo hạt giống có thể nở hoa suốt đời.',
        'Học để hiểu, hiểu để làm, làm để cùng nhau tiến bộ.',
    ];
    return $quotes[((int)date('z')) % count($quotes)];
}

function cds_dashboard_feed_data(): array {
    $out = ['notices'=>[],'tasks'=>[],'observations'=>[]];
    $sources = [DATA_PATH . '/dashboard_feed.json'];
    if (defined('PCCM_DATA_PATH') && PCCM_DATA_PATH !== '') {
        foreach (['dashboard_feed.json','dashboard.json','notifications.json','tasks.json','observations.json','lesson_observations.json'] as $file) $sources[] = rtrim(PCCM_DATA_PATH,'/') . '/' . $file;
    }
    foreach (array_unique($sources) as $file) {
        if (!is_file($file)) continue; $data = load_json($file, []); if (!is_array($data)) continue;
        foreach (array_keys($out) as $key) if (isset($data[$key]) && is_array($data[$key])) $out[$key] = array_merge($out[$key], array_values($data[$key]));
        $base = basename($file);
        if ($base === 'notifications.json' && array_is_list($data)) $out['notices'] = array_merge($out['notices'], $data);
        if ($base === 'tasks.json' && array_is_list($data)) $out['tasks'] = array_merge($out['tasks'], $data);
        if (in_array($base, ['observations.json','lesson_observations.json'], true) && array_is_list($data)) $out['observations'] = array_merge($out['observations'], $data);
    }
    return $out;
}
function cds_dashboard_feed_date(array $row): string {
    foreach (['due_date','deadline','date','start_date','created_at','updated_at'] as $key) if (!empty($row[$key])) return substr((string)$row[$key],0,10);
    return '';
}
function cds_dashboard_notice_tasks(array $user, int $limit = 5): array {
    $feed = cds_dashboard_feed_data(); $rows = [];
    foreach ($feed['notices'] as $row) { $row['kind']='notice'; $rows[]=$row; }
    foreach ($feed['tasks'] as $row) {
        $assignee = (string)($row['assignee_id'] ?? $row['assigned_to'] ?? '');
        if ($assignee !== '' && !in_array($assignee, [(string)($user['id']??''),(string)($user['username']??''),(string)($user['name']??'')], true) && ($user['role']??'')!=='admin') continue;
        $status = (string)($row['status'] ?? '');
        $status = function_exists('mb_strtolower') ? mb_strtolower($status, 'UTF-8') : strtolower($status);
        if (in_array($status, ['done','completed','hoàn thành'], true)) continue;
        $row['kind']='task'; $rows[]=$row;
    }
    $today = date('Y-m-d');
    usort($rows, function($a,$b) use ($today) {
        $da=cds_dashboard_feed_date($a);$db=cds_dashboard_feed_date($b);
        $aUpcoming=$da!==''&&$da>=$today;$bUpcoming=$db!==''&&$db>=$today;
        if($aUpcoming!==$bUpcoming)return $aUpcoming?-1:1;
        return $aUpcoming?strcmp($da,$db):strcmp($db,$da);
    });
    return array_slice($rows,0,$limit);
}
function cds_dashboard_observations(): array {
    $today=date('Y-m-d');$rows=[];
    foreach(cds_dashboard_feed_data()['observations'] as $row){$date=cds_dashboard_feed_date($row);if($date!==''&&$date>=$today){$row['dashboard_date']=$date;$rows[]=$row;}}
    usort($rows,fn($a,$b)=>strcmp($a['dashboard_date'],$b['dashboard_date']) ?: strcmp((string)($a['time']??''),(string)($b['time']??'')));
    return array_slice($rows,0,4);
}

function cds_dashboard_scope_data(array $user): array {
    $classes=csdl_classes_all();$students=csdl_students_all();$teachers=csdl_teachers_all();$allowed=allowed_classes();$classIds=[];
    foreach($classes as $class)if((!isset($class['active'])||!empty($class['active']))&&($allowed===null||in_array((string)($class['name']??''),$allowed,true)))$classIds[]=(string)($class['id']??'');
    $scopeStudents=array_values(array_filter($students,fn($s)=>!empty($s['active'])&&in_array((string)($s['class_id']??''),$classIds,true)));
    $scopeClasses=array_values(array_filter($classes,fn($c)=>(!isset($c['active'])||!empty($c['active']))&&in_array((string)($c['id']??''),$classIds,true)));
    $activeTeachers=array_values(array_filter($teachers,fn($t)=>!isset($t['active'])||!empty($t['active'])));
    $noitru=['boarders'=>0,'present'=>0,'absent'=>0,'attendance_date'=>'','attendance_shift'=>'','pending_exits'=>0,'duty'=>null];
    if(can_module('noitru','view')){
        require_once __DIR__.'/noitru_store.php';$boarders=array_values(array_filter(noitru_boarders_live(),fn($s)=>$allowed===null||in_array((string)($s['class_name']??''),$allowed,true)));$boarderIds=array_fill_keys(array_column($boarders,'id'),true);$today=date('Y-m-d');
        $attRows=array_values(array_filter(noitru_att_all(),fn($r)=>isset($boarderIds[$r['student_id']??''])));$latestKey='';$latestDate='';$latestShift='';
        $shiftOrder=['morning'=>1,'sang'=>1,'noon'=>2,'trua'=>2,'afternoon'=>3,'evening'=>4,'toi'=>4,'night'=>5];
        foreach($attRows as $r){$d=(string)($r['date']??'');if($d===''||$d>$today)continue;$shift=(string)($r['shift']??'');$updated=(string)($r['updated_at']??$r['created_at']??'');$key=$d.'|'.($updated!==''?$updated:sprintf('%02d',$shiftOrder[$shift]??0));if($key>$latestKey){$latestKey=$key;$latestDate=$d;$latestShift=$shift;}}
        $attendance=['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];foreach($attRows as $r)if(($r['date']??'')===$latestDate&&($r['shift']??'')===$latestShift){$st=$r['status']??'present';if(isset($attendance[$st]))$attendance[$st]++;}
        $settings=noitru_duty_settings();$start=$settings['start_time']??'06:00';$end=$settings['end_time']??'06:00';$todayStart=strtotime($today.' '.$start);$dutyDate=time()<$todayStart?date('Y-m-d',strtotime($today.' -1 day')):$today;$dutyEnd=strtotime($dutyDate.' '.$end);if($dutyEnd<=strtotime($dutyDate.' '.$start))$dutyEnd=strtotime('+1 day',$dutyEnd);
        $dutyRows=array_values(array_filter(noitru_duty_all(),fn($r)=>($r['date']??'')===$dutyDate));$manager=noitru_duty_manager_for_date($dutyDate)??[];
        $noitru=['boarders'=>count($boarders),'present'=>$attendance['present']+$attendance['late'],'absent'=>$attendance['absent']+$attendance['excused'],'attendance_date'=>$latestDate,'attendance_shift'=>$latestShift,'pending_exits'=>count(array_filter(noitru_exits_all(),fn($r)=>($r['status']??'')==='pending'&&isset($boarderIds[$r['student_id']??'']))),'duty'=>['date'=>$dutyDate,'start'=>$start,'end'=>$end,'people'=>array_values(array_filter(array_column($dutyRows,'teacher_name'))),'managers'=>array_values(array_filter($manager['teacher_names']??[])),'note'=>$manager['note']??'','remaining'=>max(0,$dutyEnd-time())]];
    }
    $leave=[];$td=load_json(DATA_PATH.'/thidua.json',['records'=>[]]);$today=date('Y-m-d');$until=date('Y-m-d',strtotime('+21 days'));
    foreach($td['records']??[] as $row){if(($row['type']??'')!=='teacher_attendance')continue;$from=$row['from_date']??$row['date']??'';$to=$row['to_date']??$from;if($to<$today||$from>$until)continue;$leave[]=['name'=>$row['person_name']??'','from'=>$from,'to'=>$to,'reason'=>trim(($row['reason']??'').(!empty($row['reason_detail'])?' · '.$row['reason_detail']:'')),'permission'=>$row['permission']??''];}
    usort($leave,fn($a,$b)=>strcmp($a['from'],$b['from']) ?: strcmp($a['name'],$b['name']));
    return ['csdl'=>['teachers'=>cds_dashboard_gender_stats($activeTeachers),'students'=>cds_dashboard_gender_stats($scopeStudents),'classes'=>count($scopeClasses)],'noitru'=>$noitru,'leave'=>array_slice($leave,0,6)];
}

function cds_dashboard_quick_actions(array $user): array {
    $items=[];$add=function($permission,$url,$label,$icon,$color)use(&$items){if(can_perm($permission))$items[]=compact('url','label','icon','color');};
    $add('nt.diemdanh','noitru.php?tab=attendance','Điểm danh','bi-person-check','#db2777');
    $add('nt.baoan','noitru.php?tab=meals','Báo ăn','bi-egg-fried','#ea580c');
    $add('td.capnhat','thidua.php?section=teacher_attendance','Chấm công','bi-calendar-check','#ca8a04');
    $add('td.xem','thidua.php?section=student_score','Bảng điểm','bi-table','#2563eb');
    return $items;
}
