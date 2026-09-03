<?php
/** MySQL an toàn cho Sổ đầu bài: JSON là bản dự phòng, SQL là nguồn đọc có chỉ mục. */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/json_store.php';
require_once __DIR__ . '/school_week_calendar.php';

function cds_lb_json($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function cds_lb_records_path() { return defined('LB_RECORDS_FILE') ? LB_RECORDS_FILE : dirname(__DIR__) . '/chuyenmon/data/lesson_book_records.json'; }
function cds_lb_pending_path() { return dirname(cds_lb_records_path()) . '/lesson_book_mysql_pending.json'; }
function cds_lb_pending_status() { $p=cds_lb_pending_path();if(!is_file($p))return null;$v=json_decode((string)@file_get_contents($p),true);return is_array($v)?$v:['at'=>'','scope'=>'unknown']; }
function cds_lb_pending_mark($scope) { $ok=@file_put_contents(cds_lb_pending_path(),cds_lb_json(['at'=>date('c'),'scope'=>(string)$scope]),LOCK_EX)!==false;unset($GLOBALS['cds_lb_read_status']);return$ok; }
function cds_lb_pending_clear() { $p=cds_lb_pending_path();$ok=!is_file($p)||@unlink($p);unset($GLOBALS['cds_lb_read_status']);return$ok; }

function cds_lb_setting($key) {
    $cacheKey='cds_lb_setting_'.$key;if(array_key_exists($cacheKey,$GLOBALS))return(bool)$GLOBALS[$cacheKey];
    try{$s=cds_db()->prepare('SELECT setting_value FROM cds_runtime_settings WHERE setting_key=?');$s->execute([$key]);return$GLOBALS[$cacheKey]=((string)$s->fetchColumn()==='1');}
    catch(Throwable $e){return$GLOBALS[$cacheKey]=false;}
}
function cds_lb_shadow_enabled(){return cds_lb_setting('lesson_book_shadow_write');}
function cds_lb_read_configured(){return cds_lb_setting('lesson_book_sql_read');}
function cds_lb_actor_name($actor){return(string)($actor['username']??$actor['name']??'');}
function cds_lb_set_setting($key,$enabled,$actor){
    $pdo=cds_db();$pdo->beginTransaction();try{
        $s=$pdo->prepare("INSERT INTO cds_runtime_settings(setting_key,setting_value,updated_by,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=NOW()");
        $s->execute([$key,$enabled?'1':'0',cds_lb_actor_name((array)$actor)]);$pdo->commit();
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    $GLOBALS['cds_lb_setting_'.$key]=(bool)$enabled;unset($GLOBALS['cds_lb_read_status']);
}

function cds_lb_normalize($value){
    $value=mb_strtolower(trim((string)$value),'UTF-8');$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii))$value=$ascii;
    return trim((string)preg_replace('/[^a-z0-9]+/','-',$value),'-');
}
function cds_lb_subject_key($subject){
    $key=cds_lb_normalize($subject);$aliases=[
        'ngoai-ngu'=>'tieng-anh','anh-van'=>'tieng-anh','english'=>'tieng-anh',
        'nghe-thuat-an'=>'am-nhac','nghe-thuat-am-nhac'=>'am-nhac',
        'nghe-thuat-mt'=>'my-thuat','nghe-thuat-my-thuat'=>'my-thuat',
        'vat-ly'=>'vat-li','khtn-ly'=>'vat-li','khtn-vat-li'=>'vat-li',
        'hoa-hoc'=>'hoa','khtn-hoa-hoc'=>'hoa','khtn-hoa'=>'hoa',
        'sinh-hoc'=>'sinh','khtn-sinh-hoc'=>'sinh','khtn-sinh'=>'sinh',
    ];return$aliases[$key]??$key;
}
function cds_lb_school_year_key(array $row){
    if(trim((string)($row['school_year_key']??''))!=='')return trim((string)$row['school_year_key']);
    $date=(string)($row['date']??'');if(function_exists('cds_school_year_rows'))foreach(cds_school_year_rows()as$year){$start=(string)($year['start']??'');$end=(string)($year['end']??'');if($date!==''&&$start!==''&&$end!==''&&$date>=$start&&$date<=$end)return(string)($year['id']??$year['label']??'');}
    if(function_exists('cds_school_year_resolve')){$year=cds_school_year_resolve();if($year)return(string)($year['id']??$year['label']??'');}
    return 'unknown';
}
function cds_lb_enrich(array $row){$row['school_year_key']=cds_lb_school_year_key($row);$row['class_key']=cds_lb_normalize($row['class']??'');$row['subject_key']=cds_lb_subject_key($row['subject']??'');return$row;}
function cds_lb_datetime($value){$t=$value?strtotime((string)$value):false;return$t?date('Y-m-d H:i:s',$t):null;}
function cds_lb_actual_status($status){return in_array((string)$status,['taught','substitute','makeup','online'],true);}

