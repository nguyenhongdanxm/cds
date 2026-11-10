<?php
$repoRoot = dirname(__DIR__);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once $repoRoot . '/includes/instance_config.php';
require_once $repoRoot . '/includes/release_manifest.php';

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
    $path = cds_instance_config_path();
    if (getenv('CDS_INSTANCE_CONFIG') || is_file(dirname(__DIR__).'/includes/instance_binding.php')) {
        if (!is_file($path) && getenv('CDS_DEPLOY_PATH')) return [];
        if (!is_file($path)) throw new RuntimeException('Missing explicit instance configuration.');
        return cds_instance_config();
    }
    if (is_file($path)) return cds_deploy_read_json($path);
    // Existing cPanel repositories used HOME; do not use this fallback for a new school profile.
    $legacy = cds_deploy_read_json(cds_deploy_private_dir() . '/instance.json');
    return ($legacy['deployment']['profile'] ?? '') === 'school' ? [] : $legacy;
}
function cds_deploy_read_config(): array
{
    $custom = getenv('CDS_DEPLOY_CONFIG');
    $path = (is_string($custom) && trim($custom) !== '') ? trim($custom) : cds_deploy_private_dir() . '/deploy.json';
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
    if (is_link($source) || is_link($target)) throw new RuntimeException('Symlink deploy is not allowed.');
    if (!is_file($source)) throw new RuntimeException('Missing source file: ' . $source);
    $dir = dirname($target);
    for ($check=$dir; $check !== dirname($check); $check=dirname($check)) if (is_link($check)) throw new RuntimeException('Symlink deploy directory.');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Cannot create directory: ' . $dir);
    if (basename($source) === '.htaccess' && is_file($target)) {
        $existing = (string)file_get_contents($target);
        $managed = (string)file_get_contents($source);
        $begin = '# BEGIN CDS PRIVATE DATA'; $end = '# END CDS PRIVATE DATA';
        if (str_contains($existing, $begin) !== str_contains($existing, $end)) throw new RuntimeException('Incomplete managed Apache rules; review target .htaccess.');
        if (str_contains($existing, $begin)) $merged = preg_replace_callback('/'.preg_quote($begin,'/').'.*?'.preg_quote($end,'/').'/s', static fn()=>rtrim($managed), $existing);
        else $merged = rtrim($existing) . "\n\n" . $managed;
        if (!is_string($merged) || file_put_contents($target, $merged, LOCK_EX) === false) throw new RuntimeException('Cannot update managed Apache rules.');
        return;
    }
    if (!copy($source, $target)) throw new RuntimeException('Copy failed: ' . $source . ' -> ' . $target);
}
function cds_deploy_copy_tree(string $source, string $target): void
{
    if (!is_dir($source)) throw new RuntimeException('Missing source directory: ' . $source);
    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) throw new RuntimeException('Cannot create directory: ' . $target);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $sourceRelative = ltrim(substr($item->getPathname(), strlen(dirname(__DIR__))), '/');
        if (!cds_release_path_allowed($sourceRelative) || (isset($GLOBALS['cdsDeployManifest']) && !$item->isDir() && !isset($GLOBALS['cdsDeployManifest'][$sourceRelative]))) continue;
        if ($item->isLink()) throw new RuntimeException('Symlink source is not allowed.');
        $dest = $target . '/' . $relative;
        if (is_link($dest)) throw new RuntimeException('Symlink target is not allowed.');
        if ($item->isDir()) {
            if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) throw new RuntimeException('Cannot create directory: ' . $dest);
        } else {
            cds_deploy_copy_file($item->getPathname(), $dest);
        }
    }
}

$envTarget = getenv('CDS_DEPLOY_PATH');
$instance = cds_deploy_read_instance();
$compatConfig = ($instance['deployment']['profile'] ?? '') === 'school' ? [] : cds_deploy_read_config();
$instanceTarget = is_array($instance['deployment'] ?? null) ? (string)($instance['deployment']['target_path'] ?? '') : '';
$target = is_string($envTarget) && trim($envTarget) !== '' ? trim($envTarget) : ($instanceTarget !== '' ? $instanceTarget : (string)($compatConfig['target_path'] ?? ''));
$target = cds_deploy_normalize_target($target);
if ($target === '') { fwrite(STDERR, "INVALID_DEPLOY_TARGET\n"); exit(2); }
// Validate and display the source plan before touching the destination.
$manifest = cds_release_manifest($repoRoot);
$GLOBALS['cdsDeployManifest'] = array_fill_keys($manifest, true);
if (in_array('--dry-run', $argv, true)) { echo 'CDS_DEPLOY_PLAN ' . $target . ' · ' . count($manifest) . " source files; runtime excluded\n"; exit; }
if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) { fwrite(STDERR, "CANNOT_CREATE_DEPLOY_TARGET\n"); exit(3); }

