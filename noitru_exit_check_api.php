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
function ntxc_public_file(array $r): string {
 $att=(string)($r['attachment']??'');
 return str_starts_with($att,'gdrive:') ? BASE_URL.'public_ktx_exit_file.php?id='.rawurlencode(substr($att,7)) : '';
}
function ntxc_fmt_delta(int $seconds): string {
 $seconds=abs($seconds);$days=intdiv($seconds,86400);$seconds%=86400;$hours=intdiv($seconds,3600);$minutes=intdiv($seconds%3600,60);$parts=[];
 if($days)$parts[]=$days.' ngày';if($hours)$parts[]=$hours.' giờ';if($minutes||!$parts)$parts[]=$minutes.' phút';return implode(' ',array_slice($parts,0,2));
}
function ntxc_found_url(array $r): string {return BASE_URL.'noitru_exit_manager.php?view=check&found='.rawurlencode((string)($r['id']??''));}

$raw=trim((string)($_REQUEST['qr']??$_REQUEST['qr_value']??''));
$sid=ntxc_student_id($raw); if($sid==='')ntxc_json(false,'Không nhận dạng được mã QR thẻ học sinh.',[],422);
$student=null;foreach(noitru_boarders_live() as $s)if((string)($s['id']??'')===$sid){$student=$s;break;}
$rows=[];foreach(noitru_exits_all() as $r)if((string)($r['student_id']??'')===$sid)$rows[]=$r;
usort($rows,fn($a,$b)=>strcmp((string)($b['from_date']??''),(string)($a['from_date']??'')));
$now=time();$approved=[];$pending=[];$rejected=[];
foreach($rows as $r){
 $status=(string)($r['status']??'pending');
 if($status==='approved')$approved[]=$r;elseif($status==='pending')$pending[]=$r;elseif($status==='rejected')$rejected[]=$r;
}
$base=['student'=>['id'=>$sid,'name'=>(string)($student['name']??''),'class_name'=>(string)($student['class_name']??'')],'checked_at'=>date('c')];

/* Ưu tiên đơn đã duyệt gần thời điểm hiện tại; nếu đã hoàn tất RA + VÀO thì đơn hết tác dụng. */
if($approved){
 $chosen=null;$best=PHP_INT_MAX;
 foreach($approved as $r){
  $from=strtotime((string)($r['from_date']??''));$to=strtotime((string)($r['to_date']??''));if($from===false||$to===false)continue;
  $distance=$now<$from?$from-$now:($now>$to?$now-$to:0);
  if($distance<$best){$best=$distance;$chosen=$r;}
 }
 if($chosen){
  $r=$chosen;$from=strtotime((string)$r['from_date']);$to=strtotime((string)$r['to_date']);$public=ntxc_public_file($r);
  $common=array_merge($base,['request'=>$r,'public_file_url'=>$public,'found_url'=>ntxc_found_url($r),'scheduled_from'=>$r['from_date']??'','scheduled_to'=>$r['to_date']??'']);
  if(!empty($r['actual_exit_at'])&&!empty($r['actual_return_at'])){
   ntxc_json(true,'THÔNG TIN NÀY ĐÃ SỬ DỤNG: Học sinh đã được ghi nhận đủ RA và VÀO KTX. Đơn này đã hết tác dụng.',array_merge($common,['state'=>'used','can_confirm'=>false]));
  }
  if($now<$from){
   $delta=$from-$now;
   ntxc_json(true,'CẢNH BÁO: Quét thẻ SỚM HƠN thời gian được đăng ký '.$deltaText=ntxc_fmt_delta($delta).'. Hãy kiểm tra kỹ trước khi xác nhận.',array_merge($common,['state'=>'approved_early','can_confirm'=>true,'time_delta_seconds'=>-$delta,'time_delta_text'=>ntxc_fmt_delta($delta)]));
  }
  if($now>$to){
   $delta=$now-$to;
   $phase=!empty($r['actual_exit_at'])?'Học sinh đang trở vào MUỘN hơn thời gian đăng ký ':'Thời điểm quét MUỘN hơn thời gian hiệu lực của đơn ';
   ntxc_json(true,'CẢNH BÁO: '.$phase.ntxc_fmt_delta($delta).'. Hãy kiểm tra kỹ trước khi xác nhận.',array_merge($common,['state'=>'approved_late','can_confirm'=>true,'time_delta_seconds'=>$delta,'time_delta_text'=>ntxc_fmt_delta($delta)]));
  }
  ntxc_json(true,'Học sinh có đơn đã được duyệt và đang trong đúng khoảng thời gian đăng ký.',array_merge($common,['state'=>'approved','can_confirm'=>true]));
 }
}

if($pending){$r=$pending[0];ntxc_json(true,'CẢNH BÁO: Học sinh đã có đơn nhưng CHƯA ĐƯỢC DUYỆT.',array_merge($base,['state'=>'pending','request'=>$r,'public_file_url'=>ntxc_public_file($r),'can_confirm'=>false]));}
if($rejected){$r=$rejected[0];ntxc_json(true,'Đơn gần nhất của học sinh đã bị từ chối.',array_merge($base,['state'=>'rejected','request'=>$r,'public_file_url'=>ntxc_public_file($r),'can_confirm'=>false]));}
ntxc_json(true,'Học sinh chưa có đơn đăng ký ra/vào KTX.',array_merge($base,['state'=>'none','can_confirm'=>false]));
