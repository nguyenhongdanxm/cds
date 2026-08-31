<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/database_read_verify.php';

function cds_shadow_write_enabled()
{
    if (array_key_exists('cds_shadow_write_enabled_cache', $GLOBALS)) {
        return (bool)$GLOBALS['cds_shadow_write_enabled_cache'];
    }
    try {
        $stmt = cds_db()->prepare(
            "SELECT setting_value
             FROM cds_runtime_settings
             WHERE setting_key='core_shadow_write'"
        );
        $stmt->execute();
        $enabled = (string)$stmt->fetchColumn() === '1';
        $GLOBALS['cds_shadow_write_enabled_cache'] = $enabled;
        return $enabled;
    } catch (Throwable $e) {
        $GLOBALS['cds_shadow_write_enabled_cache'] = false;
        return false;
    }
}

function cds_shadow_write_set($enabled, $actor)
{
    $stmt = cds_db()->prepare(
        "INSERT INTO cds_runtime_settings
            (setting_key, setting_value, updated_by, updated_at)
         VALUES ('core_shadow_write', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            setting_value=VALUES(setting_value),
            updated_by=VALUES(updated_by),
            updated_at=NOW()"
    );
    $stmt->execute(array(
        $enabled ? '1' : '0',
        (string)($actor['username'] ?? $actor['name'] ?? ''),
    ));
    $GLOBALS['cds_shadow_write_enabled_cache'] = (bool)$enabled;
}

function cds_shadow_pending_path()
{
    return defined('DATA_PATH') ? DATA_PATH . '/mysql_shadow_pending.json' : '';
}

function cds_shadow_pending_status()
{
    $path = cds_shadow_pending_path();
    if ($path === '' || !is_file($path)) return null;
    $decoded = json_decode((string)@file_get_contents($path), true);
    return is_array($decoded) ? $decoded : array('at' => '', 'entity_type' => '', 'entity_id' => '');
}

function cds_shadow_pending_mark($entityType, $entityId)
{
    $path = cds_shadow_pending_path();
    if ($path === '') return false;
    if (empty($GLOBALS['cds_shadow_pending_token'])) {
        try {
            $GLOBALS['cds_shadow_pending_token'] = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $GLOBALS['cds_shadow_pending_token'] = uniqid('shadow_', true);
        }
    }
    $payload = json_encode(array(
        'token' => (string)$GLOBALS['cds_shadow_pending_token'],
        'at' => date('c'),
        'entity_type' => (string)$entityType,
        'entity_id' => (string)$entityId,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($path, $payload, LOCK_EX) !== false;
}

function cds_shadow_pending_clear()
{
    $path = cds_shadow_pending_path();
    if ($path === '' || !is_file($path)) return true;
    $token = (string)($GLOBALS['cds_shadow_pending_token'] ?? '');
    if ($token !== '') {
        $current = cds_shadow_pending_status();
        if (is_array($current) && (string)($current['token'] ?? '') !== $token) {
            return true;
        }
    }
    return @unlink($path);
}

/**
 * Bắt đầu một thao tác hàng loạt. Trong khoảng này các hàm save/delete vẫn
 * ghi JSON như cũ, nhưng MySQL chỉ được đồng bộ một lần khi kết thúc.
 */
function cds_shadow_batch_begin()
{
    $depth = (int)($GLOBALS['cds_shadow_batch_depth'] ?? 0);
    if ($depth === 0) {
        $GLOBALS['cds_shadow_batch_enabled'] = cds_shadow_write_enabled();
        $GLOBALS['cds_shadow_batch_dirty'] = array();
        $GLOBALS['cds_shadow_batch_previous_force_json'] = !empty($GLOBALS['cds_force_json_core_read']);
        // Trong một lô, các lần lưu sau phải thấy JSON vừa được lần lưu trước
        // cập nhật; nếu tiếp tục đọc cache SQL, các dòng trước có thể bị mất.
        $GLOBALS['cds_force_json_core_read'] = true;
        if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear();
    }
    $GLOBALS['cds_shadow_batch_depth'] = $depth + 1;
}

function cds_shadow_notify_failure($message)
{
    error_log('[CDS MySQL shadow] ' . (string)$message);
    if (empty($GLOBALS['cds_shadow_failure_notified']) && function_exists('flash')) {
        $GLOBALS['cds_shadow_failure_notified'] = true;
        flash(
            'Dữ liệu đã lưu an toàn vào JSON nhưng MySQL chưa đồng bộ được. '
            . 'Quản trị hãy kiểm tra trang Trạng thái MySQL.',
            'warning'
        );
    }
}

function cds_shadow_refresh_snapshot($sourceType, $sourceLabel, $context = array())
{
    if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear();
    cds_read_verify_mark_snapshot_pending();

    try {
        require_once __DIR__ . '/database_core_import.php';
        $actor = function_exists('current_user') ? current_user() : array();
        $actor = is_array($actor) ? $actor : array();
        foreach ((array)$context as $key => $value) $actor[(string)$key] = $value;
        $result = cds_core_import_snapshot($actor, (string)$sourceType, (string)$sourceLabel);
        if (!cds_read_verify_mark_snapshot_match($result['counts'] ?? array())) {
            throw new RuntimeException('Đã cập nhật MySQL nhưng chưa xác nhận được trạng thái kiểm chứng.');
        }
        if (!cds_shadow_pending_clear()) {
            throw new RuntimeException('MySQL đã đồng bộ nhưng chưa xóa được dấu đồng bộ đang chờ.');
        }
        return true;
    } catch (Throwable $e) {
        cds_shadow_notify_failure($e->getMessage());
        return false;
    }
}

function cds_shadow_batch_end()
{
    $depth = (int)($GLOBALS['cds_shadow_batch_depth'] ?? 0);
    if ($depth <= 0) return true;

    $depth--;
    $GLOBALS['cds_shadow_batch_depth'] = $depth;
    if ($depth > 0) return true;

    $enabled = !empty($GLOBALS['cds_shadow_batch_enabled']);
    $dirty = (array)($GLOBALS['cds_shadow_batch_dirty'] ?? array());
    $previousForceJson = !empty($GLOBALS['cds_shadow_batch_previous_force_json']);
    unset(
        $GLOBALS['cds_shadow_batch_enabled'],
        $GLOBALS['cds_shadow_batch_dirty'],
        $GLOBALS['cds_shadow_batch_previous_force_json'],
        $GLOBALS['cds_shadow_batch_depth']
    );
    if ($previousForceJson) $GLOBALS['cds_force_json_core_read'] = true;
    else unset($GLOBALS['cds_force_json_core_read']);
    if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear();
    if (!$enabled || !$dirty) return true;

    $types = array_keys($dirty);
    return cds_shadow_refresh_snapshot(
        'json_shadow_batch',
        'Đồng bộ một lần sau thao tác hàng loạt',
        array('shadow_entity_type' => implode(',', $types), 'shadow_entity_id' => 'batch')
    );
}

function cds_shadow_batch_run(callable $callback)
{
    cds_shadow_batch_begin();
    $syncOk = true;
    try {
        $result = $callback();
    } finally {
        $syncOk = cds_shadow_batch_end();
    }
    if (is_array($result)) {
        $result['shadow_sync_ok'] = $syncOk;
        if (!$syncOk) {
            $result['message'] = rtrim((string)($result['message'] ?? ''), '. ')
                . '. Dữ liệu JSON đã lưu; MySQL chưa đồng bộ được và cần quản trị kiểm tra.';
        }
    }
    return $result;
}

function cds_shadow_refresh_core($entityType, $entityId)
{
    if ((int)($GLOBALS['cds_shadow_batch_depth'] ?? 0) > 0) {
        if (!empty($GLOBALS['cds_shadow_batch_enabled'])) {
            $type = (string)$entityType;
            if (!isset($GLOBALS['cds_shadow_batch_dirty'][$type])) {
                $GLOBALS['cds_shadow_batch_dirty'][$type] = array();
            }
            $GLOBALS['cds_shadow_batch_dirty'][$type][(string)$entityId] = true;
            if (!cds_shadow_pending_mark($type, (string)$entityId)) {
                cds_read_verify_mark_snapshot_pending();
            }
        }
        return true;
    }

    if (!cds_shadow_write_enabled()) return true;

    $hadPendingSync = is_array(cds_shadow_pending_status());
    if (!cds_shadow_pending_mark((string)$entityType, (string)$entityId)) {
        cds_read_verify_mark_snapshot_pending();
        cds_shadow_notify_failure('Không tạo được dấu đồng bộ MySQL đang chờ.');
        return false;
    }

    if ($hadPendingSync) {
        return cds_shadow_refresh_snapshot(
            'json_shadow_recovery',
            'Khôi phục MySQL sau lần đồng bộ chưa hoàn tất',
            array(
                'shadow_entity_type' => (string)$entityType,
                'shadow_entity_id' => (string)$entityId,
            )
        );
    }

    if (in_array((string)$entityType, array('teacher','class','student'), true)) {
        try {
            require_once __DIR__ . '/database_core_incremental.php';
            return cds_core_incremental_sync((string)$entityType, (string)$entityId);
        } catch (Throwable $e) {
            cds_shadow_notify_failure(
                (string)$entityType . ' ' . (string)$entityId . ': ' . $e->getMessage()
            );
            return false;
        }
    }

    return cds_shadow_refresh_snapshot(
        'json_shadow',
        'Đồng bộ tự động từ JSON',
        array(
            'shadow_entity_type' => (string)$entityType,
            'shadow_entity_id' => (string)$entityId,
        )
    );
}
