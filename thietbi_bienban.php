<?php
require_once __DIR__.'/includes/auth.php';
require_login();
$user=current_user()??[];
if(($user['role']??'')!=='admin'&&!can_perm_level('tb.baoduong','view')){http_response_code(403);exit('Không có quyền xem biên bản kiểm tra thiết bị.');}
require_once __DIR__.'/includes/library_maintenance.php';
$data=load_json(DATA_PATH.'/library_equipment.json',['maintenance'=>[],'equipment'=>[]]);
$id=(string)($_GET['id']??'');$record=null;
foreach((array)($data['maintenance']??[]) as $row)if((string)($row['id']??'')===$id){$record=$row;break;}
if(!$record){http_response_code(404);exit('Không tìm thấy hồ sơ kiểm tra.');}
$labels=lib_mt_labels($record,(array)($data['equipment']??[]));
header('Cache-Control: private, no-store');
require __DIR__.'/includes/library_maintenance_report.php';
