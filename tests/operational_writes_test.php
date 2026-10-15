<?php
/** Runs production functions against temporary fixtures, never operational data. */
require __DIR__ . '/../includes/json_store.php';

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
require __DIR__ . '/support/load_functions.php';

$dir = $argv[2] ?? (sys_get_temp_dir() . '/cds-operational-test-' . bin2hex(random_bytes(6)));
if (!is_dir($dir)) mkdir($dir, 0700, true);
define('NOITRU_DIR', $dir);
define('NOITRU_MEALS', $dir . '/meals.json');
define('NOITRU_MEAL_REPORTS', $dir . '/reports.json');
define('LB_WEEKLY_REVIEWS_FILE', $dir . '/reviews.json');
function noitru_ensure_dir() {}
function load_json($file, $default = []) { return cds_json_load($file, $default); }
function save_json($file, $data) {
    if (($GLOBALS['fail_write_path'] ?? '') === $file) return false;
    return cds_json_save($file, $data);
}
function noitru_now() { return '2026-10-14T22:00:00+07:00'; }
function noitru_uid($prefix) { return $prefix . '_' . bin2hex(random_bytes(6)); }
function cds_meal_sql_read_effective() { return empty($GLOBALS['cds_force_json_meal_read']); }
function cds_meal_sql_daily_all() { throw new RuntimeException('Writer must not use a stale SQL mirror'); }
function cds_meal_sql_reports_data() { throw new RuntimeException('Writer must read current JSON'); }
function cds_meal_sql_fallback($context, $error) { throw $error; }
function cds_meal_pending_status() { return $GLOBALS['pending'] ?? null; }
function cds_meal_shadow_daily_row($row) {
    // Widen the competing-request window; the shared lock must cover the mirror too.
    usleep(2000);
    $GLOBALS['mirrored'][] = $row;
    if (!empty($GLOBALS['mirror_failure'])) { $GLOBALS['pending'] = ['scope'=>'test']; return false; }
    return true;
}
function cds_meal_shadow_daily_rows($rows, $date) { $GLOBALS['mirrored_days'][] = [$date, $rows]; return true; }
function cds_meal_shadow_reports_data($data) { return true; }
function cds_meal_shadow_sync($scope) { return true; }
function cds_meal_shadow_notify_failure() { $GLOBALS['warning'] = true; }
function noitru_boarders_on_date($date) { return [['id'=>'s1'], ['id'=>'s2'], ['id'=>'s3']]; }
function noitru_student_away_on_date($id, $date) { return $id === 's2' ? ['reason'=>'Nghỉ phép'] : null; }
function noitru_boarders_live() { return [['id'=>'s1','class_name'=>'6A']]; }

$mealFunctions = ['noitru_meal_write','noitru_meal_read_cache_clear','noitru_meals_all',
    'noitru_meal_upsert_many','noitru_meal_upsert_many_unlocked','noitru_meals_replace_rows','noitru_meal_upsert','noitru_meal_upsert_unlocked','noitru_meals_generate_day',
    'noitru_meals_generate_day_unlocked','noitru_meals_lock_day','noitru_meals_lock_day_unlocked',
    'noitru_meal_target_key','noitru_meal_deleted_targets','noitru_meal_deleted_targets_save',
    'noitru_meal_deleted_targets_save_unlocked','noitru_meal_reports_data','noitru_meal_reports_save',
    'noitru_meal_reports_save_unlocked','noitru_meal_report_upsert','noitru_meal_report_upsert_unlocked',
    'noitru_meal_state_set','noitru_meal_state_set_unlocked'];
load_functions(__DIR__ . '/../includes/noitru_store.php', $mealFunctions);
load_functions(__DIR__ . '/../includes/noitru_meal_history.php', ['nt_meal_history_delete_related_data', 'nt_meal_history_delete_related_data_unlocked']);

if (($argv[1] ?? '') === 'worker') {
    $worker = (int)$argv[3];
    for ($i = 0; $i < 8; $i++) {
        check(noitru_meal_upsert(['date'=>'2026-10-14','student_id'=>"w$worker-$i",'sang'=>'yes']), 'Concurrent meal save');
        noitru_meal_report_upsert(['date'=>'2026-10-14','class_name'=>"class-$worker-$i",'meal'=>'sang','student_snapshot'=>[['id'=>'fixture']],'eat_count'=>1]);
    }
    exit(0);
}

