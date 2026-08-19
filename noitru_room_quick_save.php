<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/noitru_assignment_store.php';
require_login();require_perm_level('nt.chiaphong','edit');header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
$data=noitru_assignments_data();$room=trim((string)($_POST['room']??''));$gender=(string)($_POST['gender']??'');$capacity=max(1,min(100,(int)($_POST['capacity']??1)));$by=(string)((current_user()['name']??current_user()['username']??''));
if($room===''||!in_array($gender,['Nam','Nữ','Linh hoạt'],true)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Dữ liệu phòng không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
if(!in_array($room,(array)($data['room_names']??[]),true))$data['room_names'][]=$room;$data['room_capacities'][$room]=$capacity;$data['room_genders'][$room]=$gender;noitru_assignments_save($data,$by);echo json_encode(['ok'=>true,'message'=>'Đã tự lưu'],JSON_UNESCAPED_UNICODE);