function cds_lb_upsert(PDO $pdo,array $row){
    $row=cds_lb_enrich($row);$slot=trim((string)($row['slot_id']??''));if($slot==='')throw new RuntimeException('Bản ghi Sổ đầu bài thiếu mã tiết.');
    $sql="INSERT INTO cds_lesson_book_records(slot_id,school_year_key,week_key,lesson_date,session_code,timetable_period,class_key,class_name,subject_key,subject_name,ppct_period,status_code,scheduled_teacher,actual_teacher,signed_at,source_updated_at,raw_json,imported_at)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE school_year_key=VALUES(school_year_key),week_key=VALUES(week_key),lesson_date=VALUES(lesson_date),session_code=VALUES(session_code),timetable_period=VALUES(timetable_period),class_key=VALUES(class_key),class_name=VALUES(class_name),subject_key=VALUES(subject_key),subject_name=VALUES(subject_name),ppct_period=VALUES(ppct_period),status_code=VALUES(status_code),scheduled_teacher=VALUES(scheduled_teacher),actual_teacher=VALUES(actual_teacher),signed_at=VALUES(signed_at),source_updated_at=VALUES(source_updated_at),raw_json=VALUES(raw_json),imported_at=NOW()";
    $claimedPeriod=cds_lb_actual_status($row['status']??'')?((int)($row['ppct_period']??0)?:null):null;
    $pdo->prepare($sql)->execute([$slot,$row['school_year_key'],(string)($row['week_key']??''),(string)($row['date']??''),(string)($row['session']??''),(int)($row['period']??0),$row['class_key'],(string)($row['class']??''),$row['subject_key'],(string)($row['subject']??''),$claimedPeriod,(string)($row['status']??'pending'),(string)($row['scheduled_teacher']??''),(string)($row['actual_teacher']??''),cds_lb_datetime($row['signed_at']??''),cds_lb_datetime($row['updated_at']??$row['created_at']??''),cds_lb_json($row)]);
}
function cds_lb_source_rows(){return array_values(array_filter((array)cds_json_load(cds_lb_records_path(),[]),'is_array'));}
function cds_lb_sql_rows($where='',$values=[]){$s=cds_db()->prepare('SELECT raw_json FROM cds_lesson_book_records '.$where);$s->execute($values);$out=[];while($db=$s->fetch(PDO::FETCH_ASSOC)){$r=json_decode((string)$db['raw_json'],true);if(!is_array($r))throw new RuntimeException('MySQL có bản ghi Sổ đầu bài không hợp lệ.');$out[]=$r;}return$out;}
function cds_lb_sql_all(){return cds_lb_sql_rows('ORDER BY lesson_date,session_code,timetable_period,class_name');}
function cds_lb_sql_until($schoolYear,$date){return cds_lb_sql_rows('WHERE school_year_key=? AND lesson_date<=? ORDER BY lesson_date,session_code,timetable_period,class_name',[(string)$schoolYear,(string)$date]);}

