<?php
define('BASE_URL', '/');
define('DATA_PATH', '/tmp/cds-voice-command-test');
$GLOBALS['voice_modules'] = [];
$GLOBALS['voice_permissions'] = [];
$GLOBALS['voice_saved_commands'] = null;

function load_json($file, $default = []) {
    $GLOBALS['voice_loaded_file'] = $file;
    return $GLOBALS['voice_saved_commands'] ?? $default;
}
function save_json($file, $data) {
    $GLOBALS['voice_saved_file'] = $file;
    $GLOBALS['voice_saved_commands'] = $data;
    return true;
}
function get_ecosystem_modules() {
    $modules = [
        ['id'=>'tintuc','title'=>'Tin tức','status'=>'link','url'=>'https://news.example/'],
        ['id'=>'noitru','title'=>'Quản lý nội trú','status'=>'live','url'=>'/noitru.php'],
        ['id'=>'chuyenmon','title'=>'Chuyên môn','status'=>'live','url'=>'/chuyenmon/'],
    ];
    return array_values(array_filter($modules, static fn($module) => empty($GLOBALS['voice_disabled_modules'][$module['id']])));
}
function permission_modules_catalog() {
    return ['noitru'=>['page'=>'noitru.php'],'chuyenmon'=>['page'=>'chuyenmon.php']];
}
function can_module($module, $level = 'view') {
    return !empty($GLOBALS['voice_modules'][$module]);
}
function can_perm($permission) {
    return !empty($GLOBALS['voice_permissions'][$permission]);
}

require_once __DIR__ . '/../includes/voice_command_catalog.php';

function check_voice($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function command_row(string $id, string $name, string $url, string $showTo = 'all', bool $enabled = true): array {
    return [
        'id'=>$id,
        'name'=>$name,
        'url'=>$url,
        'category'=>'Tùy chỉnh',
        'keywords'=>"từ khóa một\ntừ khóa hai",
        'note'=>'',
        'show_to'=>$showTo,
        'enabled'=>$enabled,
    ];
}

$defaults = cds_voice_load_commands();
check_voice(count($defaults) >= 8, 'Default catalog was not loaded.');
$externalModule = array_values(array_filter($defaults, static fn($row) => ($row['id'] ?? '') === 'module_tintuc'))[0] ?? [];
check_voice(($externalModule['url'] ?? null) === '' && empty($externalModule['enabled']), 'External menu destination was enabled for voice navigation.');
check_voice(cds_voice_module_for_url('/chuyenmon/kehoach.php') === 'chuyenmon', 'Nested module URL was not permission-mapped.');
check_voice(cds_voice_module_for_url('/noitru_attendance_report_detail.php') === 'noitru', 'Module-specific sibling page was not permission-mapped.');
check_voice(cds_voice_url_is_internal('/local.php?tab=main'), 'Internal URL was rejected.');
foreach (['https://evil.example/path', '//evil.example/path', 'javascript:alert(1)', 'data:text/html,x', '../outside.php'] as $url) {
    check_voice(!cds_voice_url_is_internal($url), 'Unsafe URL was accepted: ' . $url);
}

cds_voice_save_commands([
    command_row('module_noitru', 'Nội trú', '/noitru.php'),
    command_row('action_attendance', 'Điểm danh', 'noitru_attendance.php'),
    command_row('', 'Báo cáo tùy chỉnh', '/noitru.php?tab=custom'),
    command_row('', 'Lệnh quản trị', '/reports.php', 'admin'),
    command_row('', 'Lệnh tắt', '/disabled.php', 'all', false),
    command_row('', 'Lệnh công khai', '/public.php'),
]);
check_voice($GLOBALS['voice_saved_file'] === DATA_PATH . '/voice_commands.json', 'Voice configuration was not stored in the data directory.');

$user = ['role'=>'gv'];
$visible = cds_voice_commands_for_user($user);
check_voice(array_column($visible, 'name') === ['Lệnh công khai'], 'A user without module permissions saw restricted commands.');
$GLOBALS['voice_modules']['noitru'] = true;
$visible = cds_voice_commands_for_user($user);
check_voice(in_array('Nội trú', array_column($visible, 'name'), true), 'Module permission did not expose its command.');
check_voice(in_array('Báo cáo tùy chỉnh', array_column($visible, 'name'), true), 'Custom URL for a known module was not permission-filtered correctly.');
check_voice(!in_array('Điểm danh', array_column($visible, 'name'), true), 'Quick action bypassed its feature permission.');
$GLOBALS['voice_permissions']['nt.diemdanh'] = true;
$visible = cds_voice_commands_for_user($user);
check_voice(in_array('Điểm danh', array_column($visible, 'name'), true), 'Quick action permission did not expose its command.');
$GLOBALS['voice_disabled_modules']['noitru'] = true;
$visible = cds_voice_commands_for_user($user);
check_voice(!in_array('Điểm danh', array_column($visible, 'name'), true), 'Quick action bypassed its disabled module.');
$GLOBALS['voice_disabled_modules']['noitru'] = false;
$adminCommands = cds_voice_commands_for_user(['role'=>'admin']);
check_voice(in_array('Lệnh quản trị', array_column($adminCommands, 'name'), true), 'Admin-only custom command was hidden from admin.');
check_voice(!in_array('Lệnh tắt', array_column($adminCommands, 'name'), true), 'Disabled command was exposed.');
check_voice($GLOBALS['voice_saved_commands'][1]['keywords'] === ['từ khóa một','từ khóa hai'], 'Multiline keywords were not saved as separate terms.');

$rejected = false;
try {
    cds_voice_save_commands([command_row('', 'External', 'https://evil.example/')]);
} catch (InvalidArgumentException $error) {
    $rejected = true;
}
check_voice($rejected, 'External URL was accepted by the save operation.');
echo "PASS: voice commands use defaults, enforce access rules, and reject unsafe URLs.\n";
