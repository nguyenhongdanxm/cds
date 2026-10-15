<?php
/** Real process termination on disposable files; no configuration/bootstrap. */
require __DIR__ . '/../includes/json_store.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$mode = $argv[1] ?? '';
$root = $argv[2] ?? sys_get_temp_dir() . '/cds-interruption-' . bin2hex(random_bytes(8));
check(str_starts_with($root, sys_get_temp_dir() . '/cds-interruption-') && !str_contains($root, '..'), 'Unsafe temporary path');
if (!is_dir($root)) mkdir($root, 0700);
$file = $root . '/source.json';
if ($mode === 'hold') {
    cds_json_update($file, static function ($rows) use ($root) {
        touch($root . '/ready');
        sleep(30);
        return [['id'=>'must-not-commit']];
    });
    exit(0);
}
if ($mode === 'save') {
    $rows = array_fill(0, 6000, ['id'=>'replacement','value'=>str_repeat('x', 1000)]);
    touch($root . '/ready');
    for ($i = 0; $i < 100; $i++) check(cds_json_save($file, $rows), 'Atomic save');
    exit(0);
}
function start_child($mode, $root) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, $mode, $root], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    check(is_resource($process), 'Child started'); fclose($pipes[0]);
    return [$process, $pipes];
}
function wait_until(callable $ready, $message) {
    $deadline = microtime(true) + 10;
    while (!$ready()) {
        check(microtime(true) < $deadline, $message);
        usleep(500);
    }
}
function kill_child($process, $pipes) {
    check(proc_terminate($process, 9), 'Terminate isolated child');
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    proc_close($process);
}
$process = null; $pipes = [];
try {
    $original = [['id'=>'old','name'=>'Dữ liệu giữ nguyên','value'=>17]];
    check(cds_json_save($file, $original), 'Seed source');
    $hash = hash_file('sha256', $file);
    [$process,$pipes] = start_child('hold',$root);
    wait_until(fn() => is_file($root . '/ready'), 'Lock holder did not become ready');
    kill_child($process,$pipes); $process = null;
    check(hash_file('sha256',$file) === $hash, 'Termination before commit preserves exact source');
    $probe = fopen($file . '.lock','c');
    check(flock($probe, LOCK_EX | LOCK_NB), 'OS releases lock after terminated writer');
    flock($probe,LOCK_UN); fclose($probe);
    check(cds_json_update($file, fn($rows) => array_merge($rows,[['id'=>'after-crash']])), 'Update works after crash');
    $beforeAtomic = cds_json_load($file);
    unlink($root . '/ready');
    [$process,$pipes] = start_child('save',$root);
    wait_until(fn() => is_file($root . '/ready'), 'Atomic writer did not become ready');
    wait_until(fn() => count(glob($file . '.tmp.*')) > 0, 'No temporary write observed');
    kill_child($process,$pipes); $process = null;
    $raw = file_get_contents($file); $rows = json_decode($raw,true);
    check(json_last_error() === JSON_ERROR_NONE && is_array($rows), 'Interrupted atomic save never publishes partial JSON');
    check($rows === $beforeAtomic || (count($rows) === 6000 && count(array_filter($rows, fn($row) => $row !== ['id'=>'replacement','value'=>str_repeat('x',1000)])) === 0), 'Source is either previous commit or whole next commit');
    echo "PASS: terminated lock holder, exact source hash, released lock, next update, interrupted temporary write and atomic JSON publication\n";
} finally {
    if (is_resource($process)) kill_child($process,$pipes);
    foreach (glob($root . '/*') as $path) if (is_file($path)) unlink($path);
    @rmdir($root);
}
