<?php
/** Sổ đầu bài: dữ liệu độc lập theo từng tiết, dùng chung Tuần học và TKB. */
require_once __DIR__ . '/timetable_store.php';

define('LB_RECORDS_FILE', DATA_PATH . '/lesson_book_records.json');
define('LB_CURRICULUM_FILE', DATA_PATH . '/lesson_book_curriculum.json');
define('LB_SETTINGS_FILE', DATA_PATH . '/lesson_book_settings.json');
define('LB_LOCKS_FILE', DATA_PATH . '/lesson_book_locks.json');
define('LB_AUDIT_FILE', DATA_PATH . '/lesson_book_audit.json');
define('LB_WEEKLY_REVIEWS_FILE', DATA_PATH . '/lesson_book_weekly_reviews.json');
define('LB_CURRICULUM_PERMISSIONS_FILE', DATA_PATH . '/lesson_book_curriculum_permissions.json');
define('LB_SIGNATURES_DIR', DATA_PATH . '/lesson_book_signatures');
require_once dirname(__DIR__, 2) . '/includes/database_lesson_book.php';
require_once __DIR__ . '/lesson_book_store_more.php';
