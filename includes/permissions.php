<?php
/**
 * Phân quyền hệ sinh thái CDS – 3 tầng:
 *  1) Module: none | view | edit | admin
 *  2) Chức năng / menu (perms[]): cm.*, nt.*, csdl.*
 *  3) Phạm vi lớp (classes[]): [] = mọi lớp; ['6A','7B'] = chỉ các lớp đó
 */

function permission_modules_catalog() {
    return [
        'chuyenmon' => ['label' => 'Chuyên môn', 'icon' => 'bi-journal-bookmark-fill'],
        'csdl'      => ['label' => 'Cơ sở dữ liệu', 'icon' => 'bi-database'],
        'noitru'    => ['label' => 'Nội trú', 'icon' => 'bi-building'],
        'vanban'    => ['label' => 'Văn bản', 'icon' => 'bi-file-earmark-text'],
        'thidua'    => ['label' => 'Thi đua', 'icon' => 'bi-trophy'],
        'truyenthong'=> ['label' => 'Truyền thông', 'icon' => 'bi-broadcast'],
        'hoclieu'   => ['label' => 'Học liệu – Thư viện – Thiết bị', 'icon' => 'bi-book'],
        'taichinh'  => ['label' => 'Tài chính – Kế toán', 'icon' => 'bi-cash-coin'],
    ];
}

