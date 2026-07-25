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
            'status' => 'link', 'url' => URL_CHUYEN_MON, 'external' => false,
        ],
        [
            'id' => 'vanban', 'num' => 3,
            'title' => 'Văn bản',
            'subtitle' => 'Công văn · biểu mẫu · lưu trữ điện tử',
            'icon' => 'bi-file-earmark-text', 'color' => '#6f42c1',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'truyenthong', 'num' => 4,
            'title' => 'Truyền thông',
            'subtitle' => 'Cuộc thi – dự án – chiến dịch hành động',
            'icon' => 'bi-broadcast', 'color' => '#fd7e14',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'csdl', 'num' => 5,
            'title' => 'Cơ sở dữ liệu',
            'subtitle' => 'Dữ liệu lõi · đồng bộ · dùng chung',
            'icon' => 'bi-database', 'color' => '#20c997',
            'status' => 'live', 'url' => BASE_URL . 'csdl.php', 'external' => false,
        ],
        [
            'id' => 'hoclieu', 'num' => 6,
            'title' => 'Học liệu & thi',
            'subtitle' => 'Học liệu số · ngân hàng đề · thi trực tuyến',
            'icon' => 'bi-laptop', 'color' => '#0dcaf0',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
        [
            'id' => 'noitru', 'num' => 7,
            'title' => 'Quản lý nội trú',
            'subtitle' => 'KTX – điểm danh – chăm sóc nuôi dưỡng',
            'icon' => 'bi-building', 'color' => '#d63384',
            'status' => 'link', 'url' => URL_QLHS, 'external' => true,
        ],
        [
            'id' => 'thidua', 'num' => 8,
            'title' => 'Thi đua',
            'subtitle' => 'Thi đua · khen thưởng · xếp loại',
            'icon' => 'bi-trophy', 'color' => '#ffc107',
            'status' => 'soon', 'url' => '', 'external' => false,
        ],
    ];
}
