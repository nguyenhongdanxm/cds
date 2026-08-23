<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_once __DIR__ . '/includes/student_card_store.php';
require_login();
require_perm('nt.ravao');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ntxc_json($ok,$message,$extra=[],$status=200){http_response_code($status);echo json_encode(array_merge(['ok'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function ntxc_student_id($raw){
 $raw=trim((string)$raw); if($raw==='')return '';
 if(filter_var($raw,FILTER_VALIDATE_URL)){$q=[];parse_str((string)parse_url($raw,PHP_URL_QUERY),$q);$id=trim((string)($q['id']??''));$t=trim((string)($q['t']??''));if($id!==''&&$t!==''&&student_card_is_valid_token($id,$t))return $id;}
 foreach(noitru_boarders_live() as $s){if($raw===(string)($s['id']??'')||strcasecmp($raw,(string)($s['code']??''))===0||strcasecmp($raw,student_card_public_code($s))===0)return (string)($s['id']??'');}
 return '';
}
$raw=trim((string)($_REQUEST['qr']??$_REQUEST['qr_value']??''));
$sid=ntxc_student_id($raw); if($sid==='')ntxc_json(false,'Không nhận dạng được mã QR thẻ học sinh.',[],422);
$student=null;foreach(noitru_boarders_live() as $s)if((string)($s['id']??'')===$sid){$student=$s;break;}
$rows=[];foreach(noitru_exits_all() as $r)if((string)($r['student_id']??'')===$sid)$rows[]=$r;
usort($rows,fn($a,$b)=>strcmp((string)($b['from_date']??''),(string)($a['from_date']??'')));
$now=time();$approved=[];$pending=[];$rejected=[];
foreach($rows as $r){$status=(string)($r['status']??'pending');$from=strtotime((string)($r['from_date']??''));$to=strtotime((string)($r['to_date']??''));$near=$from!==false&&$to!==false&&$now>=$from-6*3600&&$now<=$to+12*3600;if($status==='approved'&&$near)$approved[]=$r;elseif($status==='pending')$pending[]=$r;elseif($status==='rejected')$rejected[]=$r;}
$base=['student'=>['id'=>$sid,'name'=>(string)($student['name']??''),'class_name'=>(string)($student['class_name']??'')]];
if($approved){$r=$approved[0];ntxc_json(true,'Học sinh có đơn đã được duyệt.',array_merge($base,['state'=>'approved','request'=>$r,'found_url'=>BASE_URL.'noitru_exit_manager.php?view=check&found='.rawurlencode((string)$r['id'])]));}
if($pending){$r=$pending[0];$public='';$att=(string)($r['attachment']??'');if(str_starts_with($att,'gdrive:'))$public=BASE_URL.'public_ktx_exit_file.php?id='.rawurlencode(substr($att,7));ntxc_json(true,'CẢNH BÁO: Học sinh đã có đơn nhưng CHƯA ĐƯỢC DUYỆT.',array_merge($base,['state'=>'pending','request'=>$r,'public_file_url'=>$public]));}
if($rejected){$r=$rejected[0];ntxc_json(true,'Đơn gần nhất của học sinh đã bị từ chối.',array_merge($base,['state'=>'rejected','request'=>$r]));}
ntxc_json(true,'Học sinh chưa có đơn đăng ký ra/vào KTX.',array_merge($base,['state'=>'none']));