/** Nhóm quyền chức năng – gắn module */
function permission_features_catalog() {
    return [
        // Chuyên môn
        'cm.tracuu'   => ['module' => 'chuyenmon', 'label' => 'Tra cứu phân công chuyên môn', 'group' => 'PCCM'],
        'cm.pccm'     => ['module' => 'chuyenmon', 'label' => 'Phân công · danh sách · đổi chéo · rà soát', 'group' => 'PCCM'],
        'cm.nhaplieu' => ['module' => 'chuyenmon', 'label' => 'Nhập liệu (GV · môn · lớp · kiêm nhiệm)', 'group' => 'PCCM'],
        'cm.thongke'  => ['module' => 'chuyenmon', 'label' => 'Thống kê PCCM', 'group' => 'PCCM'],
        'cm.kehoach'  => ['module' => 'chuyenmon', 'label' => 'Kế hoạch (văn bản · TB · chỉ tiêu)', 'group' => 'Kế hoạch'],
        'cm.baocao.dinhky' => ['module' => 'chuyenmon', 'label' => 'Báo cáo định kỳ', 'group' => 'Báo cáo'],
        'cm.baocao.tiendo' => ['module' => 'chuyenmon', 'label' => 'Tiến độ chương trình', 'group' => 'Báo cáo'],
        'cm.baocao.dugio'  => ['module' => 'chuyenmon', 'label' => 'Dự giờ', 'group' => 'Báo cáo'],
        'cm.baocao.kythi'  => ['module' => 'chuyenmon', 'label' => 'Kết quả cuộc thi', 'group' => 'Báo cáo'],
        'cm.dashboard'=> ['module' => 'chuyenmon', 'label' => 'Bảng điều khiển trang chủ CM', 'group' => 'Chung'],

        // CSDL
        'csdl.view'   => ['module' => 'csdl', 'label' => 'Xem danh sách GV / HS / lớp', 'group' => 'CSDL'],
        'csdl.edit'   => ['module' => 'csdl', 'label' => 'Thêm · sửa · xóa · nhập Excel', 'group' => 'CSDL'],
        'csdl.export' => ['module' => 'csdl', 'label' => 'Xuất dữ liệu', 'group' => 'CSDL'],
        'csdl.year'   => ['module' => 'csdl', 'label' => 'Quản lý năm học', 'group' => 'CSDL'],

        // Nội trú
        'nt.tongquan' => ['module' => 'noitru', 'label' => 'Tổng quan nội trú', 'group' => 'Nội trú'],
        'nt.danhsach' => ['module' => 'noitru', 'label' => 'Danh sách nội trú', 'group' => 'Nội trú'],
        'nt.diemdanh' => ['module' => 'noitru', 'label' => 'Điểm danh', 'group' => 'Nội trú'],
        'nt.baoan'    => ['module' => 'noitru', 'label' => 'Báo ăn / biểu ăn', 'group' => 'Nội trú'],
        'nt.ravao'    => ['module' => 'noitru', 'label' => 'Xin ra vào KTX', 'group' => 'Nội trú'],
        'nt.yte'      => ['module' => 'noitru', 'label' => 'Sức khỏe / y tế', 'group' => 'Nội trú'],
        'nt.lichtruc' => ['module' => 'noitru', 'label' => 'Lịch trực', 'group' => 'Nội trú'],
        'nt.thucdon'  => ['module' => 'noitru', 'label' => 'Thực đơn / nhóm ăn', 'group' => 'Nội trú'],
        'nt.thongke'  => ['module' => 'noitru', 'label' => 'Thống kê nội trú', 'group' => 'Nội trú'],

        // Văn bản
        'vb.xem'      => ['module' => 'vanban', 'label' => 'Xem văn bản và biểu mẫu', 'group' => 'Văn bản'],
        'vb.quanly'   => ['module' => 'vanban', 'label' => 'Thêm · sửa · phát hành văn bản', 'group' => 'Văn bản'],

        // Thi đua — quyền đến từng menu con
        'td.teacher_attendance'  => ['module' => 'thidua', 'label' => 'Giáo viên · Chấm công', 'group' => 'Thi đua'],
        'td.teacher_achievement' => ['module' => 'thidua', 'label' => 'Giáo viên · Thành tích', 'group' => 'Thi đua'],
        'td.teacher_rating'      => ['module' => 'thidua', 'label' => 'Giáo viên · Xếp loại', 'group' => 'Thi đua'],
        'td.student_score'       => ['module' => 'thidua', 'label' => 'Học sinh · Bảng điểm', 'group' => 'Thi đua'],
        'td.student_profile'     => ['module' => 'thidua', 'label' => 'Học sinh · Hồ sơ thi đua', 'group' => 'Thi đua'],
        'td.stats'               => ['module' => 'thidua', 'label' => 'Thống kê thi đua', 'group' => 'Thi đua'],
        'td.all_data'            => ['module' => 'thidua', 'label' => 'Xem dữ liệu của tất cả giáo viên/lớp', 'group' => 'Phạm vi dữ liệu'],

        // Truyền thông
        'tt.xem'      => ['module' => 'truyenthong', 'label' => 'Xem nội dung truyền thông', 'group' => 'Truyền thông'],
        'tt.bientap'  => ['module' => 'truyenthong', 'label' => 'Biên tập tin · bài · hoạt động', 'group' => 'Truyền thông'],
        'tt.xuatban'  => ['module' => 'truyenthong', 'label' => 'Duyệt và xuất bản', 'group' => 'Truyền thông'],

        // Học liệu, thư viện, thiết bị
        'hl.xem'      => ['module' => 'hoclieu', 'label' => 'Xem học liệu và danh mục', 'group' => 'Học liệu'],
        'hl.thuvien'  => ['module' => 'hoclieu', 'label' => 'Quản lý thư viện · mượn trả', 'group' => 'Học liệu'],
        'hl.thietbi'  => ['module' => 'hoclieu', 'label' => 'Quản lý thiết bị', 'group' => 'Học liệu'],
        'hl.kiemtra'  => ['module' => 'hoclieu', 'label' => 'Ngân hàng đề và kiểm tra', 'group' => 'Học liệu'],

        // Tài chính
        'tc.xem'      => ['module' => 'taichinh', 'label' => 'Xem dữ liệu tài chính được giao', 'group' => 'Tài chính'],
        'tc.capnhat'  => ['module' => 'taichinh', 'label' => 'Cập nhật thu · chi · chế độ', 'group' => 'Tài chính'],
        'tc.baocao'   => ['module' => 'taichinh', 'label' => 'Xem và xuất báo cáo tài chính', 'group' => 'Tài chính'],
    ];
}

function permission_levels() {
    return ['none' => 'Không', 'view' => 'Xem', 'edit' => 'Sửa', 'delete' => 'Xóa', 'admin' => 'Quản trị module'];
}

function permission_access_levels($includeInherit = false) {
    $levels = ['none' => 'Không quyền', 'view' => 'Xem', 'edit' => 'Sửa', 'delete' => 'Xóa'];
    return $includeInherit ? ['inherit' => 'Theo nhóm'] + $levels : $levels;
}

