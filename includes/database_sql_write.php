<?php
require_once __DIR__ . '/database_sql_read.php';
require_once __DIR__ . '/database_core_incremental.php';

function cds_core_sql_write_enabled()
{
    if (array_key_exists('cds_core_sql_write_enabled_cache', $GLOBALS)) {
        return (bool)$GLOBALS['cds_core_sql_write_enabled_cache'];
    }
    try {
        $stmt = cds_db()->prepare(
            "SELECT setting_value FROM cds_runtime_settings WHERE setting_key='core_sql_primary_write'"
        );
        $stmt->execute();
        $enabled = (string)$stmt->fetchColumn() === '1';
        $GLOBALS['cds_core_sql_write_enabled_cache'] = $enabled;
        return $enabled;
    } catch (Throwable $e) {
        $GLOBALS['cds_core_sql_write_enabled_cache'] = false;
        return false;
    }
}

function cds_core_sql_write_readiness()
{
    $read = cds_core_sql_read_status();
    if (empty($read['effective'])) {
        return array('ready' => false, 'reason' => 'CDS chưa đọc SQL an toàn: ' . ($read['reason'] ?? 'chưa đủ điều kiện.'));
    }
    if (!cds_shadow_write_enabled()) {
        return array('ready' => false, 'reason' => 'Ghi bản dự phòng JSON → MySQL đang tắt.');
    }
    if (is_array(cds_shadow_pending_status())) {
        return array('ready' => false, 'reason' => 'Còn một lần đồng bộ MySQL đang chờ xử lý.');
    }
    if (is_array(cds_core_sql_backup_pending_status())) {
        return array('ready' => false, 'reason' => 'Còn một bản dự phòng JSON đang chờ phục hồi.');
    }
    return array('ready' => true, 'reason' => '');
}

