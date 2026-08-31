<?php
/**
 * Config-driven CDS deploy wrapper.
 *
 * Priority for target path:
 *   1) CDS_DEPLOY_PATH environment variable
 *   2) unified instance.json -> deployment.target_path
 *   3) CDS_DEPLOY_CONFIG / deploy.json compatibility file
 *   4) legacy Xín Mần target (safe fallback for current production)
 */

$repoRoot = dirname(__DIR__);
$legacyTarget = '/home/capnachi/cds.noitruxinman.edu.vn';

function cds_deploy_private_dir(): string
{
    $home = getenv('HOME');
    if (is_string($home) && trim($home) !== '') return rtrim(trim($home), '/') . '/cds_private';
    return dirname(__DIR__, 3) . '/cds_private';
}

function cds_deploy_read_json(string $path): array
{
    if (!is_file($path) || !is_readable($path)) return [];
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function cds_deploy_read_instance(): array
{
    $custom = getenv('CDS_INSTANCE_CONFIG');
    $path = (is_string($custom) && trim($custom) !== '')
        ? trim($custom)
        : cds_deploy_private_dir() . '/instance.json';
    return cds_deploy_read_json($path);
}

function cds_deploy_read_config(): array
{
    $custom = getenv('CDS_DEPLOY_CONFIG');
    $path = (is_string($custom) && trim($custom) !== '')
        ? trim($custom)
        : cds_deploy_private_dir() . '/deploy.json';
    return cds_deploy_read_json($path);
}

function cds_deploy_normalize_target(string $path): string
{
    $path = rtrim(trim($path), '/');
    if ($path === '' || $path === '/' || strpos($path, "\0") !== false) return '';
    if ($path[0] !== '/' || preg_match('#(?:^|/)\.\.(?:/|$)#', $path)) return '';
    return $path;
}

function cds_deploy_copy_file(string $source, string $target): void
{
    if (!is_file($source)) throw new RuntimeException('Missing source file: ' . $source);
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create directory: ' . $dir);
    }
    if (!copy($source, $target)) throw new RuntimeException('Copy failed: ' . $source . ' -> ' . $target);
}

function cds_deploy_copy_tree(string $source, string $target): void
{
    if (!is_dir($source)) throw new RuntimeException('Missing source directory: ' . $source);
    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
        throw new RuntimeException('Cannot create directory: ' . $target);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $dest = $target . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
                throw new RuntimeException('Cannot create directory: ' . $dest);
            }
        } else {
            cds_deploy_copy_file($item->getPathname(), $dest);
        }
    }
}

$envTarget = getenv('CDS_DEPLOY_PATH');
$instance = cds_deploy_read_instance();
$compatConfig = cds_deploy_read_config();
$instanceTarget = is_array($instance['deployment'] ?? null) ? (string)($instance['deployment']['target_path'] ?? '') : '';
$target = is_string($envTarget) && trim($envTarget) !== ''
    ? trim($envTarget)
    : ($instanceTarget !== '' ? $instanceTarget : (string)($compatConfig['target_path'] ?? $legacyTarget));
$target = cds_deploy_normalize_target($target);
if ($target === '') {
    fwrite(STDERR, "INVALID_DEPLOY_TARGET\n");
    exit(2);
}

if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
    fwrite(STDERR, "CANNOT_CREATE_DEPLOY_TARGET\n");
    exit(3);
}

$directories = ['assets', 'includes', 'chuyenmon'];
$rootFiles = [
    'activity.php','admin.php','notices.php','drive_viewer.php','public_notice.php',
    'public_drive_viewer.php','public_ktx_exit_file.php','drive_file.php','drive_settings.php',
    'hoclieu.php','hoclieu_file.php',
    'chuyenmon.php','csdl.php','csdl_preweeks.php','csdl_export.php','csdl_export_filtered_excel.php',
    'csdl_statistics_export_xlsx.php','csdl_student_cards.php','danhgia.php','dashboard_settings.php',
    'database_admin.php','instance_settings.php','initial_setup.php','index.php','login.php','logout.php','manifest.php','manifest.webmanifest','sw.js',
    'noitru.php','noitru_overview_api.php','noitru_duty_drive.php','noitru_exit.php','noitru_exit_manager.php',
    'noitru_exit_drive_api.php','noitru_exit_check_api.php','noitru_exit_check.php','noitru_attendance.php',
    'noitru_list.php','noitru_assign.php','noitru_assign_enhanced.php','noitru_assign_sync.php',
    'noitru_room_template.php','noitru_room_roles.php','noitru_room_roles_data.php','noitru_room_quick_save.php',
    'noitru_meal_quantity_data.php','noitru_medicine_excel.php','push_api.php','student_card_students.php',
    'student_photo.php','student_verify.php','thoikhoabieu.php','thuvien.php','thuvien_book_supplement.php',
    'thuvien_bienban.php','thietbi_phieu.php','thidua.php','thidua_baiviet.php','thidua_phongnoitru.php',
    'thidua_phongnoitru_delete.php','thidua_phongnoitru_history_api.php','users.php','vanban.php','vanban_open.php',
    'vanban_preview.php','vanban_preview_file.php'
];

/*
 * Kiểm tra đầy đủ nguồn trước khi ghi bất kỳ tệp nào vào website. Nếu một
 * commit khai báo thiếu tệp hoặc thư mục, deploy dừng tại đây thay vì để lại
 * bản triển khai trộn giữa phiên bản cũ và mới.
 */
foreach ($directories as $dir) {
    if (!is_dir($repoRoot . '/' . $dir)) {
        fwrite(STDERR, 'DEPLOY_SOURCE_MISSING: ' . $dir . "\n");
        exit(4);
    }
}
foreach ($rootFiles as $file) {
    if (!is_file($repoRoot . '/' . $file)) {
        fwrite(STDERR, 'DEPLOY_SOURCE_MISSING: ' . $file . "\n");
        exit(4);
    }
}

try {
    foreach ($directories as $dir) cds_deploy_copy_tree($repoRoot . '/' . $dir, $target . '/' . $dir);
    foreach ($rootFiles as $file) cds_deploy_copy_file($repoRoot . '/' . $file, $target . '/' . $file);

    $postDeploy = [
        $repoRoot . '/tools/ensure_room_input_only_group.php',
        $repoRoot . '/tools/cleanup_chuyenmon_notice.php',
    ];
    foreach ($postDeploy as $script) {
        if (!is_file($script)) continue;
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($target);
        passthru($cmd, $status);
        if ($status !== 0) throw new RuntimeException('Post-deploy task failed: ' . basename($script));
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'DEPLOY_FAILED: ' . $e->getMessage() . "\n");
    exit(4);
}

echo 'CDS_DEPLOY_OK ' . $target . PHP_EOL;
