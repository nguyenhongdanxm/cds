<?php
require_once __DIR__ . '/database.php';

function cds_meal_json($value)
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cds_meal_pending_path()
{
    return defined('DATA_PATH') ? DATA_PATH . '/noitru/meal_mysql_shadow_pending.json' : '';
}

function cds_meal_pending_status()
{
    $path = cds_meal_pending_path();
    if ($path === '' || !is_file($path)) return null;
    $value = json_decode((string)@file_get_contents($path), true);
    return is_array($value) ? $value : array('at' => '', 'scope' => 'unknown');
}

function cds_meal_pending_mark($scope)
{
    $path = cds_meal_pending_path();
    if ($path === '') return false;
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $payload = cds_meal_json(array('at' => date('c'), 'scope' => (string)$scope));
    return $payload !== false && @file_put_contents($path, $payload, LOCK_EX) !== false;
}

function cds_meal_pending_clear()
{
    $path = cds_meal_pending_path();
    return $path === '' || !is_file($path) || @unlink($path);
}

function cds_meal_shadow_enabled()
{
    if (array_key_exists('cds_meal_shadow_enabled_cache', $GLOBALS)) {
        return (bool)$GLOBALS['cds_meal_shadow_enabled_cache'];
    }
    try {
        $stmt = cds_db()->query("SELECT setting_value FROM cds_runtime_settings WHERE setting_key='meal_shadow_write'");
        $enabled = (string)$stmt->fetchColumn() === '1';
        $GLOBALS['cds_meal_shadow_enabled_cache'] = $enabled;
        return $enabled;
    } catch (Throwable $e) {
        $GLOBALS['cds_meal_shadow_enabled_cache'] = false;
        return false;
    }
}

function cds_meal_shadow_set($enabled, $actor)
{
    if ($enabled) {
        $status = cds_meal_compare_snapshot();
        if (empty($status['is_match'])) throw new RuntimeException('Bản sao báo ăn MySQL chưa khớp JSON. Hãy nhập và đối chiếu trước.');
        if (is_array(cds_meal_pending_status())) throw new RuntimeException('Còn lần đồng bộ báo ăn đang chờ xử lý.');
    }
    $pdo = cds_db(); $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO cds_runtime_settings (setting_key, setting_value, updated_by, updated_at)
             VALUES ('meal_shadow_write', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by), updated_at=NOW()"
        );
        $stmt->execute(array($enabled ? '1' : '0', (string)($actor['username'] ?? $actor['name'] ?? '')));
        $audit = $pdo->prepare("INSERT INTO cds_audit_log
            (actor_user_id,actor_name,module_key,action_key,entity_type,entity_id,after_json,request_ip,created_at)
            VALUES (?,?,'noitru','meal_shadow_toggle','meal_shadow','3A',?,?,NOW())");
        $audit->execute(array((string)($actor['id']??''),(string)($actor['name']??''),
            cds_meal_json(array('enabled'=>(bool)$enabled)),(string)($_SERVER['REMOTE_ADDR']??'')));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $GLOBALS['cds_meal_shadow_enabled_cache'] = (bool)$enabled;
}

function cds_meal_source_snapshot()
{
    $dir = defined('DATA_PATH') ? DATA_PATH . '/noitru' : '';
    $reports = $dir !== '' ? cds_json_load($dir . '/meal_reports.json', array()) : array();
    $reports = is_array($reports) ? $reports : array();
    return array(
        'daily' => $dir !== '' ? array_values((array)cds_json_load($dir . '/meals_daily.json', array())) : array(),
        'reports' => array_values((array)($reports['reports'] ?? array())),
        'states' => array_values((array)($reports['states'] ?? array())),
        'settings' => is_array($reports['settings'] ?? null) ? $reports['settings'] : array(),
    );
}

