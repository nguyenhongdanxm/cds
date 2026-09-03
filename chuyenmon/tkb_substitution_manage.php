<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/timetable_store.php';
header('Content-Type: application/json; charset=utf-8');

$user = cds_user();
if (!$user) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Chưa đăng nhập.'], JSON_UNESCAPED_UNICODE); exit; }
$isAdmin = (($user['role'] ?? '') === 'admin');
$canApprove = $isAdmin || cds_can_feature('cm.pccm','edit');
$canDelete = $isAdmin || cds_can_feature('cm.pccm','delete') || cds_can_feature('cm.pccm','edit');
if (!$canApprove && !$canDelete) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Bạn chưa có quyền quản lý đăng ký dạy thay - bù.'], JSON_UNESCAPED_UNICODE); exit; }

function tkbm_is_makeup(array $row): bool {
    $kind=tkb_key((string)($row['registration_type']??$row['type']??$row['kind']??''));
    return str_contains($kind,'makeup')||str_contains($kind,'daybu');
}
function tkbm_same_class(string $a,string $b): bool { return tkb_key($a)!==''&&tkb_key($a)===tkb_key($b); }
function tkbm_makeup_conflict(array $row): string {
    $week=tkb_week_by_id((string)($row['week_id']??''));
    if(!$week)return 'Không tìm thấy TKB tuần của đăng ký.';
    $teacher=(string)($row['substitute_teacher']??'');$class=(string)($row['class']??'');$day=(int)($row['day']??0);$session=(string)($row['session']??'');$period=(int)($row['period']??0);$date=(string)($row['date']??'');$id=(string)($row['id']??'');
    if(tkb_teacher_busy($week,$teacher,$day,$session,$period,$date))return 'Giáo viên '.$teacher.' đã có lịch ở thời gian đăng ký.';
    foreach(tkb_resolved_slots($week)as$s){$slotClass=(string)($s['class']?:($s['class_raw']??''));if(tkbm_same_class($slotClass,$class)&&(int)$s['day']===$day&&(string)$s['session']===$session&&(int)$s['period']===$period)return 'Lớp '.$class.' đã có lịch học ở thời gian đăng ký.';}
    foreach(tkb_substitutions()as$r){if((string)($r['id']??'')===$id||tkb_substitution_status($r)!=='approved')continue;if((string)($r['date']??'')!==$date||(string)($r['session']??'')!==$session||(int)($r['period']??0)!==$period)continue;if(tkb_key((string)($r['substitute_teacher']??''))===tkb_key($teacher))return 'Giáo viên '.$teacher.' đã có lịch dạy thay/bù khác ở thời gian đăng ký.';if(tkbm_is_makeup($r)&&tkbm_same_class((string)($r['class']??''),$class))return 'Lớp '.$class.' đã có lịch dạy bù khác ở thời gian đăng ký.';}
    return '';
}

