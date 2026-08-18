<?php
/**
 * Lịch tuần học dùng chung của CDS.
 *
 * Hai tuần đặc biệt `pre_1`, `pre_2` nằm trước Tuần 1 và không làm thay đổi
 * số thứ tự các tuần chính khóa. Dữ liệu được lưu ngay trong school_years.json
 * để các module có thể dùng chung một nguồn.
 */

function cds_school_week_date_valid($value): bool {
    if (function_exists('csdl_date_valid')) return csdl_date_valid($value);
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

/**
 * Luôn ưu tiên kho CSDL gốc `/data/school_years.json` của CDS.
 * Chuyên môn có DATA_PATH riêng nên tuyệt đối không dựa vào DATA_PATH ở đây.
 */
function cds_school_year_rows(): array {
    if (function_exists('csdl_years_all')) return (array)csdl_years_all();
    if (defined('CSDL_YEARS') && function_exists('load_json')) return (array)load_json(CSDL_YEARS, []);
    $rootFile = dirname(__DIR__) . '/data/school_years.json';
    if (is_file($rootFile) && is_readable($rootFile)) {
        $decoded = json_decode((string)file_get_contents($rootFile), true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function cds_school_year_resolve($year = null): ?array {
    $rows = cds_school_year_rows();
    if (is_array($year)) return $year;
    if (is_string($year) && $year !== '') {
        foreach ($rows as $row) if (($row['id'] ?? '') === $year) return $row;
    }
    foreach ($rows as $row) if (!empty($row['is_current'])) return $row;
    return $rows[0] ?? null;
}

function cds_school_preweek_values($year = null): array {
    $year = cds_school_year_resolve($year);
    $values = ['pre_1'=>'', 'pre_2'=>''];
    if (!$year) return $values;
    foreach ((array)($year['pre_weeks'] ?? []) as $row) {
        $key = (string)($row['key'] ?? '');
        $start = (string)($row['start'] ?? '');
        if (isset($values[$key]) && cds_school_week_date_valid($start)) $values[$key] = $start;
    }
    return $values;
}

/**
 * Trả về lịch tuần theo thứ tự thời gian:
 * Tuần học trước 1 → Tuần học trước 2 → Tuần 1 → Tuần 2 → ...
 */
function cds_school_week_calendar($year = null): array {
    $year = cds_school_year_resolve($year);
    if (!$year) return [];
    $start = (string)($year['start'] ?? '');
    $end = (string)($year['end'] ?? '');
    if (!cds_school_week_date_valid($start) || !cds_school_week_date_valid($end) || $end < $start) return [];

    $weeks = [];
    $preValues = cds_school_preweek_values($year);
    foreach ([1,2] as $index) {
        $key = 'pre_' . $index;
        $preStart = $preValues[$key] ?? '';
        if (!cds_school_week_date_valid($preStart)) continue;
        $preDate = new DateTimeImmutable($preStart);
        $preEnd = $preDate->modify('+6 days')->format('Y-m-d');
        if ($preEnd >= $start) continue;
        $weeks[] = [
            'key'=>$key,
            'number'=>0,
            'is_pre'=>true,
            'pre_index'=>$index,
            'start'=>$preStart,
            'end'=>$preEnd,
            'label'=>'Tuần học trước ' . $index,
        ];
    }
    usort($weeks, static fn($a,$b) => strcmp((string)$a['start'], (string)$b['start']));

    $saved = [];
    foreach ((array)($year['weeks'] ?? []) as $row) {
        $number = (int)($row['number'] ?? 0);
        $weekStart = (string)($row['start'] ?? '');
        if ($number > 0 && cds_school_week_date_valid($weekStart)) $saved[$number] = $weekStart;
    }

    $cursor = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    for ($number = 1; $number <= 60 && $cursor <= $endDate; $number++) {
        if (isset($saved[$number])) $cursor = new DateTimeImmutable($saved[$number]);
        $weekEnd = $cursor->modify('+6 days');
        $weeks[] = [
            'key'=>(string)$number,
            'number'=>$number,
            'is_pre'=>false,
            'start'=>$cursor->format('Y-m-d'),
            'end'=>$weekEnd->format('Y-m-d'),
            'label'=>'Tuần ' . $number,
        ];
        $cursor = $cursor->modify('+7 days');
    }
    return $weeks;
}

function cds_school_week_by_key(string $key, $year = null): ?array {
    foreach (cds_school_week_calendar($year) as $week) {
        if ((string)($week['key'] ?? '') === $key) return $week;
    }
    return null;
}

function cds_school_week_for_date(?string $date = null, $year = null): ?array {
    $date = $date ?: date('Y-m-d');
    if (!cds_school_week_date_valid($date)) return null;
    foreach (cds_school_week_calendar($year) as $week) {
        if ($date >= $week['start'] && $date <= $week['end']) return $week;
    }
    return null;
}

/** Lưu hoặc bỏ hai tuần học trước. Để trống một ô nghĩa là không dùng tuần đó. */
function cds_school_save_preweeks(string $yearId, string $pre1, string $pre2): array {
    if (!defined('CSDL_YEARS') || !function_exists('save_json')) {
        return ['ok'=>false,'message'=>'Chưa nạp được kho dữ liệu năm học.'];
    }
    $pre1 = trim($pre1);
    $pre2 = trim($pre2);
    foreach ([$pre1,$pre2] as $value) {
        if ($value !== '' && !cds_school_week_date_valid($value)) return ['ok'=>false,'message'=>'Ngày bắt đầu tuần học trước không hợp lệ.'];
    }

    $years = cds_school_year_rows();
    foreach ($years as &$year) {
        if (($year['id'] ?? '') !== $yearId) continue;
        $officialStart = (string)($year['start'] ?? '');
        if (!cds_school_week_date_valid($officialStart)) {
            unset($year);
            return ['ok'=>false,'message'=>'Năm học chưa có ngày bắt đầu hợp lệ.'];
        }
        $values = ['pre_1'=>$pre1,'pre_2'=>$pre2];
        $rows = [];
        foreach ($values as $key=>$value) {
            if ($value === '') continue;
            $end = (new DateTimeImmutable($value))->modify('+6 days')->format('Y-m-d');
            if ($end >= $officialStart) {
                unset($year);
                return ['ok'=>false,'message'=>'Tuần học trước phải kết thúc trước Tuần 1 chính thức.'];
            }
            $rows[$key] = ['key'=>$key,'start'=>$value,'end'=>$end];
        }
        if ($pre1 !== '' && $pre2 !== '') {
            if ($pre1 >= $pre2) {
                unset($year);
                return ['ok'=>false,'message'=>'Tuần học trước 1 phải nằm trước Tuần học trước 2.'];
            }
            $pre1End = (new DateTimeImmutable($pre1))->modify('+6 days')->format('Y-m-d');
            if ($pre1End >= $pre2) {
                unset($year);
                return ['ok'=>false,'message'=>'Hai tuần học trước không được chồng lấn thời gian.'];
            }
        }
        $year['pre_weeks'] = array_values($rows);
        $year['pre_weeks_updated_at'] = function_exists('csdl_now') ? csdl_now() : date('c');
        $ok = save_json(CSDL_YEARS, $years);
        if ($ok && function_exists('cds_shadow_refresh_core')) cds_shadow_refresh_core('school_year', $yearId);
        unset($year);
        return ['ok'=>(bool)$ok,'message'=>$ok?'Đã lưu Tuần học trước 1/2 vào lịch tuần dùng chung.':'Không lưu được cấu hình tuần học trước.'];
    }
    unset($year);
    return ['ok'=>false,'message'=>'Không tìm thấy năm học.'];
}
