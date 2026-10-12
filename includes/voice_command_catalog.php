<?php
if (!defined('CDS_VOICE_COMMANDS_FILE') && defined('DATA_PATH')) {
    define('CDS_VOICE_COMMANDS_FILE', DATA_PATH . '/voice_commands.json');
}

function cds_voice_module_keywords(): array {
    return [
        'tintuc'=>['tin tức','website','bài viết'],
        'chuyenmon'=>['chuyên môn','kế hoạch dạy học','phân công'],
        'chunhiem'=>['chủ nhiệm','tiện ích chủ nhiệm','lớp chủ nhiệm'],
        'vanban'=>['văn bản','công văn','văn bản mới'],
        'thuvien'=>['thư viện','thiết bị','sách'],
        'csdl'=>['cơ sở dữ liệu','dữ liệu','học sinh','giáo viên','CSDL'],
        'hoclieu'=>['học liệu','trò chơi','game'],
        'noitru'=>['nội trú','ký túc xá','KTX'],
        'thidua'=>['thi đua','chấm công','xếp loại'],
        'trolyai'=>['trợ lý AI','trợ lý','trí tuệ nhân tạo','AI'],
    ];
}

function cds_voice_quick_action_catalog(): array {
    return [
        'Điểm danh'=>['id'=>'action_attendance','url'=>'noitru_attendance.php','category'=>'Nội trú','keywords'=>['điểm danh nội trú','điểm danh học sinh'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền điểm danh.','permission'=>'nt.diemdanh','module_id'=>'noitru'],
        'Báo ăn'=>['id'=>'action_meals','url'=>'noitru.php?tab=meals','category'=>'Nội trú','keywords'=>['báo ăn nội trú','báo bữa ăn'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền báo ăn.','permission'=>'nt.baoan','module_id'=>'noitru'],
        'Chấm công'=>['id'=>'action_teacher_attendance','url'=>'thidua.php?section=teacher_attendance','category'=>'Thi đua','keywords'=>['chấm công giáo viên'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền chấm công.','permission'=>'td.teacher_attendance','module_id'=>'thidua'],
        'Hồ sơ đánh giá'=>['id'=>'action_evaluation_profile','url'=>'danhgia.php?view=profile','category'=>'Chuyên môn','keywords'=>['hồ sơ đánh giá','đánh giá cá nhân'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền xem hồ sơ.','permission'=>'cm.baocao.dugio','module_id'=>'chuyenmon'],
        'Chuyên môn'=>['id'=>'action_professional','url'=>defined('URL_CHUYEN_MON')?URL_CHUYEN_MON:'chuyenmon/','category'=>'Chuyên môn','keywords'=>['quản lý chuyên môn'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền module.','permission'=>'cm.dashboard','module_id'=>'chuyenmon'],
        'Thời khóa biểu'=>['id'=>'action_timetable','url'=>'thoikhoabieu.php','category'=>'Chuyên môn','keywords'=>['thời khóa biểu','TKB'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền Chuyên môn.','module_id'=>'chuyenmon'],
    ];
}

function cds_voice_default_commands(): array {
    $commands = [[
        'id'=>'home',
        'name'=>'Trang chủ',
        'url'=>'admin.php',
        'category'=>'Chung',
        'keywords'=>['tổng quan','màn hình chính','home'],
        'note'=>'Trang tổng quan CDS.',
        'enabled'=>true,
        'show_to'=>'all',
        'module_id'=>'',
        'permission'=>'',
    ]];
    if (function_exists('get_ecosystem_modules')) {
        foreach (get_ecosystem_modules() as $module) {
            if (($module['status'] ?? '') === 'soon') continue;
            $id = (string)($module['id'] ?? '');
            $moduleUrl = (string)($module['url'] ?? '');
            $urlIsInternal = cds_voice_url_is_internal($moduleUrl);
            $commands[] = [
                'id'=>'module_' . $id,
                'name'=>(string)($module['title'] ?? ''),
                'url'=>$urlIsInternal ? $moduleUrl : '',
                'category'=>(string)($module['title'] ?? ''),
                'keywords'=>cds_voice_module_keywords()[$id] ?? [],
                'note'=>!$urlIsInternal
                    ? 'Đích menu nằm ngoài hệ thống; quản trị cần đặt URL nội bộ để bật điều hướng giọng nói.'
                    : (($module['status'] ?? '') === 'link'
                    ? 'Mở trang liên kết; quyền truy cập phụ thuộc trang đích.'
                    : 'Chỉ mở nếu tài khoản có quyền module.'),
                'enabled'=>$urlIsInternal,
                'show_to'=>'all',
                'module_id'=>($module['status'] ?? '') === 'link' ? '' : $id,
                'permission'=>'',
            ];
        }
    }
    foreach (cds_voice_quick_action_catalog() as $name=>$action) {
        $commands[] = [
            'id'=>$action['id'],
            'name'=>$name,
            'url'=>$action['url'],
            'category'=>$action['category'],
            'keywords'=>$action['keywords'],
            'note'=>$action['note'],
            'enabled'=>true,
            'show_to'=>'all',
            'module_id'=>$action['module_id'] ?? '',
            'permission'=>$action['permission'] ?? '',
        ];
    }
    return $commands;
}

function cds_voice_load_commands(): array {
    $defaults = cds_voice_default_commands();
    $saved = load_json(CDS_VOICE_COMMANDS_FILE, null);
    if (!is_array($saved)) return $defaults;
    return array_values(array_filter($saved, 'is_array'));
}

function cds_voice_url_is_internal(string $url): bool {
    $url = trim($url);
    if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\\\\]/', $url)) return false;
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
    $path = (string)($parts['path'] ?? '');
    if ($path === '' || str_starts_with($path, '//')) return false;
    foreach (explode('/', rawurldecode($path)) as $segment) {
        if ($segment === '..') return false;
    }
    $basePath = parse_url((string)(defined('BASE_URL') ? BASE_URL : '/'), PHP_URL_PATH) ?: '/';
    if (str_starts_with($path, '/') && $basePath !== '/' && !str_starts_with($path, rtrim($basePath, '/') . '/')) return false;
    return true;
}

function cds_voice_default_by_id(): array {
    $defaults = [];
    foreach (cds_voice_default_commands() as $command) $defaults[$command['id']] = $command;
    return $defaults;
}

function cds_voice_save_commands(array $rows): bool {
    if (count($rows) > 50) throw new InvalidArgumentException('Tối đa 50 câu lệnh.');
    $defaults = cds_voice_default_by_id();
    $clean = [];
    $ids = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !empty($row['remove'])) continue;
        $id = trim((string)($row['id'] ?? ''));
        if ($id === '') $id = 'custom_' . bin2hex(random_bytes(8));
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id) || isset($ids[$id])) {
            throw new InvalidArgumentException('Mã câu lệnh không hợp lệ hoặc bị trùng.');
        }
        $name = trim((string)($row['name'] ?? ''));
        $url = trim((string)($row['url'] ?? ''));
        $category = trim((string)($row['category'] ?? ''));
        $note = trim((string)($row['note'] ?? ''));
        $length = static function (string $value): int {
            return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        };
        if ($name === '' || $length($name) > 80 || $category === '' || $length($category) > 40 || $length($note) > 300) {
            throw new InvalidArgumentException('Tên, nhóm hoặc ghi chú vượt giới hạn cho phép.');
        }
        $enabled = !empty($row['enabled']);
        if (($url !== '' && !cds_voice_url_is_internal($url)) || ($enabled && $url === '')) {
            throw new InvalidArgumentException('Chỉ được dùng đường dẫn nội bộ CDS.');
        }
        $rawKeywords = $row['keywords'] ?? [];
        if (is_string($rawKeywords)) $rawKeywords = preg_split('/\R/u', $rawKeywords) ?: [];
        if (!is_array($rawKeywords)) $rawKeywords = [];
        $keywords = [];
        foreach (array_slice($rawKeywords, 0, 20) as $keyword) {
            $keyword = trim((string)$keyword);
            if ($keyword === '') continue;
            if ($length($keyword) > 80) throw new InvalidArgumentException('Từ khóa không được dài quá 80 ký tự.');
            $keywords[] = $keyword;
        }
        $default = $defaults[$id] ?? [];
        $clean[] = [
            'id'=>$id,
            'name'=>$name,
            'url'=>$url,
            'category'=>$category,
            'keywords'=>$keywords,
            'note'=>$note,
            'enabled'=>$enabled,
            'show_to'=>($row['show_to'] ?? 'all') === 'admin' ? 'admin' : 'all',
            'module_id'=>(string)($default['module_id'] ?? cds_voice_module_for_url($url)),
            'permission'=>(string)($default['permission'] ?? cds_voice_permission_for_url($url)),
        ];
        $ids[$id] = true;
    }
    return save_json(CDS_VOICE_COMMANDS_FILE, $clean);
}

function cds_voice_module_for_url(string $url): string {
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
    if ($path === '') return '';
    foreach (cds_voice_quick_action_catalog() as $action) {
        if ($path !== (string)(parse_url((string)$action['url'], PHP_URL_PATH) ?: '')) continue;
        if (!empty($action['module_id'])) return (string)$action['module_id'];
    }
    $paths = [];
    if (function_exists('get_ecosystem_modules')) {
        foreach (get_ecosystem_modules() as $module) {
            if (($module['status'] ?? '') === 'soon') continue;
            $moduleUrl = (string)($module['url'] ?? '');
            $moduleParts = parse_url($moduleUrl);
            if (!is_array($moduleParts) || isset($moduleParts['scheme']) || isset($moduleParts['host'])) continue;
            $paths[(string)($module['id'] ?? '')][] = (string)($moduleParts['path'] ?? '');
        }
    }
    if (function_exists('permission_modules_catalog')) {
        foreach (permission_modules_catalog() as $id=>$module) $paths[(string)$id][] = (string)($module['page'] ?? '');
    }
    if (defined('URL_CHUYEN_MON')) $paths['chuyenmon'][] = (string)parse_url(URL_CHUYEN_MON, PHP_URL_PATH);
    $paths['chunhiem'][] = 'tienich_chunhiem.php';
    $targetPath = '/' . ltrim($path, '/');
    foreach ($paths as $id=>$modulePaths) {
        foreach ($modulePaths as $modulePath) {
            if ($modulePath === '') continue;
            $modulePath = '/' . ltrim($modulePath, '/');
            if (rtrim($targetPath, '/') === rtrim($modulePath, '/')
                || (str_ends_with($modulePath, '/') && str_starts_with($targetPath, $modulePath))) return $id;
        }
    }
    $basename = basename(rtrim($targetPath, '/'));
    foreach (array_keys(function_exists('permission_modules_catalog') ? permission_modules_catalog() : []) as $id) {
        if (str_starts_with($basename, (string)$id . '_')) return (string)$id;
    }
    return '';
}

function cds_voice_module_is_available(string $moduleId): bool {
    if (!function_exists('get_ecosystem_modules')) return true;
    foreach (get_ecosystem_modules() as $module) {
        if (($module['id'] ?? '') === $moduleId && ($module['status'] ?? '') !== 'soon') return true;
    }
    return false;
}

function cds_voice_permission_for_url(string $url): string {
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
    foreach (cds_voice_quick_action_catalog() as $action) {
        if ($path !== '' && $path === (string)(parse_url((string)$action['url'], PHP_URL_PATH) ?: '')) {
            return (string)($action['permission'] ?? '');
        }
    }
    return '';
}

function cds_voice_command_allowed(array $command, array $user): bool {
    if (($user['role'] ?? '') === 'admin') return true;
    if (($command['show_to'] ?? '') !== 'all') return false;
    $module = (string)($command['module_id'] ?? '');
    if ($module === '') $module = cds_voice_module_for_url((string)($command['url'] ?? ''));
    if ($module !== '' && !cds_voice_module_is_available($module)) return false;
    if (!empty($command['permission'])) {
        return function_exists('can_perm') && can_perm((string)$command['permission']);
    }
    if ($module !== '') {
        return function_exists('can_module') && can_module($module, 'view');
    }
    return true;
}

function cds_voice_commands_for_user(array $user): array {
    return array_values(array_filter(cds_voice_load_commands(), static function ($command) use ($user): bool {
        return !empty($command['enabled']) && trim((string)($command['url'] ?? '')) !== ''
            && cds_voice_command_allowed($command, $user);
    }));
}