if (empty($_SESSION['tkb_sub_manage_csrf'])) $_SESSION['tkb_sub_manage_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['tkb_sub_manage_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $scope=(string)($_GET['scope']??'week');
    $date=(string)($_GET['date']??date('Y-m-d'));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))$date=date('Y-m-d');
    if($scope==='today'){$from=$to=$date;}else{$dow=(int)date('N',strtotime($date));$from=date('Y-m-d',strtotime($date.' -'.($dow-1).' days'));$to=date('Y-m-d',strtotime($from.' +6 days'));}
    $rows = array_values(array_filter(tkb_substitutions(),static fn($r)=>(string)($r['date']??'')>=$from && (string)($r['date']??'')<=$to));
    usort($rows, static function($a,$b){$dateCmp=strcmp((string)($a['date']??''),(string)($b['date']??''));if($dateCmp!==0)return$dateCmp;$sessionCmp=strcmp((string)($a['session']??''),(string)($b['session']??''));if($sessionCmp!==0)return$sessionCmp;return((int)($a['period']??0))<=>((int)($b['period']??0));});
    $out=[];foreach($rows as $row){$makeup=tkbm_is_makeup($row);$out[]=['id'=>(string)($row['id']??''),'date'=>(string)($row['date']??''),'session'=>(string)($row['session']??''),'period'=>(int)($row['period']??0),'class'=>(string)($row['class']??''),'subject'=>(string)($row['subject']??''),'absent_teacher'=>$makeup?'Dạy bù':(string)($row['absent_teacher']??''),'substitute_teacher'=>(string)($row['substitute_teacher']??''),'status'=>(string)($row['status']??'approved'),'kind'=>$makeup?'makeup':'substitution'];}
    echo json_encode(['ok'=>true,'csrf'=>$csrf,'rows'=>$out,'scope'=>$scope,'from'=>$from,'to'=>$to,'can_approve'=>$canApprove,'can_delete'=>$canDelete], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
if (!hash_equals($csrf,(string)($_POST['csrf']??''))) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ.'], JSON_UNESCAPED_UNICODE); exit; }
$action=(string)($_POST['action']??'');

if($action==='approve_many'){
    if(!$canApprove){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Bạn chưa có quyền duyệt.'],JSON_UNESCAPED_UNICODE);exit;}
    $ids=array_values(array_unique(array_filter(array_map('strval',(array)($_POST['ids']??[])))));if(!$ids){echo json_encode(['ok'=>false,'message'=>'Chưa chọn đăng ký cần duyệt.'],JSON_UNESCAPED_UNICODE);exit;}
    $map=array_fill_keys($ids,true);$rows=tkb_substitutions();$count=0;$blocked=[];
    foreach($rows as &$row){if(!isset($map[(string)($row['id']??'')]))continue;if(tkbm_is_makeup($row)){$conflict=tkbm_makeup_conflict($row);if($conflict!==''){$blocked[]=$conflict;continue;}}$row['status']='approved';$row['approved_at']=date('c');$row['approved_by']=(string)($user['name']??'');$row['updated_at']=date('c');$count++;}unset($row);
    if($count&&tkb_save(TKB_SUBSTITUTIONS_FILE,$rows)){$msg='Đã duyệt '.$count.' đăng ký dạy thay/bù.';if($blocked)$msg.=' Có '.count($blocked).' đăng ký dạy bù chưa duyệt do xung đột lịch.';echo json_encode(['ok'=>true,'approved'=>$count,'blocked'=>count($blocked),'message'=>$msg],JSON_UNESCAPED_UNICODE);exit;}
    echo json_encode(['ok'=>false,'message'=>$blocked?('Chưa thể duyệt: '.implode(' ',array_slice($blocked,0,3))):'Không duyệt được đăng ký đã chọn.'],JSON_UNESCAPED_UNICODE);exit;
}

if ($action === 'delete_many') {
    if(!$canDelete){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Bạn chưa có quyền xóa.'],JSON_UNESCAPED_UNICODE);exit;}
    $ids=array_values(array_unique(array_filter(array_map('strval',(array)($_POST['ids']??[])))));if(!$ids){echo json_encode(['ok'=>false,'message'=>'Chưa chọn đăng ký dạy thay/bù cần xóa.'],JSON_UNESCAPED_UNICODE);exit;}
    $idMap=array_fill_keys($ids,true);$rows=tkb_substitutions();$before=count($rows);$rows=array_values(array_filter($rows,static fn($row)=>!isset($idMap[(string)($row['id']??'')])));$deleted=$before-count($rows);
    if($deleted<=0){echo json_encode(['ok'=>false,'message'=>'Không tìm thấy đăng ký đã chọn.'],JSON_UNESCAPED_UNICODE);exit;}
    if(!tkb_save(TKB_SUBSTITUTIONS_FILE,$rows)){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Không lưu được dữ liệu sau khi xóa.'],JSON_UNESCAPED_UNICODE);exit;}
    echo json_encode(['ok'=>true,'deleted'=>$deleted,'message'=>'Đã xóa '.$deleted.' đăng ký dạy thay/bù. Các hiển thị liên quan trên TKB, Sổ đầu bài và Tổng quan cũng được gỡ theo.'],JSON_UNESCAPED_UNICODE);exit;
}

http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Thao tác không hợp lệ.'],JSON_UNESCAPED_UNICODE);
