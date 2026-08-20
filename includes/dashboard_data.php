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
    $c1 -= 0.4068 * sin($mpr * $dr);
    $c1 += 0.0161 * sin(2 * $mpr * $dr);
    $c1 -= 0.0004 * sin(3 * $mpr * $dr);
    $c1 += 0.0104 * sin(2 * $f * $dr) - 0.0051 * sin(($m + $mpr) * $dr);
    $c1 -= 0.0074 * sin(($m - $mpr) * $dr);
    $c1 += 0.0004 * sin((2 * $f + $m) * $dr);
    $c1 -= 0.0004 * sin((2 * $f - $m) * $dr);
    $c1 -= 0.0006 * sin((2 * $f + $mpr) * $dr);
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

function cds_dashboard_birthdays(array $teachers, array $students): array {
    $todayMd = date('m-d');
    $tomorrowMd = date('m-d', strtotime('+1 day'));
    $groups = ['today'=>[], 'tomorrow'=>[]];
    $seen = [];

    $collect = static function (array $rows, string $personType) use (&$groups, &$seen, $todayMd, $tomorrowMd): void {
        foreach ($rows as $row) {
            if (isset($row['active']) && empty($row['active'])) continue;
            $name = trim((string)($row['name'] ?? ''));
            $dob = trim((string)($row['dob'] ?? ''));
            if ($name === '' || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $dob, $match)) continue;

            $monthDay = $match[1] . '-' . $match[2];
            $group = $monthDay === $todayMd ? 'today' : ($monthDay === $tomorrowMd ? 'tomorrow' : '');
            if ($group === '') continue;

            $id = trim((string)($row['id'] ?? ''));
            $identity = $personType . '|' . ($id !== '' ? $id : $name . '|' . $dob);
            if (isset($seen[$identity])) continue;
            $seen[$identity] = true;
            $groups[$group][] = [
                'name'=>$name,
                'type'=>$personType,
                'class_id'=>trim((string)($row['class_id'] ?? '')),
                'class_name'=>trim((string)($row['class_name'] ?? $row['class'] ?? '')),
            ];
        }
    };

    $collect($teachers, 'teacher');
    $collect($students, 'student');
    foreach ($groups as &$people) {
        usort($people, static function ($left, $right): int {
            if (function_exists('csdl_compare_person_names')) return csdl_compare_person_names($left['name'], $right['name']);
            return strnatcasecmp($left['name'], $right['name']);
        });
    }
    unset($people);
    return $groups;
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

