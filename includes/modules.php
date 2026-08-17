<?php
/** Danh sách module hệ sinh thái: live | link | soon */
function get_ecosystem_modules() {
    return [
        [
            'id' => 'tintuc', 'num' => 1,
            'title' => 'Tin tức',
            'subtitle' => 'Website · tin bài · thông báo sự kiện nhà trường',
            'icon' => 'bi-newspaper', 'color' => '#0d6efd',
            'status' => 'link', 'url' => URL_TIN_TUC, 'external' => true,
        ],
        [
            'id' => 'chuyenmon', 'num' => 2,
            'title' => 'Chuyên môn',
            'subtitle' => 'Phân công – kế hoạch – tiến độ - thống kê',
            'icon' => 'bi-journal-bookmark-fill', 'color' => '#198754',
            'status' => 'live', 'url' => URL_CHUYEN_MON, 'external' => false,
        ],
        [
            'id' => 'vanban', 'num' => 3,
            'title' => 'Văn bản',
            'subtitle' => 'Văn thư nội bộ · lấy số · văn bản mẫu',
            'icon' => 'bi-file-earmark-text', 'color' => '#6f42c1',
            'status' => 'live', 'url' => BASE_URL . 'vanban.php', 'external' => false,
        ],
        [
            'id' => 'thuvien', 'num' => 4,
            'title' => 'Thư viện – Thiết bị',
            'subtitle' => 'Sách · mượn trả · kho thiết bị · thống kê',
            'icon' => 'bi-book-half', 'color' => '#6f42c1',
            'status' => 'live', 'url' => BASE_URL . 'thuvien.php', 'external' => false,
        ],
        [
            'id' => 'csdl', 'num' => 5,
            'title' => 'Cơ sở dữ liệu',
            'subtitle' => 'Nguồn chuẩn · tìm kiếm · module khác đồng bộ 1 chiều',
            'icon' => 'bi-database', 'color' => '#20c997',
            'status' => 'live', 'url' => URL_CSDL, 'external' => false,
        ],
        [
            'id' => 'hoclieu', 'num' => 6,
            'title' => 'Học liệu & thi',
            'subtitle' => 'Học liệu số · ngân hàng đề · thi trực tuyến',
            'icon' => 'bi-laptop', 'color' => '#0dcaf0',
            'status' => 'live', 'url' => BASE_URL . 'hoclieu.php', 'external' => false,
        ],
        [
            'id' => 'noitru', 'num' => 7,
            'title' => 'Quản lý nội trú',
            'subtitle' => 'KTX · điểm danh · báo ăn · xin ra vào',
            'icon' => 'bi-building', 'color' => '#d63384',
            'status' => 'live', 'url' => URL_NOITRU, 'external' => false,
        ],
        [
            'id' => 'thidua', 'num' => 8,
            'title' => 'Thi đua',
            'subtitle' => 'Thi đua · khen thưởng · xếp loại',
            'icon' => 'bi-trophy', 'color' => '#ffc107',
            'status' => 'live', 'url' => BASE_URL . 'thidua.php', 'external' => false,
        ],
    ];
}
