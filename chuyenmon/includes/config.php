<?php
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
/* Deploy độc lập: /pccm/ — Deploy trong CDS: /chuyenmon/ */
if (!defined('BASE_URL')) {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/chuyenmon') !== false) {
        define('BASE_URL', '/chuyenmon/');
    } elseif (strpos($script, '/pccm') !== false) {
        define('BASE_URL', '/pccm/');
    } else {
        define('BASE_URL', '/chuyenmon/');
    }
}

if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);

define('TEACHERS_FILE', DATA_PATH . '/teachers.json');
define('TEACHER_META_FILE', DATA_PATH . '/teacher_meta.json');
define('GROUPS_FILE', DATA_PATH . '/groups.json');
define('SETTINGS_FILE', DATA_PATH . '/settings.json');
define('SUBJECTS_FILE', DATA_PATH . '/subjects.json');
define('SUBJECT_META_FILE', DATA_PATH . '/subject_meta.json');
define('CLASSES_FILE', DATA_PATH . '/classes.json');
define('ROLES_FILE', DATA_PATH . '/roles.json');
define('VERSIONS_FILE', DATA_PATH . '/versions.json');
define('ACTIVE_VERSION_FILE', DATA_PATH . '/active_version.json');

define('LEGACY_ASSIGNMENTS_FILE', DATA_PATH . '/assignments.json');
define('LEGACY_ROLE_ASSIGNMENTS_FILE', DATA_PATH . '/role_assignments.json');

define('DEFAULT_QUOTA_THCS', 17);
define('DEFAULT_QUOTA_THPT', 15);

define('SCHOOL_SO', 'Sở GD&ĐT Tuyên Quang');
define('SCHOOL_NAME', 'Trường PTDTNT THCS&THPT Xín Mần');

$DEFAULT_TEACHERS = ["Nguyễn Thị Ngân","Nguyễn Hồng Dân","Lục Thị Kim Liên","Nguyễn Thị Hoa T","Hoàng Tú Phượng","Hoàng Thị Thanh Huệ","Hoàng Trọng Đại","Ma Ngọc Doanh","Nguyễn Thị Hoa V","Nguyễn Thị Ninh","Phú","Lê Thị Hiền","Nguyễn Thị Thu Hường","Vũ Thị Linh","Hoàng Minh Hải","Đinh Thị Phượng","Vương Hữu Sơn","Vũ Thị Thanh Hường","Vàng Thị Thêm","Lương Thị Bích Tuệ","Ma Thị Hà","Vũ Tiến Sĩ","Sùng Đức Kinh","Nguyễn Thị Thu Huyền","Nguyễn Đức Hội","Đinh Xuân Nghĩa","Bùi Thị Xuân","Nguyễn Trọng Dũng","Nguyễn Thị Kim Dung","Hoàng Thị Hiền","Nguyễn Khắc Kiên","Vương Văn Quân"];
$DEFAULT_CLASSES = ["6A","6B","7A","7B","7C","8A","8B","8C","9A","9B","10A","10B","11A","11B","12A","12B"];
$DEFAULT_GROUPS = ["Tổ Toán","Tổ Ngữ văn","Tổ Ngoại ngữ","Tổ KHTN","Tổ KHXH","Tổ Nghệ thuật - Thể dục","Tổ Tin - Công nghệ"];
$DEFAULT_SUBJECTS = ["Toán học"=>["6"=>4,"7"=>4,"8"=>4,"9"=>4,"10"=>4,"11"=>4,"12"=>3],"Ngữ văn"=>["6"=>4,"7"=>4,"8"=>4,"9"=>4,"10"=>4,"11"=>3,"12"=>3],"Ngoại ngữ"=>["6"=>3,"7"=>3,"8"=>3,"9"=>3,"10"=>3,"11"=>3,"12"=>3],"Vật lí"=>["10"=>2,"11"=>2,"12"=>2],"Hóa học"=>["10"=>2,"11"=>2,"12"=>2],"Sinh học"=>["10"=>2,"11"=>2,"12"=>2],"Lịch sử"=>["10"=>1.5,"11"=>1.5,"12"=>1.5],"Địa lí"=>["10"=>2,"11"=>2,"12"=>2],"GDCD"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1],"GD&KTPL"=>["10"=>2,"11"=>2,"12"=>2],"GDTC"=>["6"=>2,"7"=>2,"8"=>2,"9"=>2,"10"=>2,"11"=>2,"12"=>2],"Tin học"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1,"10"=>2,"11"=>2,"12"=>2],"Công nghệ"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1],"Âm nhạc"=>["10"=>2,"11"=>2,"12"=>2],"Nghệ thuật"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1],"KHTN (Lí)"=>["6"=>1.9,"7"=>1.2,"8"=>1.5,"9"=>1.6],"KHTN (Hoá)"=>["6"=>0.6,"7"=>0.7,"8"=>1.2,"9"=>1.4],"KHTN (Sinh)"=>["6"=>1.5,"7"=>2.1,"8"=>1.25,"9"=>1.0],"LS&ĐL"=>["6"=>1.5,"7"=>1.5,"8"=>1.5,"9"=>1.5],"CĐ Toán"=>["10"=>1,"11"=>1,"12"=>1],"CĐ Văn"=>["10"=>1,"11"=>1,"12"=>1],"CĐ Hoá"=>["10"=>1,"11"=>1,"12"=>1],"CĐ Sinh"=>["10"=>1,"11"=>1,"12"=>1],"GDQP"=>["10"=>1,"11"=>1,"12"=>1],"GDĐP"=>["6"=>1,"7"=>1,"8"=>1,"9"=>1,"10"=>1,"11"=>1,"12"=>1],"HĐTN"=>["6"=>2,"7"=>3,"8"=>3,"9"=>3,"10"=>3,"11"=>2,"12"=>2]];

$DEFAULT_ROLES = [
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
];
