<?php
require_once __DIR__ . '/database.php';

function cds_read_verify_enabled()
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }

    try {
        $stmt = cds_db()->prepare(
            "SELECT setting_value
             FROM cds_runtime_settings
             WHERE setting_key='core_read_verify'"
        );
        $stmt->execute();
        $enabled = (string)$stmt->fetchColumn() === '1';
    } catch (Throwable $e) {
        $enabled = false;
    }
    return $enabled;
}

function cds_read_verify_set($enabled, $actor)
{
    $stmt = cds_db()->prepare(
        "INSERT INTO cds_runtime_settings
            (setting_key, setting_value, updated_by, updated_at)
         VALUES ('core_read_verify', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            setting_value=VALUES(setting_value),
            updated_by=VALUES(updated_by),
            updated_at=NOW()"
    );
    $stmt->execute(array(
        $enabled ? '1' : '0',
        (string)($actor['username'] ?? $actor['name'] ?? ''),
    ));
}

function cds_read_verify_canonicalize($value)
{
    if (!is_array($value)) {
        return $value;
    }

    $keys = array_keys($value);
    $isList = $keys === range(0, count($value) - 1);
    if (!$isList) {
        ksort($value);
    }
    foreach ($value as $key => $item) {
        $value[$key] = cds_read_verify_canonicalize($item);
    }
    return $value;
}

function cds_read_verify_hash($value)
{
    return hash(
        'sha256',
        json_encode(
            cds_read_verify_canonicalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )
    );
}

function cds_read_verify_rows($entityType, $jsonRows)
{
    static $checked = array();

    if (isset($checked[$entityType]) || !cds_read_verify_enabled()) {
        return;
    }
    $checked[$entityType] = true;

    $tables = array(
        'years' => 'cds_school_years',
        'teachers' => 'cds_teachers',
        'classes' => 'cds_classes',
        'students' => 'cds_students',
    );
    if (!isset($tables[$entityType])) {
        return;
    }

    try {
        $json = array();
        foreach ((array)$jsonRows as $row) {
            $id = (string)($row['id'] ?? '');
            if ($id !== '') {
                $json[$id] = cds_read_verify_hash($row);
            }
        }

        $mysql = array();
        $stmt = cds_db()->query(
            'SELECT id, raw_json FROM ' . $tables[$entityType]
        );
        while ($dbRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $decoded = json_decode((string)$dbRow['raw_json'], true);
            $mysql[(string)$dbRow['id']] = cds_read_verify_hash(
                is_array($decoded) ? $decoded : array()
            );
        }

        $missing = array_values(array_diff(array_keys($json), array_keys($mysql)));
        $extra = array_values(array_diff(array_keys($mysql), array_keys($json)));
        $changed = array();
        foreach (array_intersect(array_keys($json), array_keys($mysql)) as $id) {
            if (!hash_equals($json[$id], $mysql[$id])) {
                $changed[] = $id;
            }
        }

        $status = (!$missing && !$extra && !$changed) ? 'match' : 'mismatch';
        $details = array(
            'missing' => $missing,
            'extra' => $extra,
            'changed' => $changed,
        );
        $save = cds_db()->prepare(
            "INSERT INTO cds_read_verification_status
                (entity_type, verify_status, json_count, mysql_count,
                 details_json, checked_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                verify_status=VALUES(verify_status),
                json_count=VALUES(json_count),
                mysql_count=VALUES(mysql_count),
                details_json=VALUES(details_json),
                checked_at=NOW()"
        );
        $save->execute(array(
            $entityType,
            $status,
            count($json),
            count($mysql),
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    } catch (Throwable $e) {
        error_log(
            '[CDS MySQL read verify] ' . (string)$entityType . ': ' . $e->getMessage()
        );
    }
}

function cds_read_verify_status()
{
    try {
        $rows = cds_db()->query(
            "SELECT entity_type, verify_status, json_count, mysql_count,
                    details_json, checked_at
             FROM cds_read_verification_status
             ORDER BY entity_type"
        )->fetchAll(PDO::FETCH_ASSOC);
        $result = array();
        foreach ($rows as $row) {
            $row['details'] = json_decode((string)$row['details_json'], true);
            $result[(string)$row['entity_type']] = $row;
        }
        return $result;
    } catch (Throwable $e) {
        return array();
    }
}

function cds_read_verify_mark_snapshot_pending()
{
    try {
        cds_db()->exec(
            "UPDATE cds_read_verification_status
             SET verify_status='pending', checked_at=NOW()
             WHERE entity_type IN ('years','teachers','classes','students')"
        );
    } catch (Throwable $e) {
        error_log('[CDS MySQL verify pending] ' . $e->getMessage());
    }
}

function cds_read_verify_mark_entity_pending($entityType)
{
    $allowed = array('years','teachers','classes','students');
    if (!in_array($entityType, $allowed, true)) return false;
    try {
        $stmt = cds_db()->prepare(
            "UPDATE cds_read_verification_status
             SET verify_status='pending', checked_at=NOW()
             WHERE entity_type=?"
        );
        $stmt->execute(array((string)$entityType));
        return true;
    } catch (Throwable $e) {
        error_log('[CDS MySQL verify entity pending] ' . $e->getMessage());
        return false;
    }
}

function cds_read_verify_mark_entity_match($entityType, $count)
{
    $allowed = array('years','teachers','classes','students');
    if (!in_array($entityType, $allowed, true)) return false;
    try {
        $stmt = cds_db()->prepare(
            "INSERT INTO cds_read_verification_status
                (entity_type, verify_status, json_count, mysql_count, details_json, checked_at)
             VALUES (?, 'match', ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                verify_status='match', json_count=VALUES(json_count),
                mysql_count=VALUES(mysql_count), details_json=VALUES(details_json),
                checked_at=NOW()"
        );
        $stmt->execute(array(
            (string)$entityType,
            (int)$count,
            (int)$count,
            json_encode(array('missing'=>array(),'extra'=>array(),'changed'=>array())),
        ));
        return true;
    } catch (Throwable $e) {
        error_log('[CDS MySQL verify entity match] ' . $e->getMessage());
        return false;
    }
}

function cds_read_verify_mark_snapshot_match($counts)
{
    $map = array(
        'years' => (int)($counts['years'] ?? 0),
        'teachers' => (int)($counts['teachers'] ?? 0),
        'classes' => (int)($counts['classes'] ?? 0),
        'students' => (int)($counts['students'] ?? 0),
    );
    try {
        $stmt = cds_db()->prepare(
            "INSERT INTO cds_read_verification_status
                (entity_type, verify_status, json_count, mysql_count, details_json, checked_at)
             VALUES (?, 'match', ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                verify_status='match', json_count=VALUES(json_count),
                mysql_count=VALUES(mysql_count), details_json=VALUES(details_json),
                checked_at=NOW()"
        );
        foreach ($map as $entityType => $count) {
            $stmt->execute(array(
                $entityType,
                $count,
                $count,
                json_encode(array('missing'=>array(),'extra'=>array(),'changed'=>array())),
            ));
        }
        return true;
    } catch (Throwable $e) {
        error_log('[CDS MySQL verify match] ' . $e->getMessage());
        return false;
    }
}
