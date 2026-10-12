<?php
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
        'Điểm danh'=>['url'=>'noitru_attendance.php','category'=>'Nội trú','keywords'=>['điểm danh nội trú','điểm danh học sinh'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền điểm danh.'],
        'Báo ăn'=>['url'=>'noitru.php?tab=meals','category'=>'Nội trú','keywords'=>['báo ăn nội trú','báo bữa ăn'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền báo ăn.'],
        'Chấm công'=>['url'=>'thidua.php?section=teacher_attendance','category'=>'Thi đua','keywords'=>['chấm công giáo viên'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền chấm công.'],
        'Hồ sơ đánh giá'=>['url'=>'danhgia.php?view=profile','category'=>'Chuyên môn','keywords'=>['hồ sơ đánh giá','đánh giá cá nhân'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền xem hồ sơ.'],
        'Chuyên môn'=>['url'=>defined('URL_CHUYEN_MON')?URL_CHUYEN_MON:'chuyenmon/','category'=>'Chuyên môn','keywords'=>['quản lý chuyên môn'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền module.'],
        'Thời khóa biểu'=>['url'=>'thoikhoabieu.php','category'=>'Chuyên môn','keywords'=>['thời khóa biểu','TKB'],'note'=>'Chỉ hiển thị nếu tài khoản có quyền Chuyên môn.'],
    ];
}
