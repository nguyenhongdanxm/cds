<?php
function cds_dashboard_widgets_catalog(): array {
    return [
        'welcome' => ['title'=>'Chào ngày mới','icon'=>'bi-stars','color'=>'#2563eb','module'=>'','size'=>'wide'],
        'modules' => ['title'=>'Module của tôi','icon'=>'bi-grid-1x2','color'=>'#0f766e','module'=>'','size'=>'wide'],
        'quick_actions' => ['title'=>'Thao tác nhanh','icon'=>'bi-lightning-charge','color'=>'#d97706','module'=>'','size'=>'normal'],
        'recent_activity' => ['title'=>'Hoạt động gần đây','icon'=>'bi-clock-history','color'=>'#7c3aed','module'=>'','size'=>'normal'],
        'noitru' => ['title'=>'Tổng quan nội trú','icon'=>'bi-building','color'=>'#db2777','module'=>'noitru','size'=>'normal'],
        'thidua' => ['title'=>'Thi đua tuần','icon'=>'bi-trophy','color'=>'#ca8a04','module'=>'thidua','size'=>'normal'],
        'account' => ['title'=>'Tài khoản và phạm vi','icon'=>'bi-person-badge','color'=>'#475569','module'=>'','size'=>'normal'],
    ];
}

function cds_dashboard_settings_file(): string { return DATA_PATH . '/dashboard_settings.json'; }
function cds_dashboard_settings(): array {
    $defaults = ['enabled'=>array_keys(cds_dashboard_widgets_catalog()), 'order'=>array_keys(cds_dashboard_widgets_catalog())];
    $saved = load_json(cds_dashboard_settings_file(), []);
    return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
}
function cds_dashboard_save(array $enabled, array $order): void {
    $valid = array_keys(cds_dashboard_widgets_catalog());
    $enabled = array_values(array_intersect($valid, $enabled));
    $order = array_values(array_unique(array_merge(array_intersect($valid, $order), $valid)));
    save_json(cds_dashboard_settings_file(), ['enabled'=>$enabled,'order'=>$order,'updated_at'=>date('c')]);
}
function cds_dashboard_user_widgets(array $user): array {
    $catalog = cds_dashboard_widgets_catalog(); $settings = cds_dashboard_settings(); $out=[];
    foreach ($settings['order'] as $id) {
        if (!isset($catalog[$id]) || !in_array($id, $settings['enabled'], true)) continue;
        $module = $catalog[$id]['module'];
        if ($module !== '' && ($user['role'] ?? '') !== 'admin' && !can_module($module, 'view')) continue;
        $out[$id] = $catalog[$id];
    }
    return $out;
}

