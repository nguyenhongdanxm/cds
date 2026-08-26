<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/csdl_store.php';
require_once __DIR__.'/includes/thidua_room_lock.php';
require_login(); require_module('thidua','view');
if(!can_perm_level('td.student_room_input','view')){http_response_code(403);exit('Không có quyền xem lịch sử.');}
header('Content-Type: application/json; charset=utf-8');
$data=tdr_lock_load(); $entries=array_values(is_array($data['entries']??null)?$data['entries']:[]); $sessions=array_values(is_array($data['sessions']??null)?$data['sessions']:[]);
$user=current_user()??[]; $userName=trim((string)($user['name']??$user['teacher_name']??$user['username']??''));
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!tdr_is_room_admin()){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Chỉ Quản trị hệ thống được mở/khóa dữ liệu.'],JSON_UNESCAPED_UNICODE);exit;}
  $csrf=(string)($_POST['csrf']??''); if(empty($_SESSION['td_room_csrf'])||!hash_equals((string)$_SESSION['td_room_csrf'],$csrf)){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
  $date=trim((string)($_POST['date']??''));$mode=(string)($_POST['mode']??'unlock'); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Ngày không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
  tdr_set_date_unlock($date,$mode==='unlock',$userName);echo json_encode(['ok'=>true,'locked'=>tdr_date_locked($date),'message'=>$mode==='unlock'?'Đã mở khóa ngày chấm.':'Đã khóa lại ngày chấm.'],JSON_UNESCAPED_UNICODE);exit;
}
$date=trim((string)($_GET['date']??''));$room=trim((string)($_GET['room']??''));
$rows=[];$roomSet=[];
foreach($entries as $e){$r=(string)($e['room']??'');if($r!=='')$roomSet[$r]=1;if($room!==''&&$r!==$room)continue;foreach((array)($e['items']??[]) as $it){$d=(string)($it['date']??'');if($date!==''&&$d!==$date)continue;$rows[]=['date'=>$d,'shift'=>(string)($it['shift']??'sang'),'room'=>$r,'criterion'=>(string)($it['name']??''),'points'=>(float)($it['points']??0),'note'=>(string)($it['note']??''),'by'=>(string)($it['created_by']??$e['created_by']??''),'at'=>(string)($it['updated_at']??$it['created_at']??'')];}}
usort($rows,function($a,$b){$c=strcmp($b['date'],$a['date']);if($c)return$c;$c=strnatcasecmp($a['room'],$b['room']);if($c)return$c;return strcmp($a['shift'],$b['shift']);});
$sessionRows=[];foreach($sessions as $s){$d=(string)($s['date']??'');if($date!==''&&$d!==$date)continue;$sessionRows[]=['date'=>$d,'shift'=>(string)($s['shift']??''),'by'=>(string)($s['by']??''),'at'=>(string)($s['updated_at']??$s['created_at']??'')];}
$dates=[];foreach($sessions as $s){$d=(string)($s['date']??'');if($d!=='')$dates[$d]=1;}foreach($rows as $r)$dates[$r['date']]=1;$dates=array_keys($dates);rsort($dates);
$rooms=array_keys($roomSet);sort($rooms,SORT_NATURAL|SORT_FLAG_CASE);
$lockDate=$date!==''?$date:($dates[0]??date('Y-m-d'));
echo json_encode(['ok'=>true,'rows'=>$rows,'sessions'=>$sessionRows,'dates'=>$dates,'rooms'=>$rooms,'filter'=>['date'=>$date,'room'=>$room],'lock'=>['date'=>$lockDate,'locked'=>tdr_date_locked($lockDate,$data),'admin'=>tdr_is_room_admin()]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
