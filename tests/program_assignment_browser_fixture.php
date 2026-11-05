<?php
require __DIR__.'/../includes/program_assignment.php';
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function get_active_version_id(){return 'v1';}
function load_json($f,$d=[]){return [];}
function get_classes(){return ['8A','8B','9A'];}
function get_teachers(){return ['Giáo viên A','Giáo viên B'];}
function get_subjects(){return ['GDĐP'=>[],'Toán học'=>[]];}
function get_version($v){return ['name'=>'Phân công thử nghiệm'];}
define('DATA_PATH','fixture');define('BASE_URL','/chuyenmon/');$_SESSION=['gdp_csrf'=>'fixture'];
if(in_array('--empty-error',$argv??[],true)){$GLOBALS['gdp_form_error']='Dữ liệu vừa thay đổi, hãy tải lại trang.';$_POST=['classes'=>['8A']];}
ob_start();require __DIR__.'/../chuyenmon/includes/program_assignment_view.php';$body=ob_get_clean();
file_put_contents('/tmp/gdp-input.html','<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://gdp.test/bootstrap.css"></head><body><main class="container py-3">'.$body.'</main></body></html>');
echo "PASS real input form fixture rendered without application data\n";