function permission_default_groups() {
    $view = function (array $codes) {
        return array_fill_keys($codes, 'view');
    };
    $edit = function (array $codes) {
        return array_fill_keys($codes, 'edit');
    };
    $cmReports = ['cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'];
    $cmView = array_merge(['cm.dashboard','cm.tracuu','cm.thongke','cm.kehoach'], $cmReports);
    $cmEdit = ['cm.pccm','cm.nhaplieu'];
    $ntView = ['nt.tongquan','nt.danhsach','nt.thongke'];
    $ntEdit = ['nt.diemdanh','nt.baoan','nt.ravao'];
    $tdSections = ['td.teacher_attendance','td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'];
    $tdNonAttendance = ['td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'];

    return [
        'bgh' => [
            'label' => 'Ban giám hiệu',
            'access' => array_merge(
                $view(array_merge($cmView, $ntView, array_merge(['csdl.view','csdl.export','vb.xem','tt.xem','hl.xem','tc.xem','tc.baocao','td.all_data'], $tdSections))),
                $edit($cmEdit)
            ),
        ],
        'totruong' => [
            'label' => 'Tổ trưởng chuyên môn',
            'access' => array_merge(
                $view(array_merge(['cm.dashboard','cm.tracuu','cm.thongke','cm.kehoach','csdl.view'], $cmReports)),
                $edit(['cm.pccm','td.teacher_attendance'])
            ),
        ],
        'gvcn' => [
            'label' => 'Giáo viên chủ nhiệm',
            'access' => array_merge($view(['cm.dashboard','cm.tracuu','csdl.view','nt.danhsach','td.student_score']), $edit($ntEdit)),
        ],
        'gv' => [
            'label' => 'Giáo viên',
            'access' => $view(array_merge(['cm.dashboard','cm.tracuu'], $cmReports)),
        ],
        'qlnt' => [
            'label' => 'Cán bộ nội trú / y tế',
            'access' => array_merge($view(['csdl.view']), $edit(array_keys(array_filter(
                permission_features_catalog(),
                fn($meta) => ($meta['module'] ?? '') === 'noitru'
            )))),
        ],
        'vanthu' => [
            'label' => 'Văn thư',
            'access' => array_merge($view(['csdl.view','vb.xem']), $edit(['vb.quanly'])),
        ],
        'ketoan' => [
            'label' => 'Kế toán',
            'access' => array_merge($view(['csdl.view','csdl.export','tc.xem','tc.baocao']), $edit(['tc.capnhat'])),
        ],
        'doandoi' => [
            'label' => 'Đoàn – Đội',
            'access' => array_merge(
                $view(array_merge(['cm.dashboard','cm.tracuu','csdl.view','nt.danhsach','tt.xem','td.all_data'], $tdSections)),
                $edit(array_merge($tdNonAttendance, ['tt.bientap']))
            ),
        ],
        'thuvien_thietbi' => [
            'label' => 'Thư viện – Thiết bị',
            'access' => array_merge($view(['csdl.view','hl.xem']), $edit(['hl.thuvien','hl.thietbi'])),
        ],
    ];
}

function permission_groups_file() {
    return DATA_PATH . '/permission_groups.json';
}

