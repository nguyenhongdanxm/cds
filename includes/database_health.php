<?php
/** JSON là nguồn ghi; MySQL là bản sao có kiểm chứng và đường đọc tùy chọn. */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/noitru_store.php';

function cds_health_file_lock() {
    noitru_ensure_dir();
    $handle = @fopen(NOITRU_DIR . '/.health.lock', 'c');
    if (!$handle) throw new RuntimeException('Không khóa được hồ sơ y tế.');
    if (!flock($handle, LOCK_EX)) { fclose($handle); throw new RuntimeException('Không khóa được hồ sơ y tế.'); }
    return $handle;
}
function cds_health_file_unlock($handle): void {
    flock($handle, LOCK_UN);
    fclose($handle);
}
function cds_health_pending_path(): string { return NOITRU_DIR . '/health_mysql_pending.json'; }
function cds_health_pending(): bool { return is_file(cds_health_pending_path()); }
function cds_health_pending_mark(): void {
    noitru_ensure_dir();
    if (@file_put_contents(cds_health_pending_path(), date('c'), LOCK_EX) === false) {
        throw new RuntimeException('Không ghi được dấu đồng bộ y tế; chưa lưu hồ sơ.');
    }
}
function cds_health_pending_clear(): bool {
    return !cds_health_pending() || @unlink(cds_health_pending_path());
}
function cds_health_setting(string $key): bool {
    try {
        $stmt = cds_db()->prepare('SELECT setting_value FROM cds_runtime_settings WHERE setting_key=?');
        $stmt->execute([$key]);
        return (string)$stmt->fetchColumn() === '1';
    } catch (Throwable $e) { return false; }
}
function cds_health_shadow_enabled(): bool { return cds_health_setting('health_shadow_write'); }
function cds_health_read_effective(): bool {
    return !cds_health_pending() && cds_health_setting('health_sql_read') && cds_health_shadow_enabled();
}
function cds_health_set_mode(string $key, bool $enabled, string $actor): void {
    if (!in_array($key, ['health_shadow_write','health_sql_read'], true)) throw new InvalidArgumentException('Chế độ không hợp lệ.');
    $lock = cds_health_file_lock();
    try {
        if ($enabled) {
            if (cds_health_pending() || !cds_health_compare()['is_match']) throw new RuntimeException('Bản sao y tế chưa khớp JSON. Hãy cập nhật và đối chiếu lại.');
            if ($key === 'health_sql_read' && !cds_health_shadow_enabled()) throw new RuntimeException('Cần bật ghi song song trước khi đọc MySQL.');
        }
        $stmt = cds_db()->prepare('UPDATE cds_runtime_settings SET setting_value=?, updated_by=?, updated_at=NOW() WHERE setting_key=?');
        $stmt->execute([$enabled?'1':'0', $actor, $key]);
        if ($stmt->rowCount() === 0 && !cds_health_setting($key)) throw new RuntimeException('Chưa cài đặt bản nâng cấp MySQL y tế.');
        if ($key === 'health_shadow_write' && !$enabled) {
            $stmt = cds_db()->prepare("UPDATE cds_runtime_settings SET setting_value='0', updated_by=?, updated_at=NOW() WHERE setting_key='health_sql_read'");
            $stmt->execute([$actor]);
        }
    } finally { cds_health_file_unlock($lock); }
}

