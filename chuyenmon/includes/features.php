<?php
function get_app_info() {
    return [
        'name' => 'Chuyên môn',
        'full_name' => 'Ứng dụng Chuyên môn',
        'year' => '2025 – 2026',
        'author' => 'Thầy giáo Nguyễn Hồng Dân',
        'school' => 'Trường PTDTNT THCS&THPT Xín Mần',
        'tagline' => 'PCCM · Kế hoạch · Báo cáo',
        'version' => '3.1',
    ];
}

function get_app_features() {
    return [
        [
            'icon' => 'bi-clipboard-check',
            'title' => 'PCCM – Phân công chuyên môn',
            'desc' => 'Tổng quan, phân công, danh sách, kết quả, thống kê PCCM, nhập liệu.',
            'items' => ['Phân công · đổi chéo · rà soát', 'Thống kê PCCM theo lớp/khối/môn', 'Xuất bảng in'],
        ],
        [
            'icon' => 'bi-calendar2-week',
            'title' => 'Kế hoạch',
            'desc' => 'Văn bản kế hoạch, thông báo chuyên môn, chỉ tiêu.',
            'items' => ['Gõ nội dung · link · tải file', 'Nút Xem văn bản'],
        ],
        [
            'icon' => 'bi-file-earmark-text',
            'title' => 'Báo cáo',
            'desc' => 'Báo cáo định kỳ, tiến độ CT, dự giờ, kết quả cuộc thi.',
            'items' => ['Tạo kỳ thi và nhập kết quả', 'Xem / tải tài liệu'],
        ],
    ];
}
