<?php
require_once __DIR__ . '/database_core_import.php';

/**
 * Lấy trực tiếp tệp JSON nguồn, không đi qua lớp đọc SQL đang bật.
 */
function cds_core_incremental_source($entityType, $entityId)
{
    $paths = array(
        'teacher' => defined('CSDL_TEACHERS') ? CSDL_TEACHERS : '',
        'class' => defined('CSDL_CLASSES') ? CSDL_CLASSES : '',
        'student' => defined('CSDL_STUDENTS') ? CSDL_STUDENTS : '',
    );
    if (!isset($paths[$entityType]) || $paths[$entityType] === '') {
        throw new InvalidArgumentException('Nhóm dữ liệu tăng dần không hợp lệ.');
    }

    $rows = load_json($paths[$entityType], array());
    if (!is_array($rows)) {
        throw new RuntimeException('Tệp JSON nguồn không hợp lệ.');
    }
    foreach ($rows as $row) {
        if (is_array($row) && (string)($row['id'] ?? '') === (string)$entityId) {
            return array('row' => $row, 'count' => count($rows));
        }
    }
    return array('row' => null, 'count' => count($rows));
}

function cds_core_incremental_current_year_id()
{
    // Khi MySQL là nguồn ghi chính, dùng năm hiện hành đã commit trong SQL.
    // Điều này đóng khoảng thời gian rất ngắn giữa SQL commit và lúc JSON dự
    // phòng được cập nhật, tránh lớp/học sinh mới bị gắn lại vào năm cũ.
    if (function_exists('cds_core_sql_write_enabled') && cds_core_sql_write_enabled()) {
        $stmt = cds_db()->query(
            'SELECT id FROM cds_school_years WHERE is_current=1 ORDER BY id'
        );
        $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) !== 1) {
            throw new RuntimeException('MySQL cần đúng một năm học hiện hành trước khi ghi dữ liệu.');
        }
        return $ids[0];
    }
    if (!defined('CSDL_YEARS')) {
        throw new RuntimeException('Chưa xác định tệp năm học nguồn.');
    }
    $years = load_json(CSDL_YEARS, array());
    $currentIds = array();
    foreach ((array)$years as $year) {
        if (!empty($year['is_current']) && trim((string)($year['id'] ?? '')) !== '') {
            $currentIds[] = (string)$year['id'];
        }
    }
    if (count($currentIds) !== 1) {
        throw new RuntimeException('Cần đúng một năm học hiện hành trước khi đồng bộ tăng dần.');
    }
    return $currentIds[0];
}