function cds_dashboard_lower($value): string {
    $value = trim((string)$value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function cds_dashboard_feed_kind($value): string {
    $value = cds_dashboard_lower($value);
    if ($value === '') return '';
    if (preg_match('/(observation|lesson.?observation|du.?gio|dự.?giờ)/u', $value)) return 'observations';
    if (preg_match('/(task|nhiem.?vu|nhiệm.?vụ|giao.?viec|giao.?việc)/u', $value)) return 'tasks';
    if (preg_match('/(notice|notification|announcement|document|van.?ban|văn.?bản|thong.?bao|thông.?báo|ke.?hoach|kế.?hoạch|plan)/u', $value)) return 'notices';
    return '';
}

function cds_dashboard_feed_title(array $row): string {
    foreach (['title','name','subject','content','tieu_de','tieude','ten_van_ban','tenvanban','noi_dung','noidung','task_name','ten_cong_viec','work_title','document_title','mo_ta','description'] as $key) {
        if (isset($row[$key]) && !is_array($row[$key]) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    }
    return '';
}

function cds_dashboard_feed_row_kind(array $row, string $hint = ''): string {
    foreach (['kind','type','category','module','section','record_type','loai','loai_noi_dung'] as $key) {
        $kind = cds_dashboard_feed_kind($row[$key] ?? '');
        if ($kind !== '') return $kind;
    }
    if ($hint !== '') return $hint;
    foreach (['deadline','due_date','due_at','assignee','assignees','assignee_id','assigned_to','assigned_users','assigned_to_ids','nguoi_thuc_hien','nguoi_phu_trach','nguoi_nhan','nguoi_duoc_giao','executor','responsible','performers','members','teacher_id'] as $key) if (!empty($row[$key])) return 'tasks';
    foreach (['publish_date','published_at','notification_date','visible_from','ngay_hieu_luc','ngay_ban_hanh'] as $key) if (!empty($row[$key])) return 'notices';
    return '';
}

function cds_dashboard_feed_collect($node, string $hint, array &$out, string $module = '', string $defaultUrl = '', int $depth = 0): void {
    if (!is_array($node) || $depth > 4) return;
    $isList = function_exists('array_is_list') ? array_is_list($node) : array_keys($node) === range(0, count($node) - 1);
    if ($isList) {
        foreach ($node as $row) {
            if (!is_array($row)) continue;
            $title = cds_dashboard_feed_title($row);
            $kind = cds_dashboard_feed_row_kind($row, $hint);
            if ($title !== '' && $kind !== '') {
                $row['title'] = $title;
                $row['_dashboard_module'] = $module;
                if (empty($row['url']) && empty($row['link']) && $defaultUrl !== '') $row['url'] = $defaultUrl;
                $out[$kind][] = $row;
            } else {
                cds_dashboard_feed_collect($row, $hint, $out, $module, $defaultUrl, $depth + 1);
            }
        }
        return;
    }
    $title = cds_dashboard_feed_title($node);
    $rowKind = cds_dashboard_feed_row_kind($node, $hint);
    if ($title !== '' && $rowKind !== '') {
        $node['title'] = $title;
        $node['_dashboard_module'] = $module;
        if (empty($node['url']) && empty($node['link']) && $defaultUrl !== '') $node['url'] = $defaultUrl;
        $out[$rowKind][] = $node;
        return;
    }
    foreach ($node as $key => $child) {
        if (!is_array($child)) continue;
        $childHint = cds_dashboard_feed_kind((string)$key) ?: $hint;
        cds_dashboard_feed_collect($child, $childHint, $out, $module, $defaultUrl, $depth + 1);
    }
}

function cds_dashboard_feed_data(): array {
    $out = ['notices'=>[],'tasks'=>[],'observations'=>[]];
    $sources = [];
    $addSource = function(string $file, string $module = '', string $url = '') use (&$sources): void {
        if (!is_file($file) || filesize($file) > 5 * 1024 * 1024) return;
        $real = realpath($file) ?: $file;
        $sources[$real] = ['file'=>$file,'module'=>$module,'url'=>$url,'hint'=>cds_dashboard_feed_kind(basename($file))];
    };
    $addSource(DATA_PATH . '/dashboard_feed.json');
    foreach (glob(DATA_PATH . '/*.json') ?: [] as $file) {
        if (cds_dashboard_feed_kind(basename($file)) !== '') $addSource($file);
    }
    if (defined('PCCM_DATA_PATH') && PCCM_DATA_PATH !== '' && is_dir(PCCM_DATA_PATH)) {
        $pccmUrl = defined('URL_CHUYEN_MON') ? URL_CHUYEN_MON : '/chuyenmon/';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PCCM_DATA_PATH, FilesystemIterator::SKIP_DOTS));
        $sourceCount = 0;
        foreach ($iterator as $entry) {
            if ($sourceCount >= 200 || $iterator->getDepth() > 3 || !$entry->isFile() || cds_dashboard_lower($entry->getExtension()) !== 'json') continue;
            $addSource($entry->getPathname(), 'chuyenmon', $pccmUrl);
            $sourceCount++;
        }
    }
    foreach ($sources as $source) {
        $data = load_json($source['file'], []);
        if (!is_array($data)) continue;
        cds_dashboard_feed_collect($data, $source['hint'], $out, $source['module'], $source['url']);
    }
    foreach ($out as $kind => $rows) {
        $unique = [];
        foreach ($rows as $row) {
            $key = (string)($row['id'] ?? $row['uuid'] ?? '') . '|' . cds_dashboard_feed_title($row) . '|' . cds_dashboard_feed_date($row);
            $unique[$key] = $row;
        }
        $out[$kind] = array_values($unique);
    }
    return $out;
}
function cds_dashboard_parse_date($value): string {
    if (is_array($value) || is_object($value)) return '';
    $raw = trim((string)$value);
    if ($raw === '') return '';
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $raw, $match)) {
        return sprintf('%04d-%02d-%02d', (int)$match[3], (int)$match[2], (int)$match[1]);
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
}

