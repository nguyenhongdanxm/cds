<?php
/**
 * One-time cleanup for legacy Chuyên môn > Thông báo.
 * - Removes legacy kh_thongbao rows from data/cm_docs.json once.
 * - Removes the Thông báo tab from deployed chuyenmon/kehoach.php.
 * - Redirects old ?tab=thongbao URLs to ?tab=vanban.
 */
$root = rtrim($argv[1] ?? '', '/');
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "INVALID_ROOT\n");
    exit(2);
}

$kehoach = $root . '/chuyenmon/kehoach.php';
if (is_file($kehoach)) {
    $src = (string)file_get_contents($kehoach);
    $src = str_replace("    'thongbao' => ['Thông báo', 'bi-megaphone'],\n", '', $src);

    $guard = "if ((\$_GET['tab'] ?? '') === 'thongbao') {\n    header('Location: ' . BASE_URL . 'kehoach.php?tab=vanban');\n    exit;\n}\n";
    if (strpos($src, "header('Location: ' . BASE_URL . 'kehoach.php?tab=vanban')") === false) {
        $needle = "require_login();\n";
        if (strpos($src, $needle) !== false) {
            $src = str_replace($needle, $needle . "\n" . $guard, $src);
        }
    }
    file_put_contents($kehoach, $src, LOCK_EX);
}

$dataDir = $root . '/data';
$marker = $dataDir . '/.legacy_chuyenmon_notices_purged_v1';
$docsFile = $dataDir . '/cm_docs.json';
if (!is_file($marker)) {
    $rows = [];
    if (is_file($docsFile)) {
        $decoded = json_decode((string)file_get_contents($docsFile), true);
        if (is_array($decoded)) $rows = $decoded;
    }
    $before = count($rows);
    $rows = array_values(array_filter($rows, static function ($row) {
        return !is_array($row) || (($row['section'] ?? '') !== 'kh_thongbao');
    }));
    if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
    file_put_contents($docsFile, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    file_put_contents($marker, date('c') . "\nremoved=" . ($before - count($rows)) . "\n", LOCK_EX);
    echo "PURGED_OLD_CHUYENMON_NOTICES=" . ($before - count($rows)) . "\n";
} else {
    echo "OLD_CHUYENMON_NOTICES_ALREADY_PURGED\n";
}

echo "CHUYENMON_NOTICE_TAB_REMOVED\n";
