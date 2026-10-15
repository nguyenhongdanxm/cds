<?php
/** Disposable GitHub service only. Never load a school's instance/configuration. */
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
check(getenv('GITHUB_ACTIONS') === 'true' && getenv('CDS_TEST_MYSQL') === 'disposable-ci', 'Refusing database access outside disposable CI');
$port = (string)getenv('CDS_TEST_MYSQL_PORT');
check(ctype_digit($port) && (int)$port > 0, 'Missing disposable service port');
function cds_instance_config($key = null, $default = null) {
    if ($key === 'database') return ['host'=>'127.0.0.1','port'=>getenv('CDS_TEST_MYSQL_PORT'),
        'database'=>'cds_disposable_test','username'=>'cds_fixture','password'=>'fixture-only-password','charset'=>'utf8mb4'];
    return $default;
}
$worker = in_array(($argv[1] ?? ''), ['worker','crash'], true);
$root = $worker ? (string)($argv[2] ?? '') : sys_get_temp_dir() . '/cds-mysql-fixture-' . bin2hex(random_bytes(8));
check(str_starts_with($root, sys_get_temp_dir() . '/cds-mysql-fixture-') && !str_contains($root, '..'), 'Unsafe fixture path');
if (!$worker) mkdir($root . '/noitru', 0700, true);
check(is_dir($root . '/noitru'), 'Fixture not initialized');
define('DATA_PATH', $root);
define('NOITRU_DIR', $root . '/noitru');
define('NOITRU_MEALS', NOITRU_DIR . '/meals_daily.json');
define('NOITRU_MEAL_REPORTS', NOITRU_DIR . '/meal_reports.json');
require __DIR__ . '/../includes/json_store.php';
require __DIR__ . '/../includes/database_meals.php';
require __DIR__ . '/../includes/database_meal_read.php';
require __DIR__ . '/../includes/database_migrations.php';
require __DIR__ . '/support/load_functions.php';
function load_json($file, $default = []) { return cds_json_load($file, $default); }
function save_json($file, $data) { return cds_json_save($file, $data); }
function noitru_ensure_dir() {}
function noitru_uid($prefix) { return $prefix . '_' . bin2hex(random_bytes(8)); }
function noitru_now() { return date('c'); }
function current_user() { return ['id'=>'fixture','name'=>'Fixture','username'=>'fixture']; }
function noitru_boarders_on_date($date) { return [['id'=>'fixture','name'=>'Fixture','class_name'=>'6A']]; }
function new_request() {
    foreach (['cds_force_json_meal_read','cds_meal_sql_read_cache','cds_meal_sql_read_status_cache','cds_meal_shadow_enabled_cache'] as $key) unset($GLOBALS[$key]);
    noitru_meal_read_cache_clear();
}
load_functions(__DIR__ . '/../includes/noitru_store.php', [
    'noitru_meal_write','noitru_meal_read_cache_clear','noitru_meals_all','noitru_meals_for_date',
    'noitru_meal_upsert_many','noitru_meal_upsert_many_unlocked','noitru_meals_replace_rows','noitru_meal_upsert','noitru_meal_upsert_unlocked','noitru_meals_lock_day','noitru_meals_lock_day_unlocked',
    'noitru_meal_target_key','noitru_meal_deleted_targets','noitru_meal_deleted_targets_save','noitru_meal_deleted_targets_save_unlocked',
    'noitru_meal_reports_data','noitru_meal_reports_save','noitru_meal_reports_save_unlocked',
    'noitru_meal_report_upsert','noitru_meal_report_upsert_unlocked','noitru_meal_state_set','noitru_meal_state_set_unlocked',
]);
$pdo = cds_db();
check($pdo->query('SELECT DATABASE()')->fetchColumn() === 'cds_disposable_test', 'Wrong database');
if ($worker) {
    if (($argv[1] ?? '') === 'crash') {
        noitru_meal_write(static function () {
            check(noitru_meals_replace_rows([], [['id'=>'crash','date'=>'2026-10-15','student_id'=>'crash','sang'=>'yes']]), 'Crash fixture JSON published');
            exit(86); // Stop after JSON publication, before the SQL mirror.
        });
        exit(87);
    }
    $id = (int)($argv[3] ?? 0);
    for ($i = 0; $i < 8; $i++) {
        check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>"w$id-$i",'sang'=>'yes','trua'=>'no','toi'=>'yes']), 'Concurrent SQL meal save');
        check(noitru_meal_report_upsert(['date'=>'2026-10-15','class_name'=>"class-$id-$i",'meal'=>'sang','student_snapshot'=>[['id'=>'fixture']],'eat_count'=>1]), 'Concurrent SQL report save');
    }
    exit(0);
}
$renamed = false;
try {
    check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() === 0, 'Service database must be empty');
    check(count(cds_db_run_pending_migrations()) > 0, 'Production migrations applied');
    check(cds_db_run_pending_migrations() === [], 'Migrations idempotent');
    $old = ['id'=>'historical','date'=>'2026-08-01','student_id'=>'old','sang'=>'no','trua'=>'yes','toi'=>'yes','note'=>'Giữ nguyên','custom'=>['a'=>1]];
    cds_json_save(NOITRU_MEALS, [$old]);
    cds_json_save(NOITRU_MEAL_REPORTS, ['reports'=>[],'states'=>[],'settings'=>['sang_lock_time'=>'20:00','trua_lock_time'=>'09:00','toi_lock_time'=>'15:00']]);
    check(cds_meal_import_snapshot(current_user())['is_match'], 'Initial JSON/SQL match');
    cds_meal_shadow_set(true, current_user());
    cds_meal_sql_read_set(true, current_user());
    check(cds_meal_sql_read_effective(), 'SQL enabled');
    check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'first','sang'=>'yes','trua'=>'no','toi'=>'yes']), 'Real SQL update');
    check(cds_meal_compare_snapshot()['is_match'], 'Exact raw JSON match after write');
    check(noitru_meal_upsert_many([
        ['date'=>'2026-10-15','student_id'=>'first','sang'=>'no'],
        ['date'=>'2026-10-15','student_id'=>'first','toi'=>'no'],
    ]), 'Real SQL batch with sparse updates');
    check(cds_meal_compare_snapshot()['is_match'], 'Real SQL batch matches source exactly');
    check(noitru_meal_state_set('2026-10-15','sang','locked','Fixture'), 'Meal state write');
    check(noitru_meals_lock_day('2026-10-15', true), 'Day lock write');
    $before = file_get_contents(NOITRU_MEALS);
    check(!noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'first','sang'=>'no']), 'Locked write refused');
    check(file_get_contents(NOITRU_MEALS) === $before && cds_meal_compare_snapshot()['is_match'], 'Refusal preserves both stores');
    check(noitru_meals_lock_day('2026-10-15', false), 'Unlock');

    // A real SQL error, restricted to this disposable database.
    $pdo->exec('RENAME TABLE cds_meal_daily TO cds_meal_daily_unavailable'); $renamed = true;
    check(noitru_meal_upsert(['date'=>'2026-10-15','student_id'=>'sql-failure','sang'=>'no']), 'JSON commit survives SQL failure');
    check(is_array(cds_meal_pending_status()), 'Durable pending marker exists');
    new_request();
    check(!cds_meal_sql_read_effective(), 'New request cannot read stale SQL while pending');
    check(isset(noitru_meals_for_date('2026-10-15')['sql-failure']), 'New request sees JSON fallback');
    $pdo->exec('RENAME TABLE cds_meal_daily_unavailable TO cds_meal_daily'); $renamed = false;
    check(noitru_meal_write(static fn() => cds_meal_shadow_sync('integration_recovery')), 'Real SQL recovery');
    check(cds_meal_compare_snapshot()['is_match'] && cds_meal_pending_status() === null, 'Recovered stores agree');
    new_request(); check(cds_meal_sql_read_effective(), 'SQL safely re-enabled on next request');

    $workers = [];
    for ($id = 0; $id < 4; $id++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $root, (string)$id], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        check(is_resource($process), 'Worker started'); fclose($pipes[0]); $workers[] = [$process,$pipes];
    }
    foreach ($workers as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); check(proc_close($process) === 0, 'Worker failed: ' . $output);
    }
    new_request();
    check(cds_meal_compare_snapshot()['is_match'], 'Concurrent JSON/real SQL hash match');
    $source = cds_meal_source_snapshot();
    check(count($source['daily']) === 35 && count($source['reports']) === 32, 'All concurrent records retained');
    check($source['daily'][0] === $old, 'Historical record retained exactly');
    check($pdo->query("SELECT raw_json FROM cds_meal_daily WHERE student_id='old'")->fetchColumn() === cds_meal_json($old), 'Historical SQL record retained');
    $pipes=[];
    $process=proc_open([PHP_BINARY,__FILE__,'crash',$root],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    check(is_resource($process),'Crash worker started'); fclose($pipes[0]);
    $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($process)===86,'Interrupted exactly after source publication: '.$output);
    check(is_file(cds_meal_inflight_path()) && !cds_meal_compare_snapshot()['is_match'],'Unfinished JSON/SQL write is durable and detected');
    new_request();
    check(!cds_meal_sql_read_effective() && isset(noitru_meals_for_date('2026-10-15')['crash']),'Fresh request must not read stale SQL after interrupted writer');
    check(noitru_meal_write(static fn()=>cds_meal_shadow_sync('crash_recovery')),'Interrupted writer recovery');
    new_request();
    check(cds_meal_compare_snapshot()['is_match'] && cds_meal_sql_read_effective() && !is_file(cds_meal_inflight_path()),'Crash recovery restores both stores before SQL reads resume');
    cds_meal_sql_read_set(false,current_user());
    new_request(); check(!cds_meal_sql_read_effective() && count(noitru_meals_all()) === 36, 'Explicit JSON-only read mode retained');
    echo 'PASS real SQL: migrations, read on/off, locks, injected SQL failure, cross-request fallback, recovery, 4 concurrent writers, exact history. Server: ' . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
} finally {
    if ($renamed) $pdo->exec('RENAME TABLE cds_meal_daily_unavailable TO cds_meal_daily');
    foreach (glob(NOITRU_DIR . '/*') as $file) if (is_file($file)) unlink($file);
    @unlink(NOITRU_DIR . '/.meal-write.lock'); @rmdir(NOITRU_DIR); @rmdir($root);
}
