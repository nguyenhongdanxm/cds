<?php
require_once __DIR__ . '/database_meals.php';

function cds_meal_sql_read_configured()
{
    if (array_key_exists('cds_meal_sql_read_cache', $GLOBALS)) return (bool)$GLOBALS['cds_meal_sql_read_cache'];
    try {
        $stmt = cds_db()->query("SELECT setting_value FROM cds_runtime_settings WHERE setting_key='meal_sql_read'");
        $enabled = (string)$stmt->fetchColumn() === '1';
        $GLOBALS['cds_meal_sql_read_cache'] = $enabled;
        return $enabled;
    } catch (Throwable $e) {
        $GLOBALS['cds_meal_sql_read_cache'] = false;
        return false;
    }
}

function cds_meal_sql_read_status()
{
    if (isset($GLOBALS['cds_meal_sql_read_status_cache'])) return $GLOBALS['cds_meal_sql_read_status_cache'];
    $configured = cds_meal_sql_read_configured();
    if (!$configured) return $GLOBALS['cds_meal_sql_read_status_cache'] = array('configured'=>false,'effective'=>false,'ready'=>false,'reason'=>'Chưa bật đọc MySQL báo ăn.');
    if (!cds_meal_shadow_enabled()) return $GLOBALS['cds_meal_sql_read_status_cache'] = array('configured'=>true,'effective'=>false,'ready'=>false,'reason'=>'Giai đoạn 3A đang tắt.');
    if (is_array(cds_meal_pending_status())) return $GLOBALS['cds_meal_sql_read_status_cache'] = array('configured'=>true,'effective'=>false,'ready'=>false,'reason'=>'Còn lần đồng bộ báo ăn đang chờ.');
    try {
        cds_db()->query('SELECT 1 FROM cds_meal_daily LIMIT 1');
        return $GLOBALS['cds_meal_sql_read_status_cache'] = array('configured'=>true,'effective'=>true,'ready'=>true,'reason'=>'');
    } catch (Throwable $e) {
        return $GLOBALS['cds_meal_sql_read_status_cache'] = array('configured'=>true,'effective'=>false,'ready'=>false,'reason'=>'MySQL báo ăn chưa sẵn sàng.');
    }
}

function cds_meal_sql_read_effective()
{
    if (!empty($GLOBALS['cds_force_json_meal_read'])) return false;
    $status = cds_meal_sql_read_status();
    return !empty($status['effective']);
}

function cds_meal_sql_read_set($enabled, $actor)
{
    if ($enabled) {
        if (!cds_meal_shadow_enabled()) throw new RuntimeException('Cần bật và kiểm thử giai đoạn 3A trước.');
        if (is_array(cds_meal_pending_status())) throw new RuntimeException('Còn lần đồng bộ báo ăn đang chờ.');
        $comparison = cds_meal_compare_snapshot();
        if (empty($comparison['is_match'])) throw new RuntimeException('Báo ăn JSON và MySQL chưa khớp.');
    }
    $pdo = cds_db(); $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO cds_runtime_settings(setting_key,setting_value,updated_by,updated_at)
            VALUES('meal_sql_read',?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
            updated_by=VALUES(updated_by),updated_at=NOW()");
        $stmt->execute(array($enabled?'1':'0',(string)($actor['username']??$actor['name']??'')));
        $audit = $pdo->prepare("INSERT INTO cds_audit_log
            (actor_user_id,actor_name,module_key,action_key,entity_type,entity_id,after_json,request_ip,created_at)
            VALUES (?,?,'noitru','meal_sql_read_toggle','meal_read','3B',?,?,NOW())");
        $audit->execute(array((string)($actor['id']??''),(string)($actor['name']??''),
            cds_meal_json(array('enabled'=>(bool)$enabled)),(string)($_SERVER['REMOTE_ADDR']??'')));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $GLOBALS['cds_meal_sql_read_cache'] = (bool)$enabled;
    unset($GLOBALS['cds_meal_sql_read_status_cache']);
}

function cds_meal_sql_decode_rows($sql, array $values = array())
{
    $stmt = cds_db()->prepare($sql); $stmt->execute($values); $rows = array();
    while ($db = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = json_decode((string)($db['raw_json']??''), true);
        if (!is_array($row)) throw new RuntimeException('MySQL có bản ghi báo ăn không hợp lệ.');
        $rows[] = $row;
    }
    return $rows;
}

function cds_meal_sql_daily_all()
{
    return cds_meal_sql_decode_rows('SELECT raw_json FROM cds_meal_daily ORDER BY meal_date,student_id');
}

function cds_meal_sql_daily_for_date($date)
{
    $date = cds_meal_date($date);
    if ($date === '') throw new InvalidArgumentException('Ngày báo ăn không hợp lệ.');
    return cds_meal_sql_decode_rows('SELECT raw_json FROM cds_meal_daily WHERE meal_date=? ORDER BY student_id', array($date));
}

function cds_meal_sql_daily_for_range($from, $to)
{
    $from = cds_meal_date($from); $to = cds_meal_date($to);
    if ($from === '' || $to === '' || $to < $from) throw new InvalidArgumentException('Khoảng ngày báo ăn không hợp lệ.');
    return cds_meal_sql_decode_rows(
        'SELECT raw_json FROM cds_meal_daily WHERE meal_date BETWEEN ? AND ? ORDER BY meal_date,student_id',
        array($from,$to)
    );
}

function cds_meal_sql_reports_data()
{
    $reports = cds_meal_sql_decode_rows('SELECT raw_json FROM cds_meal_reports ORDER BY meal_date,class_name,meal_code');
    $states = cds_meal_sql_decode_rows('SELECT raw_json FROM cds_meal_states ORDER BY meal_date,meal_code');
    $stmt = cds_db()->query('SELECT raw_json FROM cds_meal_settings WHERE id=1');
    $settings = json_decode((string)$stmt->fetchColumn(), true);
    if (!is_array($settings)) $settings = array();
    return array('reports'=>$reports,'states'=>$states,'settings'=>$settings);
}

function cds_meal_sql_fallback($context, Throwable $error)
{
    $GLOBALS['cds_force_json_meal_read'] = true;
    unset($GLOBALS['cds_meal_sql_read_status_cache']);
    error_log('[CDS meal SQL read fallback][' . (string)$context . '] ' . $error->getMessage());
}
