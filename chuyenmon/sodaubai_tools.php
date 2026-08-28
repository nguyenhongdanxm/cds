<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lesson_book_store.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function lbx_json(array $data, int $status=200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function lbx_settings(): array {
    $s = lb_settings();
    $defaults = ['Tốt'=>10,'Khá'=>8,'Trung bình'=>6,'Yếu'=>4];
    $points = is_array($s['rating_points'] ?? null) ? $s['rating_points'] : [];
    foreach ($defaults as $k=>$v) $points[$k] = isset($points[$k]) && is_numeric($points[$k]) ? round((float)$points[$k],2) : $v;
    return ['rating_points'=>$points];
}
function lbx_saved_rows_for_week(array $week): array {
    $out=[];
    foreach (lb_rows(LB_RECORDS_FILE) as $r) if (($r['week_key'] ?? '') === ($week['key'] ?? '')) $out[]=$r;
    return $out;
}
function lbx_weekly_class_scores(array $week): array {
    $points=lbx_settings()['rating_points']; $groups=[];
    foreach (lbx_saved_rows_for_week($week) as $r) {
        $class=trim((string)($r['class']??'')); $rating=(string)($r['rating']??'');
        if ($class==='' || !array_key_exists($rating,$points)) continue;
        if (!isset($groups[$class])) $groups[$class]=['class'=>$class,'rated_lessons'=>0,'total_points'=>0.0,'ratings'=>['Tốt'=>0,'Khá'=>0,'Trung bình'=>0,'Yếu'=>0]];
        $groups[$class]['rated_lessons']++; $groups[$class]['total_points']+=(float)$points[$rating];
        if (isset($groups[$class]['ratings'][$rating])) $groups[$class]['ratings'][$rating]++;
    }
    foreach ($groups as &$g) $g['average']=$g['rated_lessons'] ? round($g['total_points']/$g['rated_lessons'],2) : 0; unset($g);
    $out=array_values($groups); usort($out,fn($a,$b)=>$b['average']<=>$a['average'] ?: strnatcasecmp($a['class'],$b['class']));
    foreach ($out as $i=>&$g) $g['rank']=$i+1; unset($g); return $out;
}

$action=(string)($_REQUEST['action']??'summary');
$week=lb_week((string)($_REQUEST['week']??''));
if (!$week) lbx_json(['ok'=>false,'message'=>'Không tìm thấy tuần học.'],400);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $csrf=(string)($_POST['csrf']??'');
    if (empty($_SESSION['lb_csrf']) || !hash_equals((string)$_SESSION['lb_csrf'],$csrf)) lbx_json(['ok'=>false,'message'=>'Phiên thao tác hết hạn. Hãy tải lại trang.'],403);
    if (!lb_is_admin()) lbx_json(['ok'=>false,'message'=>'Chỉ quản trị hệ thống được thực hiện thao tác này.'],403);

    if ($action==='save_rating_points') {
        $labels=['Tốt','Khá','Trung bình','Yếu']; $points=[];
        foreach ($labels as $label) {
            $key=['Tốt'=>'tot','Khá'=>'kha','Trung bình'=>'trungbinh','Yếu'=>'yeu'][$label];
            $v=$_POST[$key]??null;
            if (!is_numeric($v) || (float)$v<0 || (float)$v>100) lbx_json(['ok'=>false,'message'=>'Điểm quy đổi phải là số từ 0 đến 100.'],400);
            $points[$label]=round((float)$v,2);
        }
        $s=lb_settings(); $s['rating_points']=$points;
        if (!save_json(LB_SETTINGS_FILE,$s)) lbx_json(['ok'=>false,'message'=>'Không lưu được cấu hình điểm quy đổi.'],500);
        lb_audit('save_rating_points',['rating_points'=>$points]);
        lbx_json(['ok'=>true,'message'=>'Đã lưu điểm quy đổi xếp loại tiết học.','settings'=>lbx_settings(),'classes'=>lbx_weekly_class_scores($week)]);
    }

    if ($action==='delete_record') {
        $slotId=trim((string)($_POST['slot_id']??'')); if ($slotId==='') lbx_json(['ok'=>false,'message'=>'Thiếu mã tiết học.'],400);
        $rows=lb_rows(LB_RECORDS_FILE); $kept=[]; $deleted=null;
        foreach ($rows as $r) { if (($r['slot_id']??'')===$slotId && $deleted===null) {$deleted=$r; continue;} $kept[]=$r; }
        if (!$deleted) lbx_json(['ok'=>false,'message'=>'Tiết này chưa có dữ liệu đã lưu để xóa.'],404);
        $audit=['slot_id'=>$slotId,'date'=>$deleted['date']??'','class'=>$deleted['class']??'','subject'=>$deleted['subject']??'','period'=>$deleted['period']??'','teacher'=>$deleted['actual_teacher']??($deleted['scheduled_teacher']??''),'was_signed'=>!empty($deleted['signed_at'])];
        if (!lb_write(LB_RECORDS_FILE,$kept)) lbx_json(['ok'=>false,'message'=>'Không xóa được dữ liệu tiết học.'],500);
        lb_audit('delete_record',$audit);
        lbx_json(['ok'=>true,'message'=>'Đã xóa dữ liệu đã lưu của tiết học. Tiết theo TKB vẫn được giữ để có thể ghi lại.']);
    }
    lbx_json(['ok'=>false,'message'=>'Thao tác không hợp lệ.'],400);
}

$saved=[]; foreach (lbx_saved_rows_for_week($week) as $r) if (!empty($r['slot_id'])) $saved[(string)$r['slot_id']]=['signed'=>!empty($r['signed_at']),'rating'=>$r['rating']??''];
lbx_json(['ok'=>true,'admin'=>lb_is_admin(),'week'=>['key'=>$week['key'],'label'=>$week['label']],'settings'=>lbx_settings(),'classes'=>lbx_weekly_class_scores($week),'saved'=>$saved]);