try {
    $corrupt = $dir . '/corrupt.json';
    foreach (['{"broken":', '', 'null', 'false', '"text"'] as $bytes) {
        file_put_contents($corrupt, $bytes); $called = false;
        check(!cds_json_update($corrupt, function ($rows) use (&$called) { $called = true; return []; }), 'Corrupt update refused');
        check(!$called && file_get_contents($corrupt) === $bytes, 'Corrupt bytes preserved');
    }
    check(cds_json_update($dir . '/new.json', fn($rows) => [['id'=>'new']]), 'Missing store initialized');
    file_put_contents($corrupt, '[]');
    check(cds_json_update($corrupt, fn($rows) => [['id'=>'valid']]), 'Valid empty JSON still writable');

    $old = ['id'=>'old','date'=>'2026-10-14','student_id'=>'s1','sang'=>'yes','trua'=>'no','toi'=>'yes','locked'=>true,'note'=>'Giữ nguyên','created_at'=>'2026-08-01'];
    cds_json_save(NOITRU_MEALS, [$old]);
    check(!noitru_meal_upsert(['date'=>$old['date'],'student_id'=>'s1','sang'=>'no']), 'Locked meal refused');
    check(cds_json_load(NOITRU_MEALS) === [$old], 'Locked row unchanged');
    check(noitru_meal_upsert(['date'=>$old['date'],'student_id'=>'s1','sang'=>'no','force'=>true]), 'Force rule retained');
    $saved = cds_json_load(NOITRU_MEALS)[0];
    check($saved['trua'] === 'no' && $saved['toi'] === 'yes' && $saved['id'] === 'old' && $saved['note'] === 'Giữ nguyên' && $saved['created_at'] === '2026-08-01' && !isset($saved['force']), 'Untouched fields/identity retained');
    check(noitru_meals_generate_day('2026-10-14') === 2, 'Generation skips existing locked pupil');
    $rows = cds_json_load(NOITRU_MEALS);
    check($rows[1]['student_id'] === 's2' && $rows[1]['sang'] === 'no' && $rows[1]['source'] === 'auto_exit', 'Approved exit rule retained');
    check($rows[2]['student_id'] === 's3' && $rows[2]['trua'] === 'yes', 'Normal generation retained');
    check(noitru_meals_lock_day('2026-10-14', false), 'Unlock supported');
    check(!cds_json_load(NOITRU_MEALS)[0]['locked'], 'Unlock preserved');

    check(noitru_meal_upsert_many([
        ['date'=>'2026-10-14','student_id'=>'s1','sang'=>'yes'],
        ['date'=>'2026-10-14','student_id'=>'s1','toi'=>'no'],
    ]), 'Batch accepts sequential updates to same pupil');
    $batchRow=cds_json_load(NOITRU_MEALS)[0];
    check($batchRow['id']==='old' && $batchRow['trua']==='no' && $batchRow['sang']==='yes' && $batchRow['toi']==='no','Batch preserves identity and untouched meals');
    check(noitru_meal_upsert_many([['date'=>'2026-10-14','student_id'=>'s1','sang'=>'no']],true),'Sparse form save');
    check(cds_json_load(NOITRU_MEALS)[0]['toi']==='no','Sparse form cannot overwrite another meal with a stale value');

    nt_meal_history_delete_related_data([['date'=>'2026-10-14','class_name'=>'6A','meal'=>'sang']]);
    $afterDelete = cds_json_load(NOITRU_MEALS);
    check(!isset($afterDelete[0]['sang']) && $afterDelete[0]['trua'] === 'no' && $afterDelete[1]['sang'] === 'no', 'History deletion retains other meals and other pupils');

    // A request-local cached read must not overwrite a later request's row.
    $GLOBALS['noitru_meals_all_cache'] = [];
    check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'s4','sang'=>'yes']), 'Latest source used');
    check(count(cds_json_load(NOITRU_MEALS)) === 4, 'Stale cache cannot drop history');
    $before = file_get_contents(NOITRU_MEALS);
    $mirrorCount = count($GLOBALS['mirrored'] ?? []);
    check(!noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'failed-write','sang'=>'yes','note'=>"\xB1"]), 'Source write failure returned');
    check(file_get_contents(NOITRU_MEALS) === $before && count($GLOBALS['mirrored'] ?? []) === $mirrorCount, 'Write failure preserves bytes and does not publish SQL');
    unset($GLOBALS['fail_write_path']);

    $before = file_get_contents(NOITRU_MEALS);
    file_put_contents(NOITRU_MEAL_REPORTS, '{invalid');
    check(!noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'s5','sang'=>'yes']), 'Corrupt report source aborts meal write');
    check(file_get_contents(NOITRU_MEALS) === $before, 'Source unchanged on corrupt companion');
    cds_json_save(NOITRU_MEAL_REPORTS, ['reports'=>[], 'states'=>[], 'settings'=>[]]);
    check(noitru_meal_write(fn() => noitru_meal_write(fn() => 42)) === 42, 'Nested write cannot deadlock');
    check(noitru_meal_write(function () { throw new RuntimeException('Fixture abort'); }) === false, 'Failure returned');
    check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'after-failure','sang'=>'yes']), 'Lock released after failure');

    cds_json_save(NOITRU_MEALS, [$old]);
    $workers = [];
    for ($i = 0; $i < 6; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $dir, (string)$i], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        check(is_resource($process), 'Worker started'); fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0, 'Worker failed: ' . $output);
    }
    $rows = cds_json_load(NOITRU_MEALS);
    check(count($rows) === 49 && $rows[0] === $old, 'All 48 concurrent inserts and historical row retained');
    check(count(cds_json_load(NOITRU_MEAL_REPORTS)['reports']) === 48, 'Concurrent class reports retained');

    $GLOBALS['fail_write_path'] = NOITRU_MEAL_REPORTS;
    $beforeReports = file_get_contents(NOITRU_MEAL_REPORTS);
    check(noitru_meal_report_upsert(['date'=>'2026-10-15','class_name'=>'failure','meal'=>'sang','student_snapshot'=>[['id'=>'fixture']]]) === false, 'Report failure propagated');
    check(file_get_contents(NOITRU_MEAL_REPORTS) === $beforeReports, 'Failed report retains existing source');
    unset($GLOBALS['fail_write_path']);

    $GLOBALS['mirror_failure'] = true;
    check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'mirror-failure','sang'=>'yes']), 'JSON survives SQL mirror failure');
    check(!empty($GLOBALS['warning']) && !empty($GLOBALS['cds_force_json_meal_read']), 'Pending mirror keeps JSON fallback');
    echo "PASS: corrupt JSON preservation, meal business rules, cache freshness, nested locks, 6 concurrent writers, SQL fallback\n";
} finally {
    foreach (glob($dir . '/*') as $file) if (is_file($file)) unlink($file);
    @unlink($dir . '/.meal-write.lock'); @rmdir($dir);
}
