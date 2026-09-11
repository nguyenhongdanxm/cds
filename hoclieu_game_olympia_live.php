<?php
require_once __DIR__.'/includes/auth.php';require_login();require_once __DIR__.'/includes/olympia_store.php';olympia_ensure_schema();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$weekId=trim((string)($_REQUEST['week']??''));$classId=trim((string)($_REQUEST['class']??''));$week=olympia_week($weekId);
try{
 if(!$week||!olympia_can_class($classId)||!in_array($classId,olympia_week_class_ids($weekId),true))throw new RuntimeException('Không có quyền truy cập phiên thi đấu này.');
 $db=olympia_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $csrf=(string)($_SESSION['olympia_play_csrf']??'');if($csrf===''||!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Phiên điều khiển không hợp lệ.');
  $state=json_decode((string)($_POST['state']??''),true);if(!is_array($state))throw new RuntimeException('Trạng thái trình chiếu không hợp lệ.');
  $allowed=['status','question_index','stage','question','answer','points','seconds','timer_ends_at','stage_score','total_score','question_score','awarded_students'];$clean=[];foreach($allowed as $key)if(array_key_exists($key,$state))$clean[$key]=$state[$key];$clean['updated_ms']=(int)round(microtime(true)*1000);
  $u=current_user()??[];$name=(string)($u['teacher_name']??$u['name']??$u['username']??'');$s=$db->prepare('INSERT INTO cds_olympia_live_sessions(week_id,class_id,state_json,controlled_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE state_json=VALUES(state_json),controlled_by=VALUES(controlled_by)');$s->execute([$weekId,$classId,json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$name]);echo json_encode(['ok'=>true],JSON_UNESCAPED_UNICODE);exit;
 }
 $s=$db->prepare('SELECT state_json,controlled_by,updated_at FROM cds_olympia_live_sessions WHERE week_id=? AND class_id=?');$s->execute([$weekId,$classId]);$row=$s->fetch();$state=$row?json_decode((string)$row['state_json'],true):['status'=>'waiting'];echo json_encode(['ok'=>true,'state'=>$state,'controlled_by'=>$row['controlled_by']??'','updated_at'=>$row['updated_at']??''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(403);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
