<?php
require_once __DIR__ . '/database_migrations.php';
require_once __DIR__ . '/csdl_store.php';

function cds_core_json($value)
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cds_core_string($row, $key)
{
    return trim((string)($row[$key] ?? ''));
}

function cds_core_identity($value)
{
    $value = preg_replace('/\s+/', '', trim((string)$value));
    return $value === '' || preg_match('/^0+$/', $value) ? '' : $value;
}

function cds_core_bool($row, $key, $default = false)
{
    if (!array_key_exists($key, $row)) {
        return $default ? 1 : 0;
    }
    return !empty($row[$key]) ? 1 : 0;
}

function cds_core_date($value)
{
    $value = trim((string)$value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $value : null;
}

function cds_core_datetime($row)
{
    $value = $row['updated_at'] ?? ($row['created_at'] ?? '');
    if (!$value) {
        return null;
    }
    $time = strtotime((string)$value);
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

function cds_core_source_data()
{
    return array(
        'years' => csdl_years_all(),
        'teachers' => csdl_teachers_all(),
        'classes' => csdl_classes_all(),
        'students' => csdl_students_all(),
    );
}

function cds_core_duplicate_values($rows, $field)
{
    $seen = array();
    $duplicates = array();
    foreach ($rows as $row) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        $key = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        if (isset($seen[$key])) {
            $duplicates[$key] = $value;
        } else {
            $seen[$key] = true;
        }
    }
    return array_values($duplicates);
}

function cds_core_duplicate_identity_groups($rows, $field)
{
    $groups = array();
    foreach ($rows as $row) {
        $value = cds_core_identity($row[$field] ?? '');
        if ($value === '') {
            continue;
        }
        $key = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        if (!isset($groups[$key])) {
            $groups[$key] = array('value' => $value, 'rows' => array());
        }
        $groups[$key]['rows'][] = $row;
    }
    return array_values(array_filter($groups, function ($group) {
        return count($group['rows']) > 1;
    }));
}

function cds_core_preview()
{
    $data = cds_core_source_data();
    $errors = array();
    $warnings = array();

    foreach ($data as $type => $rows) {
        foreach ($rows as $index => $row) {
            if (trim((string)($row['id'] ?? '')) === '') {
                $errors[] = ucfirst($type) . ': bản ghi thứ ' . ($index + 1) . ' thiếu ID.';
            }
        }
        $duplicateIds = cds_core_duplicate_values($rows, 'id');
        if ($duplicateIds) {
            $errors[] = ucfirst($type) . ': trùng ID ' . implode(', ', array_slice($duplicateIds, 0, 5));
        }
    }

    $teacherIds = array();
    foreach ($data['teachers'] as $row) {
        $teacherIds[(string)($row['id'] ?? '')] = true;
        if (trim((string)($row['name'] ?? '')) === '') {
            $errors[] = 'Giáo viên có ID ' . ($row['id'] ?? '?') . ' nhưng thiếu họ tên.';
        }
    }

    $classIds = array();
    foreach ($data['classes'] as $row) {
        $classIds[(string)($row['id'] ?? '')] = true;
        if (trim((string)($row['name'] ?? '')) === '') {
            $errors[] = 'Lớp có ID ' . ($row['id'] ?? '?') . ' nhưng thiếu tên lớp.';
        }
        $teacherId = trim((string)($row['homeroom_teacher_id'] ?? ''));
        if ($teacherId !== '' && !isset($teacherIds[$teacherId])) {
            $errors[] = 'Lớp ' . ($row['name'] ?? $row['id'] ?? '?')
                . ' tham chiếu GVCN không tồn tại: ' . $teacherId;
        }
    }

    foreach ($data['students'] as $row) {
        if (trim((string)($row['name'] ?? '')) === '') {
            $errors[] = 'Học sinh có ID ' . ($row['id'] ?? '?') . ' nhưng thiếu họ tên.';
        }
        $classId = trim((string)($row['class_id'] ?? ''));
        if ($classId !== '' && !isset($classIds[$classId])) {
            $errors[] = 'Học sinh ' . ($row['name'] ?? $row['id'] ?? '?')
                . ' tham chiếu lớp không tồn tại: ' . $classId;
        }
    }

    $currentYears = array_values(array_filter($data['years'], function ($row) {
        return !empty($row['is_current']);
    }));
    if (count($currentYears) === 0) {
        $errors[] = 'Chưa có năm học hiện hành.';
    } elseif (count($currentYears) > 1) {
        $errors[] = 'Có nhiều hơn một năm học được đánh dấu hiện hành.';
    }

    foreach (array('teachers', 'students') as $type) {
        $duplicateCodes = cds_core_duplicate_values($data[$type], 'code');
        if ($duplicateCodes) {
            $warnings[] = ucfirst($type) . ': trùng code — '
                . implode(', ', array_slice($duplicateCodes, 0, 5));
        }

        foreach (cds_core_duplicate_identity_groups($data[$type], 'cccd') as $group) {
            $people = array();
            foreach ($group['rows'] as $row) {
                $label = trim((string)($row['name'] ?? 'Không rõ tên'));
                if ($type === 'students') {
                    $classId = trim((string)($row['class_id'] ?? ''));
                    $className = '';
                    foreach ($data['classes'] as $classRow) {
                        if ((string)($classRow['id'] ?? '') === $classId) {
                            $className = trim((string)($classRow['name'] ?? ''));
                            break;
                        }
                    }
                    if ($className !== '') {
                        $label .= ' (' . $className . ')';
                    }
                }
                $label .= ' [ID ' . (string)($row['id'] ?? '?') . ']';
                $people[] = $label;
            }
            $errors[] = ucfirst($type) . ': CCCD ' . $group['value']
                . ' đang dùng cho ' . implode(' và ', $people) . '.';
        }
    }

    return array(
        'data' => $data,
        'counts' => array(
            'years' => count($data['years']),
            'teachers' => count($data['teachers']),
            'classes' => count($data['classes']),
            'students' => count($data['students']),
        ),
        'errors' => array_values(array_unique($errors)),
        'warnings' => array_values(array_unique($warnings)),
        'can_import' => count($errors) === 0,
    );
}

function cds_core_upsert(PDO $pdo, $sql, $values)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function cds_core_import_snapshot($actor)
{
    $preview = cds_core_preview();
    if (!$preview['can_import']) {
        throw new RuntimeException('Dữ liệu nguồn còn mâu thuẫn; chưa thể nhập MySQL.');
    }

    $pdo = cds_db();
    $migrationStatus = cds_db_migration_status();
    if (isset($migrationStatus['pending']['20260730_002_core_school_data'])) {
        throw new RuntimeException('Chưa cài đặt migration bảng dữ liệu lõi.');
    }

    $data = $preview['data'];
    $currentYear = null;
    foreach ($data['years'] as $year) {
        if (!empty($year['is_current'])) {
            $currentYear = $year;
            break;
        }
    }
    $currentYearId = (string)$currentYear['id'];
    $checksum = hash('sha256', cds_core_json($data));
    $total = array_sum($preview['counts']);

    $pdo->beginTransaction();
    try {
        $batch = $pdo->prepare(
            "INSERT INTO cds_import_batches
                (source_type, source_label, source_checksum, status, records_total,
                 started_at, created_by, created_at)
             VALUES ('json_core', 'CSDL JSON lõi', ?, 'running', ?, NOW(), ?, NOW())"
        );
        $batch->execute(array(
            $checksum,
            $total,
            (string)($actor['username'] ?? $actor['name'] ?? ''),
        ));
        $batchId = (int)$pdo->lastInsertId();

        $yearSql = "INSERT INTO cds_school_years
            (id, label, start_date, end_date, is_current, raw_json, source_updated_at, imported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                label=VALUES(label), start_date=VALUES(start_date), end_date=VALUES(end_date),
                is_current=VALUES(is_current), raw_json=VALUES(raw_json),
                source_updated_at=VALUES(source_updated_at), imported_at=NOW()";
        foreach ($data['years'] as $row) {
            cds_core_upsert($pdo, $yearSql, array(
                (string)$row['id'], cds_core_string($row, 'label'),
                cds_core_date($row['start'] ?? ''), cds_core_date($row['end'] ?? ''),
                cds_core_bool($row, 'is_current'), cds_core_json($row), cds_core_datetime($row),
            ));
        }

        $teacherSql = "INSERT INTO cds_teachers
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
        foreach ($data['teachers'] as $row) {
            $flags = is_array($row['role_flags'] ?? null) ? $row['role_flags'] : array();
            $roles = $row['kiem_nhiem_text'] ?? ($row['kiem_nhiem'] ?? '');
            if (is_array($roles)) {
                $roles = implode(', ', $roles);
            }
            cds_core_upsert($pdo, $teacherSql, array(
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

        $classSql = "INSERT INTO cds_classes
            (id, school_year_id, name, grade, level_name, homeroom_teacher_id, room,
             capacity, active, note, raw_json, source_updated_at, imported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                school_year_id=VALUES(school_year_id), name=VALUES(name), grade=VALUES(grade),
                level_name=VALUES(level_name), homeroom_teacher_id=VALUES(homeroom_teacher_id),
                room=VALUES(room), capacity=VALUES(capacity), active=VALUES(active),
                note=VALUES(note), raw_json=VALUES(raw_json),
                source_updated_at=VALUES(source_updated_at), imported_at=NOW()";
        foreach ($data['classes'] as $row) {
            $teacherId = cds_core_string($row, 'homeroom_teacher_id');
            $capacity = cds_core_string($row, 'capacity');
            cds_core_upsert($pdo, $classSql, array(
                (string)$row['id'], $currentYearId, cds_core_string($row, 'name'),
                is_numeric($row['grade'] ?? null) ? (int)$row['grade'] : null,
                cds_core_string($row, 'level'), $teacherId !== '' ? $teacherId : null,
                cds_core_string($row, 'room'), is_numeric($capacity) ? (int)$capacity : null,
                cds_core_bool($row, 'active', true), cds_core_string($row, 'note'),
                cds_core_json($row), cds_core_datetime($row),
            ));
        }

        $studentSql = "INSERT INTO cds_students
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
        foreach ($data['students'] as $row) {
            $classId = cds_core_string($row, 'class_id');
            cds_core_upsert($pdo, $studentSql, array(
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

        $done = $pdo->prepare(
            "UPDATE cds_import_batches
             SET status='completed', records_imported=?, completed_at=NOW()
             WHERE id=?"
        );
        $done->execute(array($total, $batchId));

        $audit = $pdo->prepare(
            "INSERT INTO cds_audit_log
                (actor_user_id, actor_name, module_key, action_key, entity_type,
                 entity_id, after_json, request_ip, created_at)
             VALUES (?, ?, 'csdl', 'import_json_snapshot', 'import_batch', ?, ?, ?, NOW())"
        );
        $audit->execute(array(
            (string)($actor['id'] ?? ''),
            (string)($actor['name'] ?? ''),
            (string)$batchId,
            cds_core_json($preview['counts']),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ));

        $pdo->commit();
        return array('batch_id' => $batchId, 'counts' => $preview['counts']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cds_core_mysql_counts()
{
    $pdo = cds_db();
    $tables = array(
        'years' => 'cds_school_years',
        'teachers' => 'cds_teachers',
        'classes' => 'cds_classes',
        'students' => 'cds_students',
    );
    $counts = array();
    foreach ($tables as $key => $table) {
        $counts[$key] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    return $counts;
}