function cds_health_shadow_row(?array $row, string $id, bool $wasPending = false): bool {
    if (!cds_health_shadow_enabled()) return false;
    try {
        if ($wasPending) return cds_health_import_locked()['is_match'];
        $pdo = cds_db();
        if ($row === null) {
            $stmt = $pdo->prepare('DELETE FROM cds_noitru_health WHERE id=?');
            $stmt->execute([$id]);
        } else {
            $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $date = (string)($row['date'] ?? '');
            if ($json === false || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('Hồ sơ y tế không hợp lệ.');
            $stmt = $pdo->prepare('INSERT INTO cds_noitru_health
                (id, student_id, record_date, treatment_type, raw_json, checksum_sha256)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE student_id=VALUES(student_id), record_date=VALUES(record_date),
                treatment_type=VALUES(treatment_type), raw_json=VALUES(raw_json), checksum_sha256=VALUES(checksum_sha256)');
            $stmt->execute([$id, (string)($row['student_id'] ?? ''), $date,
                (string)($row['type'] ?? ''), $json, hash('sha256', $json)]);
        }
        return cds_health_pending_clear();
    } catch (Throwable $e) {
        error_log('[CDS health shadow] ' . $e->getMessage());
        return false;
    }
}

function cds_health_sql_range(string $from, string $to): array {
    if (!cds_health_read_effective()) throw new RuntimeException('MySQL y tế chưa sẵn sàng.');
    $stmt = cds_db()->prepare('SELECT raw_json FROM cds_noitru_health WHERE record_date BETWEEN ? AND ? ORDER BY record_date DESC, id DESC');
    $stmt->execute([$from, $to]);
    $rows = [];
    foreach ($stmt as $record) {
        $row = json_decode((string)$record['raw_json'], true);
        if (!is_array($row)) throw new RuntimeException('Bản sao y tế MySQL không hợp lệ.');
        $rows[] = $row;
    }
    return $rows;
}

function cds_health_source_rows(): array {
    if (!defined('NOITRU_HEALTH')) throw new RuntimeException('Chưa nạp kho dữ liệu nội trú.');
    if (!is_file(NOITRU_HEALTH)) return [];
    $raw = @file_get_contents(NOITRU_HEALTH);
    $rows = $raw === false ? null : json_decode($raw, true);
    if (!is_array($rows) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Không đọc được hồ sơ y tế JSON; dừng đồng bộ để giữ dữ liệu cũ.');
    }
    return $rows;
}

function cds_health_source_snapshot(): array {
    $snapshot = [];
    foreach (cds_health_source_rows() as $row) {
        if (!is_array($row)) throw new RuntimeException('Hồ sơ y tế JSON có dòng không hợp lệ.');
        $id = (string)($row['id'] ?? '');
        $date = (string)($row['date'] ?? '');
        if ($id === '' || strlen($id) > 100 || isset($snapshot[$id]) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
            throw new RuntimeException('ID trùng/thiếu hoặc ngày hồ sơ y tế không hợp lệ; chưa thay đổi MySQL.');
        }
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new RuntimeException('Không mã hóa được hồ sơ y tế JSON.');
        $snapshot[$id] = ['row'=>$row, 'json'=>$json, 'hash'=>hash('sha256', $json)];
    }
    return $snapshot;
}

function cds_health_compare(): array {
    $source = cds_health_source_snapshot();
    $dbRows = [];
    foreach (cds_db()->query('SELECT id, raw_json, checksum_sha256 FROM cds_noitru_health') as $row) {
        $dbRows[(string)$row['id']] = $row;
    }
    $missing = $different = $extra = 0;
    foreach ($source as $id=>$item) {
        if (!isset($dbRows[$id])) $missing++;
        elseif (!hash_equals($item['hash'], (string)$dbRows[$id]['checksum_sha256']) ||
            !hash_equals($item['hash'], hash('sha256', (string)$dbRows[$id]['raw_json']))) $different++;
    }
    foreach ($dbRows as $id=>$row) if (!isset($source[$id])) $extra++;
    return ['json'=>count($source), 'mysql'=>count($dbRows), 'missing'=>$missing,
        'different'=>$different, 'extra'=>$extra,
        'is_match'=>$missing===0 && $different===0 && $extra===0];
}

/** Ghi lại bản sao trong một transaction; không sửa/xóa tệp JSON. */
function cds_health_import_snapshot(): array {
    $fileLock = cds_health_file_lock();
    try { return cds_health_import_locked(); }
    finally { cds_health_file_unlock($fileLock); }
}
function cds_health_import_locked(): array {
    $pdo = cds_db();
    $lock = $pdo->query("SELECT GET_LOCK('cds_noitru_health_import', 10)")->fetchColumn();
    if ((int)$lock !== 1) throw new RuntimeException('Đang có tiến trình đồng bộ y tế khác.');
    try {
        $source = cds_health_source_snapshot();
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM cds_noitru_health');
            $insert = $pdo->prepare('INSERT INTO cds_noitru_health
                (id, student_id, record_date, treatment_type, raw_json, checksum_sha256)
                VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($source as $id=>$item) {
                $row = $item['row'];
                $insert->execute([$id, (string)($row['student_id'] ?? ''), (string)$row['date'],
                    (string)($row['type'] ?? ''), $item['json'], $item['hash']]);
            }
            $check = [];
            foreach ($pdo->query('SELECT id, raw_json, checksum_sha256 FROM cds_noitru_health') as $row) {
                $check[(string)$row['id']] = $row;
            }
            if (count($check) !== count($source)) throw new RuntimeException('Số bản ghi MySQL không khớp JSON.');
            foreach ($source as $id=>$item) {
                if (!isset($check[$id]) || !hash_equals($item['hash'], (string)$check[$id]['checksum_sha256']) ||
                    !hash_equals($item['hash'], hash('sha256', (string)$check[$id]['raw_json']))) {
                    throw new RuntimeException('Có hồ sơ MySQL không khớp JSON.');
                }
            }
            // Nếu nguồn thay đổi trong lúc nhập, bỏ toàn bộ transaction và thử lại sau.
            if (cds_health_source_snapshot() !== $source) throw new RuntimeException('Hồ sơ JSON vừa thay đổi; hãy đồng bộ lại.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $comparison = cds_health_compare();
        if ($comparison['is_match'] && !cds_health_pending_clear()) {
            throw new RuntimeException('Bản sao khớp nhưng chưa xóa được dấu đồng bộ chờ.');
        }
        return $comparison;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('cds_noitru_health_import')");
    }
}
