<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/database_shadow.php';
require_once __DIR__ . '/database_read_verify.php';

function cds_core_sql_read_enabled()
{
    static $enabled = null;
    if ($enabled !== null) return $enabled;
    try {
        $stmt = cds_db()->prepare(
            "SELECT setting_value FROM cds_runtime_settings
             WHERE setting_key='core_sql_read'"
        );
        $stmt->execute();
        $enabled = (string)$stmt->fetchColumn() === '1';
    } catch (Throwable $e) {
        $enabled = false;
    }
    return $enabled;
}

function cds_core_sql_read_ready()
{
    $readiness = cds_core_sql_read_readiness();
    return !empty($readiness['ready']);
}

/**
 * Trả về nguyên nhân có thể khiến CDS phải dùng JSON. Hàm chỉ đọc các trạng
 * thái sẵn có, không ghi log/bảng và không thay đổi nguồn dữ liệu.
 */
function cds_core_sql_read_readiness()
{
    $pending = function_exists('cds_shadow_pending_status')
        ? cds_shadow_pending_status()
        : null;
    if (is_array($pending)) {
        return array(
            'ready' => false,
            'reason_code' => 'shadow_sync_pending',
            'reason' => 'Lần ghi JSON gần nhất chưa được MySQL xác nhận đồng bộ.',
            'entities' => array(),
        );
    }

    if (!cds_shadow_write_enabled()) {
        return array(
            'ready' => false,
            'reason_code' => 'shadow_write_disabled',
            'reason' => 'Ghi song song JSON → MySQL đang tắt.',
            'entities' => array(),
        );
    }

    if (!cds_read_verify_enabled()) {
        return array(
            'ready' => false,
            'reason_code' => 'read_verification_disabled',
            'reason' => 'Kiểm chứng đọc MySQL đang tắt.',
            'entities' => array(),
        );
    }

    $status = cds_read_verify_status();
    $issues = array();
    foreach (array('years','teachers','classes','students') as $entityType) {
        $row = $status[$entityType] ?? null;
        if (!$row) {
            $issues[$entityType] = 'Chưa có kết quả kiểm chứng.';
            continue;
        }
        if (($row['verify_status'] ?? '') !== 'match') {
            $issues[$entityType] = 'Dữ liệu đang sai lệch.';
            continue;
        }
        if ((int)($row['json_count'] ?? -1) !== (int)($row['mysql_count'] ?? -2)) {
            $issues[$entityType] = 'Số lượng JSON và MySQL không khớp.';
        }
    }

    if ($issues) {
        return array(
            'ready' => false,
            'reason_code' => 'verification_not_match',
            'reason' => 'Một hoặc nhiều nhóm dữ liệu chưa khớp.',
            'entities' => $issues,
        );
    }

    return array(
        'ready' => true,
        'reason_code' => '',
        'reason' => '',
        'entities' => array(),
    );
}

function cds_core_sql_read_status()
{
    $configured = cds_core_sql_read_enabled();
    $readiness = cds_core_sql_read_readiness();
    return array_merge($readiness, array(
        'configured' => $configured,
        'effective' => $configured && !empty($readiness['ready']),
    ));
}

function cds_core_sql_read_set($enabled, $actor)
{
    if ($enabled && !cds_core_sql_read_ready()) {
        throw new RuntimeException(
            'Chưa thể đọc SQL: cần bật ghi song song và xác nhận đủ bốn nhóm dữ liệu đang khớp.'
        );
    }
    $stmt = cds_db()->prepare(
        "INSERT INTO cds_runtime_settings
            (setting_key, setting_value, updated_by, updated_at)
         VALUES ('core_sql_read', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
            updated_by=VALUES(updated_by), updated_at=NOW()"
    );
    $stmt->execute(array(
        $enabled ? '1' : '0',
        (string)($actor['username'] ?? $actor['name'] ?? ''),
    ));
}

function cds_core_sql_read_cache_clear($entityType = null)
{
    if (!isset($GLOBALS['cds_core_sql_rows_cache'])
        || !is_array($GLOBALS['cds_core_sql_rows_cache'])) return;
    if ($entityType === null) $GLOBALS['cds_core_sql_rows_cache'] = array();
    else unset($GLOBALS['cds_core_sql_rows_cache'][(string)$entityType]);
}

function cds_core_sql_rows($entityType)
{
    if (!empty($GLOBALS['cds_force_json_core_read'])) return null;
    $readStatus = cds_core_sql_read_status();
    if (empty($readStatus['effective'])) return null;
    $tables = array(
        'years' => 'cds_school_years',
        'teachers' => 'cds_teachers',
        'classes' => 'cds_classes',
        'students' => 'cds_students',
    );
    if (!isset($tables[$entityType])) return null;
    $cache = $GLOBALS['cds_core_sql_rows_cache'] ?? array();
    if (array_key_exists($entityType, $cache)) return $cache[$entityType];
    try {
        $rows = array();
        $stmt = cds_db()->query('SELECT raw_json FROM ' . $tables[$entityType] . ' ORDER BY id');
        while ($dbRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row = json_decode((string)($dbRow['raw_json'] ?? ''), true);
            if (!is_array($row) || trim((string)($row['id'] ?? '')) === '') return null;
            $rows[] = $row;
        }
        $status = cds_read_verify_status();
        $expected = (int)($status[$entityType]['mysql_count'] ?? -1);
        if ($expected < 0 || count($rows) !== $expected) return null;
        $GLOBALS['cds_core_sql_rows_cache'][$entityType] = $rows;
        return $rows;
    } catch (Throwable $e) {
        error_log('[CDS MySQL safe read] ' . $entityType . ': ' . $e->getMessage());
        return null;
    }
}