$directories = ['assets', 'includes', 'chuyenmon'];
$rootFiles = [
    '.htaccess','game_asset.php','notice_file.php',
    'ioffice_settings.php','bin/ioffice-vault.php','activity.php','admin.php','admin_operation_settings.php','notices.php','drive_viewer.php','public_notice.php',
    'public_drive_viewer.php','public_ktx_exit_file.php','drive_file.php','drive_settings.php',
    'hoclieu_game_muc.php','hoclieu_game_buzzer.php','hoclieu_game_buzzer_api.php','hoclieu.php','hoclieu_game_thabong.php','hoclieu_game_rocket.php','hoclieu_clock.php','hoclieu_file.php','hoclieu_game.php','hoclieu_game_secret.php','hoclieu_game_secret_api.php','hoclieu_game_keoco.php','hoclieu_game_keoco_api.php','hoclieu_game_keoco_match.php','hoclieu_game_keoco_team.php','hoclieu_game_keoco_screen.php','hoclieu_game_chopnot.php','hoclieu_game_duavit.php','hoclieu_game_loto.php','hoclieu_game_qr_trial.php','hoclieu_game_quiz.php','hoclieu_game_quiz_media.php','hoclieu_game_quiz_join.php','hoclieu_game_quiz_screen.php','vaochoi.php','chieu.php','quet.php','hoclieu_quiz_cards.php','hoclieu_game_olympia.php','hoclieu_game_olympia_sets.php','hoclieu_game_olympia_play.php','hoclieu_game_olympia_screen.php','hoclieu_game_olympia_live.php','hoclieu_game_olympia_rankings.php','olympia_stage.php','olympia_stage_api.php','olympia_san_khau_preview.html',
    'chuyenmon.php','csdl.php','csdl_preweeks.php','csdl_export.php','csdl_export_filtered_excel.php',
    'csdl_statistics_export_xlsx.php','csdl_student_cards.php','csdl_staff_cards.php','staff_card_teachers.php','staff_photo.php','staff_verify.php','danhgia.php','dashboard_settings.php',
    'database_admin.php','instance_settings.php','initial_setup.php','index.php','login.php','logout.php','manifest.php','manifest.webmanifest','sw.js',
    'noitru.php','noitru_health_excel.php','noitru_overview_api.php','noitru_duty_drive.php','noitru_duty_reports_zip.php','noitru_exit.php','noitru_exit_manager.php',
    'noitru_exit_drive_api.php','noitru_exit_check_api.php','noitru_exit_check.php','noitru_attendance.php',
    'noitru_list.php','noitru_assign.php','noitru_assign_enhanced.php','noitru_assign_sync.php',
    'noitru_room_template.php','noitru_room_roles.php','noitru_room_roles_data.php','noitru_room_quick_save.php',
    'noitru_meal_quantity_data.php','noitru_medicine_excel.php','push_api.php','student_card_students.php',
    'student_photo.php','student_verify.php','thoikhoabieu.php','tkb_xep.php','tkb_editor.php','tkb_constraints.php','tkb_publish.php','trolyai.php','ai_api.php','ai_document_download.php','thuvien.php','thuvien_book_supplement.php',
    'thuvien_bienban.php','thietbi_phieu.php','thietbi_mau.php','thietbi_bienban.php','thidua.php','thidua_danhgia.php','thidua_baiviet.php','thidua_phongnoitru.php','tienich_chunhiem.php',
    'thidua_phongnoitru_delete.php','thidua_phongnoitru_history_api.php','users.php','vanban.php','vanban_open.php',
    'vanban_preview.php','vanban_preview_file.php','voice-commands-help.php'
];
foreach ($directories as $dir) { if (!is_dir($repoRoot . '/' . $dir)) { fwrite(STDERR, 'DEPLOY_SOURCE_MISSING: ' . $dir . "\n"); exit(4); } }
foreach ($rootFiles as $file) { if (!is_file($repoRoot . '/' . $file)) { fwrite(STDERR, 'DEPLOY_SOURCE_MISSING: ' . $file . "\n"); exit(4); } }
try {
    foreach ($directories as $dir) cds_deploy_copy_tree($repoRoot . '/' . $dir, $target . '/' . $dir);
    foreach ($rootFiles as $file) cds_deploy_copy_file($repoRoot . '/' . $file, $target . '/' . $file);
} catch (Throwable $e) {
    fwrite(STDERR, 'DEPLOY_FAILED: ' . $e->getMessage() . "\n");
    exit(4);
}
echo 'CDS_DEPLOY_OK ' . $target . PHP_EOL;
