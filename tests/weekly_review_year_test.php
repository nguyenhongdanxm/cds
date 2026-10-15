<?php
require __DIR__ . '/../includes/json_store.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
require __DIR__ . '/support/load_functions.php';
$dir = sys_get_temp_dir() . '/cds-review-years-' . bin2hex(random_bytes(6));
mkdir($dir, 0700);
define('LB_WEEKLY_REVIEWS_FILE', $dir . '/reviews.json');
function lb_norm($value) { return strtolower(trim($value)); }
function lb_same($a, $b) { return lb_norm($a) === lb_norm($b); }
function cds_lb_normalize($value) { return lb_norm($value); }
function cds_school_year_resolve() { return ['id'=>$GLOBALS['year']]; }
function cds_lb_weekly_review_table_ready() { return false; }
function lb_can_review_class($class, $type) { return empty($GLOBALS['denied']); }
function lb_teacher_name() { return 'GV fixture'; }
function lb_id($prefix) { return $prefix . '_' . bin2hex(random_bytes(6)); }
function lb_rows($file) { return cds_json_load($file, []); }
function lb_rows_bust($file) {}
function lb_audit($action, $row) {}
load_functions(__DIR__ . '/../includes/database_lesson_book.php', ['cds_lb_weekly_review_school_year', 'cds_lb_weekly_review_enrich']);
load_functions(__DIR__ . '/../chuyenmon/includes/lesson_book_store.php', ['lb_weekly_reviews', 'lb_save_weekly_review']);
try {
    $old = ['id'=>'old-review', 'key'=>'1|6a|homeroom', 'school_year_key'=>'y_2026',
        'week_key'=>'1','class'=>'6A','type'=>'homeroom','content'=>'Nhận xét cũ',
        'created_at'=>'2026-09-05','created_by'=>'GV cũ','custom_field'=>'Giữ nguyên'];
    cds_json_save(LB_WEEKLY_REVIEWS_FILE, [$old]);
    $GLOBALS['year'] = 'y_2027';
    $week = ['key'=>'1','label'=>'Tuần 1'];
    $input = ['class'=>'6A','review_type'=>'homeroom','content'=>'Nhận xét năm mới'];
    check(lb_save_weekly_review($week, $input)['ok'], 'New year saved');
    $rows = cds_json_load(LB_WEEKLY_REVIEWS_FILE);
    check(count($rows) === 2 && $rows[0] === $old, 'Old-year row preserved byte-equivalent as an array');
    check($rows[1]['key'] === 'y_2027|1|6a|homeroom', 'New key contains year');
    check(count(lb_weekly_reviews('1','6A')) === 1 && lb_weekly_reviews('1','6A')[0]['content'] === $input['content'], 'JSON fallback isolates current year');
    $GLOBALS['year'] = 'y_2026';
    check(lb_weekly_reviews('1','6A')[0] === $old, 'Old year remains readable');
    $input['content'] = 'Sửa nhận xét cùng năm';
    check(lb_save_weekly_review($week, $input)['ok'], 'Legacy key in same year editable');
    $rows = cds_json_load(LB_WEEKLY_REVIEWS_FILE);
    check(count($rows) === 2 && $rows[0]['id'] === 'old-review' && $rows[0]['created_at'] === '2026-09-05' && $rows[0]['created_by'] === 'GV cũ' && $rows[0]['custom_field'] === 'Giữ nguyên', 'Legacy identity and unrelated fields preserved');
    $before = file_get_contents(LB_WEEKLY_REVIEWS_FILE);
    $GLOBALS['denied'] = true;
    check(!lb_save_weekly_review($week, $input)['ok'] && file_get_contents(LB_WEEKLY_REVIEWS_FILE) === $before, 'Permission rule unchanged');
    echo "PASS: cross-year review preservation, legacy key compatibility, identity, JSON fallback and permissions\n";
} finally {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
}