function cds_dashboard_feed_date(array $row): string {
    foreach (['due_date','deadline','due_at','han_hoan_thanh','han_xu_ly','ngay_het_han','end_date','to_date','ngay_ket_thuc','date','ngay','start_date','from_date','ngay_bat_dau','starts_at','start_at','publish_date','published_at','notification_date','visible_from','ngay_hieu_luc','ngay_ban_hanh','created_at','updated_at'] as $key) {
        if (empty($row[$key]) || is_array($row[$key])) continue;
        $date = cds_dashboard_parse_date($row[$key]);
        if ($date !== '') return $date;
    }
    return '';
}

function cds_dashboard_feed_assignees(array $row): array {
    $values = [];
    $flatten = function($value) use (&$values, &$flatten): void {
        if (is_array($value)) {
            foreach (['name','full_name','username','id','user_id','teacher_id','ho_ten'] as $key) {
                if (isset($value[$key]) && !is_array($value[$key]) && trim((string)$value[$key]) !== '') {
                    $values[] = trim((string)$value[$key]);
                    return;
                }
            }
            foreach ($value as $child) $flatten($child);
            return;
        }
        if (!is_object($value)) foreach (preg_split('/[,;|]/u', trim((string)$value)) ?: [] as $part) if (trim($part) !== '') $values[] = trim($part);
    };
    foreach (['assignee_id','assigned_to','assignee','assignees','assigned_users','assigned_to_ids','recipient_ids','responsible_ids','nguoi_thuc_hien','nguoi_phu_trach','nguoi_nhan','nguoi_duoc_giao','executor','responsible','performers','members','teacher_id','teacher'] as $key) {
        if (array_key_exists($key, $row)) $flatten($row[$key]);
    }
    return array_values(array_unique($values));
}

function cds_dashboard_feed_schedule(array $row): array {
    $start = ''; $end = '';
    foreach (['start_date','from_date','ngay_bat_dau','starts_at','start_at','effective_from','publish_date','published_at','notification_date','visible_from','ngay_hieu_luc','ngay','date'] as $key) {
        if (!empty($row[$key])) { $start = cds_dashboard_parse_date($row[$key]); if ($start !== '') break; }
    }
    foreach (['due_date','deadline','due_at','han_hoan_thanh','han_xu_ly','ngay_het_han','end_date','to_date','ngay_ket_thuc','expires_at','expiry_date','visible_to'] as $key) {
        if (!empty($row[$key])) { $end = cds_dashboard_parse_date($row[$key]); if ($end !== '') break; }
    }
    $recurringDay = (int)(!empty($row['day_to']) ? $row['day_to'] : ($row['day_from'] ?? 0));
    if ($end === '' && $recurringDay > 0) {
        $todayDate = new DateTimeImmutable(date('Y-m-d'));
        $day = min(31, max(1, $recurringDay));
        $monthStart = $todayDate->modify('first day of this month');
        $day = min($day, (int)$monthStart->format('t'));
        $recurringDue = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $day);
        if ($recurringDue < $todayDate) {
            $monthStart = $monthStart->modify('first day of next month');
            $day = min($recurringDay, (int)$monthStart->format('t'));
            $recurringDue = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), max(1, $day));
        }
        $end = $recurringDue->format('Y-m-d');
    }
    if ($start === '' && $end === '') return [];
    $today = date('Y-m-d');
    if ($end !== '' && $end < $today) return [];
    $upcoming = $start !== '' && $start > $today;
    return [
        'start' => $start,
        'end' => $end,
        'state' => $upcoming ? 'Sắp diễn ra' : 'Đang diễn ra',
        'nearest' => $upcoming ? $start : ($end !== '' ? $end : $start),
    ];
}

function cds_dashboard_feed_visible(array $row): bool {
    if (array_key_exists('active', $row) && !$row['active']) return false;
    if (!empty($row['completed']) || !empty($row['is_completed']) || !empty($row['done'])) return false;
    $status = cds_dashboard_lower($row['status'] ?? $row['trang_thai'] ?? '');
    if (in_array($status, ['draft','inactive','deleted','cancelled','canceled','archived','nháp','da_xoa','đã xóa','hủy','done','completed','complete','finished','hoàn thành','đã hoàn thành','da_hoan_thanh'], true)) return false;
    $today = date('Y-m-d');
    foreach (['visible_to','expires_at','expiry_date','end_date','to_date','ngay_ket_thuc'] as $key) {
        if (empty($row[$key]) || is_array($row[$key])) continue;
        $timestamp = strtotime((string)$row[$key]);
        if ($timestamp !== false && date('Y-m-d', $timestamp) < $today) return false;
    }
    return true;
}

