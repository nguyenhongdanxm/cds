<?php
/** Bộ lọc dùng chung cho lịch sử y tế và xuất Excel; giữ nguyên phạm vi lớp. */
$historyRange = in_array($_GET['range'] ?? 'month', ['day','week','month'], true) ? ($_GET['range'] ?? 'month') : 'month';
$historyDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$historyTs = strtotime($historyDate);
if ($historyRange === 'day') { $historyFrom = $historyTo = $historyDate; }
elseif ($historyRange === 'week') { $historyFrom = date('Y-m-d', strtotime('monday this week', $historyTs)); $historyTo = date('Y-m-d', strtotime('sunday this week', $historyTs)); }
else { $historyFrom = date('Y-m-01', $historyTs); $historyTo = date('Y-m-t', $historyTs); }
$historySearch = mb_strtolower(trim($_GET['q'] ?? ''), 'UTF-8');
$historyType = trim($_GET['type'] ?? 'all');
$healthAllowedIds = [];
if ($healthView === 'history') {
    foreach (noitru_boarders_on_date($historyDate) as $student) {
        if (can_class($student['class_name'] ?? '')) $healthAllowedIds[(string)($student['id'] ?? '')] = true;
    }
}
$filteredHealth = $healthView === 'history' ? array_values(array_filter(noitru_health_for_range($historyFrom, $historyTo), function($row) use ($historyFrom,$historyTo,$historySearch,$historyType,$healthAllowedIds) {
    if (!isset($healthAllowedIds[(string)($row['student_id'] ?? '')])) return false;
    $date = $row['date'] ?? '';
    if ($date < $historyFrom || $date > $historyTo) return false;
    if ($historyType !== 'all' && ($row['type'] ?? '') !== $historyType) return false;
    if ($historySearch !== '') {
        $haystack = mb_strtolower(($row['student_name'] ?? '') . ' ' . ($row['diagnosis'] ?? '') . ' ' . ($row['class_name'] ?? ''), 'UTF-8');
        if (mb_strpos($haystack, $historySearch) === false) return false;
    }
    return true;
})) : [];
if ($filteredHealth) usort($filteredHealth, fn($a,$b) => strcmp(($b['date'] ?? '') . ($b['created_at'] ?? ''), ($a['date'] ?? '') . ($a['created_at'] ?? '')));
