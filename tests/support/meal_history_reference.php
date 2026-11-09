<?php
/** Original legacy-history synthesis, frozen for source-transition parity. */
function reference_nt_meal_history_all() {
    noitru_ensure_dir();
    $rows = load_json(NOITRU_MEAL_HISTORY, []);
    $rows = is_array($rows) ? $rows : [];
    /* Đưa các phiếu đã báo trước khi có nhật ký vào sổ lịch sử một lần. */
    $known = []; $knownKeys = [];
    foreach ($rows as $row) {
        $known[(string)($row['source_report_id'] ?? '')] = true;
        $knownKeys[implode('|', [(string)($row['date']??''),(string)($row['class_name']??''),(string)($row['meal']??'')])] = true;
    }
    $changed = false;
    $deletedSources = nt_meal_history_deleted_sources();
    foreach (noitru_meal_report_rows() as $report) {
        $reportId = trim((string)($report['id'] ?? ''));
        $reportKey = implode('|', [(string)($report['date']??''),(string)($report['class_name']??''),(string)($report['meal']??'')]);
        if ($reportId === '' || isset($deletedSources[$reportId]) || isset($known[$reportId]) || isset($knownKeys[$reportKey])) continue;
        $rows[] = [
            'id'=>noitru_uid('mh'),'source_report_id'=>$reportId,'date'=>(string)($report['date']??''),
            'class_name'=>(string)($report['class_name']??''),'meal'=>(string)($report['meal']??''),'changes'=>[],
            'changed_count'=>(int)($report['absent_count']??0),'student_count'=>(int)($report['student_count']??0),
            'changed_by'=>(string)($report['reported_by']??''),'changed_by_id'=>'',
            'changed_at'=>(string)($report['updated_at']??$report['created_at']??noitru_now()),
            'after_lock'=>false,'submission_mode'=>'regular','legacy_import'=>true,
        ];
        $known[$reportId] = true; $knownKeys[$reportKey] = true; $changed = true;
    }
    if ($changed) save_json(NOITRU_MEAL_HISTORY, array_values(array_slice($rows, -3000)));
    return $rows;
}

