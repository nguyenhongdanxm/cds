<?php
/** Giai đoạn 2B: giữ thay đổi lõi trong bộ nhớ và chỉ công bố sau một transaction. */

function cds_batch_primary_files()
{
    if (!defined('DATA_PATH')) return array();
    return array(
        DATA_PATH . '/school_years.json' => 'years',
        DATA_PATH . '/teachers.json' => 'teachers',
        DATA_PATH . '/classes.json' => 'classes',
        DATA_PATH . '/students.json' => 'students',
    );
}

function cds_batch_primary_key($file)
{
    return str_replace('\\', '/', (string)$file);
}

function cds_batch_primary_staging_active()
{
    return !empty($GLOBALS['cds_batch_primary_active']);
}

function cds_batch_primary_begin()
{
    if (!function_exists('cds_core_sql_batch_write_enabled')
        || !cds_core_sql_batch_write_enabled()) return false;
    $readiness = cds_core_sql_batch_write_readiness();
    if (empty($readiness['ready'])) return false;

    $staged = array();
    foreach (cds_batch_primary_files() as $file => $type) {
        $rows = cds_json_load($file, array());
        if (!is_array($rows)) throw new RuntimeException('Không đọc được dữ liệu nguồn ' . basename($file) . '.');
        $staged[cds_batch_primary_key($file)] = array_values($rows);
    }
    $GLOBALS['cds_batch_primary_active'] = true;
    $GLOBALS['cds_batch_primary_staged'] = $staged;
    $GLOBALS['cds_batch_primary_original'] = $staged;
    $GLOBALS['cds_batch_primary_changed'] = array();
    return true;
}

function cds_batch_primary_load($file)
{
    $key = cds_batch_primary_key($file);
    if (!cds_batch_primary_staging_active()
        || !array_key_exists($key, $GLOBALS['cds_batch_primary_staged'] ?? array())) {
        return array('handled' => false, 'data' => null);
    }
    return array('handled' => true, 'data' => $GLOBALS['cds_batch_primary_staged'][$key]);
}

function cds_batch_primary_save($file, $data)
{
    $key = cds_batch_primary_key($file);
    if (!cds_batch_primary_staging_active()
        || !array_key_exists($key, $GLOBALS['cds_batch_primary_staged'] ?? array())) return false;
    if (!is_array($data)) throw new RuntimeException('Dữ liệu hàng loạt không phải danh sách hợp lệ.');
    $GLOBALS['cds_batch_primary_staged'][$key] = array_values($data);
    $GLOBALS['cds_batch_primary_changed'][$key] = true;
    return true;
}

function cds_batch_primary_data()
{
    $data = array();
    foreach (cds_batch_primary_files() as $file => $type) {
        $key = cds_batch_primary_key($file);
        $data[$type] = array_values($GLOBALS['cds_batch_primary_staged'][$key] ?? array());
    }
    return $data;
}

function cds_batch_primary_reset()
{
    unset(
        $GLOBALS['cds_batch_primary_active'],
        $GLOBALS['cds_batch_primary_staged'],
        $GLOBALS['cds_batch_primary_original'],
        $GLOBALS['cds_batch_primary_changed']
    );
}

function cds_batch_primary_finish($commit)
{
    if (!cds_batch_primary_staging_active()) return null;
    $changed = (array)($GLOBALS['cds_batch_primary_changed'] ?? array());
    $data = cds_batch_primary_data();
    $originalStaged = (array)($GLOBALS['cds_batch_primary_original'] ?? array());
    $original = array();
    foreach (cds_batch_primary_files() as $file => $type) {
        $original[$type] = array_values($originalStaged[cds_batch_primary_key($file)] ?? array());
    }
    if (!$commit || !$changed) {
        cds_batch_primary_reset();
        return true;
    }

    require_once __DIR__ . '/database_core_import.php';
    $preview = cds_core_preview($data);
    if (empty($preview['can_import'])) {
        cds_batch_primary_reset();
        throw new RuntimeException('Lô dữ liệu không hợp lệ: ' . implode(' ', array_slice($preview['errors'], 0, 3)));
    }
    if (!cds_core_sql_backup_pending_mark('core_batch', 'batch')) {
        cds_batch_primary_reset();
        throw new RuntimeException('Không tạo được dấu bảo vệ JSON; lô chưa được ghi.');
    }

    $sqlCommitted = false;
    try {
        $actor = function_exists('current_user') ? current_user() : array();
        $actor = is_array($actor) ? $actor : array();
        cds_core_import_snapshot($actor, 'sql_primary_batch', 'Giai đoạn 2B', $data, $original);
        $sqlCommitted = true;
        cds_read_verify_mark_snapshot_pending();

        // Chỉ ghi các tệp thực sự đổi; dùng kho JSON trực tiếp để không quay lại vùng tạm.
        foreach (cds_batch_primary_files() as $file => $type) {
            $key = cds_batch_primary_key($file);
            if (empty($changed[$key])) continue;
            if (!cds_json_save($file, $data[$type])) {
                throw new RuntimeException('MySQL đã lưu nhưng chưa cập nhật được ' . basename($file) . '.');
            }
        }
        $comparison = cds_core_compare_snapshot($data);
        if (empty($comparison['is_match'])) throw new RuntimeException('Đối chiếu sau lô chưa khớp.');
        if (!cds_read_verify_mark_snapshot_match($preview['counts'])) {
            throw new RuntimeException('Chưa ghi được trạng thái đối chiếu sau lô.');
        }
        if (!cds_core_sql_backup_pending_clear()) throw new RuntimeException('Chưa xóa được dấu phục hồi JSON.');
        if (function_exists('cds_core_sql_read_cache_clear')) cds_core_sql_read_cache_clear();
        cds_batch_primary_reset();
        return true;
    } catch (Throwable $e) {
        cds_batch_primary_reset();
        if (!$sqlCommitted) cds_core_sql_backup_pending_clear();
        throw $e;
    }
}
