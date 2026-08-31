<?php
require_once __DIR__ . '/database.php';

function cds_db_migrations()
{
    return array(
        '20260730_001_mysql_foundation' => array(
            'description' => 'Bảng nền quản lý nhập dữ liệu và nhật ký thay đổi',
            'statements' => array(
                "CREATE TABLE IF NOT EXISTS cds_import_batches (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    source_type VARCHAR(50) NOT NULL,
                    source_label VARCHAR(255) NOT NULL DEFAULT '',
                    source_checksum CHAR(64) NOT NULL DEFAULT '',
                    status VARCHAR(30) NOT NULL DEFAULT 'pending',
                    records_total INT UNSIGNED NOT NULL DEFAULT 0,
                    records_imported INT UNSIGNED NOT NULL DEFAULT 0,
                    records_skipped INT UNSIGNED NOT NULL DEFAULT 0,
                    error_summary TEXT NULL,
                    started_at DATETIME NULL,
                    completed_at DATETIME NULL,
                    created_by VARCHAR(100) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_cds_import_status (status),
                    KEY idx_cds_import_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS cds_audit_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    actor_user_id VARCHAR(100) NOT NULL DEFAULT '',
                    actor_name VARCHAR(255) NOT NULL DEFAULT '',
                    module_key VARCHAR(80) NOT NULL DEFAULT '',
                    action_key VARCHAR(80) NOT NULL,
                    entity_type VARCHAR(100) NOT NULL DEFAULT '',
                    entity_id VARCHAR(191) NOT NULL DEFAULT '',
                    before_json LONGTEXT NULL,
                    after_json LONGTEXT NULL,
                    request_ip VARCHAR(45) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_cds_audit_entity (entity_type, entity_id),
                    KEY idx_cds_audit_actor (actor_user_id),
                    KEY idx_cds_audit_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ),
        ),
        '20260730_002_core_school_data' => array(
            'description' => 'Bảng năm học, giáo viên, lớp và học sinh theo định danh chuẩn',
            'statements' => array(
                "CREATE TABLE IF NOT EXISTS cds_school_years (
                    id VARCHAR(100) NOT NULL,
                    label VARCHAR(50) NOT NULL,
                    start_date DATE NULL,
                    end_date DATE NULL,
                    is_current TINYINT(1) NOT NULL DEFAULT 0,
                    raw_json LONGTEXT NULL,
                    source_updated_at DATETIME NULL,
                    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_cds_year_current (is_current)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS cds_teachers (
                    id VARCHAR(100) NOT NULL,
                    code VARCHAR(100) NOT NULL DEFAULT '',
                    name VARCHAR(255) NOT NULL,
                    cccd VARCHAR(30) NOT NULL DEFAULT '',
                    dob DATE NULL,
                    gender VARCHAR(30) NOT NULL DEFAULT '',
                    ethnicity VARCHAR(100) NOT NULL DEFAULT '',
                    phone VARCHAR(50) NOT NULL DEFAULT '',
                    email VARCHAR(255) NOT NULL DEFAULT '',
                    hometown VARCHAR(500) NOT NULL DEFAULT '',
                    address VARCHAR(500) NOT NULL DEFAULT '',
                    teaching_level VARCHAR(100) NOT NULL DEFAULT '',
                    specialty VARCHAR(255) NOT NULL DEFAULT '',
                    professional_group VARCHAR(255) NOT NULL DEFAULT '',
                    position_name VARCHAR(255) NOT NULL DEFAULT '',
                    additional_roles TEXT NULL,
                    join_date DATE NULL,
                    salary_grade VARCHAR(50) NOT NULL DEFAULT '',
                    professional_rank VARCHAR(100) NOT NULL DEFAULT '',
                    salary_level VARCHAR(50) NOT NULL DEFAULT '',
                    salary_coefficient VARCHAR(50) NOT NULL DEFAULT '',
                    salary_from DATE NULL,
                    is_probation TINYINT(1) NOT NULL DEFAULT 0,
                    is_principal TINYINT(1) NOT NULL DEFAULT 0,
                    is_vice_principal TINYINT(1) NOT NULL DEFAULT 0,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    note TEXT NULL,
                    raw_json LONGTEXT NULL,
                    source_updated_at DATETIME NULL,
                    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_cds_teacher_code (code),
                    KEY idx_cds_teacher_name (name),
                    KEY idx_cds_teacher_active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS cds_classes (
                    id VARCHAR(100) NOT NULL,
                    school_year_id VARCHAR(100) NOT NULL,
                    name VARCHAR(50) NOT NULL,
                    grade TINYINT UNSIGNED NULL,
                    level_name VARCHAR(30) NOT NULL DEFAULT '',
                    homeroom_teacher_id VARCHAR(100) NULL,
                    room VARCHAR(100) NOT NULL DEFAULT '',
                    capacity SMALLINT UNSIGNED NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    note TEXT NULL,
                    raw_json LONGTEXT NULL,
                    source_updated_at DATETIME NULL,
                    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_cds_class_year_name (school_year_id, name),
                    KEY idx_cds_class_teacher (homeroom_teacher_id),
                    KEY idx_cds_class_active (active),
                    CONSTRAINT fk_cds_class_year
                        FOREIGN KEY (school_year_id) REFERENCES cds_school_years (id)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
                    CONSTRAINT fk_cds_class_teacher
                        FOREIGN KEY (homeroom_teacher_id) REFERENCES cds_teachers (id)
                        ON UPDATE CASCADE ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS cds_students (
                    id VARCHAR(100) NOT NULL,
                    school_year_id VARCHAR(100) NOT NULL,
                    class_id VARCHAR(100) NULL,
                    code VARCHAR(100) NOT NULL DEFAULT '',
                    name VARCHAR(255) NOT NULL,
                    cccd VARCHAR(30) NOT NULL DEFAULT '',
                    dob DATE NULL,
                    gender VARCHAR(30) NOT NULL DEFAULT '',
                    ethnicity VARCHAR(100) NOT NULL DEFAULT '',
                    hometown VARCHAR(500) NOT NULL DEFAULT '',
                    address VARCHAR(500) NOT NULL DEFAULT '',
                    phone VARCHAR(50) NOT NULL DEFAULT '',
                    parent_name VARCHAR(255) NOT NULL DEFAULT '',
                    parent_phone VARCHAR(50) NOT NULL DEFAULT '',
                    is_boarder TINYINT(1) NOT NULL DEFAULT 0,
                    dorm_room VARCHAR(100) NOT NULL DEFAULT '',
                    meal_group VARCHAR(100) NOT NULL DEFAULT '',
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    note TEXT NULL,
                    raw_json LONGTEXT NULL,
                    source_updated_at DATETIME NULL,
                    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_cds_student_code (code),
                    KEY idx_cds_student_name (name),
                    KEY idx_cds_student_class (class_id),
                    KEY idx_cds_student_year (school_year_id),
                    KEY idx_cds_student_boarder (is_boarder, active),
                    CONSTRAINT fk_cds_student_year
                        FOREIGN KEY (school_year_id) REFERENCES cds_school_years (id)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
                    CONSTRAINT fk_cds_student_class
                        FOREIGN KEY (class_id) REFERENCES cds_classes (id)
                        ON UPDATE CASCADE ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ),
        ),
        '20260731_003_shadow_write_settings' => array(
            'description' => 'Công tắc ghi song song JSON sang MySQL',
            'statements' => array(
                "CREATE TABLE IF NOT EXISTS cds_runtime_settings (
                    setting_key VARCHAR(100) NOT NULL,
                    setting_value VARCHAR(255) NOT NULL DEFAULT '',
                    updated_by VARCHAR(100) NOT NULL DEFAULT '',
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "INSERT IGNORE INTO cds_runtime_settings
                    (setting_key, setting_value, updated_by)
                 VALUES ('core_shadow_write', '0', 'migration')",
            ),
        ),
        '20260731_004_read_verification' => array(
            'description' => 'Kiểm chứng dữ liệu đọc giữa JSON và MySQL',
            'statements' => array(
                "CREATE TABLE IF NOT EXISTS cds_read_verification_status (
                    entity_type VARCHAR(50) NOT NULL,
                    verify_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    json_count INT UNSIGNED NOT NULL DEFAULT 0,
                    mysql_count INT UNSIGNED NOT NULL DEFAULT 0,
                    details_json LONGTEXT NULL,
                    checked_at DATETIME NULL,
                    PRIMARY KEY (entity_type),
                    KEY idx_cds_read_verify_status (verify_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "INSERT IGNORE INTO cds_runtime_settings
                    (setting_key, setting_value, updated_by)
                 VALUES ('core_read_verify', '0', 'migration')",
            ),
        ),
        '20260830_005_safe_core_sql_read' => array(
            'description' => 'Công tắc đọc dữ liệu lõi từ MySQL có tự động quay về JSON',
            'statements' => array(
                "INSERT IGNORE INTO cds_runtime_settings
                    (setting_key, setting_value, updated_by)
                 VALUES ('core_sql_read', '0', 'migration')",
            ),
        ),
        '20260831_006_pilot_core_sql_primary_write' => array(
            'description' => 'Công tắc thí điểm ghi MySQL trước cho giáo viên, lớp và học sinh',
            'statements' => array(
                "INSERT IGNORE INTO cds_runtime_settings
                    (setting_key, setting_value, updated_by)
                 VALUES ('core_sql_primary_write', '0', 'migration')",
            ),
        ),
        '20260831_007_pilot_school_year_sql_write' => array(
            'description' => 'Công tắc thí điểm ghi MySQL trước cho năm học',
            'statements' => array(
                "INSERT IGNORE INTO cds_runtime_settings
                    (setting_key, setting_value, updated_by)
                 VALUES ('core_sql_primary_year_write', '0', 'migration')",
            ),
        ),
        '20260831_008_pilot_batch_sql_write' => array(
            'description' => 'Công tắc giai đoạn 2B nhập và xóa hàng loạt an toàn',
            'statements' => array(
                "INSERT IGNORE INTO cds_runtime_settings
                    (setting_key, setting_value, updated_by)
                 VALUES ('core_sql_primary_batch_write', '0', 'migration')",
            ),
        ),
    );
}

function cds_db_ensure_migration_table(PDO $pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cds_schema_migrations (
            migration_id VARCHAR(191) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (migration_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function cds_db_applied_migrations(PDO $pdo)
{
    $tableExists = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'cds_schema_migrations'"
    )->fetchColumn();

    if ((int)$tableExists === 0) {
        return array();
    }

    $rows = $pdo->query(
        'SELECT migration_id, description, applied_at
         FROM cds_schema_migrations
         ORDER BY migration_id'
    )->fetchAll();

    $applied = array();
    foreach ($rows as $row) {
        $applied[$row['migration_id']] = $row;
    }

    return $applied;
}

function cds_db_migration_status()
{
    $pdo = cds_db();
    $available = cds_db_migrations();
    $applied = cds_db_applied_migrations($pdo);
    $pending = array();

    foreach ($available as $id => $migration) {
        if (!isset($applied[$id])) {
            $pending[$id] = $migration;
        }
    }

    return array(
        'available' => $available,
        'applied' => $applied,
        'pending' => $pending,
    );
}

function cds_db_run_pending_migrations()
{
    $pdo = cds_db();
    $lockName = 'cds_schema_migrations_lock';
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lockStmt->execute(array($lockName));

    if ((int)$lockStmt->fetchColumn() !== 1) {
        throw new RuntimeException('Không thể khóa tiến trình nâng cấp. Hãy thử lại sau.');
    }

    $completed = array();
    try {
        cds_db_ensure_migration_table($pdo);
        $status = cds_db_migration_status();
        foreach ($status['pending'] as $id => $migration) {
            foreach ($migration['statements'] as $sql) {
                $pdo->exec($sql);
            }

            $insert = $pdo->prepare(
                'INSERT INTO cds_schema_migrations
                    (migration_id, description, applied_at)
                 VALUES (?, ?, NOW())'
            );
            $insert->execute(array($id, $migration['description']));
            $completed[] = $id;
        }
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute(array($lockName));
    }

    return $completed;
}
