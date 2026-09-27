<?php
/** Bản sao y tế giai đoạn đầu: JSON vẫn là nguồn vận hành duy nhất. */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/noitru_store.php';

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
        return cds_health_compare();
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('cds_noitru_health_import')");
    }
}
