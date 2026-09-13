<?php
/**
 * Kho nháp xếp TKB trên MySQL, có JSON dự phòng tương thích ngược.
 *
 * - Lần đọc đầu tiên tự nhập nguyên trạng JSON cũ nếu MySQL chưa có dữ liệu.
 * - MySQL là nguồn đọc chính sau khi nhập; JSON tiếp tục được cập nhật làm bản sao.
 * - Mọi lỗi kết nối/DDL đều tự quay về JSON, không chặn việc xếp TKB.
 */
require_once __DIR__ . '/database.php';

function ttb_store_json_encode($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Không mã hóa được dữ liệu xếp TKB.');
    return $json;
}

function ttb_store_mysql_ready(): bool
{
    if (array_key_exists('ttb_store_mysql_ready', $GLOBALS)) return (bool)$GLOBALS['ttb_store_mysql_ready'];
    try {
        cds_db()->exec("CREATE TABLE IF NOT EXISTS cds_timetable_builder_state (
            state_key VARCHAR(191) NOT NULL,
            payload LONGTEXT NOT NULL,
            checksum_sha256 CHAR(64) NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (state_key),
            KEY idx_cds_ttb_state_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $GLOBALS['ttb_store_mysql_ready'] = true;
    } catch (Throwable $e) {
        error_log('[CDS TKB MySQL fallback] '.$e->getMessage());
        return $GLOBALS['ttb_store_mysql_ready'] = false;
    }
}

function ttb_store_read_mysql(string $key): ?array
{
    if (!ttb_store_mysql_ready()) return null;
    try {
        $stmt = cds_db()->prepare('SELECT payload, checksum_sha256 FROM cds_timetable_builder_state WHERE state_key=?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $payload = (string)$row['payload'];
        if (!hash_equals((string)$row['checksum_sha256'], hash('sha256', $payload))) {
            throw new RuntimeException('Checksum dữ liệu nháp TKB trên MySQL không khớp.');
        }
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) throw new RuntimeException('Dữ liệu nháp TKB trên MySQL không hợp lệ.');
        return $decoded;
    } catch (Throwable $e) {
        error_log('[CDS TKB MySQL read fallback] '.$e->getMessage());
        return null;
    }
}

function ttb_store_write_mysql(string $key, array $value): bool
{
    if (!ttb_store_mysql_ready()) return false;
    try {
        $json = ttb_store_json_encode($value);
        $stmt = cds_db()->prepare("INSERT INTO cds_timetable_builder_state
            (state_key,payload,checksum_sha256,revision,updated_at) VALUES(?,?,?,1,NOW())
            ON DUPLICATE KEY UPDATE payload=VALUES(payload),checksum_sha256=VALUES(checksum_sha256),revision=revision+1,updated_at=NOW()");
        $stmt->execute([$key, $json, hash('sha256', $json)]);
        return true;
    } catch (Throwable $e) {
        error_log('[CDS TKB MySQL write fallback] '.$e->getMessage());
        return false;
    }
}

function ttb_store_load(string $key, string $jsonFile, array $default=[]): array
{
    $sql = ttb_store_read_mysql($key);
    if (is_array($sql)) return $sql;
    $legacy = load_json($jsonFile, $default);
    if (!is_array($legacy)) $legacy = $default;
    // Nhập một chiều, không sửa nội dung JSON cũ.
    ttb_store_write_mysql($key, $legacy);
    return $legacy;
}

function ttb_store_save(string $key, string $jsonFile, array $value): bool
{
    $mysqlSaved = ttb_store_write_mysql($key, $value);
    $jsonSaved = save_json($jsonFile, $value);
    // Khi MySQL đã nhận dữ liệu, lỗi bản sao JSON không làm mất thao tác của người dùng.
    return $mysqlSaved || $jsonSaved;
}

function ttb_store_delete(string $key): bool
{
    if (!ttb_store_mysql_ready()) return true;
    try {
        $stmt = cds_db()->prepare('DELETE FROM cds_timetable_builder_state WHERE state_key=?');
        return $stmt->execute([$key]);
    } catch (Throwable $e) {
        error_log('[CDS TKB MySQL delete fallback] '.$e->getMessage());
        return false;
    }
}

function ttb_optimizer_lock(string $workspaceId): bool
{
    if (!ttb_store_mysql_ready()) return true;
    try {
        $stmt = cds_db()->prepare('SELECT GET_LOCK(?,0)');
        $stmt->execute(['cds_ttb_opt_'.substr(hash('sha256',$workspaceId),0,32)]);
        return (int)$stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        return true; // Không để sự cố khóa MySQL làm mất chế độ JSON dự phòng.
    }
}

function ttb_optimizer_unlock(string $workspaceId): void
{
    if (!ttb_store_mysql_ready()) return;
    try {
        $stmt = cds_db()->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute(['cds_ttb_opt_'.substr(hash('sha256',$workspaceId),0,32)]);
    } catch (Throwable $e) {}
}
