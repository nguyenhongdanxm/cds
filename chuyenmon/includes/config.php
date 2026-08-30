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

$sharedSchoolConfig = dirname(__DIR__, 2) . '/includes/school_config.php';
if (is_file($sharedSchoolConfig)) {
    require_once $sharedSchoolConfig;
}

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

if (!defined('SCHOOL_SO')) define('SCHOOL_SO', function_exists('school_department') ? school_department() : 'Sở GD&ĐT Tuyên Quang');
if (!defined('SCHOOL_NAME')) define('SCHOOL_NAME', function_exists('school_name') ? school_name() : 'Trường PTDTNT THCS&THPT Xín Mần');

/*
 * Dữ liệu mẫu lịch sử của Xín Mần được tách khỏi core để thuận lợi nhân bản.
 * Đây chỉ là nguồn fallback khởi tạo; KHÔNG tác động các file dữ liệu hiện có.
 */
$seedDefaultsFile = __DIR__ . '/defaults_xinman.php';
$seedDefaults = is_file($seedDefaultsFile) ? require $seedDefaultsFile : [];

$DEFAULT_TEACHERS = is_array($seedDefaults['teachers'] ?? null) ? $seedDefaults['teachers'] : [];
$DEFAULT_CLASSES = is_array($seedDefaults['classes'] ?? null) ? $seedDefaults['classes'] : [];
$DEFAULT_GROUPS = is_array($seedDefaults['groups'] ?? null) ? $seedDefaults['groups'] : [];
$DEFAULT_SUBJECTS = is_array($seedDefaults['subjects'] ?? null) ? $seedDefaults['subjects'] : [];
$DEFAULT_ROLES = is_array($seedDefaults['roles'] ?? null) ? $seedDefaults['roles'] : [];
unset($seedDefaults, $seedDefaultsFile);

/*
 * Chỉ lọc BỘ DỮ LIỆU KHỞI TẠO theo cấp học của trường mới. Các file dữ liệu
 * đã tồn tại trong chuyenmon/data không bị sửa, xóa hay chuyển đổi.
 */
if (function_exists('school_has_level')) {
    $useThcs = school_has_level('thcs');
    $useThpt = school_has_level('thpt');
    if ($useThcs !== $useThpt) {
        $allowedGrades = $useThcs ? ['6','7','8','9'] : ['10','11','12'];
        $DEFAULT_CLASSES = array_values(array_filter($DEFAULT_CLASSES, static function ($class) use ($allowedGrades) {
            return preg_match('/^(\d{1,2})/', (string)$class, $m) && in_array($m[1], $allowedGrades, true);
        }));
        foreach ($DEFAULT_SUBJECTS as $subject => $grades) {
            $filtered = array_intersect_key($grades, array_flip($allowedGrades));
            if ($filtered) $DEFAULT_SUBJECTS[$subject] = $filtered;
            else unset($DEFAULT_SUBJECTS[$subject]);
        }
    }
}