function permission_groups_all() {
    $defaults = permission_default_groups();
    $saved = load_json(permission_groups_file(), []);
    if (!is_array($saved) || !$saved) return $defaults;

    foreach ($defaults as $key => $group) {
        if (!isset($saved[$key]) || !is_array($saved[$key])) {
            $saved[$key] = $group;
            continue;
        }
        $saved[$key]['label'] = trim((string)($saved[$key]['label'] ?? $group['label'])) ?: $group['label'];
        $saved[$key]['access'] = is_array($saved[$key]['access'] ?? null) ? $saved[$key]['access'] : [];
    }
    // Tổ trưởng được chấm công; phạm vi đúng tổ được giới hạn tại thidua.php.
    if (isset($saved['totruong']) && level_rank($saved['totruong']['access']['td.teacher_attendance'] ?? 'none') < level_rank('edit')) {
        $saved['totruong']['access']['td.teacher_attendance'] = 'edit';
    }
    if (isset($saved['gvcn']) && !isset($saved['gvcn']['access']['td.student_score'])) {
        $saved['gvcn']['access']['td.student_score'] = 'view';
    }
    if (isset($saved['gvcn']) && level_rank($saved['gvcn']['access']['nt.diemdanh'] ?? 'none') < level_rank('edit')) {
        $saved['gvcn']['access']['nt.diemdanh'] = 'edit';
    }
    if (isset($saved['doandoi']) && level_rank($saved['doandoi']['access']['td.teacher_attendance'] ?? 'none') > level_rank('view')) {
        $saved['doandoi']['access']['td.teacher_attendance'] = 'view';
    }

    // Tự chuyển các nhóm quyền cũ sang quyền menu con.
    foreach ($saved as &$group) {
        if (!is_array($group)) continue;
        $group['access'] = is_array($group['access'] ?? null) ? $group['access'] : [];
        if (isset($group['access']['td.xem']) || isset($group['access']['td.capnhat']) || isset($group['access']['td.duyet'])) {
            $legacyTdLevel = $group['access']['td.capnhat'] ?? ($group['access']['td.xem'] ?? 'view');
            foreach (['td.teacher_attendance','td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'] as $childCode) {
                if (!isset($group['access'][$childCode])) $group['access'][$childCode] = $legacyTdLevel;
            }
            if (isset($group['access']['td.duyet']) && !isset($group['access']['td.all_data'])) $group['access']['td.all_data'] = 'view';
            unset($group['access']['td.xem'], $group['access']['td.capnhat'], $group['access']['td.duyet']);
        }
        if (isset($group['access']['cm.baocao'])) {
            $legacyLevel = $group['access']['cm.baocao'];
            foreach (['cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'] as $childCode) {
                if (!isset($group['access'][$childCode])) $group['access'][$childCode] = $legacyLevel;
            }
            unset($group['access']['cm.baocao']);
        }
    }
    unset($group);
    // Chuẩn hóa lại sau khi chuyển quyền cũ.
    if (isset($saved['totruong'])) $saved['totruong']['access']['td.teacher_attendance'] = 'edit';
    if (isset($saved['gvcn']) && !isset($saved['gvcn']['access']['td.student_score'])) $saved['gvcn']['access']['td.student_score'] = 'view';
    if (isset($saved['gvcn'])) $saved['gvcn']['access']['nt.diemdanh'] = 'edit';
    if (isset($saved['doandoi']) && level_rank($saved['doandoi']['access']['td.teacher_attendance'] ?? 'none') > level_rank('view')) {
        $saved['doandoi']['access']['td.teacher_attendance'] = 'view';
    }
    return $saved;
}

function permission_groups_save(array $groups) {
    $catalog = permission_features_catalog();
    $clean = [];
    foreach ($groups as $key => $group) {
        if (!preg_match('/^[a-z0-9_]+$/', (string)$key)) continue;
        $label = trim((string)($group['label'] ?? $key));
        $access = [];
        foreach (($group['access'] ?? []) as $code => $level) {
            if (!isset($catalog[$code]) || !in_array($level, ['none','view','edit','delete'], true)) continue;
            if ($level !== 'none') $access[$code] = $level;
        }
        $clean[$key] = ['label' => $label !== '' ? $label : $key, 'access' => $access];
    }
    save_json(permission_groups_file(), $clean);
}

function permission_group_access_for_user(array $user) {
    $access = [];
    $groups = permission_groups_all();
    $userGroups = is_array($user['groups'] ?? null) ? $user['groups'] : [];
    // Vai trò chuẩn tự nhận nhóm quyền cùng tên. Điều này sửa các tài khoản
    // cũ đã chọn vai trò GVCN nhưng chưa lưu checkbox nhóm GVCN.
    $roleGroup = (string)($user['role'] ?? '');
    if (isset($groups[$roleGroup]) && !in_array($roleGroup, $userGroups, true)) $userGroups[] = $roleGroup;
    foreach ($userGroups as $groupKey) {
        foreach (($groups[$groupKey]['access'] ?? []) as $code => $level) {
            if (level_rank($level) > level_rank($access[$code] ?? 'none')) $access[$code] = $level;
        }
    }
    return $access;
}

function permission_legacy_access_for_user(array $user) {
    $access = [];
    $legacyPerms = is_array($user['perms'] ?? null) ? $user['perms'] : [];
    foreach ($legacyPerms as $code) $access[$code] = 'view';
    if (in_array('td.xem', $legacyPerms, true) || in_array('td.capnhat', $legacyPerms, true)) {
        $legacyTdLevel = in_array('td.capnhat', $legacyPerms, true) ? 'edit' : 'view';
        foreach (['td.teacher_attendance','td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'] as $childCode) {
            $access[$childCode] = $legacyTdLevel;
        }
    }
    if (in_array('td.duyet', $legacyPerms, true)) $access['td.all_data'] = 'view';
    if (in_array('cm.baocao', $legacyPerms, true)) {
        foreach (['cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'] as $childCode) {
            $access[$childCode] = 'view';
        }
    }
    foreach (permission_features_catalog() as $code => $meta) {
        $moduleLevel = $user['modules'][$meta['module']] ?? 'none';
        if (level_rank($moduleLevel) >= level_rank('edit')) $access[$code] = 'edit';
        elseif (level_rank($moduleLevel) >= level_rank('view') && !isset($access[$code])) $access[$code] = 'view';
    }
    return $access;
}

