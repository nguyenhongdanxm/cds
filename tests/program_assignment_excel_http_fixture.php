<?php
// Isolated fixture: no application bootstrap, accounts, or production data paths.
require __DIR__.'/../includes/json_store.php';require __DIR__.'/../includes/program_assignment.php';require __DIR__.'/../includes/program_assignment_excel.php';require __DIR__.'/../includes/pccm_release.php';require __DIR__.'/../chuyenmon/includes/program_excel_template.php';
$dir=(string)getenv('GDP_EXCEL_TEST_DIR');if(!str_starts_with($dir,sys_get_temp_dir().'/cds-gdp-excel-http-'))throw new RuntimeException('Isolated test directory required');
define('DATA_PATH',$dir.'/chuyenmon/data');define('BASE_PATH',$dir.'/chuyenmon');define('BASE_URL','/');
function load_json($f,$d=[]){return cds_json_load($f,$d);}
function get_active_version_id(){return 'v1';}
function get_classes(){return ['8A','8B'];}
function get_teachers(){return ['Giáo viên A','Giáo viên B'];}
function get_subjects(){return ['GDĐP'=>[]];}
function get_version($v){return ['name'=>'Bản thử'];}
function cds_can_feature($c,$l){return empty($_SESSION['test_readonly']);}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function flash($m,$t){$_SESSION['test_flash']=$m;}
if(PHP_SAPI==='cli'){
 mkdir(DATA_PATH,0700,true);mkdir($dir.'/data',0700,true);mkdir($dir.'/sessions',0700,true);
 cds_json_save($dir.'/data/teachers.json',[['id'=>'gv_a','code'=>'02001','name'=>'Giáo viên A'],['id'=>'gv_b','code'=>'02002','name'=>'Giáo viên B']]);
 cds_json_save(DATA_PATH.'/active_version.json',['id'=>'v1']);$configs=[];foreach(get_classes()as$c)$configs[$c]=['subject'=>'GDĐP','ranges'=>[['teacher'=>'Giáo viên A','from'=>1,'to'=>35]]];cds_json_save(gdp_file(DATA_PATH,'v1'),$configs);
 $context=gdp_excel_context();$context['classes']=['8A'];gdp_excel_template($context,['8A'=>['subject'=>'GDĐP','ranges'=>[['teacher'=>'Giáo viên B','from'=>1,'to'=>7],['teacher'=>'Giáo viên A','from'=>8,'to'=>35]]]],'Bản thử',$dir.'/valid.xlsx');exit;
}
ini_set('session.save_path',$dir.'/sessions');session_start();if(empty($_SESSION['gdp_csrf']))$_SESSION['gdp_csrf']='test-token';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/state'){header('Content-Type: application/json');echo json_encode(gdp_load(DATA_PATH,'v1'));return;}
if($path==='/readonly'){$_SESSION['test_readonly']=true;echo 'readonly';return;}
if($path==='/program_assignment_template.php'){
 if(!cds_can_feature('cm.pccm','edit')){http_response_code(403);return;}
 $file=$dir.'/download.xlsx';gdp_excel_template(gdp_excel_context(),gdp_load(DATA_PATH,'v1'),'Bản thử',$file);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="phan-cong-GDDP-1-35.xlsx"');readfile($file);return;
}
require __DIR__.'/../chuyenmon/includes/pccm_release_controller.php';
require __DIR__.'/../chuyenmon/includes/program_assignment_controller.php';
?><!doctype html><html><head><meta charset="utf-8"><base href="/"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:Arial;margin:12px}.table-responsive{overflow:auto}table{border-collapse:collapse}td,th{padding:5px;text-align:left}input,select{max-width:100%}</style></head><body><?php require __DIR__.'/../chuyenmon/includes/program_assignment_view.php'; ?></body></html>