function cds_lb_hash_map(array $rows){$out=[];foreach($rows as$r){$r=cds_lb_enrich($r);$id=(string)($r['slot_id']??'');if($id==='')continue;ksort($r);$out[$id]=hash('sha256',cds_lb_json($r));}ksort($out);return$out;}
function cds_lb_compare(){try{$json=cds_lb_source_rows();$sql=cds_lb_sql_all();$a=cds_lb_hash_map($json);$b=cds_lb_hash_map($sql);return['is_match'=>$a===$b,'json_count'=>count($a),'mysql_count'=>count($b)];}catch(Throwable$e){return['is_match'=>false,'json_count'=>count(cds_lb_source_rows()),'mysql_count'=>0,'error'=>$e->getMessage()];}}
function cds_lb_import_snapshot($actor=[]){
    $rows=cds_lb_source_rows();$seen=[];foreach($rows as$i=>$row){$row=cds_lb_enrich($row);$id=trim((string)($row['slot_id']??''));if($id===''||($row['date']??'')===''||$row['class_key']===''||$row['subject_key']==='')throw new RuntimeException('Bản ghi '.($i+1).' thiếu khóa chuẩn.');if(isset($seen[$id]))throw new RuntimeException('Trùng mã tiết '.$id.'.');$seen[$id]=true;}
    $pdo=cds_db();$pdo->beginTransaction();try{$pdo->exec('DELETE FROM cds_lesson_book_records');foreach($rows as$row)cds_lb_upsert($pdo,$row);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    cds_lb_pending_clear();$result=cds_lb_compare();if(empty($result['is_match']))throw new RuntimeException('Đã nhập nhưng đối chiếu chi tiết chưa khớp.');return$result;
}
function cds_lb_shadow_set($enabled,$actor){if($enabled){$c=cds_lb_compare();if(empty($c['is_match']))throw new RuntimeException('Bản sao MySQL chưa khớp JSON.');if(cds_lb_pending_status())throw new RuntimeException('Còn đồng bộ Sổ đầu bài đang chờ.');}cds_lb_set_setting('lesson_book_shadow_write',$enabled,$actor);}
function cds_lb_read_status(){
    if(isset($GLOBALS['cds_lb_read_status']))return$GLOBALS['cds_lb_read_status'];
    if(!cds_lb_read_configured())return$GLOBALS['cds_lb_read_status']=['configured'=>false,'effective'=>false,'reason'=>'Chưa bật đọc MySQL Sổ đầu bài.'];
    if(!cds_lb_shadow_enabled())return$GLOBALS['cds_lb_read_status']=['configured'=>true,'effective'=>false,'reason'=>'Ghi bản sao MySQL đang tắt.'];
    if(cds_lb_pending_status())return$GLOBALS['cds_lb_read_status']=['configured'=>true,'effective'=>false,'reason'=>'Còn đồng bộ Sổ đầu bài đang chờ.'];
    try{cds_db()->query('SELECT 1 FROM cds_lesson_book_records LIMIT 1');return$GLOBALS['cds_lb_read_status']=['configured'=>true,'effective'=>true,'reason'=>''];}catch(Throwable$e){return$GLOBALS['cds_lb_read_status']=['configured'=>true,'effective'=>false,'reason'=>'MySQL Sổ đầu bài chưa sẵn sàng.'];}
}
function cds_lb_read_effective(){return empty($GLOBALS['cds_force_json_lb_read'])&&!empty(cds_lb_read_status()['effective']);}
function cds_lb_read_set($enabled,$actor){if($enabled){if(!cds_lb_shadow_enabled())throw new RuntimeException('Cần bật ghi bản sao trước.');if(cds_lb_pending_status())throw new RuntimeException('Còn đồng bộ đang chờ.');if(empty(cds_lb_compare()['is_match']))throw new RuntimeException('JSON và MySQL chưa khớp.');}cds_lb_set_setting('lesson_book_sql_read',$enabled,$actor);}

function cds_lb_shadow_upsert(array $row){if(!cds_lb_shadow_enabled())return true;if(!cds_lb_pending_mark('upsert'))return false;try{$pdo=cds_db();$pdo->beginTransaction();cds_lb_upsert($pdo,$row);$pdo->commit();return cds_lb_pending_clear();}catch(Throwable$e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log('[CDS lesson book shadow] '.$e->getMessage());return false;}}
function cds_lb_shadow_delete($slotId){if(!cds_lb_shadow_enabled())return true;if(!cds_lb_pending_mark('delete'))return false;try{$s=cds_db()->prepare('DELETE FROM cds_lesson_book_records WHERE slot_id=?');$s->execute([(string)$slotId]);return cds_lb_pending_clear();}catch(Throwable$e){error_log('[CDS lesson book shadow delete] '.$e->getMessage());return false;}}

function cds_lb_mutate_json($operation,$slotId,array $row=[]){
    $error='';$result=null;$ok=cds_json_update(cds_lb_records_path(),function($rows)use($operation,$slotId,$row,&$result,&$error){$rows=array_values(array_filter(is_array($rows)?$rows:[],'is_array'));$found=false;$next=[];foreach($rows as$current){if((string)($current['slot_id']??'')!==(string)$slotId){$next[]=$current;continue;}$found=true;$result=$current;if($operation==='delete')continue;$result=array_merge($current,$row);$next[]=$result;}if(!$found&&$operation==='upsert'){$result=$row;$next[]=$row;}if(!$found&&$operation!=='upsert')return false;
        if($operation!=='delete'&&cds_lb_actual_status($result['status']??'')&&(int)($result['ppct_period']??0)>0){$target=cds_lb_enrich($result);$period=(int)$target['ppct_period'];$prior=false;foreach($next as$current){if((string)($current['slot_id']??'')===(string)$slotId||!cds_lb_actual_status($current['status']??''))continue;$current=cds_lb_enrich($current);if($current['school_year_key']!==$target['school_year_key']||$current['class_key']!==$target['class_key']||$current['subject_key']!==$target['subject_key'])continue;$used=(int)($current['ppct_period']??0);if($used===$period){$error='Tiết PPCT '.$period.' đã được một yêu cầu khác lưu trước.';return false;}if($used===$period-1)$prior=true;}if($period>1&&!$prior){$error='Chưa có tiết PPCT '.($period-1).' trong cùng năm học, lớp và môn.';return false;}}
        return$next;},[]);
    if(!$ok)return['ok'=>false,'row'=>$result,'message'=>$error];$shadow=$operation==='delete'?cds_lb_shadow_delete($slotId):cds_lb_shadow_upsert((array)$result);if(!$shadow){$GLOBALS['cds_force_json_lb_read']=true;unset($GLOBALS['cds_lb_read_status']);}return['ok'=>true,'shadow_ok'=>$shadow,'row'=>$result];
}