function permission_effective_access_for_user(array $user) {
    if (($user['role'] ?? '') === 'admin') {
        return array_fill_keys(array_keys(permission_features_catalog()), 'delete');
    }

    $access = (int)($user['permission_model_version'] ?? 1) >= 2
        ? []
        : permission_legacy_access_for_user($user);
    foreach (permission_group_access_for_user($user) as $code => $level) {
        if (level_rank($level) > level_rank($access[$code] ?? 'none')) $access[$code] = $level;
    }

    $overrides = is_array($user['permission_overrides'] ?? null) ? $user['permission_overrides'] : [];
    $legacyTdOverride = $overrides['td.capnhat'] ?? ($overrides['td.xem'] ?? 'inherit');
    if (in_array($legacyTdOverride, ['none','view','edit','delete'], true)) {
        foreach (['td.teacher_attendance','td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'] as $childCode) {
            if (!array_key_exists($childCode, $overrides)) $overrides[$childCode] = $legacyTdOverride;
        }
    }
    if (isset($overrides['td.duyet']) && !array_key_exists('td.all_data', $overrides)) {
        $overrides['td.all_data'] = $overrides['td.duyet'] === 'none' ? 'none' : 'view';
    }
    foreach ($overrides as $code => $level) {
        if (!isset(permission_features_catalog()[$code])) continue;
        if ($level === 'inherit') continue;
        if (in_array($level, ['none','view','edit','delete'], true)) $access[$code] = $level;
    }
    return $access;
}

function permission_access($perm, array $user = null) {
    $user = $user ?? current_user();
    if (!$user) return 'none';
    return permission_effective_access_for_user($user)[$perm] ?? 'none';
}

function can_perm_level($perm, $level = 'view') {
    return level_rank(permission_access($perm)) >= level_rank($level);
}

/** Role mẫu → modules + perms mặc định */
function permission_role_presets() {
    $allCm = ['cm.dashboard','cm.tracuu','cm.pccm','cm.nhaplieu','cm.thongke','cm.kehoach','cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'];
    $allNt = ['nt.tongquan','nt.danhsach','nt.diemdanh','nt.baoan','nt.ravao','nt.yte','nt.lichtruc','nt.thucdon','nt.thongke'];
    $allCs = ['csdl.view','csdl.edit','csdl.export','csdl.year'];

    return [
        'admin' => [
            'label' => 'Quản trị hệ thống',
            'modules' => ['chuyenmon'=>'admin','csdl'=>'admin','noitru'=>'admin','vanban'=>'admin','thidua'=>'admin'],
            'perms' => array_merge($allCm, $allCs, $allNt),
            'classes' => [], // mọi lớp
        ],
        'bgh' => [
            'label' => 'Ban giám hiệu',
            'modules' => ['chuyenmon'=>'edit','csdl'=>'view','noitru'=>'view','vanban'=>'view','thidua'=>'view'],
            'perms' => array_merge($allCm, ['csdl.view','csdl.export'], ['nt.tongquan','nt.danhsach','nt.thongke']),
            'classes' => [],
        ],
        'totruong' => [
            'label' => 'Tổ trưởng chuyên môn',
            'modules' => ['chuyenmon'=>'edit','csdl'=>'view','noitru'=>'none'],
            'perms' => ['cm.dashboard','cm.tracuu','cm.pccm','cm.thongke','cm.kehoach','cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi','csdl.view'],
            'classes' => [],
        ],
        'gvcn' => [
            'label' => 'Giáo viên chủ nhiệm',
            'modules' => ['chuyenmon'=>'view','csdl'=>'view','noitru'=>'edit'],
            'perms' => ['cm.tracuu','cm.dashboard','csdl.view','nt.diemdanh','nt.baoan','nt.ravao','nt.danhsach'],
            'classes' => [], // gán lớp chủ nhiệm khi tạo user
        ],
        'gv' => [
            'label' => 'Giáo viên',
            'modules' => ['chuyenmon'=>'view','csdl'=>'none','noitru'=>'none'],
            'perms' => ['cm.tracuu','cm.dashboard','cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'],
            'classes' => [],
        ],
        'ktx' => [
            'label' => 'Cán bộ KTX / y tế',
            'modules' => ['chuyenmon'=>'none','csdl'=>'view','noitru'=>'edit'],
            'perms' => array_merge(['csdl.view'], $allNt),
            'classes' => [],
        ],
        'custom' => [
            'label' => 'Tùy chỉnh',
            'modules' => ['chuyenmon'=>'none','csdl'=>'none','noitru'=>'none'],
            'perms' => [],
            'classes' => [],
        ],
    ];
}

