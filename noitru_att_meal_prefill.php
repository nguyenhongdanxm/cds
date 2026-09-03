<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_once __DIR__ . '/includes/noitru_att_shifts.php';
require_login();
require_perm('nt.diemdanh');
header('Content-Type: application/json; charset=utf-8');

$date=trim((string)($_GET['date']??''));$shiftId=trim((string)($_GET['shift']??''));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$shiftId===''){echo json_encode(['ok'=>false,'applied'=>false,'message'=>'Ngày hoặc buổi điểm danh không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
if(function_exists('noitru_att_report_for')&&noitru_att_report_for($date,$shiftId)){echo json_encode(['ok'=>true,'applied'=>false,'reason'=>'attendance_saved','student_ids'=>[]],JSON_UNESCAPED_UNICODE);exit;}
$shiftLabel='';foreach(noitru_att_shifts_all() as $shift){if(($shift['id']??'')===$shiftId){$shiftLabel=trim((string)($shift['label']??''));break;}}
$meal=function_exists('noitru_att_meal_for_shift')?noitru_att_meal_for_shift($shiftId,$shiftLabel):'';
if($meal===''){echo json_encode(['ok'=>true,'applied'=>false,'reason'=>'not_meal_attendance','shift_label'=>$shiftLabel,'student_ids'=>[]],JSON_UNESCAPED_UNICODE);exit;}
$state=noitru_meal_state($date,$meal);if(($state['status']??'open')!=='locked'){echo json_encode(['ok'=>true,'applied'=>false,'reason'=>'meal_not_locked','meal'=>$meal,'date'=>$date,'shift_label'=>$shiftLabel,'deadline'=>$state['deadline']??'','student_ids'=>[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$reportedClasses=[];foreach(noitru_meal_reports_for_date($date) as $report){if(($report['meal']??'')!==$meal)continue;if(($report['status']??'submitted')!=='submitted')continue;$className=trim((string)($report['class_name']??''));if($className!=='')$reportedClasses[$className]=true;}
$studentClass=[];foreach(noitru_boarders_live() as $student){$sid=(string)($student['id']??'');if($sid!=='')$studentClass[$sid]=trim((string)($student['class_name']??''));}
$ids=[];foreach(noitru_meals_for_date($date) as $sid=>$mealRow){$sid=(string)$sid;$className=$studentClass[$sid]??'';if($className===''||empty($reportedClasses[$className]))continue;if(($mealRow[$meal]??'yes')==='no')$ids[]=$sid;}
$ids=array_values(array_unique($ids));$labels=['sang'=>'Ăn sáng','trua'=>'Ăn trưa','toi'=>'Ăn tối'];
echo json_encode(['ok'=>true,'applied'=>true,'date'=>$date,'meal'=>$meal,'meal_label'=>$labels[$meal]??$meal,'shift_label'=>$shiftLabel,'reported_class_count'=>count($reportedClasses),'student_ids'=>$ids,'count'=>count($ids)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