function cds_dashboard_user_identities(array $user): array {
    $values = [];
    foreach (['id','username','name','teacher_name','email'] as $key) {
        $value = cds_dashboard_lower($user[$key] ?? '');
        if ($value !== '') $values[] = $value;
    }
    return array_values(array_unique($values));
}

function cds_dashboard_teacher_for_user(array $user): ?array {
    $identities = cds_dashboard_user_identities($user);
    if (!$identities) return null;
    foreach (csdl_teachers_all() as $teacher) {
        if (isset($teacher['active']) && !$teacher['active']) continue;
        $teacherIdentities = [];
        foreach (['id','code','name','email'] as $key) {
            $value = cds_dashboard_lower($teacher[$key] ?? '');
            if ($value !== '') $teacherIdentities[] = $value;
        }
        if (array_intersect($identities, $teacherIdentities)) return $teacher;
    }
    return null;
}

function cds_dashboard_next_anniversary(string $sourceDate): array {
    $sourceDate = cds_dashboard_parse_date($sourceDate);
    if ($sourceDate === '') return [];
    $source = new DateTimeImmutable($sourceDate);
    $today = new DateTimeImmutable(date('Y-m-d'));
    $year = (int)$today->format('Y');
    $next = new DateTimeImmutable($year . '-' . $source->format('m-d'));
    if ($next < $today) $next = new DateTimeImmutable(($year + 1) . '-' . $source->format('m-d'));
    return ['date'=>$next->format('Y-m-d'),'years'=>max(0, (int)$next->format('Y') - (int)$source->format('Y'))];
}

function cds_dashboard_staff_milestones(array $user): array {
    $teacher = cds_dashboard_teacher_for_user($user);
    if (!$teacher) return [];
    $today = date('Y-m-d');
    $until = date('Y-m-d', strtotime('+365 days'));
    $rows = [];
    $salaryFrom = cds_dashboard_parse_date($teacher['he_so_from'] ?? $teacher['salary_from'] ?? '');
    if ($salaryFrom !== '') {
        $nextSalary = new DateTimeImmutable($salaryFrom);
        $todayDate = new DateTimeImmutable($today);
        do { $nextSalary = $nextSalary->modify('+3 years'); } while ($nextSalary < $todayDate);
        $nextSalaryDate = $nextSalary->format('Y-m-d');
        if ($nextSalaryDate <= $until) {
            $detail = implode(' · ', array_filter([
                !empty($teacher['bac']) ? 'Bậc ' . $teacher['bac'] : '',
                !empty($teacher['he_so']) ? 'Hệ số ' . $teacher['he_so'] : '',
            ]));
            $rows[] = ['kind'=>'salary','title'=>'Dự kiến đến mốc nâng lương','_dashboard_detail'=>$detail ?: 'Mốc nhân sự cá nhân','_dashboard_start'=>$nextSalaryDate,'_dashboard_end'=>'','_dashboard_nearest'=>$nextSalaryDate,'_dashboard_state'=>'Sắp đến'];
        }
    }
    $seniority = cds_dashboard_next_anniversary((string)($teacher['join_date'] ?? ''));
    if ($seniority && $seniority['date'] >= $today && $seniority['date'] <= $until && $seniority['years'] > 0) {
        $rows[] = ['kind'=>'seniority','title'=>'Mốc thâm niên ' . $seniority['years'] . ' năm','_dashboard_detail'=>'Tính theo ngày vào ngành','_dashboard_start'=>$seniority['date'],'_dashboard_end'=>'','_dashboard_nearest'=>$seniority['date'],'_dashboard_state'=>'Sắp đến'];
    }
    return $rows;
}

