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
    ];
}

/** Nhóm quyền chức năng – gắn module */
function permission_features_catalog() {
    return [
        // Chuyên môn
        'cm.tracuu'   => ['module' => 'chuyenmon', 'label' => 'Tra cứu / xem kết quả phân công', 'group' => 'PCCM'],
        'cm.pccm'     => ['module' => 'chuyenmon', 'label' => 'Phân công · danh sách · đổi chéo · rà soát', 'group' => 'PCCM'],
        'cm.nhaplieu' => ['module' => 'chuyenmon', 'label' => 'Nhập liệu (GV · môn · lớp · kiêm nhiệm)', 'group' => 'PCCM'],
        'cm.thongke'  => ['module' => 'chuyenmon', 'label' => 'Thống kê PCCM', 'group' => 'PCCM'],
        'cm.kehoach'  => ['module' => 'chuyenmon', 'label' => 'Kế hoạch (văn bản · TB · chỉ tiêu)', 'group' => 'Kế hoạch'],
        'cm.baocao'   => ['module' => 'chuyenmon', 'label' => 'Báo cáo · dự giờ · cuộc thi', 'group' => 'Báo cáo'],
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
    ];
}

function permission_levels() {
    return ['none' => 'Không', 'view' => 'Xem', 'edit' => 'Sửa', 'admin' => 'Quản trị module'];
}

/** Role mẫu → modules + perms mặc định */
function permission_role_presets() {
    $allCm = ['cm.dashboard','cm.tracuu','cm.pccm','cm.nhaplieu','cm.thongke','cm.kehoach','cm.baocao'];
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
            'perms' => ['cm.dashboard','cm.tracuu','cm.pccm','cm.thongke','cm.kehoach','cm.baocao','csdl.view'],
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
            'perms' => ['cm.tracuu','cm.dashboard','cm.baocao'],
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
    $map = ['none' => 0, 'view' => 1, 'edit' => 2, 'admin' => 3];
    return $map[$level] ?? 0;
}

/** User hiện tại có quyền module ≥ level? */
function can_module($module, $level = 'view') {
    $u = current_user();
    if (!$u) return false;
    if (($u['role'] ?? '') === 'admin') return true;
    $have = $u['modules'][$module] ?? 'none';
    return level_rank($have) >= level_rank($level);
}

/** User có mã chức năng? */
function can_perm($perm) {
    $u = current_user();
    if (!$u) return false;
    if (($u['role'] ?? '') === 'admin') return true;
    $list = $u['perms'] ?? [];
    if (!is_array($list)) return false;
    if (in_array($perm, $list, true)) return true;

    // Module admin → full perm thuộc module đó
    $cat = permission_features_catalog();
    $mod = $cat[$perm]['module'] ?? '';
    if ($mod && level_rank($u['modules'][$mod] ?? 'none') >= level_rank('admin')) {
        return true;
    }
    return false;
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
    $cls = $u['classes'] ?? [];
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
    require_login();
    if (!can_perm($perm) && !can_module(permission_features_catalog()[$perm]['module'] ?? '', 'admin')) {
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
