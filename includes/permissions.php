<?php
/**
 * Phân quyền hệ sinh thái CDS – 4 tầng:
 *  1) Module
 *  2) Nhóm menu
 *  3) Chức năng: none | view | edit | delete
 *  4) Phạm vi dữ liệu/lớp
 * Một tài khoản có thể thuộc nhiều nhóm; quyền nhóm được cộng theo mức cao
 * nhất, sau đó quyền cá nhân có thể ghi đè (kể cả từ chối quyền).
 */

function permission_modules_catalog() {
    return [
        // Mỗi mục tương ứng đúng một trang/module trên hệ sinh thái.
        'chuyenmon' => ['label' => 'Chuyên môn', 'icon' => 'bi-journal-bookmark-fill', 'page' => 'chuyenmon.php'],
        'vanban'    => ['label' => 'Văn bản', 'icon' => 'bi-file-earmark-text', 'page' => 'vanban.php'],
        'thuvien'   => ['label' => 'Thư viện – Thiết bị', 'icon' => 'bi-book-half', 'page' => 'thuvien.php'],
        'csdl'      => ['label' => 'Cơ sở dữ liệu', 'icon' => 'bi-database', 'page' => 'csdl.php'],
        'hoclieu'   => ['label' => 'Học liệu và thi', 'icon' => 'bi-laptop', 'page' => 'hoclieu.php'],
        'noitru'    => ['label' => 'Quản lý nội trú', 'icon' => 'bi-building', 'page' => 'noitru.php'],
        'thidua'    => ['label' => 'Thi đua', 'icon' => 'bi-trophy', 'page' => 'thidua.php'],
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
        'cm.baocao.kythi'  => ['module' => 'chuyenmon', 'label' => 'Kiểm tra', 'group' => 'Theo dõi – Đánh giá'],
        'cm.dashboard'=> ['module' => 'chuyenmon', 'label' => 'Bảng điều khiển trang chủ CM', 'group' => 'Chung'],

        // CSDL
        'csdl.overview' => ['module' => 'csdl', 'label' => 'Tổng quan cơ sở dữ liệu', 'group' => 'CSDL'],
        'csdl.statistics' => ['module' => 'csdl', 'label' => 'Thống kê giáo viên và học sinh', 'group' => 'CSDL'],
        'csdl.teachers' => ['module' => 'csdl', 'label' => 'Giáo viên / CBGVNV', 'group' => 'CSDL'],
        'csdl.classes'  => ['module' => 'csdl', 'label' => 'Lớp / khối', 'group' => 'CSDL'],
        'csdl.students' => ['module' => 'csdl', 'label' => 'Học sinh', 'group' => 'CSDL'],
        'csdl.export' => ['module' => 'csdl', 'label' => 'Xuất dữ liệu', 'group' => 'CSDL'],
        'csdl.year'   => ['module' => 'csdl', 'label' => 'Quản lý năm học', 'group' => 'CSDL'],

        // Nội trú
        'nt.tongquan' => ['module' => 'noitru', 'label' => 'Tổng quan nội trú', 'group' => 'Nội trú'],
        'nt.danhsach' => ['module' => 'noitru', 'label' => 'Danh sách nội trú', 'group' => 'Nội trú'],
        'nt.chiaphong' => ['module' => 'noitru', 'label' => 'Chia phòng nội trú', 'group' => 'Nội trú'],
        'nt.chiamam'   => ['module' => 'noitru', 'label' => 'Chia mâm / nhóm ăn', 'group' => 'Nội trú'],
        'nt.diemdanh' => ['module' => 'noitru', 'label' => 'Điểm danh', 'group' => 'Nội trú'],
        'nt.diemdanh.quantri' => ['module' => 'noitru', 'label' => 'Quản trị điểm danh (cài buổi · sửa/xóa lịch sử)', 'group' => 'Nội trú'],
        'nt.baoan'    => ['module' => 'noitru', 'label' => 'Báo ăn theo lớp', 'group' => 'Bữa ăn'],
        'nt.buaan.tonghop' => ['module' => 'noitru', 'label' => 'Tổng hợp · chốt · xuất báo cáo bữa ăn', 'group' => 'Bữa ăn'],
        'nt.gao'      => ['module' => 'noitru', 'label' => 'Kho gạo · định mức · thống kê · xuất báo cáo', 'group' => 'Bữa ăn'],
        'nt.ravao'    => ['module' => 'noitru', 'label' => 'Xin ra vào KTX', 'group' => 'Nội trú'],
        'nt.yte'      => ['module' => 'noitru', 'label' => 'Sức khỏe / y tế', 'group' => 'Nội trú'],
        'nt.lichtruc' => ['module' => 'noitru', 'label' => 'Lịch trực', 'group' => 'Nội trú'],
        'nt.thucdon'  => ['module' => 'noitru', 'label' => 'Thực đơn / nhóm ăn', 'group' => 'Bữa ăn'],
        'nt.thongke'  => ['module' => 'noitru', 'label' => 'Thống kê nội trú', 'group' => 'Nội trú'],

        // Văn bản
        'vb.xem'      => ['module' => 'vanban', 'label' => 'Xem văn bản và biểu mẫu', 'group' => 'Văn bản'],
        'vb.quanly'   => ['module' => 'vanban', 'label' => 'Thêm · sửa · phát hành văn bản', 'group' => 'Văn bản'],
        'vb.layso'    => ['module' => 'vanban', 'label' => 'Lấy số · phát hành văn bản', 'group' => 'Văn bản'],
        'vb.hosoluutru'=> ['module' => 'vanban', 'label' => 'Quản lý văn bản mẫu', 'group' => 'Văn bản'],
        'vb.tuongtac' => ['module' => 'vanban', 'label' => 'Tạo bình chọn · khảo sát · xử lý góp ý', 'group' => 'Văn bản'],

        // Thi đua — quyền đến từng menu con
        'td.teacher_attendance'  => ['module' => 'thidua', 'label' => 'Giáo viên · Chấm công', 'group' => 'Thi đua'],
        'td.teacher_achievement' => ['module' => 'thidua', 'label' => 'Giáo viên · Thành tích', 'group' => 'Thi đua'],
        'td.teacher_rating'      => ['module' => 'thidua', 'label' => 'Giáo viên · Xếp loại', 'group' => 'Thi đua'],
        'td.student_score'       => ['module' => 'thidua', 'label' => 'Học sinh · Bảng điểm', 'group' => 'Thi đua'],
        'td.student_profile'     => ['module' => 'thidua', 'label' => 'Học sinh · Hồ sơ thi đua', 'group' => 'Thi đua'],
        'td.stats'               => ['module' => 'thidua', 'label' => 'Thống kê thi đua', 'group' => 'Thi đua'],
        'td.all_data'            => ['module' => 'thidua', 'label' => 'Xem dữ liệu của tất cả giáo viên/lớp', 'group' => 'Phạm vi dữ liệu'],

        // Trang Học liệu và thi
        'hl.xem'      => ['module' => 'hoclieu', 'label' => 'Học liệu: xem · nộp · sửa · xóa', 'group' => 'Học liệu'],
        'hl.kiemtra'  => ['module' => 'hoclieu', 'label' => 'Kiểm tra và thi: xem · nộp · sửa · xóa', 'group' => 'Kiểm tra và thi'],
        'hl.lienket'  => ['module' => 'hoclieu', 'label' => 'Liên kết hữu ích: xem · thêm · sửa · xóa', 'group' => 'Liên kết'],
        'hl.duyet'    => ['module' => 'hoclieu', 'label' => 'Duyệt · công khai · đánh dấu nổi bật', 'group' => 'Quản trị nội dung'],

        // Trang Thư viện – Thiết bị — quyền đến từng menu con
        'tv.tongquan' => ['module' => 'thuvien', 'label' => 'Tổng quan Thư viện – Thiết bị', 'group' => 'Tổng quan'],
        'tv.danhmuc'  => ['module' => 'thuvien', 'label' => 'Danh mục sách và tài liệu', 'group' => 'Thư viện'],
        'tv.muontra'  => ['module' => 'thuvien', 'label' => 'Mượn – trả sách', 'group' => 'Thư viện'],
        'tv.thongke'  => ['module' => 'thuvien', 'label' => 'Thống kê thư viện', 'group' => 'Thư viện'],
        'tb.danhmuc'  => ['module' => 'thuvien', 'label' => 'Danh mục thiết bị', 'group' => 'Thiết bị'],
        'tb.nguongoc' => ['module' => 'thuvien', 'label' => 'Mã số và nguồn gốc tài sản', 'group' => 'Thiết bị'],
        'tb.muontra'  => ['module' => 'thuvien', 'label' => 'Phiếu mượn – trả thiết bị', 'group' => 'Thiết bị'],
        'tb.baoduong' => ['module' => 'thuvien', 'label' => 'Bảo dưỡng – sửa chữa', 'group' => 'Thiết bị'],
        'tb.kiemke'   => ['module' => 'thuvien', 'label' => 'Kiểm kê tài sản', 'group' => 'Thiết bị'],

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
                $view(array_merge($cmView, $ntView, array_merge(['csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students','csdl.export','vb.xem','hl.xem','hl.kiemtra','hl.lienket','td.all_data'], $tdSections))),
                $edit($cmEdit)
            ),
        ],
        'totruong' => [
            'label' => 'Tổ trưởng chuyên môn',
            'access' => array_merge(
                $view(array_merge(['cm.dashboard','cm.tracuu','cm.thongke','cm.kehoach','csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students','vb.xem','hl.xem','hl.kiemtra','hl.lienket'], $cmReports)),
                $edit(['cm.pccm','td.teacher_attendance'])
            ),
        ],
        'gvcn' => [
            'label' => 'Giáo viên chủ nhiệm',
            'access' => array_merge($view(['cm.dashboard','cm.tracuu','cm.baocao.dugio','csdl.overview','csdl.students','nt.danhsach','td.student_score','vb.xem','hl.xem','hl.kiemtra','hl.lienket']), $edit(array_merge($ntEdit, ['cm.baocao.tiendo']))),
        ],
        'gv' => [
            'label' => 'Giáo viên',
            'access' => array_merge($view(array_merge(['cm.dashboard','cm.tracuu','nt.danhsach','vb.xem','hl.xem','hl.kiemtra','hl.lienket'], array_values(array_diff($cmReports, ['cm.baocao.tiendo'])))), $edit(['cm.baocao.tiendo'])),
        ],
        'qlnt' => [
            'label' => 'Cán bộ nội trú / y tế',
            'access' => array_merge($view(['csdl.overview','csdl.students']), $edit(array_keys(array_filter(
                permission_features_catalog(),
                fn($meta, $code) => ($meta['module'] ?? '') === 'noitru' && $code !== 'nt.diemdanh.quantri',
                ARRAY_FILTER_USE_BOTH
            )))),
        ],
        'vanthu' => [
            'label' => 'Văn thư',
            'access' => array_merge($view(['csdl.overview','csdl.teachers','csdl.classes','csdl.students']), $edit(['vb.xem','vb.quanly','vb.layso','vb.hosoluutru','vb.tuongtac'])),
        ],
        'ketoan' => [
            'label' => 'Kế toán',
            'access' => array_merge($view(['csdl.overview','csdl.students','csdl.export']), ['nt.baoan'=>'delete']),
        ],
        'doandoi' => [
            'label' => 'Đoàn – Đội',
            'access' => array_merge(
                $view(array_merge(['cm.dashboard','cm.tracuu','csdl.overview','csdl.students','nt.danhsach','hl.xem','hl.kiemtra','hl.lienket','td.all_data'], $tdSections)),
                $edit(array_merge($tdNonAttendance, ['tt.bientap']))
            ),
        ],
        'thuvien_thietbi' => [
            'label' => 'Thư viện – Thiết bị',
            'access' => array_merge($view(['csdl.overview','hl.xem']), $edit(['tv.danhmuc','tv.muontra','tv.thongke','tb.danhmuc','tb.nguongoc','tb.muontra','tb.baoduong','tb.kiemke'])),
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

    // Chỉ chuyển dữ liệu cũ. Từ phiên bản 3, mức "none" cũng được lưu rõ
    // nên tuyệt đối không tự cấp lại quyền mà quản trị đã gỡ.
    foreach ($saved as $groupKey => &$group) {
        if (!is_array($group)) continue;
        $group['access'] = is_array($group['access'] ?? null) ? $group['access'] : [];
        $version = (int)($group['version'] ?? 1);
        if ($version < 3 && (isset($group['access']['td.xem']) || isset($group['access']['td.capnhat']) || isset($group['access']['td.duyet']))) {
            $legacyTdLevel = $group['access']['td.capnhat'] ?? ($group['access']['td.xem'] ?? 'view');
            foreach (['td.teacher_attendance','td.teacher_achievement','td.teacher_rating','td.student_score','td.student_profile','td.stats'] as $childCode) {
                if (!isset($group['access'][$childCode])) $group['access'][$childCode] = $legacyTdLevel;
            }
            if (isset($group['access']['td.duyet']) && !isset($group['access']['td.all_data'])) $group['access']['td.all_data'] = 'view';
            unset($group['access']['td.xem'], $group['access']['td.capnhat'], $group['access']['td.duyet']);
        }
        if ($version < 3 && isset($group['access']['cm.baocao'])) {
            $legacyLevel = $group['access']['cm.baocao'];
            foreach (['cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi'] as $childCode) {
                if (!isset($group['access'][$childCode])) $group['access'][$childCode] = $legacyLevel;
            }
            unset($group['access']['cm.baocao']);
        }
        if ($version < 3) {
            $mealLevel = $group['access']['nt.baoan'] ?? ($group['access']['nt.thongke'] ?? 'none');
            if (!isset($group['access']['nt.buaan.tonghop']) && $mealLevel !== 'none') {
                $group['access']['nt.buaan.tonghop'] = $mealLevel;
            }
            if (!isset($group['access']['nt.gao']) && isset($group['access']['nt.baoan'])) {
                $group['access']['nt.gao'] = $group['access']['nt.baoan'];
            }
        }
        if ($version < 4) {
            $legacyView = $group['access']['csdl.view'] ?? 'none';
            $legacyEdit = $group['access']['csdl.edit'] ?? 'none';
            foreach (['csdl.overview','csdl.teachers','csdl.classes','csdl.students'] as $childCode) {
                $childLevel = $group['access'][$childCode] ?? 'none';
                if (level_rank($legacyView) > level_rank($childLevel)) $childLevel = $legacyView;
                if ($childCode !== 'csdl.overview' && level_rank($legacyEdit) > level_rank($childLevel)) $childLevel = $legacyEdit;
                $group['access'][$childCode] = $childLevel;
            }
            unset($group['access']['csdl.view'], $group['access']['csdl.edit']);
        }
        if ($version < 5 && !isset($group['access']['csdl.statistics'])) {
            // Quyền mới kế thừa đúng một lần từ Tổng quan. Sau khi nhóm được
            // lưu ở phiên bản 5, quản trị có thể gỡ độc lập mà không bị cấp lại.
            $group['access']['csdl.statistics'] = $group['access']['csdl.overview'] ?? 'none';
        }
        if ($version < 8 && $groupKey === 'ketoan') {
            if (level_rank($group['access']['nt.baoan'] ?? 'none') < level_rank('delete')) $group['access']['nt.baoan'] = 'delete';
        }
        if ($version < 11) {
            // Trước khi tách quyền, Chia phòng và Chia mâm dùng chung quyền
            // Sửa Danh sách nội trú. Kế thừa đúng một lần để không làm mất
            // quyền đang có; sau khi lưu có thể điều chỉnh hai quyền độc lập.
            $assignmentLevel = $group['access']['nt.danhsach'] ?? 'none';
            if (!isset($group['access']['nt.chiaphong'])) $group['access']['nt.chiaphong'] = $assignmentLevel;
            if (!isset($group['access']['nt.chiamam'])) $group['access']['nt.chiamam'] = $assignmentLevel;
        }
        if ($version < 10) {
            $legacyLibrary = $group['access']['hl.thuvien'] ?? 'none';
            $legacyEquipment = $group['access']['hl.thietbi'] ?? 'none';
            foreach (['tv.danhmuc','tv.muontra','tv.thongke'] as $code) {
                if (!isset($group['access'][$code]) && $legacyLibrary !== 'none') $group['access'][$code] = $legacyLibrary;
            }
            foreach (['tb.danhmuc','tb.nguongoc','tb.muontra','tb.baoduong','tb.kiemke'] as $code) {
                if (!isset($group['access'][$code]) && $legacyEquipment !== 'none') $group['access'][$code] = $legacyEquipment;
            }
            if (!isset($group['access']['tv.tongquan']) && (level_rank($legacyLibrary) >= 1 || level_rank($legacyEquipment) >= 1)) {
                $group['access']['tv.tongquan'] = 'view';
            }
            // Giáo viên trước đây mặc định được xem danh mục và lập phiếu mượn thiết bị.
            if (in_array($groupKey, ['bgh','totruong','gvcn','gv','doandoi'], true)) {
                if (!isset($group['access']['tv.danhmuc'])) $group['access']['tv.danhmuc'] = 'view';
                if (!isset($group['access']['tb.danhmuc'])) $group['access']['tb.danhmuc'] = 'view';
                if (!isset($group['access']['tb.muontra'])) $group['access']['tb.muontra'] = 'edit';
                if (!isset($group['access']['tb.baoduong'])) $group['access']['tb.baoduong'] = 'view';
            }
            unset($group['access']['hl.thuvien'], $group['access']['hl.thietbi']);
        }
        if ($version < 9) {
            // Trước đây mọi giáo viên đã đăng nhập đều xem được Học liệu và
            // Kiểm tra. Giữ nguyên quyền xem khi chuyển sang kiểm soát thật.
            if (in_array($groupKey, ['bgh','totruong','gvcn','gv','doandoi','thuvien_thietbi'], true)) {
                if (!isset($group['access']['hl.xem'])) $group['access']['hl.xem'] = 'view';
                if (!isset($group['access']['hl.kiemtra'])) $group['access']['hl.kiemtra'] = 'view';
                if (!isset($group['access']['hl.lienket'])) $group['access']['hl.lienket'] = 'view';
            }
            if ($groupKey === 'thuvien_thietbi') {
                foreach (['tv.danhmuc','tv.muontra','tv.thongke','tb.danhmuc','tb.nguongoc','tb.muontra','tb.baoduong','tb.kiemke'] as $code) {
                    if (!isset($group['access'][$code])) $group['access'][$code] = 'edit';
                }
            }
        }
        if ($version < 6) {
            if (!isset($group['access']['vb.xem'])) $group['access']['vb.xem'] = 'view';
            $manageLevel = $group['access']['vb.quanly'] ?? 'none';
            if (!isset($group['access']['vb.layso'])) $group['access']['vb.layso'] = $manageLevel;
            if (!isset($group['access']['vb.hosoluutru'])) $group['access']['vb.hosoluutru'] = $manageLevel;
        }
        if ($version < 7 && !isset($group['access']['vb.tuongtac'])) {
            $group['access']['vb.tuongtac'] = $group['access']['vb.quanly'] ?? 'none';
        }
    }
    unset($group);
    return $saved;
}

