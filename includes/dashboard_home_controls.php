<?php
/** Điều khiển nội dung trang chủ dành cho quản trị. */
if (!defined('DATA_PATH') || !function_exists('load_json')) return;

function cds_home_controls_file(): string {
    return DATA_PATH . '/dashboard_home_controls.json';
}

function cds_home_controls_defaults(): array {
    $moduleIds = [];
    if (function_exists('get_ecosystem_modules')) {
        foreach (get_ecosystem_modules() as $module) {
            if (($module['status'] ?? '') !== 'soon') $moduleIds[] = (string)($module['id'] ?? '');
        }
    } else {
        $moduleIds = ['tintuc','chuyenmon','csdl','noitru','thidua'];
    }
    return [
        'visible_modules' => array_values(array_filter($moduleIds)),
        'birthday_enabled' => true,
        'notices' => [],
    ];
}

function cds_home_controls(): array {
    $defaults = cds_home_controls_defaults();
    $saved = load_json(cds_home_controls_file(), []);
    if (!is_array($saved)) $saved = [];
    $result = array_merge($defaults, $saved);
    $result['visible_modules'] = array_values(array_unique(array_filter(array_map('strval', is_array($result['visible_modules'] ?? null) ? $result['visible_modules'] : []))));
    $result['birthday_enabled'] = !array_key_exists('birthday_enabled', $result) || !empty($result['birthday_enabled']);
    $result['notices'] = array_values(array_filter(is_array($result['notices'] ?? null) ? $result['notices'] : [], fn($row) => is_array($row) && trim((string)($row['text'] ?? '')) !== ''));
    return $result;
}

function cds_home_controls_save(array $data): void {
    $defaults = cds_home_controls_defaults();
    $validModules = $defaults['visible_modules'];
    $visibleModules = array_values(array_intersect($validModules, array_values(array_unique(array_map('strval', $data['visible_modules'] ?? [])))));
    $notices = [];
    foreach (($data['notices'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $text = trim((string)($row['text'] ?? ''));
        if ($text === '') continue;
        $type = (string)($row['type'] ?? 'info');
        if (!in_array($type, ['info','success','warning','danger'], true)) $type = 'info';
        $notices[] = [
            'id' => trim((string)($row['id'] ?? '')) ?: ('notice_' . bin2hex(random_bytes(4))),
            'text' => function_exists('mb_substr') ? mb_substr($text, 0, 500, 'UTF-8') : substr($text, 0, 500),
            'type' => $type,
            'active' => !empty($row['active']),
            'created_at' => (string)($row['created_at'] ?? date('c')),
        ];
    }
    save_json(cds_home_controls_file(), [
        'visible_modules' => $visibleModules,
        'birthday_enabled' => !empty($data['birthday_enabled']),
        'notices' => array_slice($notices, -20),
        'updated_at' => date('c'),
    ]);
}

function cds_home_controls_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cds_home_controls_notice_html(array $notices): string {
    $active = array_values(array_filter($notices, fn($row) => !empty($row['active']) && trim((string)($row['text'] ?? '')) !== ''));
    if (!$active) return '';
    $icons = ['info'=>'bi-info-circle-fill','success'=>'bi-check-circle-fill','warning'=>'bi-exclamation-triangle-fill','danger'=>'bi-exclamation-octagon-fill'];
    $html = '<div class="home-admin-notices" style="display:grid;gap:.5rem;margin-top:.75rem">';
    foreach ($active as $row) {
        $type = in_array($row['type'] ?? '', ['info','success','warning','danger'], true) ? $row['type'] : 'info';
        $html .= '<div class="home-admin-notice home-admin-notice-' . $type . '" style="display:flex;align-items:flex-start;gap:.55rem;padding:.65rem .8rem;border-radius:12px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22)"><i class="bi ' . $icons[$type] . '"></i><span>' . cds_home_controls_escape((string)$row['text']) . '</span></div>';
    }
    return $html . '</div>';
}

function cds_home_controls_filter_admin_html(string $html): string {
    $settings = cds_home_controls();
    if (empty($settings['birthday_enabled'])) {
        $html = preg_replace('/<div class="birthday-line">.*?<\/form><\/div>/s', '', $html, 1) ?? $html;
    }

    $visible = array_flip($settings['visible_modules']);
    $moduleUrls = [];
    if (function_exists('get_ecosystem_modules')) {
        foreach (get_ecosystem_modules() as $module) {
            $id = (string)($module['id'] ?? '');
            if ($id !== '' && !isset($visible[$id])) $moduleUrls[] = (string)($module['url'] ?? '');
        }
    }
    foreach (array_filter($moduleUrls) as $url) {
        $quoted = preg_quote(cds_home_controls_escape($url), '/');
        $html = preg_replace('/<a href="' . $quoted . '"[^>]*>.*?<\/a>/s', '', $html) ?? $html;
    }

    $noticeHtml = cds_home_controls_notice_html($settings['notices']);
    if ($noticeHtml !== '') {
        $html = preg_replace('/(<div class="welcome-copy">.*?<h1>.*?<\/h1>)/s', '$1' . $noticeHtml, $html, 1) ?? $html;
    }
    return $html;
}

$homeScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($homeScript === 'admin.php' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    require_once __DIR__ . '/modules.php';
    ob_start('cds_home_controls_filter_admin_html');
}