function cds_dashboard_document_notices(): array {
    if (!can_module('vanban', 'view')) return [];
    require_once __DIR__ . '/vanban_store.php';
    $now = time();
    $rows = [];
    foreach (vb_rows(VANBAN_DOCUMENTS_FILE) as $document) {
        if (empty($document['dashboard_visible'])) continue;
        $from = strtotime((string)($document['dashboard_from'] ?? ''));
        $to = strtotime((string)($document['dashboard_to'] ?? ''));
        if ($from === false || $to === false || $now < $from || $now > $to) continue;
        $state = 'Đang hiệu lực';
        $badgeClass = 'active';
        if ($to - $now <= 3 * 86400) {
            $state = 'Sắp hết hạn';
            $badgeClass = 'expiring';
        } elseif ($now - $from <= 3 * 86400) {
            $state = 'Mới';
            $badgeClass = 'new';
        }
        $rows[] = [
            'id'=>(string)($document['id'] ?? ''), 'kind'=>'notice',
            'title'=>(string)($document['title'] ?? 'Văn bản'),
            'url'=>vb_file_url((string)($document['file_path'] ?? '')),
            '_dashboard_detail'=>'Văn bản ' . trim((string)($document['symbol'] ?? '')),
            '_dashboard_start'=>date('Y-m-d H:i', $from), '_dashboard_end'=>date('Y-m-d H:i', $to),
            '_dashboard_nearest'=>date('Y-m-d H:i', $to), '_dashboard_state'=>$state,
            '_dashboard_badge_class'=>$badgeClass, '_dashboard_assignees'=>[],
        ];
    }
    require_once __DIR__ . '/vanban_engagement.php';
    foreach ([['poll', VANBAN_POLLS_FILE, 'Bình chọn'], ['survey', VANBAN_SURVEYS_FILE, 'Khảo sát']] as [$kind, $file, $label]) {
        foreach (vb_rows($file) as $round) {
            if (empty($round['show_on_dashboard']) || ($round['status'] ?? 'active') !== 'active') continue;
            $viewer=current_user()??[];
            if(($viewer['role']??'')!=='admin'&&!vb_engagement_can_participate($round,$viewer))continue;
            $end = trim((string)($round['ends_at'] ?? ''));
            if ($end === '' || $end < date('Y-m-d')) continue;
            $days = (int)floor((strtotime($end . ' 23:59:59') - $now) / 86400);
            $rows[] = [
                'id'=>(string)($round['id'] ?? ''), 'kind'=>'notice',
                'title'=>(string)($round['title'] ?? $label),
                'url'=>BASE_URL . 'vanban.php?tab=engagement&engagement_tab=' . ($kind === 'poll' ? 'polls' : 'surveys'),
                '_dashboard_detail'=>$label . ' · ' . vb_response_count($round) . ' lượt tham gia',
                '_dashboard_start'=>substr((string)($round['created_at'] ?? ''), 0, 10), '_dashboard_end'=>$end,
                '_dashboard_nearest'=>$end, '_dashboard_state'=>$days <= 3 ? 'Sắp hết hạn' : 'Đang diễn ra',
                '_dashboard_badge_class'=>$days <= 3 ? 'expiring' : 'active', '_dashboard_assignees'=>[],
            ];
        }
    }
    return $rows;
}