function cds_core_sql_write_set($enabled, $actor)
{
    if ($enabled) {
        $readiness = cds_core_sql_write_readiness();
        if (empty($readiness['ready'])) throw new RuntimeException($readiness['reason']);
    }
    $stmt = cds_db()->prepare(
        "INSERT INTO cds_runtime_settings (setting_key, setting_value, updated_by, updated_at)
         VALUES ('core_sql_primary_write', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
             updated_by=VALUES(updated_by), updated_at=NOW()"
    );
    $stmt->execute(array($enabled ? '1' : '0', (string)($actor['username'] ?? $actor['name'] ?? '')));
    $GLOBALS['cds_core_sql_write_enabled_cache'] = (bool)$enabled;
}

function cds_core_sql_backup_pending_path()
{
    return defined('DATA_PATH') ? DATA_PATH . '/mysql_primary_json_backup_pending.json' : '';
}

function cds_core_sql_backup_pending_status()
{
    $path = cds_core_sql_backup_pending_path();
    if ($path === '' || !is_file($path)) return null;
    $value = json_decode((string)@file_get_contents($path), true);
    return is_array($value) ? $value : array('at' => '', 'entity_type' => '', 'entity_id' => '');
}

function cds_core_sql_backup_pending_mark($entityType, $entityId)
{
    $path = cds_core_sql_backup_pending_path();
    if ($path === '') return false;
    $payload = json_encode(array(
        'at' => date('c'), 'entity_type' => (string)$entityType, 'entity_id' => (string)$entityId,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $payload !== false && @file_put_contents($path, $payload, LOCK_EX) !== false;
}

function cds_core_sql_backup_pending_clear()
{
    $path = cds_core_sql_backup_pending_path();
    return $path === '' || !is_file($path) || @unlink($path);
}

function cds_core_sql_restore_json_backup()
{
    $pending = cds_core_sql_backup_pending_status();
    if (!is_array($pending)) throw new RuntimeException('Không có bản dự phòng JSON đang chờ phục hồi.');
    $types = array(
        'teacher' => array('verify' => 'teachers', 'table' => 'cds_teachers', 'file' => defined('DATA_PATH') ? DATA_PATH . '/teachers.json' : ''),
        'class' => array('verify' => 'classes', 'table' => 'cds_classes', 'file' => defined('DATA_PATH') ? DATA_PATH . '/classes.json' : ''),
        'student' => array('verify' => 'students', 'table' => 'cds_students', 'file' => defined('DATA_PATH') ? DATA_PATH . '/students.json' : ''),
    );
    $type = (string)($pending['entity_type'] ?? '');
    if (!isset($types[$type]) || $types[$type]['file'] === '') {
        throw new RuntimeException('Dấu phục hồi không xác định được nhóm dữ liệu.');
    }
    $meta = $types[$type];
    $stmt = cds_db()->query('SELECT raw_json FROM ' . $meta['table'] . ' ORDER BY id');
    $rows = array();
    while ($dbRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = json_decode((string)($dbRow['raw_json'] ?? ''), true);
        if (!is_array($row) || trim((string)($row['id'] ?? '')) === '') {
            throw new RuntimeException('MySQL có bản ghi thiếu raw_json hợp lệ; chưa thay đổi tệp dự phòng.');
        }
        $rows[] = $row;
    }
    if (!save_json($meta['file'], $rows)) throw new RuntimeException('Không thể ghi tệp JSON dự phòng.');
    if (!cds_read_verify_mark_entity_match($meta['verify'], count($rows))) {
        throw new RuntimeException('JSON đã phục hồi nhưng chưa cập nhật được trạng thái đối chiếu.');
    }
    if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear($meta['verify']);
    if (!cds_core_sql_backup_pending_clear()) throw new RuntimeException('Đã phục hồi JSON nhưng chưa xóa được dấu đang chờ.');
    return array('entity_type' => $type, 'count' => count($rows));
}

/**
 * Ghi một bản ghi MySQL trước, rồi lưu toàn bộ JSON làm bản dự phòng.
 * Chỉ dùng cho giáo viên/lớp/học sinh; các nhóm khác giữ luồng JSON-first.
 */
function cds_core_sql_primary_save($entityType, $entityId, array $rows, $jsonFile)
{
    if (!cds_core_sql_write_enabled()) return false;
    $types = array(
        'teacher' => array('verify' => 'teachers', 'table' => 'cds_teachers'),
        'class' => array('verify' => 'classes', 'table' => 'cds_classes'),
        'student' => array('verify' => 'students', 'table' => 'cds_students'),
    );
    if (!isset($types[$entityType])) return false;
    $readiness = cds_core_sql_write_readiness();
    if (empty($readiness['ready'])) throw new RuntimeException($readiness['reason']);
    if (!cds_core_sql_backup_pending_mark($entityType, $entityId)) {
        throw new RuntimeException('Không tạo được dấu bảo vệ bản dự phòng JSON; chưa ghi dữ liệu.');
    }

    $target = null;
    foreach ($rows as $row) {
        if (is_array($row) && (string)($row['id'] ?? '') === (string)$entityId) {
            $target = $row;
            break;
        }
    }
    $meta = $types[$entityType];
    $pdo = cds_db();
    cds_read_verify_mark_entity_pending($meta['verify']);
    try {
        $pdo->beginTransaction();
        if ($target !== null) {
            if ($entityType === 'teacher') cds_core_incremental_upsert_teacher($pdo, $target);
            elseif ($entityType === 'class') cds_core_incremental_upsert_class($pdo, $target, cds_core_incremental_current_year_id());
            else cds_core_incremental_upsert_student($pdo, $target, cds_core_incremental_current_year_id());
        } else {
            $stmt = $pdo->prepare('DELETE FROM ' . $meta['table'] . ' WHERE id=?');
            $stmt->execute(array((string)$entityId));
        }
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $meta['table'])->fetchColumn();
        if ($count !== count($rows)) throw new RuntimeException('Số lượng MySQL không khớp dữ liệu chuẩn bị lưu.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        cds_core_sql_backup_pending_clear();
        throw $e;
    }

    if (!save_json($jsonFile, $rows)) {
        error_log('[CDS SQL primary] MySQL committed; JSON backup pending for ' . $entityType . ' ' . $entityId);
        if (function_exists('flash')) {
            flash('Dữ liệu đã lưu vào MySQL nhưng bản dự phòng JSON chưa cập nhật. Quản trị cần phục hồi tại trang Trạng thái MySQL.', 'warning');
        }
        return true;
    }
    if (!cds_read_verify_mark_entity_match($meta['verify'], count($rows))) {
        error_log('[CDS SQL primary] JSON saved; verification status remains pending.');
        return true;
    }
    if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear($meta['verify']);
    if (!cds_core_sql_backup_pending_clear()) {
        error_log('[CDS SQL primary] Could not clear JSON backup pending marker.');
    }
    return true;
}
