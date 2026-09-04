<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/lesson_book_store.php';
require_login();
$from=(string)($_GET['stats_from']??'');$to=(string)($_GET['stats_to']??'');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){http_response_code(422);exit('Khoảng ngày không hợp lệ.');}
$completion=in_array((string)($_GET['stats_completion']??'all'),['all','completed','incomplete','saved_unsigned','not_saved'],true)?(string)$_GET['stats_completion']:'all';
$rows=lb_stat_rows($from,$to,trim((string)($_GET['stats_teacher']??'')),trim((string)($_GET['stats_subject']??'')),trim((string)($_GET['stats_class']??'')),$completion);
$labels=['pending'=>'Chưa ghi','taught'=>'Đã dạy','substitute'=>'Dạy thay','makeup'=>'Dạy bù','online'=>'Trực tuyến','holiday'=>'Nghỉ lễ','teacher_absent'=>'GV nghỉ','class_absent'=>'Lớp nghỉ','postponed'=>'Hoãn','cancelled'=>'Hủy'];
$filename='Thong-ke-So-dau-bai-'.$from.'-'.$to.'.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
$out=fopen('php://output','w');
fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['THONG KE SO DAU BAI',$from,$to],';');
fputcsv($out,['Nhom','Ten','Tong TKB','Hoan thanh','Chua HT','Da day','Day thay','Day bu','Truc tuyen','Nghi/hoan/huy','Nghi le','GV nghi','Lop nghi','Hoan','Huy','Chua ghi','Ty le %'],';');
foreach(['TONG'=>[lb_stat_totals($rows)],'GIAO VIEN'=>lb_stat_group($rows,'actual_teacher'),'MON'=>lb_stat_group($rows,'subject'),'LOP'=>lb_stat_group($rows,'class'),'TUAN'=>lb_stat_group($rows,'week_label')] as$group=>$list){
 foreach($list as$g){
  $rate=$g['scheduled']?round($g['completed']*100/$g['scheduled'],1):0;
  fputcsv($out,[$group,$g['name'],$g['scheduled'],$g['completed'],$g['incomplete'],$g['taught']??0,$g['substitute']??0,$g['makeup']??0,$g['online']??0,$g['off']??0,$g['holiday']??0,$g['teacher_absent']??0,$g['class_absent']??0,$g['postponed']??0,$g['cancelled']??0,$g['pending']??0,$rate],';');
 }
}
fputcsv($out,[],';');
fputcsv($out,['CHI TIET TUNG TIET'],';');
fputcsv($out,['Ngay','Thu','Buoi','Tiet TKB','Lop','Mon','GV TKB','GV thuc te','Tiet PPCT','Dau bai','Trang thai','Da ky','Nguoi ky'],';');
foreach($rows as$r){
 $st=lb_stat_status($r);
 fputcsv($out,[
  (string)($r['date']??''),
  isset($r['date'])?date('N',strtotime($r['date']))+1:'',
  (string)($r['session']??''),
  (string)($r['period']??''),
  (string)($r['class']??''),
  (string)($r['subject']??''),
  (string)($r['scheduled_teacher']??''),
  (string)($r['actual_teacher']??''),
  (string)($r['ppct_period']??''),
  (string)($r['lesson_title']??''),
  $labels[$st]??$st,
  !empty($r['signed_at'])?'Da ky':'Chua ky',
  (string)($r['signed_by']??''),
 ],';');
}
fclose($out);
exit;