function permission_groups_save(array $groups) {
    $catalog = permission_features_catalog();
    $clean = [];
    foreach ($groups as $key => $group) {
        if (!preg_match('/^[a-z0-9_]+$/', (string)$key)) continue;
        $label = trim((string)($group['label'] ?? $key));
        $access = [];
        foreach ($catalog as $code => $meta) {
            $level = $group['access'][$code] ?? 'none';
            $access[$code] = in_array($level, ['none','view','edit','delete'], true) ? $level : 'none';
        }
        $clean[$key] = ['version' => 11, 'label' => $label !== '' ? $label : $key, 'access' => $access];
    }
    if (!$clean || !save_json(permission_groups_file(), $clean)) return false;
    $check = load_json(permission_groups_file(), []);
    return is_array($check) && $check === $clean;
}

function permission_group_access_for_user(array $user) {
    $access = [];
    $groups = permission_groups_all();
    $userGroups = is_array($user['groups'] ?? null) ? $user['groups'] : [];
    // Chỉ tài khoản mô hình cũ mới kế thừa nhóm cùng tên với vai trò. Với mô
    // hình v2, danh sách nhóm đã chọn là nguồn chính xác để gỡ nhóm có hiệu lực.
    $roleGroup = (string)($user['role'] ?? '');
    if ((int)($user['permission_model_version'] ?? 1) < 2 && !$userGroups && isset($groups[$roleGroup])) $userGroups[] = $roleGroup;
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
    if (in_array('csdl.view', $legacyPerms, true)) {
        foreach (['csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students'] as $childCode) $access[$childCode] = 'view';
    }
    if (in_array('csdl.edit', $legacyPerms, true)) {
        foreach (['csdl.teachers','csdl.classes','csdl.students'] as $childCode) $access[$childCode] = 'edit';
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

    // Có danh sách nhóm rõ ràng thì nhóm là nguồn quyền chính xác, kể cả tài
    // khoản cũ chưa có cờ phiên bản. Nhờ vậy gỡ quyền khỏi nhóm sẽ gỡ thật,
    // không bị modules/perms cũ âm thầm cấp lại sau khi lưu.
    $usesGroupModel = (int)($user['permission_model_version'] ?? 1) >= 2
        || !empty($user['groups']);
    $access = $usesGroupModel
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
    // Chuyên môn và màn hình phân quyền dùng chung đúng một bộ tính quyền.
    foreach (cds_cm_permission_codes() as $code) {
        $access[$code] = cds_cm_feature_access_for_user($user, $code, DATA_PATH);
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
    $allNt = ['nt.tongquan','nt.danhsach','nt.chiaphong','nt.chiamam','nt.diemdanh','nt.diemdanh.quantri','nt.baoan','nt.buaan.tonghop','nt.gao','nt.ravao','nt.yte','nt.lichtruc','nt.thucdon','nt.thongke'];
    $allCs = ['csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students','csdl.export','csdl.year'];

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
            'perms' => array_merge($allCm, ['csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students','csdl.export'], ['nt.tongquan','nt.danhsach','nt.thongke']),
            'classes' => [],
        ],
        'totruong' => [
            'label' => 'Tổ trưởng chuyên môn',
            'modules' => ['chuyenmon'=>'edit','csdl'=>'view','noitru'=>'none'],
            'perms' => ['cm.dashboard','cm.tracuu','cm.pccm','cm.thongke','cm.kehoach','cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi','csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students'],
            'classes' => [],
        ],
        'gvcn' => [
            'label' => 'Giáo viên chủ nhiệm',
            'modules' => ['chuyenmon'=>'view','csdl'=>'view','noitru'=>'edit'],
            'perms' => ['cm.tracuu','cm.dashboard','csdl.overview','csdl.students','nt.diemdanh','nt.baoan','nt.ravao','nt.danhsach'],
            'classes' => [], // gán lớp chủ nhiệm khi tạo user
        ],
        'gv' => [
            'label' => 'Giáo viên',
            'modules' => ['chuyenmon'=>'view','csdl'=>'none','noitru'=>'none','vanban'=>'view'],
            'perms' => ['cm.tracuu','cm.dashboard','cm.baocao.dinhky','cm.baocao.tiendo','cm.baocao.dugio','cm.baocao.kythi','nt.danhsach','vb.xem'],
            'classes' => [],
        ],
        'ktx' => [
            'label' => 'Cán bộ KTX / y tế',
            'modules' => ['chuyenmon'=>'none','csdl'=>'view','noitru'=>'edit'],
            'perms' => array_merge(['csdl.overview','csdl.students'], array_values(array_diff($allNt, ['nt.diemdanh.quantri']))),
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
    $usesGroupModel = (int)($u['permission_model_version'] ?? 1) >= 2
        || !empty($u['groups']);
    $have = $usesGroupModel
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
