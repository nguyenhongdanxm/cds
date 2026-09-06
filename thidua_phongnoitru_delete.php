<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/thidua_room_lock.php';
require_login();
require_module('thidua', 'view');
if (!can_perm_level('td.student_room_input', 'delete')) {http_response_code(403);exit('Tài khoản chưa có quyền xóa dữ liệu chấm phòng nội trú.');}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {http_response_code(405);exit('Method not allowed');}
$csrf=(string)($_POST['csrf']??'');$sessionCsrf=(string)($_SESSION['td_room_csrf']??'');if($sessionCsrf===''||!hash_equals($sessionCsrf,$csrf)){http_response_code(403);exit('Phiên làm việc không hợp lệ.');}
$dataFile=DATA_PATH.'/thidua_rooms.json';$data=load_json($dataFile,[]);if(!is_array($data))$data=[];$data=array_merge(['entries'=>[],'sessions'=>[],'skip_dates'=>[]],$data);$data['entries']=array_values(is_array($data['entries']??null)?$data['entries']:[]);$data['sessions']=array_values(is_array($data['sessions']??null)?$data['sessions']:[]);
$week=trim((string)($_POST['week']??''));$date=trim((string)($_POST['date']??''));$shift=trim((string)($_POST['shift']??''));$scope=trim((string)($_POST['scope']??'shift'));$dates=array_values(array_unique(array_filter(array_map('strval',(array)($_POST['dates']??[$date])),fn($value)=>preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))));$shifts=array_values(array_intersect(['sang','chieu'],array_map('strval',(array)($_POST['shifts']??[$shift]))));
if(!in_array($scope,['shift','day','bulk'],true)){http_response_code(400);exit('Phạm vi xóa không hợp lệ.');}
if(!$dates||($scope!=='bulk'&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$week))){http_response_code(400);exit('Ngày hoặc tuần không hợp lệ.');}
if($scope==='shift'&&!in_array($shift,['sang','chieu'],true)){http_response_code(400);exit('Buổi chấm không hợp lệ.');}
if($scope==='bulk'&&!$shifts){http_response_code(400);exit('Cần chọn ít nhất một buổi chấm.');}
foreach($dates as $targetDate)tdr_assert_date_editable($targetDate);$dateSet=array_fill_keys($dates,true);$shiftSet=array_fill_keys($scope==='day'?['sang','chieu']:$shifts,true);
$removedItems=0;foreach($data['entries'] as &$entry){if($scope!=='bulk'&&(string)($entry['week_start']??'')!==$week)continue;$items=array_values(is_array($entry['items']??null)?$entry['items']:[]);$kept=[];foreach($items as $item){$sameDate=isset($dateSet[(string)($item['date']??'')]);$itemShift=(string)($item['shift']??'sang');$remove=$sameDate&&isset($shiftSet[$itemShift]);if($remove)$removedItems++;else$kept[]=$item;}$entry['items']=$kept;}unset($entry);
$data['entries']=array_values(array_filter($data['entries'],fn($entry)=>!empty($entry['items'])));
$data['sessions']=array_values(array_filter($data['sessions'],function($s)use($week,$dateSet,$shiftSet,$scope){if($scope!=='bulk'&&(string)($s['week_start']??'')!==$week)return true;if(!isset($dateSet[(string)($s['date']??'')]))return true;return !isset($shiftSet[(string)($s['shift']??'')]);}));
if(!save_json($dataFile,$data)){http_response_code(500);exit('Không thể lưu dữ liệu sau khi xóa.');}
if($scope==='bulk')flash('Đã xóa '.$removedItems.' nội dung chấm của '.count($dates).' ngày đã chọn.','warning');elseif($scope==='day')flash('Đã xóa toàn bộ dữ liệu chấm ngày '.date('d/m/Y',strtotime($date)).'. Điểm đã được trả về trạng thái trước khi chấm ngày này.','warning');else flash('Đã xóa dữ liệu chấm buổi '.($shift==='chieu'?'Chiều':'Sáng').' ngày '.date('d/m/Y',strtotime($date)).'. Điểm đã được tính lại.','warning');
header('Location: '.BASE_URL.'thidua_phongnoitru.php?'.http_build_query(['tab'=>'input','week'=>$week,'day'=>$date]));exit;