function cds_core_incremental_upsert_teacher(PDO $pdo, $row)
{
    $flags = is_array($row['role_flags'] ?? null) ? $row['role_flags'] : array();
    $roles = $row['kiem_nhiem_text'] ?? ($row['kiem_nhiem'] ?? '');
    if (is_array($roles)) $roles = implode(', ', $roles);

    $sql = "INSERT INTO cds_teachers
        (id, code, name, cccd, dob, gender, ethnicity, phone, email, hometown, address,
         teaching_level, specialty, professional_group, position_name, additional_roles,
         join_date, salary_grade, professional_rank, salary_level, salary_coefficient,
         salary_from, is_probation, is_principal, is_vice_principal, active, note,
         raw_json, source_updated_at, imported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            code=VALUES(code), name=VALUES(name), cccd=VALUES(cccd), dob=VALUES(dob),
            gender=VALUES(gender), ethnicity=VALUES(ethnicity), phone=VALUES(phone),
            email=VALUES(email), hometown=VALUES(hometown), address=VALUES(address),
            teaching_level=VALUES(teaching_level), specialty=VALUES(specialty),
            professional_group=VALUES(professional_group), position_name=VALUES(position_name),
            additional_roles=VALUES(additional_roles), join_date=VALUES(join_date),
            salary_grade=VALUES(salary_grade), professional_rank=VALUES(professional_rank),
            salary_level=VALUES(salary_level), salary_coefficient=VALUES(salary_coefficient),
            salary_from=VALUES(salary_from), is_probation=VALUES(is_probation),
            is_principal=VALUES(is_principal), is_vice_principal=VALUES(is_vice_principal),
            active=VALUES(active), note=VALUES(note), raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at), imported_at=NOW()";
    cds_core_upsert($pdo, $sql, array(
        (string)$row['id'], cds_core_string($row, 'code'), cds_core_string($row, 'name'),
        cds_core_identity($row['cccd'] ?? ''), cds_core_date($row['dob'] ?? ''),
        cds_core_string($row, 'gender'), cds_core_string($row, 'ethnicity'),
        cds_core_string($row, 'phone'), cds_core_string($row, 'email'),
        cds_core_string($row, 'hometown'), cds_core_string($row, 'address'),
        cds_core_string($row, 'teaching_level'), cds_core_string($row, 'specialty'),
        cds_core_string($row, 'to_chuyen_mon') ?: cds_core_string($row, 'pccm_group'),
        cds_core_string($row, 'chuc_vu'), (string)$roles,
        cds_core_date($row['join_date'] ?? ''), cds_core_string($row, 'bac'),
        cds_core_string($row, 'hang'), cds_core_string($row, 'cap_luong'),
        cds_core_string($row, 'he_so'), cds_core_date($row['he_so_from'] ?? ''),
        !empty($flags['is_probation']) ? 1 : 0, !empty($flags['is_principal']) ? 1 : 0,
        !empty($flags['is_vice']) ? 1 : 0, cds_core_bool($row, 'active', true),
        cds_core_string($row, 'note'), cds_core_json($row), cds_core_datetime($row),
    ));
}

function cds_core_incremental_upsert_class(PDO $pdo, $row, $currentYearId)
{
    $teacherId = cds_core_string($row, 'homeroom_teacher_id');
    $capacity = cds_core_string($row, 'capacity');
    $sql = "INSERT INTO cds_classes
        (id, school_year_id, name, grade, level_name, homeroom_teacher_id, room,
         capacity, active, note, raw_json, source_updated_at, imported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            school_year_id=VALUES(school_year_id), name=VALUES(name), grade=VALUES(grade),
            level_name=VALUES(level_name), homeroom_teacher_id=VALUES(homeroom_teacher_id),
            room=VALUES(room), capacity=VALUES(capacity), active=VALUES(active),
            note=VALUES(note), raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at), imported_at=NOW()";
    cds_core_upsert($pdo, $sql, array(
        (string)$row['id'], $currentYearId, cds_core_string($row, 'name'),
        is_numeric($row['grade'] ?? null) ? (int)$row['grade'] : null,
        cds_core_string($row, 'level'), $teacherId !== '' ? $teacherId : null,
        cds_core_string($row, 'room'), is_numeric($capacity) ? (int)$capacity : null,
        cds_core_bool($row, 'active', true), cds_core_string($row, 'note'),
        cds_core_json($row), cds_core_datetime($row),
    ));
}

