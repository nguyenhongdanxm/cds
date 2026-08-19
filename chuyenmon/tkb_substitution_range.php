<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/timetable_store.php';
header('Content-Type: application/json; charset=utf-8');

$user = cds_user();
if (!$user) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Chưa đăng nhập.'], JSON_UNESCAPED_UNICODE); exit; }
$isAdmin = (($user['role'] ?? '') === 'admin');
$canApprove = $isAdmin || cds_can_feature('cm.pccm','edit');
$teachers = array_values(array_filter(array_map('strval',(array)load_json(TEACHERS_FILE,[]))));
$teachers = sort_teachers_by_ten($teachers);
$selfTeacher = trim((string)($user['teacher_name'] ?? $user['name'] ?? ''));
if ($selfTeacher !== '' && !in_array($selfTeacher,$teachers,true)) { foreach ($teachers as $t) if (tkb_key($t) === tkb_key($selfTeacher)) { $selfTeacher=$t; break; } }
if(empty($_SESSION['tkb_range_csrf']))$_SESSION['tkb_range_csrf']=bin2hex(random_bytes(24));

$from = trim((string)($_GET['from'] ?? date('Y-m-d')));$to = trim((string)($_GET['to'] ?? $from));$absent = trim((string)($_GET['absent_teacher'] ?? $selfTeacher));
if (!$canApprove && $selfTeacher !== '') $absent = $selfTeacher;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) { echo json_encode(['ok'=>false,'message'=>'Khoảng ngày không hợp lệ.'],JSON_UNESCAPED_UNICODE); exit; }
if ($to < $from) [$from,$to]=[$to,$from];$maxTo = date('Y-m-d', strtotime($from.' +13 days'));if ($to > $maxTo) $to = $maxTo;
if ($absent === '') { echo json_encode(['ok'=>false,'message'=>'Hãy chọn giáo viên nghỉ.'],JSON_UNESCAPED_UNICODE); exit; }

$weeks = tkb_weeks();$subs=tkb_substitutions();$out=[];
for ($ts=strtotime($from),$end=strtotime($to); $ts!==false && $ts<=$end; $ts+=86400) {
    $date=date('Y-m-d',$ts);$week=null;foreach ($weeks as $w) {$ws=(string)($w['start_date']??'');$we=(string)($w['end_date']??'');if ($ws!==''&&$we!==''&&$date>=$ws&&$date<=$we){$week=$w;break;}}
    if (!$week) continue;$day=(int)date('N',$ts)+1;
    foreach (tkb_resolved_slots($week) as $slot) {
        if (($slot['teacher']??'')!==$absent || (int)($slot['day']??0)!==$day) continue;$candidates=[];
        foreach (tkb_substitute_candidates($teachers,$slot,$week,$date,$absent) as $c) $candidates[]=['teacher'=>$c['teacher'],'busy'=>(bool)$c['busy'],'labels'=>array_values((array)$c['labels'])];
        $saved=null;foreach ($subs as $r) if (($r['date']??'')===$date&&($r['slot_key']??'')===tkb_slot_key($slot)&&($r['absent_teacher']??'')===$absent){$saved=$r;break;}
        $out[]=['date'=>$date,'week_id'=>(string)($week['id']??''),'week_label'=>(string)($week['label']??''),'slot_key'=>tkb_slot_key($slot),'session'=>(string)($slot['session']??''),'period'=>(int)($slot['period']??0),'class'=>(string)($slot['class']?:($slot['class_raw']??'')),'subject'=>(string)($slot['subject']??''),'candidates'=>$candidates,'saved'=>$saved?['id'=>(string)($saved['id']??''),'substitute_teacher'=>(string)($saved['substitute_teacher']??''),'status'=>(string)($saved['status']??'approved')]:null];
    }
}
echo json_encode(['ok'=>true,'csrf'=>(string)$_SESSION['tkb_range_csrf'],'from'=>$from,'to'=>$to,'absent_teacher'=>$absent,'can_approve'=>$canApprove,'rows'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