function level_rank($level) {
    $map = ['none' => 0, 'view' => 1, 'edit' => 2, 'delete' => 3, 'admin' => 4];
    return $map[$level] ?? 0;
}

/** User hiện tại có quyền module ≥ level? */
function can_module($module, $level = 'view') {
    $u = current_user();
    if (!$u) return false;
    if (($u['role'] ?? '') === 'admin') return true;
    $have = (int)($u['permission_model_version'] ?? 1) >= 2
        ? 'none'
        : ($u['modules'][$module] ?? 'none');
    foreach (permission_features_catalog() as $code => $meta) {
        if (($meta['module'] ?? '') !== $module) continue;
        $featureLevel = permission_access($code, $u);
        if (level_rank($featureLevel) > level_rank($have)) $have = $featureLevel;
    }
    return level_rank($have) >= level_rank($level);
}

/** User có mã chức năng? */
function can_perm($perm) {
    return can_perm_level($perm, 'view');
}

function can_edit_perm($perm) {
    return can_perm_level($perm, 'edit');
}

function can_delete_perm($perm) {
    return can_perm_level($perm, 'delete');
}

/** Alias ngắn */
function can($permOrModule, $level = null) {
    if ($level !== null) return can_module($permOrModule, $level);
    // nếu là tên module trong catalog
    if (isset(permission_modules_catalog()[$permOrModule])) {
        return can_module($permOrModule, 'view');
    }
    return can_perm($permOrModule);
}

/**
 * Phạm vi lớp của user.
 * Trả về null = mọi lớp; mảng = chỉ các lớp đó.
 */
function allowed_classes() {
    $u = current_user();
    if (!$u) return [];
    if (($u['role'] ?? '') === 'admin') return null;
    $cls = is_array($u['classes'] ?? null) ? $u['classes'] : [];
    $homeroom = is_array($u['homeroom_classes'] ?? null) ? $u['homeroom_classes'] : [];
    if (in_array('gvcn', $u['groups'] ?? [], true)) {
        return array_values(array_unique(array_filter(array_map('strval', array_merge($cls, $homeroom)))));
    }
    if (!is_array($cls) || count($cls) === 0) return null; // không giới hạn
    return array_values(array_filter(array_map('strval', $cls)));
}

function can_class($className) {
    $allowed = allowed_classes();
    if ($allowed === null) return true;
    return in_array((string)$className, $allowed, true);
}

/** Lọc danh sách lớp theo quyền */
function filter_classes_by_permission(array $classes) {
    $allowed = allowed_classes();
    if ($allowed === null) return $classes;
    return array_values(array_filter($classes, fn($c) => in_array((string)$c, $allowed, true)));
}

function require_perm($perm) {
    require_perm_level($perm, 'view');
}

function require_perm_level($perm, $level = 'view') {
    require_login();
    if (!can_perm_level($perm, $level) && !can_module(permission_features_catalog()[$perm]['module'] ?? '', 'admin')) {
        flash('Bạn không có quyền thực hiện chức năng này.', 'danger');
        header('Location: ' . BASE_URL . 'admin.php');
        exit;
    }
}

function require_module($module, $level = 'view') {
    require_login();
    if (!can_module($module, $level)) {
        flash('Bạn không có quyền truy cập module này.', 'danger');
        header('Location: ' . BASE_URL . 'admin.php');
        exit;
    }
}

/** Áp preset role vào mảng user (khi tạo / đổi role) */
function apply_role_preset(array &$user, $roleKey) {
    $presets = permission_role_presets();
    if (!isset($presets[$roleKey])) $roleKey = 'custom';
    $p = $presets[$roleKey];
    $user['role'] = $roleKey;
    if ($roleKey !== 'custom') {
        $user['modules'] = $p['modules'];
        $user['perms'] = $p['perms'];
        // classes giữ nguyên nếu đã gán (VD GVCN)
        if (!isset($user['classes'])) $user['classes'] = $p['classes'];
    }
}
