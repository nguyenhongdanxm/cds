<?php
/** Danh sách module hệ sinh thái: live | link | soon */
function get_ecosystem_modules() {
    return [
        [
            'id' => 'tintuc', 'num' => 1,
            'title' => 'Tin tức', 'subtitle' => 'Website nhà trường',
            'icon' => 'bi-newspaper', 'color' => '#0d6efd',
            'status' => 'link', 'url' => URL_TIN_TUC, 'external' => true,
        ],
        [
            'id' => 'chuyenmon', 'num' => 2,
            'title' => 'Chuyên môn', 'subtitle' => 'Phân công · rà soát · thống kê',
            'icon' => 'bi-journal-bookmark-fill', 'color' => '#198754',
            'status' => 'link', 'url' => URL_CHUYEN_MON, 'external' => false,
        ],
        [
            'id' => 'vanban', 'num' => 3,
            'title' => 'Văn bản', 'subtitle' => 'Công văn · biểu mẫu · lưu trữ',
            'icon' => 'bi-file-earmark-text', 'color' => '#6f42c1',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'tuyentruyen', 'num' => 4,
            'title' => 'Tuyên truyền', 'subtitle' => 'Tin nội bộ · banner · truyền thông',
            'icon' => 'bi-megaphone', 'color' => '#fd7e14',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'csdl', 'num' => 5,
            'title' => 'Cơ sở dữ liệu', 'subtitle' => 'Dữ liệu lõi dùng chung',
            'icon' => 'bi-database', 'color' => '#20c997',
            'status' => 'live', 'url' => BASE_URL . 'csdl.php', 'external' => false,
        ],
        [
            'id' => 'hoclieu', 'num' => 6,
            'title' => 'Học liệu & thi', 'subtitle' => 'Học liệu · thi trực tuyến',
            'icon' => 'bi-laptop', 'color' => '#0dcaf0',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'noitru', 'num' => 7,
            'title' => 'Quản lý nội trú', 'subtitle' => 'Phòng · học sinh nội trú',
            'icon' => 'bi-building', 'color' => '#d63384',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'thidua', 'num' => 8,
            'title' => 'Thi đua', 'subtitle' => 'Thi đua · khen thưởng',
            'icon' => 'bi-trophy', 'color' => '#ffc107',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
    ];
}
