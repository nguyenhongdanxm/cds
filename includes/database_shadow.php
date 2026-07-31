<?php
require_once __DIR__ . '/database.php';

function cds_shadow_write_enabled()
{
    try {
        $stmt = cds_db()->prepare(
            "SELECT setting_value
             FROM cds_runtime_settings
             WHERE setting_key='core_shadow_write'"
        );
        $stmt->execute();
        return (string)$stmt->fetchColumn() === '1';
    } catch (Throwable $e) {
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
}

function cds_shadow_refresh_core($entityType, $entityId)
{
    if (!cds_shadow_write_enabled()) {
        return;
    }

    try {
        require_once __DIR__ . '/database_core_import.php';
        $actor = function_exists('current_user') ? current_user() : array();
        $actor = is_array($actor) ? $actor : array();
        $actor['shadow_entity_type'] = (string)$entityType;
        $actor['shadow_entity_id'] = (string)$entityId;
        cds_core_import_snapshot($actor, 'json_shadow', 'Đồng bộ tự động từ JSON');
    } catch (Throwable $e) {
        error_log(
            '[CDS MySQL shadow] ' . (string)$entityType . ' '
            . (string)$entityId . ': ' . $e->getMessage()
        );
    }
}
