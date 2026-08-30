<?php
/**
 * Bộ dữ liệu mặc định lịch sử của Trường PTDTNT THCS&THPT Xín Mần.
 *
 * Tệp này CHỈ cung cấp dữ liệu khởi tạo khi các file dữ liệu Chuyên môn chưa có.
 * Không đọc/ghi/xóa bất kỳ dữ liệu vận hành nào trong chuyenmon/data.
 *
 * Việc tách riêng giúp core Chuyên môn không còn chứa trực tiếp dữ liệu mẫu
 * của một trường cụ thể, đồng thời giữ nguyên 100% giá trị mặc định hiện tại.
 */
return [
    'teachers' => ["Nguyễn Thị Ngân","Nguyễn Hồng Dân","Lục Thị Kim Liên","Nguyễn Thị Hoa T","Hoàng Tú Phượng","Hoàng Thị Thanh Huệ","Hoàng Trọng Đại","Ma Ngọc Doanh","Nguyễn Thị Hoa V","Nguyễn Thị Ninh","Phú","Lê Thị Hiền","Nguyễn Thị Thu Hường","Vũ Thị Linh","Hoàng Minh Hải","Đinh Thị Phượng","Vương Hữu Sơn","Vũ Thị Thanh Hường","Vàng Thị Thêm","Lương Thị Bích Tuệ","Ma Thị Hà","Vũ Tiến Sĩ","Sùng Đức Kinh","Nguyễn Thị Thu Huyền","Nguyễn Đức Hội","Đinh Xuân Nghĩa","Bùi Thị Xuân","Nguyễn Trọng Dũng","Nguyễn Thị Kim Dung","Hoàng Thị Hiền","Nguyễn Khắc Kiên","Vương Văn Quân"],
    'classes' => ["6A","6B","7A","7B","7C","8A","8B","8C","9A","9B","10A","10B","11A","11B","12A","12B"],
    'groups' => ["Tổ Toán","Tổ Ngữ văn","Tổ Ngoại ngữ","Tổ KHTN","Tổ KHXH","Tổ Nghệ thuật - Thể dục","Tổ Tin - Công nghệ"],
    'subjects' => ["Toán học"=>["6"=>4,"7"=>4,"8"=>4,"9"=>4,"10"=>4,"11"=>4,"12"=>3],"Ngữ văn"=>["6"=>4,"7"=>4,"8"=>4,"9"=>4,"10"=>4,"11"=>3,"12"=>3],"Ngoại ngữ"=>["6"=>3,"7"=>3,"8"=>3,"9"=>3,"10"=>3,"11"=>3,"12"=>3],"Vật lí"=>["10"=>2,"11"=>2,"12"=>2],"Hóa học"=>["10"=>2,"11"=>2,"12"=>2],"Sinh học"=>["10"=>2,"11"=>2,"12"=>2],"Lịch sử"=>["10"=>1.5,"11"=>1.5,"12"=>1.5],"Địa lí"=>["10"=>2,"11"=>2,"12"=>2],"GDCD"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1],"GD&KTPL"=>["10"=>2,"11"=>2,"12"=>2],"GDTC"=>["6"=>2,"7"=>2,"8"=>2,"9"=>2,"10"=>2,"11"=>2,"12"=>2],"Tin học"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1,"10"=>2,"11"=>2,"12"=>2],"Công nghệ"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1],"Âm nhạc"=>["10"=>2,"11"=>2,"12"=>2],"Nghệ thuật"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1],"KHTN (Lí)"=>["6"=>1.9,"7"=>1.2,"8"=>1.5,"9"=>1.6],"KHTN (Hoá)"=>["6"=>0.6,"7"=>0.7,"8"=>1.2,"9"=>1.4],"KHTN (Sinh)"=>["6"=>1.5,"7"=>2.1,"8"=>1.25,"9"=>1.0],"LS&ĐL"=>["6"=>1.5,"7"=>1.5,"8"=>1.5,"9"=>1.5],"CĐ Toán"=>["10"=>1,"11"=>1,"12"=>1],"CĐ Văn"=>["10"=>1,"11"=>1,"12"=>1],"CĐ Hoá"=>["10"=>1,"11"=>1,"12"=>1],"CĐ Sinh"=>["10"=>1,"11"=>1,"12"=>1],"GDQP"=>["10"=>1,"11"=>1,"12"=>1],"GDĐP"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1,"10"=>1,"11"=>1,"12"=>1],"HĐTN"=>["6"=>2,"7"=>3,"8"=>3,"9"=>3,"10"=>3,"11"=>2,"12"=>2]],
    'roles' => [
        ["name" => "GVCN", "need_class" => true, "periods" => 3, "note" => "Giáo viên chủ nhiệm"],
        ["name" => "TTCM", "need_class" => false, "periods" => 2, "note" => "Tổ trưởng chuyên môn"],
        ["name" => "TPCM", "need_class" => false, "periods" => 1, "note" => "Tổ phó chuyên môn"],
        ["name" => "Bí thư Đoàn", "need_class" => false, "periods" => 2, "note" => ""],
        ["name" => "Phó BT Đoàn", "need_class" => false, "periods" => 1, "note" => ""],
        ["name" => "Tổng phụ trách Đội", "need_class" => false, "periods" => 2, "note" => ""],
        ["name" => "Cố vấn học tập", "need_class" => false, "periods" => 1, "note" => ""],
        ["name" => "Phụ trách CSVC", "need_class" => false, "periods" => 1, "note" => ""],
        ["name" => "Phụ trách thư viện", "need_class" => false, "periods" => 1, "note" => ""],
        ["name" => "Phụ trách thiết bị", "need_class" => false, "periods" => 1, "note" => ""],
    ],
];
