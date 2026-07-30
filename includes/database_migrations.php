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