function cds_meal_date($value)
{
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function cds_meal_datetime($value)
{
    $time = $value ? strtotime((string)$value) : false;
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

function cds_meal_canonicalize($value)
{
    if (!is_array($value)) return $value;
    $keys = array_keys($value); $isList = $keys === range(0, count($value) - 1);
    if (!$isList) ksort($value);
    foreach ($value as $key => $item) $value[$key] = cds_meal_canonicalize($item);
    return $value;
}

function cds_meal_report_id(array $row)
{
    $saved = trim((string)($row['id'] ?? ''));
    if ($saved !== '') return $saved;
    $target = implode('|', array(
        (string)($row['date'] ?? ''),
        (string)($row['class_name'] ?? ''),
        (string)($row['meal'] ?? ''),
    ));
    return 'mr_' . substr(hash('sha256', $target), 0, 16);
}

function cds_meal_validate_snapshot(array $data)
{
    $errors = array();
    $seen = array();
    foreach ((array)($data['daily'] ?? array()) as $i => $row) {
        $date = cds_meal_date($row['date'] ?? '');
        $student = trim((string)($row['student_id'] ?? ''));
        $key = $date . '|' . $student;
        if ($date === '' || $student === '') $errors[] = 'Dòng báo ăn cá nhân ' . ($i + 1) . ' thiếu ngày hoặc học sinh.';
        elseif (isset($seen[$key])) $errors[] = 'Trùng báo ăn cá nhân ' . $key . '.';
        $seen[$key] = true;
    }
    $seen = array();
    foreach ((array)($data['reports'] ?? array()) as $i => $row) {
        $date = cds_meal_date($row['date'] ?? '');
        $class = trim((string)($row['class_name'] ?? ''));
        $meal = trim((string)($row['meal'] ?? ''));
        $key = $date . '|' . $class . '|' . $meal;
        if ($date === '' || $class === '' || !in_array($meal, array('sang','trua','toi'), true)) {
            $errors[] = 'Phiếu báo ăn ' . ($i + 1) . ' thiếu ngày, lớp hoặc bữa hợp lệ.';
        } elseif (isset($seen[$key])) $errors[] = 'Trùng phiếu báo ăn ' . $key . '.';
        $seen[$key] = true;
    }
    return array_values(array_unique($errors));
}

function cds_meal_delete_missing(PDO $pdo, $table, array $keys, array $columns, $allowEmpty = false)
{
    $allowed = array('cds_meal_daily','cds_meal_reports','cds_meal_states');
    if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Bảng báo ăn không hợp lệ.');
    $allowedColumns = array('id','meal_date','student_id','meal_code');
    foreach ($columns as $column) {
        if (!in_array($column, $allowedColumns, true)) throw new InvalidArgumentException('Cột báo ăn không hợp lệ.');
    }
    if (!$keys) {
        $existing = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        if ($existing > 0 && !$allowEmpty) throw new RuntimeException('Nguồn ' . $table . ' đang rỗng; từ chối xóa bản sao MySQL hiện có.');
        if ($existing > 0) $pdo->exec('DELETE FROM ' . $table);
        return;
    }
    /* Dùng bảng tạm theo từng lô để lịch sử dài không tạo một câu SQL quá lớn. */
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS cds_meal_sync_keys');
    $pdo->exec('CREATE TEMPORARY TABLE cds_meal_sync_keys (key_value VARCHAR(512) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $insert = $pdo->prepare('INSERT IGNORE INTO cds_meal_sync_keys (key_value) VALUES (?)');
    foreach (array_values(array_unique($keys)) as $key) {
        $insert->execute(array((string)$key));
    }
    $expression = count($columns) === 1
        ? 'CAST(t.' . $columns[0] . ' AS CHAR)'
        : 'CONCAT_WS(\'|\',' . implode(',', array_map(function ($column) { return 't.' . $column; }, $columns)) . ')';
    $pdo->exec('DELETE t FROM ' . $table . ' t LEFT JOIN cds_meal_sync_keys k ON k.key_value=' . $expression . ' WHERE k.key_value IS NULL');
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS cds_meal_sync_keys');
}

function cds_meal_import_snapshot($actor = array(), $sourceData = null)
{
    $data = is_array($sourceData) ? $sourceData : cds_meal_source_snapshot();
    $errors = cds_meal_validate_snapshot($data);
    if ($errors) throw new RuntimeException(implode(' ', array_slice($errors, 0, 3)));
    $pdo = cds_db();
    $pdo->beginTransaction();
    try {
        $dailySql = "INSERT INTO cds_meal_daily
            (meal_date,student_id,breakfast,lunch,dinner,is_locked,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE breakfast=VALUES(breakfast),lunch=VALUES(lunch),
            dinner=VALUES(dinner),is_locked=VALUES(is_locked),raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at),imported_at=NOW()";
        $stmt = $pdo->prepare($dailySql); $dailyKeys = array();
        foreach ($data['daily'] as $row) {
            $date = (string)$row['date']; $sid = (string)$row['student_id']; $dailyKeys[] = $date . '|' . $sid;
            $stmt->execute(array($date,$sid,(string)($row['sang']??''),(string)($row['trua']??''),(string)($row['toi']??''),
                !empty($row['locked'])?1:0,cds_meal_json($row),cds_meal_datetime($row['updated_at']??$row['created_at']??'')));
        }
        cds_meal_delete_missing($pdo, 'cds_meal_daily', $dailyKeys, array('meal_date','student_id'));

        $reportSql = "INSERT INTO cds_meal_reports
            (id,meal_date,class_name,meal_code,student_count,absent_count,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE meal_date=VALUES(meal_date),class_name=VALUES(class_name),
            meal_code=VALUES(meal_code),student_count=VALUES(student_count),absent_count=VALUES(absent_count),
            raw_json=VALUES(raw_json),source_updated_at=VALUES(source_updated_at),imported_at=NOW()";
        $stmt = $pdo->prepare($reportSql); $reportIds = array();
        foreach ($data['reports'] as $row) {
            $id = cds_meal_report_id($row);
            $reportIds[] = $id;
            $stmt->execute(array($id,(string)$row['date'],(string)$row['class_name'],(string)$row['meal'],
                max(0,(int)($row['student_count']??0)),max(0,(int)($row['absent_count']??0)),cds_meal_json($row),
                cds_meal_datetime($row['updated_at']??$row['created_at']??'')));
        }
        cds_meal_delete_missing($pdo, 'cds_meal_reports', $reportIds, array('id'), true);

        $stateSql = "INSERT INTO cds_meal_states
            (meal_date,meal_code,status_code,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status_code=VALUES(status_code),raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at),imported_at=NOW()";
        $stmt = $pdo->prepare($stateSql); $stateKeys = array();
        foreach ($data['states'] as $row) {
            $date = cds_meal_date($row['date']??''); $meal = trim((string)($row['meal']??''));
            if ($date === '' || $meal === '') continue; $stateKeys[] = $date . '|' . $meal;
            $stmt->execute(array($date,$meal,(string)($row['status']??'open'),cds_meal_json($row),cds_meal_datetime($row['updated_at']??'')));
        }
        cds_meal_delete_missing($pdo, 'cds_meal_states', $stateKeys, array('meal_date','meal_code'), true);
        $pdo->prepare("INSERT INTO cds_meal_settings (id,raw_json,imported_at) VALUES (1,?,NOW())
            ON DUPLICATE KEY UPDATE raw_json=VALUES(raw_json),imported_at=NOW()")
            ->execute(array(cds_meal_json($data['settings'])));
        $audit = $pdo->prepare("INSERT INTO cds_audit_log
            (actor_user_id,actor_name,module_key,action_key,entity_type,entity_id,after_json,request_ip,created_at)
            VALUES (?,?,'noitru','import_meal_snapshot','meal_snapshot','3A',?,?,NOW())");
        $audit->execute(array((string)($actor['id']??''),(string)($actor['name']??''),cds_meal_json(array(
            'daily'=>count($data['daily']),'reports'=>count($data['reports']),'states'=>count($data['states'])
        )),(string)($_SERVER['REMOTE_ADDR']??'')));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    cds_meal_pending_clear();
    return cds_meal_compare_snapshot($data);
}

function cds_meal_sql_snapshot()
{
    $pdo = cds_db(); $data = array('daily'=>array(),'reports'=>array(),'states'=>array(),'settings'=>array());
    foreach (array('daily'=>'cds_meal_daily','reports'=>'cds_meal_reports','states'=>'cds_meal_states') as $type=>$table) {
        $stmt = $pdo->query('SELECT raw_json FROM ' . $table);
        while ($db = $stmt->fetch(PDO::FETCH_ASSOC)) { $row=json_decode((string)$db['raw_json'],true); if(is_array($row))$data[$type][]=$row; }
    }
    $raw = $pdo->query('SELECT raw_json FROM cds_meal_settings WHERE id=1')->fetchColumn();
    $settings = json_decode((string)$raw, true); $data['settings'] = is_array($settings) ? $settings : array();
    return $data;
}

function cds_meal_hash_map(array $rows, array $keys)
{
    $map=array(); foreach($rows as $row){$parts=array();foreach($keys as $key)$parts[]=(string)($row[$key]??'');$map[implode('|',$parts)]=hash('sha256',cds_meal_json(cds_meal_canonicalize($row)));}ksort($map);return$map;
}

function cds_meal_compare_snapshot($sourceData = null)
{
    $source = is_array($sourceData) ? $sourceData : cds_meal_source_snapshot();
    $mysql = cds_meal_sql_snapshot();
    $types = array(
        'daily'=>cds_meal_hash_map($source['daily'],array('date','student_id'))===cds_meal_hash_map($mysql['daily'],array('date','student_id')),
        'reports'=>cds_meal_hash_map($source['reports'],array('date','class_name','meal'))===cds_meal_hash_map($mysql['reports'],array('date','class_name','meal')),
        'states'=>cds_meal_hash_map($source['states'],array('date','meal'))===cds_meal_hash_map($mysql['states'],array('date','meal')),
        'settings'=>hash('sha256',cds_meal_json(cds_meal_canonicalize($source['settings'])))===hash('sha256',cds_meal_json(cds_meal_canonicalize($mysql['settings']))),
    );
    return array('is_match'=>!in_array(false,$types,true),'types'=>$types,'counts'=>array(
        'daily'=>count($source['daily']),'reports'=>count($source['reports']),'states'=>count($source['states'])
    ),'mysql_counts'=>array('daily'=>count($mysql['daily']),'reports'=>count($mysql['reports']),'states'=>count($mysql['states'])));
}

function cds_meal_shadow_sync($scope = 'all')
{
    if (!cds_meal_shadow_enabled()) return true;
    if (!cds_meal_pending_mark($scope)) return false;
    try { cds_meal_import_snapshot(function_exists('current_user')?(array)current_user():array()); return true; }
    catch(Throwable $e){error_log('[CDS meal shadow] '.$e->getMessage());return false;}
}

function cds_meal_shadow_notify_failure()
{
    static $shown = false;
    if ($shown) return;
    $shown = true;
    if (function_exists('flash')) {
        flash('Dữ liệu báo ăn đã lưu an toàn vào JSON; bản sao MySQL đang chờ đồng bộ lại.', 'warning');
    }
}

function cds_meal_shadow_daily_rows(array $rows, $date)
{
    if (!cds_meal_shadow_enabled()) return true;
    if (is_array(cds_meal_pending_status())) return cds_meal_shadow_sync('daily_rows_recovery');
    if (!cds_meal_pending_mark('daily_rows')) return false;
    $pdo = null;
    try {
        $date = cds_meal_date($date);
        if ($date === '') throw new RuntimeException('Ngày báo ăn không hợp lệ.');
        $pdo = cds_db(); $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO cds_meal_daily
            (meal_date,student_id,breakfast,lunch,dinner,is_locked,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE breakfast=VALUES(breakfast),lunch=VALUES(lunch),
            dinner=VALUES(dinner),is_locked=VALUES(is_locked),raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at),imported_at=NOW()");
        $ids = array();
        foreach ($rows as $row) {
            if (($row['date'] ?? '') !== $date) continue;
            $sid = trim((string)($row['student_id'] ?? ''));
            if ($sid === '') throw new RuntimeException('Báo ăn thiếu học sinh.');
            $ids[] = $sid;
            $stmt->execute(array($date,$sid,(string)($row['sang']??''),(string)($row['trua']??''),(string)($row['toi']??''),
                !empty($row['locked'])?1:0,cds_meal_json($row),cds_meal_datetime($row['updated_at']??$row['created_at']??'')));
        }
        if (!$ids) {
            $delete = $pdo->prepare('DELETE FROM cds_meal_daily WHERE meal_date=?');
            $delete->execute(array($date));
        } else {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $delete = $pdo->prepare('DELETE FROM cds_meal_daily WHERE meal_date=? AND student_id NOT IN (' . $marks . ')');
            $delete->execute(array_merge(array($date), $ids));
        }
        $pdo->commit();
        return cds_meal_pending_clear();
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[CDS meal daily rows shadow] ' . $e->getMessage());
        return false;
    }
}

function cds_meal_shadow_daily_row(array $row)
{
    if (!cds_meal_shadow_enabled()) return true;
    if (is_array(cds_meal_pending_status())) return cds_meal_shadow_sync('daily_recovery');
    if (!cds_meal_pending_mark('daily_row')) return false;
    try {
        $date=cds_meal_date($row['date']??'');$sid=trim((string)($row['student_id']??''));
        if($date===''||$sid==='')throw new RuntimeException('Báo ăn thiếu ngày hoặc học sinh.');
        $stmt=cds_db()->prepare("INSERT INTO cds_meal_daily
            (meal_date,student_id,breakfast,lunch,dinner,is_locked,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE breakfast=VALUES(breakfast),lunch=VALUES(lunch),
            dinner=VALUES(dinner),is_locked=VALUES(is_locked),raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at),imported_at=NOW()");
        $stmt->execute(array($date,$sid,(string)($row['sang']??''),(string)($row['trua']??''),(string)($row['toi']??''),
            !empty($row['locked'])?1:0,cds_meal_json($row),cds_meal_datetime($row['updated_at']??$row['created_at']??'')));
        return cds_meal_pending_clear();
    } catch(Throwable $e){error_log('[CDS meal daily shadow] '.$e->getMessage());return false;}
}

function cds_meal_shadow_reports_data(array $data)
{
    if (!cds_meal_shadow_enabled()) return true;
    if (is_array(cds_meal_pending_status())) return cds_meal_shadow_sync('reports_recovery');
    if (!cds_meal_pending_mark('reports')) return false;
    $pdo=null;
    try {
        $pdo=cds_db();$pdo->beginTransaction();
        $stmt=$pdo->prepare("INSERT INTO cds_meal_reports
            (id,meal_date,class_name,meal_code,student_count,absent_count,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE meal_date=VALUES(meal_date),class_name=VALUES(class_name),
            meal_code=VALUES(meal_code),student_count=VALUES(student_count),absent_count=VALUES(absent_count),
            raw_json=VALUES(raw_json),source_updated_at=VALUES(source_updated_at),imported_at=NOW()");
        $ids=array();foreach(array_values((array)($data['reports']??array()))as$row){
            $id=cds_meal_report_id($row);$ids[]=$id;
            $stmt->execute(array($id,(string)($row['date']??''),(string)($row['class_name']??''),(string)($row['meal']??''),
                max(0,(int)($row['student_count']??0)),max(0,(int)($row['absent_count']??0)),cds_meal_json($row),
                cds_meal_datetime($row['updated_at']??$row['created_at']??'')));
        }
        cds_meal_delete_missing($pdo,'cds_meal_reports',$ids,array('id'),true);
        $stmt=$pdo->prepare("INSERT INTO cds_meal_states
            (meal_date,meal_code,status_code,raw_json,source_updated_at,imported_at)
            VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status_code=VALUES(status_code),raw_json=VALUES(raw_json),
            source_updated_at=VALUES(source_updated_at),imported_at=NOW()");
        $keys=array();foreach(array_values((array)($data['states']??array()))as$row){$date=cds_meal_date($row['date']??'');$meal=trim((string)($row['meal']??''));if($date===''||$meal==='')continue;$keys[]=$date.'|'.$meal;$stmt->execute(array($date,$meal,(string)($row['status']??'open'),cds_meal_json($row),cds_meal_datetime($row['updated_at']??'')));}
        cds_meal_delete_missing($pdo,'cds_meal_states',$keys,array('meal_date','meal_code'),true);
        $settings=is_array($data['settings']??null)?$data['settings']:array();
        $pdo->prepare("INSERT INTO cds_meal_settings(id,raw_json,imported_at)VALUES(1,?,NOW())
            ON DUPLICATE KEY UPDATE raw_json=VALUES(raw_json),imported_at=NOW()")
            ->execute(array(cds_meal_json($settings)));
        $pdo->commit();return cds_meal_pending_clear();
    }catch(Throwable$e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log('[CDS meal reports shadow] '.$e->getMessage());return false;}
}
