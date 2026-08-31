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

function cds_core_sql_batch_write_enabled()
{
    if (array_key_exists('cds_core_sql_batch_write_enabled_cache', $GLOBALS)) {
        return (bool)$GLOBALS['cds_core_sql_batch_write_enabled_cache'];
    }
    try {
        $stmt = cds_db()->query(
            "SELECT setting_value FROM cds_runtime_settings WHERE setting_key='core_sql_primary_batch_write'"
        );
        $enabled = (string)$stmt->fetchColumn() === '1';
        $GLOBALS['cds_core_sql_batch_write_enabled_cache'] = $enabled;
        return $enabled;
    } catch (Throwable $e) {
        $GLOBALS['cds_core_sql_batch_write_enabled_cache'] = false;
        return false;
    }
}

function cds_core_sql_batch_write_readiness()
{
    if (!cds_core_sql_write_enabled() || !cds_core_sql_year_write_enabled()) {
        return array('ready' => false, 'reason' => 'Cần bật và kiểm thử thành công giai đoạn ghi đơn lẻ và 2A trước.');
    }
    return cds_core_sql_write_readiness();
}

function cds_core_sql_batch_write_set($enabled, $actor)
{
    if ($enabled) {
        $readiness = cds_core_sql_batch_write_readiness();
        if (empty($readiness['ready'])) throw new RuntimeException($readiness['reason']);
    }
    $stmt = cds_db()->prepare(
        "INSERT INTO cds_runtime_settings (setting_key, setting_value, updated_by, updated_at)
         VALUES ('core_sql_primary_batch_write', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
             updated_by=VALUES(updated_by), updated_at=NOW()"
    );
    $stmt->execute(array($enabled ? '1' : '0', (string)($actor['username'] ?? $actor['name'] ?? '')));
    $GLOBALS['cds_core_sql_batch_write_enabled_cache'] = (bool)$enabled;
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
    if (!$enabled) {
        try {
            $disableYear = cds_db()->prepare(
                "UPDATE cds_runtime_settings
                 SET setting_value='0', updated_by=?, updated_at=NOW()
                 WHERE setting_key='core_sql_primary_year_write'"
            );
            $disableYear->execute(array((string)($actor['username'] ?? $actor['name'] ?? '')));
        } catch (Throwable $e) {
            // Migration 007 có thể chưa được cài; công tắc cha vẫn được tắt an toàn.
        }
        $GLOBALS['cds_core_sql_year_write_enabled_cache'] = false;
        try {
            $disableBatch = cds_db()->prepare(
                "UPDATE cds_runtime_settings SET setting_value='0', updated_by=?, updated_at=NOW()
                 WHERE setting_key='core_sql_primary_batch_write'"
            );
            $disableBatch->execute(array((string)($actor['username'] ?? $actor['name'] ?? '')));
        } catch (Throwable $e) {
            // Migration 008 có thể chưa được cài.
        }
        $GLOBALS['cds_core_sql_batch_write_enabled_cache'] = false;
    }
}

function cds_core_sql_year_write_enabled()
{
    if (array_key_exists('cds_core_sql_year_write_enabled_cache', $GLOBALS)) {
        return (bool)$GLOBALS['cds_core_sql_year_write_enabled_cache'];
    }
    try {
        $stmt = cds_db()->prepare(
            "SELECT setting_value FROM cds_runtime_settings WHERE setting_key='core_sql_primary_year_write'"
        );
        $stmt->execute();
        $enabled = (string)$stmt->fetchColumn() === '1';
        $GLOBALS['cds_core_sql_year_write_enabled_cache'] = $enabled;
        return $enabled;
    } catch (Throwable $e) {
        $GLOBALS['cds_core_sql_year_write_enabled_cache'] = false;
        return false;
    }
}

function cds_core_sql_year_write_readiness()
{
    if (!cds_core_sql_write_enabled()) {
        return array('ready' => false, 'reason' => 'Cần bật và kiểm thử ghi MySQL trước cho giáo viên, lớp và học sinh.');
    }
    return cds_core_sql_write_readiness();
}

function cds_core_sql_year_write_set($enabled, $actor)
{
    if ($enabled) {
        $readiness = cds_core_sql_year_write_readiness();
        if (empty($readiness['ready'])) throw new RuntimeException($readiness['reason']);
    }
    $stmt = cds_db()->prepare(
        "INSERT INTO cds_runtime_settings (setting_key, setting_value, updated_by, updated_at)
         VALUES ('core_sql_primary_year_write', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
             updated_by=VALUES(updated_by), updated_at=NOW()"
    );
    $stmt->execute(array($enabled ? '1' : '0', (string)($actor['username'] ?? $actor['name'] ?? '')));
    $GLOBALS['cds_core_sql_year_write_enabled_cache'] = (bool)$enabled;
    if (!$enabled) {
        try {
            $stmt = cds_db()->prepare(
                "UPDATE cds_runtime_settings SET setting_value='0', updated_by=?, updated_at=NOW()
                 WHERE setting_key='core_sql_primary_batch_write'"
            );
            $stmt->execute(array((string)($actor['username'] ?? $actor['name'] ?? '')));
        } catch (Throwable $e) {
            // Migration 008 có thể chưa được cài.
        }
        $GLOBALS['cds_core_sql_batch_write_enabled_cache'] = false;
    }
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
    try {
        $token = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $token = uniqid('sql_primary_', true);
    }
    $GLOBALS['cds_core_sql_backup_pending_token'] = $token;
    $payload = json_encode(array(
        'token' => $token, 'at' => date('c'),
        'entity_type' => (string)$entityType, 'entity_id' => (string)$entityId,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $payload !== false && @file_put_contents($path, $payload, LOCK_EX) !== false;
}

function cds_core_sql_backup_pending_clear()
{
    $path = cds_core_sql_backup_pending_path();
    if ($path === '' || !is_file($path)) return true;
    $token = (string)($GLOBALS['cds_core_sql_backup_pending_token'] ?? '');
    if ($token !== '') {
        $current = cds_core_sql_backup_pending_status();
        if (is_array($current) && (string)($current['token'] ?? '') !== $token) return true;
    }
    return @unlink($path);
}

function cds_core_sql_restore_json_backup()
{
    $pending = cds_core_sql_backup_pending_status();
    if (!is_array($pending)) throw new RuntimeException('Không có bản dự phòng JSON đang chờ phục hồi.');
    $types = array(
        'school_year' => array('verify' => 'years', 'table' => 'cds_school_years', 'file' => defined('DATA_PATH') ? DATA_PATH . '/school_years.json' : ''),
        'teacher' => array('verify' => 'teachers', 'table' => 'cds_teachers', 'file' => defined('DATA_PATH') ? DATA_PATH . '/teachers.json' : ''),
        'class' => array('verify' => 'classes', 'table' => 'cds_classes', 'file' => defined('DATA_PATH') ? DATA_PATH . '/classes.json' : ''),
        'student' => array('verify' => 'students', 'table' => 'cds_students', 'file' => defined('DATA_PATH') ? DATA_PATH . '/students.json' : ''),
    );
    $type = (string)($pending['entity_type'] ?? '');
    if ($type === 'core_batch') {
        $allRows = array();
        foreach ($types as $entityType => $meta) {
            $stmt = cds_db()->query('SELECT raw_json FROM ' . $meta['table'] . ' ORDER BY id');
            $rows = array();
            while ($dbRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row = json_decode((string)($dbRow['raw_json'] ?? ''), true);
                if (!is_array($row) || trim((string)($row['id'] ?? '')) === '') {
                    throw new RuntimeException('MySQL có raw_json không hợp lệ; chưa thay đổi tệp dự phòng.');
                }
                $rows[] = $row;
            }
            if (!$rows) throw new RuntimeException('Từ chối phục hồi lô vì nhóm ' . $entityType . ' đang rỗng.');
            $allRows[$entityType] = $rows;
        }
        $total = 0;
        foreach ($types as $entityType => $meta) {
            $rows = $allRows[$entityType];
            if (!cds_json_save($meta['file'], $rows)) {
                throw new RuntimeException('Chưa phục hồi được ' . basename($meta['file']) . '.');
            }
            $total += count($rows);
            cds_read_verify_mark_entity_match($meta['verify'], count($rows));
        }
        if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear();
        if (!cds_core_sql_backup_pending_clear()) throw new RuntimeException('Đã phục hồi JSON nhưng chưa xóa được dấu đang chờ.');
        return array('entity_type' => $type, 'count' => $total);
    }
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

function cds_core_sql_years_map(array $rows)
{
    $map = array();
    foreach ($rows as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id === '' || isset($map[$id])) throw new RuntimeException('Năm học thiếu hoặc trùng ID.');
        $map[$id] = cds_read_verify_hash($row);
    }
    ksort($map);
    return $map;
}

function cds_core_sql_primary_year_save($entityId, array $originalYears, array $years, $jsonFile)
{
    if (function_exists('cds_batch_primary_staging_active') && cds_batch_primary_staging_active()) return false;
    if (!cds_core_sql_year_write_enabled()) return false;
    $readiness = cds_core_sql_year_write_readiness();
    if (empty($readiness['ready'])) throw new RuntimeException($readiness['reason']);
    $current = array_values(array_filter($years, static function ($row) { return !empty($row['is_current']); }));
    if (count($current) !== 1) throw new RuntimeException('Cần đúng một năm học hiện hành.');
    if (!$years) throw new RuntimeException('Không thể xóa toàn bộ năm học.');
    if (!cds_core_sql_backup_pending_mark('school_year', $entityId)) {
        throw new RuntimeException('Không tạo được dấu bảo vệ bản dự phòng JSON; chưa ghi dữ liệu.');
    }

    $pdo = null;
    $committed = false;
    try {
        $pdo = cds_db();
        $pdo->beginTransaction();
        $actual = array();
        $locked = $pdo->query('SELECT id, raw_json FROM cds_school_years FOR UPDATE');
        while ($dbRow = $locked->fetch(PDO::FETCH_ASSOC)) {
            $row = json_decode((string)($dbRow['raw_json'] ?? ''), true);
            if (!is_array($row)) throw new RuntimeException('MySQL có raw_json năm học không hợp lệ.');
            $actual[(string)$dbRow['id']] = cds_read_verify_hash($row);
        }
        ksort($actual);
        if ($actual !== cds_core_sql_years_map($originalYears)) {
            throw new RuntimeException('Năm học vừa được yêu cầu khác cập nhật; vui lòng tải lại trước khi lưu.');
        }

        $sql = "INSERT INTO cds_school_years
            (id, label, start_date, end_date, is_current, raw_json, source_updated_at, imported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE label=VALUES(label), start_date=VALUES(start_date),
                end_date=VALUES(end_date), is_current=VALUES(is_current), raw_json=VALUES(raw_json),
                source_updated_at=VALUES(source_updated_at), imported_at=NOW()";
        foreach ($years as $row) {
            cds_core_upsert($pdo, $sql, array(
                (string)$row['id'], cds_core_string($row, 'label'),
                cds_core_date($row['start'] ?? ''), cds_core_date($row['end'] ?? ''),
                cds_core_bool($row, 'is_current'), cds_core_json($row), cds_core_datetime($row),
            ));
        }
        $currentId = (string)$current[0]['id'];
        $stmt = $pdo->prepare('UPDATE cds_classes SET school_year_id=? WHERE school_year_id<>?');
        $stmt->execute(array($currentId, $currentId));
        $stmt = $pdo->prepare('UPDATE cds_students SET school_year_id=? WHERE school_year_id<>?');
        $stmt->execute(array($currentId, $currentId));
        cds_core_delete_missing_rows($pdo, 'cds_school_years', array_column($years, 'id'));
        $count = (int)$pdo->query('SELECT COUNT(*) FROM cds_school_years')->fetchColumn();
        if ($count !== count($years)) throw new RuntimeException('Số lượng năm học MySQL không khớp.');

        $actor = function_exists('current_user') ? current_user() : array();
        $audit = $pdo->prepare(
            "INSERT INTO cds_audit_log
                (actor_user_id, actor_name, module_key, action_key, entity_type,
                 entity_id, after_json, request_ip, created_at)
             VALUES (?, ?, 'csdl', 'sql_primary_year_write', 'school_year', ?, ?, ?, NOW())"
        );
        $audit->execute(array(
            (string)($actor['id'] ?? ''), (string)($actor['name'] ?? ''),
            (string)$entityId, cds_core_json($years), (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ));
        $pdo->commit();
        $committed = true;
        cds_read_verify_mark_entity_pending('years');
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (!$committed) cds_core_sql_backup_pending_clear();
        throw $e;
    }

    if (!save_json($jsonFile, $years)) {
        error_log('[CDS SQL primary] MySQL committed; JSON year backup pending.');
        if (function_exists('flash')) flash('Năm học đã lưu vào MySQL nhưng JSON dự phòng chưa cập nhật. Hãy phục hồi tại trang Trạng thái MySQL.', 'warning');
        return true;
    }
    if (!cds_read_verify_mark_entity_match('years', count($years))) return true;
    if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear();
    cds_core_sql_backup_pending_clear();
    return true;
}

function cds_core_sql_assert_unchanged_rows(PDO $pdo, $table, $entityId, array $rows)
{
    $expected = array();
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id !== '' && $id !== (string)$entityId) {
            $expected[$id] = cds_read_verify_hash($row);
        }
    }
    $actual = array();
    $stmt = $pdo->query('SELECT id, raw_json FROM ' . $table . ' FOR UPDATE');
    while ($dbRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (string)($dbRow['id'] ?? '');
        if ($id === (string)$entityId) continue;
        $row = json_decode((string)($dbRow['raw_json'] ?? ''), true);
        if (!is_array($row)) throw new RuntimeException('MySQL có raw_json không hợp lệ.');
        $actual[$id] = cds_read_verify_hash($row);
    }
    ksort($expected);
    ksort($actual);
    if ($expected !== $actual) {
        throw new RuntimeException('Dữ liệu vừa được yêu cầu khác cập nhật; vui lòng tải lại trước khi lưu.');
    }
}

/**
 * Ghi một bản ghi MySQL trước, rồi lưu toàn bộ JSON làm bản dự phòng.
 * Dùng cho giáo viên/lớp/học sinh; năm học có transaction ảnh chụp riêng.
 */
function cds_core_sql_primary_save($entityType, $entityId, array $rows, $jsonFile)
{
    if (function_exists('cds_batch_primary_staging_active') && cds_batch_primary_staging_active()) return false;
    if (!cds_core_sql_write_enabled()) return false;
    // Nhập/xóa hàng loạt tiếp tục dùng JSON-first và chỉ chụp MySQL một lần.
    // Việc này tránh hàng trăm transaction SQL trong một yêu cầu.
    if ((int)($GLOBALS['cds_shadow_batch_depth'] ?? 0) > 0) return false;
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
    $pdo = null;
    $committed = false;
    try {
        $pdo = cds_db();
        $pdo->beginTransaction();
        // Khóa và đối chiếu toàn bộ các dòng còn lại để không ghi đè một thay
        // đổi đồng thời bằng bản JSON được đọc trước đó.
        cds_core_sql_assert_unchanged_rows($pdo, $meta['table'], $entityId, $rows);
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
        $committed = true;
        cds_read_verify_mark_entity_pending($meta['verify']);
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (!$committed) cds_core_sql_backup_pending_clear();
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
