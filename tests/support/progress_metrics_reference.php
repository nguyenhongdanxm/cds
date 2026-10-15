<?php
/** Original cumulative algorithm from main before this optimization. */
function reference_cm_progress_auto_metrics(array $assignments, array $year, ?array $week): array {
    $result = [];
    if (!$week) return $result;
    $from = (string)($year['start'] ?? $week['start'] ?? '');
    $to = (string)($week['end'] ?? '');
    if ($from === '' || $to === '') return $result;

    /*
     * Tiến độ chỉ cần ba số tổng hợp theo lớp-môn. Không gọi lb_stat_rows():
     * hàm đó dựng và đối chiếu chi tiết từng tiết để phục vụ trang thống kê
     * Sổ đầu bài, sau đó mã cũ lại quét toàn bộ kết quả cho từng phân công.
     */
    $wantedScopes = [];
    foreach ($assignments as $assignment) {
        $class = trim((string)($assignment['class'] ?? ''));
        $subject = trim((string)($assignment['subject'] ?? ''));
        if ($class === '' || $subject === '') continue;
        $wantedScopes[cm_progress_scope_key($class, $subject)] = true;
    }
    if (!$wantedScopes) return $result;

    $scheduledByScope = [];
    foreach (lb_weeks() as $statWeek) {
        $start = (string)($statWeek['start'] ?? '');
        $end = (string)($statWeek['end'] ?? '');
        if (($to !== '' && $start > $to) || ($from !== '' && $end < $from)) continue;
        /* Mảng rỗng buộc lb_slots chỉ dựng TKB, không dò ghép từng tiết với
           toàn bộ bản ghi Sổ đầu bài. Dạy thay, dạy bù và lấp tiết đã duyệt
           vẫn được lb_slots bổ sung từ dữ liệu TKB. */
        foreach (lb_slots($statWeek, []) as $row) {
            $date = (string)($row['date'] ?? '');
            if ($date === '' || $date < $from || $date > $to) continue;
            $scopeKey = cm_progress_scope_key(
                (string)($row['class'] ?? ''),
                (string)($row['subject'] ?? '')
            );
            if (!isset($wantedScopes[$scopeKey])) continue;
            $scheduledByScope[$scopeKey] = ($scheduledByScope[$scopeKey] ?? 0) + 1;
        }
    }

    $actualByScope = [];
    foreach (lb_record_map_range($from, $to) as $row) {
        if (empty($row['signed_at'])) continue;
        if (!in_array((string)($row['status'] ?? ''), ['taught','substitute','makeup','online'], true)) continue;
        $scopeKey = cm_progress_scope_key(
            (string)($row['class'] ?? ''),
            (string)($row['subject'] ?? '')
        );
        if (!isset($wantedScopes[$scopeKey])) continue;
        $actualByScope[$scopeKey] = max(
            (int)($actualByScope[$scopeKey] ?? 0),
            (int)($row['ppct_period'] ?? 0)
        );
    }

    $curriculumByScope = [];
    foreach ($assignments as $key => $assignment) {
        $class = trim((string)($assignment['class'] ?? ''));
        $subject = trim((string)($assignment['subject'] ?? ''));
        $scopeKey = cm_progress_scope_key($class, $subject);
        $scheduled = (int)($scheduledByScope[$scopeKey] ?? 0);
        $actualPpct = (int)($actualByScope[$scopeKey] ?? 0);
        $grade = function_exists('lb_grade') ? lb_grade($class) : preg_replace('/\D+/', '', $class);
        if (!array_key_exists($scopeKey, $curriculumByScope)) {
            $titles = lb_curriculum_titles_for($subject, (string)$grade, $class);
            $curriculumByScope[$scopeKey] = $titles ? max(array_map('intval', array_keys($titles))) : 0;
        }
        $ppctMax = (int)$curriculumByScope[$scopeKey];
        $planned = $ppctMax > 0 ? min($scheduled, $ppctMax) : $scheduled;
        $result[$key] = [
            'standard_weekly' => cm_progress_num($assignment['periods'] ?? 0),
            'scheduled_period' => $scheduled,
            'actual_period' => $actualPpct,
            'ppct_period' => $planned,
            'ppct_total' => $ppctMax,
            'ppct_missing' => $ppctMax < 1,
        ];
    }
    return $result;
}