function cds_dashboard_notice_tasks(array $user, int $limit = 0): array {
    $feed = cds_dashboard_feed_data();
    require_once __DIR__ . '/timetable_store.php';
    $rows = array_merge(cds_dashboard_staff_milestones($user), cds_dashboard_document_notices(), can_module('chuyenmon','view') ? tt_dashboard_notices() : []);
    $identityLower = cds_dashboard_user_identities($user);
    foreach ($feed['notices'] as $row) {
        if (($row['_dashboard_module'] ?? '') !== 'chuyenmon' || !cds_dashboard_feed_visible($row)) continue;
        $assignees = cds_dashboard_feed_assignees($row);
        $schedule = cds_dashboard_feed_schedule($row);
        if (!$schedule) continue;
        $assigneeLower = array_map('cds_dashboard_lower', $assignees);
        $isGeneral = !$assignees || array_intersect($assigneeLower, ['all','everyone','tất cả','tat ca','toàn trường','toan truong']);
        $row['kind'] = 'notice';
        $row['_dashboard_assignees'] = $assignees;
        $row['_dashboard_detail'] = $isGeneral ? 'Thông báo chung' : 'Chỉ định: ' . implode(', ', array_slice($assignees, 0, 3));
        $row['_dashboard_start'] = $schedule['start'];
        $row['_dashboard_end'] = $schedule['end'];
        $row['_dashboard_state'] = $schedule['state'];
        $row['_dashboard_nearest'] = $schedule['end'] ?: ($schedule['state'] === 'Sắp diễn ra' ? $schedule['start'] : '9999-12-31');
        $recordId=trim((string)($row['id']??''));
        $section=cds_dashboard_lower($row['section']??'');
        if($recordId!==''&&($section==='kh_thongbao'||str_contains($section,'thongbao'))){
            $cmUrl=defined('URL_CHUYEN_MON')?URL_CHUYEN_MON:'/chuyenmon/';
            $row['url']=$cmUrl.'kehoach.php?tab=thongbao&notice='.rawurlencode($recordId);
        }
        $rows[] = $row;
    }
    foreach ($feed['tasks'] as $row) {
        if (($row['_dashboard_module'] ?? '') !== 'chuyenmon' || !cds_dashboard_feed_visible($row)) continue;
        $assignees = cds_dashboard_feed_assignees($row);
        $schedule = cds_dashboard_feed_schedule($row);
        if (!$assignees || !$schedule) continue;
        $status = cds_dashboard_lower($row['status'] ?? $row['trang_thai'] ?? '');
        if (in_array($status, ['done','completed','complete','finished','hoàn thành','đã hoàn thành','da_hoan_thanh'], true)) continue;
        $assigneeLower = array_map('cds_dashboard_lower', $assignees);
        if (!array_intersect($assigneeLower, $identityLower)) continue;
        $row['kind'] = 'task';
        $row['_dashboard_assignees'] = $assignees;
        $row['_dashboard_detail'] = 'Công việc được giao cho bạn';
        $row['_dashboard_start'] = $schedule['start'];
        $row['_dashboard_end'] = $schedule['end'];
        $row['_dashboard_state'] = $schedule['state'];
        $row['_dashboard_nearest'] = $schedule['nearest'];
        $rows[] = $row;
    }
    usort($rows, function($a, $b) {
        $dateOrder = strcmp((string)($a['_dashboard_nearest'] ?? ''), (string)($b['_dashboard_nearest'] ?? ''));
        if ($dateOrder !== 0) return $dateOrder;
        return strnatcasecmp(cds_dashboard_feed_title($a), cds_dashboard_feed_title($b));
    });
    return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
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
        require_once __DIR__.'/noitru_store.php';$allBoarders=noitru_boarders_live();noitru_att_ensure_legacy_reports(count($allBoarders));$boarders=array_values(array_filter($allBoarders,fn($s)=>$allowed===null||in_array((string)($s['class_name']??''),$allowed,true)));$boarderIds=array_fill_keys(array_column($boarders,'id'),true);$today=date('Y-m-d');
        $attRows=array_values(array_filter(noitru_att_all(),fn($r)=>isset($boarderIds[$r['student_id']??''])));$attReports=noitru_att_reports_all();$latestKey='';$latestDate='';$latestShift='';
        $shiftOrder=['morning'=>1,'sang'=>1,'noon'=>2,'trua'=>2,'afternoon'=>3,'evening'=>4,'toi'=>4,'night'=>5];
        foreach($attReports as $r){$d=(string)($r['date']??'');if($d===''||$d>$today)continue;$shift=(string)($r['shift']??'');$updated=(string)($r['updated_at']??$r['created_at']??'');$key=$d.'|'.($updated!==''?$updated:sprintf('%02d',$shiftOrder[$shift]??0));if($key>$latestKey){$latestKey=$key;$latestDate=$d;$latestShift=$shift;}}
        $attendance=['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];foreach($attReports as $r)if(($r['date']??'')===$latestDate&&($r['shift']??'')===$latestShift)foreach($attendance as $st=>$_)$attendance[$st]+=(int)($r[$st]??0);
        if($latestDate===''){foreach($attRows as $r){$d=(string)($r['date']??'');if($d===''||$d>$today)continue;$shift=(string)($r['shift']??'');$key=$d.'|'.(string)($r['updated_at']??$r['created_at']??'');if($key>$latestKey){$latestKey=$key;$latestDate=$d;$latestShift=$shift;}}foreach($attRows as $r)if(($r['date']??'')===$latestDate&&($r['shift']??'')===$latestShift){$st=$r['status']??'present';if(isset($attendance[$st]))$attendance[$st]++;}}
        $settings=noitru_duty_settings();$start=$settings['start_time']??'06:00';$end=$settings['end_time']??'06:00';$todayStart=strtotime($today.' '.$start);$dutyDate=time()<$todayStart?date('Y-m-d',strtotime($today.' -1 day')):$today;$dutyEnd=strtotime($dutyDate.' '.$end);if($dutyEnd<=strtotime($dutyDate.' '.$start))$dutyEnd=strtotime('+1 day',$dutyEnd);
        $dutyRows=array_values(array_filter(noitru_duty_all(),fn($r)=>($r['date']??'')===$dutyDate));$manager=noitru_duty_manager_for_date($dutyDate)??[];
        $noitru=['boarders'=>count($boarders),'present'=>$attendance['present']+$attendance['late'],'absent'=>$attendance['absent']+$attendance['excused'],'attendance_date'=>$latestDate,'attendance_shift'=>$latestShift,'pending_exits'=>count(array_filter(noitru_exits_all(),fn($r)=>($r['status']??'')==='pending'&&isset($boarderIds[$r['student_id']??'']))),'duty'=>['date'=>$dutyDate,'start'=>$start,'end'=>$end,'people'=>array_values(array_filter(array_column($dutyRows,'teacher_name'))),'managers'=>array_values(array_filter($manager['teacher_names']??[])),'note'=>$manager['note']??'','remaining'=>max(0,$dutyEnd-time())]];
    }
    $leave=[];$td=load_json(DATA_PATH.'/thidua.json',['records'=>[]]);$today=date('Y-m-d');$until=date('Y-m-d',strtotime('+21 days'));
    foreach($td['records']??[] as $row){if(($row['type']??'')!=='teacher_attendance')continue;$from=$row['from_date']??$row['date']??'';$to=$row['to_date']??$from;if($to<$today||$from>$until)continue;$leave[]=['name'=>$row['person_name']??'','from'=>$from,'to'=>$to,'reason'=>trim(($row['reason']??'').(!empty($row['reason_detail'])?' · '.$row['reason_detail']:'')),'permission'=>$row['permission']??''];}

    /* Lịch dạy thay đã duyệt hiển thị cùng khối Nhân sự – Lịch nghỉ giáo viên. */
    $tkbFile=(defined('PCCM_DATA_PATH')?rtrim(PCCM_DATA_PATH,'/\\'):dirname(__DIR__).'/chuyenmon/data').'/timetable_substitutions.json';
    $tkbRows=load_json($tkbFile,[]);
    foreach(is_array($tkbRows)?$tkbRows:[] as $row){
        if(($row['status']??'')!=='approved')continue;
        $date=trim((string)($row['date']??''));if($date===''||$date<$today||$date>$until)continue;
        $absent=trim((string)($row['absent_teacher']??''));$sub=trim((string)($row['substitute_teacher']??''));
        $session=trim((string)($row['session']??''));$period=trim((string)($row['period']??''));$class=trim((string)($row['class']??''));$subject=trim((string)($row['subject']??''));
        $detail='Dạy thay: '.$sub;
        $slot=trim($session.($period!==''?' tiết '.$period:''));if($slot!=='')$detail.=' · '.$slot;if($class!=='')$detail.=' · lớp '.$class;if($subject!=='')$detail.=' · '.$subject;
        $leave[]=['name'=>$absent!==''?$absent:'Lịch dạy thay','from'=>$date,'to'=>$date,'reason'=>$detail,'permission'=>'Đã duyệt'];
    }

    usort($leave,fn($a,$b)=>strcmp($a['from'],$b['from']) ?: strcmp($a['name'],$b['name']));
    return ['csdl'=>['teachers'=>cds_dashboard_gender_stats($activeTeachers),'students'=>cds_dashboard_gender_stats($scopeStudents),'classes'=>count($scopeClasses)],'noitru'=>$noitru,'leave'=>array_slice($leave,0,8)];
}

function cds_dashboard_quick_actions(array $user): array {
    $items=[];$add=function($permission,$url,$label,$icon,$color)use(&$items){if(can_perm($permission))$items[]=compact('url','label','icon','color');};
    $add('nt.diemdanh','noitru_attendance.php','Điểm danh','bi-person-check','#db2777');
    $add('nt.baoan','noitru.php?tab=meals','Báo ăn','bi-egg-fried','#ea580c');
    $add('td.teacher_attendance','thidua.php?section=teacher_attendance','Chấm công','bi-calendar-check','#ca8a04');
    $add('cm.baocao.dugio','danhgia.php?view=profile','Hồ sơ đánh giá','bi-person-vcard','#315b8a');
    $add('cm.dashboard',defined('URL_CHUYEN_MON') ? URL_CHUYEN_MON : 'chuyenmon/','Chuyên môn','bi-journal-bookmark-fill','#168652');
    if (can_module('chuyenmon', 'view')) $items[] = ['url'=>'thoikhoabieu.php','label'=>'Thời khóa biểu','icon'=>'bi-calendar3','color'=>'#2563eb'];
    return $items;
}