function cds_core_incremental_upsert_student(PDO $pdo, $row, $currentYearId)
{
    $classId = cds_core_string($row, 'class_id');
    $sql = "INSERT INTO cds_students
        (id, school_year_id, class_id, code, name, cccd, dob, gender, ethnicity,
         hometown, address, phone, parent_name, parent_phone, is_boarder, dorm_room,
         meal_group, active, note, raw_json, source_updated_at, imported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            school_year_id=VALUES(school_year_id), class_id=VALUES(class_id),
            code=VALUES(code), name=VALUES(name), cccd=VALUES(cccd), dob=VALUES(dob),
            gender=VALUES(gender), ethnicity=VALUES(ethnicity), hometown=VALUES(hometown),
            address=VALUES(address), phone=VALUES(phone), parent_name=VALUES(parent_name),
            parent_phone=VALUES(parent_phone), is_boarder=VALUES(is_boarder),
            dorm_room=VALUES(dorm_room), meal_group=VALUES(meal_group),
            active=VALUES(active), note=VALUES(note), raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at), imported_at=NOW()";
    cds_core_upsert($pdo, $sql, array(
        (string)$row['id'], $currentYearId, $classId !== '' ? $classId : null,
        cds_core_string($row, 'code'), cds_core_string($row, 'name'),
        cds_core_identity($row['cccd'] ?? ''), cds_core_date($row['dob'] ?? ''),
        cds_core_string($row, 'gender'), cds_core_string($row, 'ethnicity'),
        cds_core_string($row, 'hometown'), cds_core_string($row, 'address'),
        cds_core_string($row, 'phone'), cds_core_string($row, 'parent_name'),
        cds_core_string($row, 'parent_phone'), cds_core_bool($row, 'boarder'),
        cds_core_string($row, 'room_ktx'), cds_core_string($row, 'meal_group'),
        cds_core_bool($row, 'active', true), cds_core_string($row, 'note'),
        cds_core_json($row), cds_core_datetime($row),
    ));
}

function cds_core_incremental_sync($entityType, $entityId)
{
    $types = array(
        'teacher' => array('verify' => 'teachers', 'table' => 'cds_teachers'),
        'class' => array('verify' => 'classes', 'table' => 'cds_classes'),
        'student' => array('verify' => 'students', 'table' => 'cds_students'),
    );
    if (!isset($types[$entityType])) {
        throw new InvalidArgumentException('Nhóm dữ liệu tăng dần không được hỗ trợ.');
    }
    $entityId = trim((string)$entityId);
    if ($entityId === '') throw new InvalidArgumentException('Bản ghi đồng bộ thiếu ID.');

    $preview = cds_core_preview();
    if (empty($preview['can_import'])) {
        throw new RuntimeException('Dữ liệu JSON còn mâu thuẫn; CDS giữ JSON và chưa ghi tăng dần vào MySQL.');
    }

    $source = cds_core_incremental_source($entityType, $entityId);
    $verifyType = $types[$entityType]['verify'];
    $table = $types[$entityType]['table'];
    cds_read_verify_mark_entity_pending($verifyType);

    $pdo = cds_db();
    $migrationStatus = cds_db_migration_status();
    if (isset($migrationStatus['pending']['20260730_002_core_school_data'])) {
        throw new RuntimeException('Chưa cài đặt migration bảng dữ liệu lõi.');
    }

    $pdo->beginTransaction();
    try {
        if (is_array($source['row'])) {
            if ($entityType === 'teacher') {
                cds_core_incremental_upsert_teacher($pdo, $source['row']);
            } elseif ($entityType === 'class') {
                cds_core_incremental_upsert_class(
                    $pdo,
                    $source['row'],
                    cds_core_incremental_current_year_id()
                );
            } else {
                cds_core_incremental_upsert_student(
                    $pdo,
                    $source['row'],
                    cds_core_incremental_current_year_id()
                );
            }
        } else {
            $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE id=?');
            $stmt->execute(array($entityId));
        }
        $mysqlCount = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        if ($mysqlCount !== (int)$source['count']) {
            throw new RuntimeException('Số lượng JSON và MySQL không khớp sau đồng bộ tăng dần.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    if (!cds_read_verify_mark_entity_match($verifyType, (int)$source['count'])) {
        throw new RuntimeException('Đã cập nhật MySQL nhưng chưa xác nhận được trạng thái kiểm chứng.');
    }
    if (function_exists('cds_core_sql_read_cache_clear')) {
        cds_core_sql_read_cache_clear($verifyType);
    }
    if (!cds_shadow_pending_clear()) {
        throw new RuntimeException('MySQL đã đồng bộ nhưng chưa xóa được dấu đồng bộ đang chờ.');
    }
    return true;
}
