<?php
$dir=(string)getenv('TH_TEST_DIR');if(!str_starts_with($dir,sys_get_temp_dir().'/cds-hours-http-'))throw new RuntimeException('Private test directory required');$root=dirname(__DIR__);
if(PHP_SAPI==='cli'){
 mkdir($dir.'/includes',0700,true);mkdir($dir.'/data',0700,true);mkdir($dir.'/sessions',0700,true);
 foreach(['gio_day.php','gio_day_xuat.php']as$f)copy($root.'/chuyenmon/'.$f,$dir.'/'.$f);
 foreach(glob($root.'/chuyenmon/includes/teaching_hours*.php')as$f)copy($f,$dir.'/includes/'.basename($f));copy($root.'/chuyenmon/includes/lesson_book_excel.php',$dir.'/includes/lesson_book_excel.php');
 $stub= <<<'STUB'
<?php
require_once '__ROOT__/includes/json_store.php';require_once '__ROOT__/tests/support/load_functions.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
load_functions('__ROOT__/chuyenmon/includes/functions.php',['require_login','cds_current_page_feature','is_logged_in']);
define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');define('SCHOOL_NAME','Trường thử nghiệm');
function cds_user(){return $_SESSION['user'];}
function cds_user_has_group($u,$g){return in_array($g,$u['groups']??[],true);}
function cds_can_feature($c,$l){return $l==='view'||in_array($_SESSION['user']['role'],['admin','bgh'],true)&&$c==='cm.pccm';}
function cds_is_viewer($u){return $u['role']==='viewer';}
function load_json($f,$d=[]){return cds_json_load($f,$d);}
function get_active_version_id(){return 'v1';}
function get_versions(){return [['id'=>'v1','name'=>'Bản thử']];}
function get_version($id){return $id==='v1'?['id'=>'v1','name'=>'Bản thử']:null;}
function role_assignments_file($v){return DATA_PATH.'/roles_'.$v.'.json';}
function get_roles(){return [['name'=>'Chủ nhiệm','periods'=>4],['name'=>'Tổ trưởng','periods'=>3]];}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
STUB;
 file_put_contents($dir.'/includes/functions.php',str_replace('__ROOT__',$root,$stub));
 file_put_contents($dir.'/includes/lesson_book_store.php', <<<'STUB'
<?php
function lb_user(){return $_SESSION['user'];}
function lb_is_management(){return in_array(lb_user()['role'],['admin','bgh'],true);}
function lb_is_leader(){return lb_user()['role']==='totruong';}
function lb_stat_leader_group(){return 'Tổ 1';}
function lb_teacher_name(){return lb_user()['teacher_name']??'Admin';}
function lb_stat_staff_rows(){return load_json(DATA_PATH.'/staff.json',[]);}
function cds_lb_read_effective(){return false;}
function lb_rows($f){return load_json($f,[]);}
define('LB_RECORDS_FILE',DATA_PATH.'/records.json');
STUB);
 file_put_contents($dir.'/includes/header.php','<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link href="/bootstrap.css" rel="stylesheet"><title><?=e($page_title)?></title></head><body><main class="container p-2">');file_put_contents($dir.'/includes/footer.php','</main></body></html>');
 require $root.'/includes/json_store.php';
 cds_json_save($dir.'/data/staff.json',[['id'=>'gv1','code'=>'0201','name'=>'Giáo viên A','to_chuyen_mon'=>'Tổ 1'],['id'=>'gv2','code'=>'0202','name'=>'Giáo viên B','to_chuyen_mon'=>'Tổ 2']]);
 $rows=[];foreach(['A','B']as$i=>$name)$rows[]=['slot_id'=>'s'.$name,'signed_by'=>'Giáo viên '.$name,'actual_teacher'=>'Giáo viên '.$name,'date'=>'2026-09-10','signed_at'=>'2026-10-01','period'=>$i+1,'class'=>'6A','subject'=>'Toán','status'=>'taught','actual_periods'=>1,'ppct_period'=>$i+1,'session'=>'Sáng','lesson_title'=>'Bài thử'];$rows[]=array_merge($rows[0],['slot_id'=>'off','status'=>'holiday','period'=>4]);cds_json_save($dir.'/data/records.json',$rows);cds_json_save($dir.'/data/roles_v1.json',[['teacher'=>'Giáo viên A','role'=>'Chủ nhiệm','class'=>'6A','periods'=>4],['teacher'=>'Giáo viên B','role'=>'Tổ trưởng','class'=>'','periods'=>3]]);exit;
}
ini_set('session.save_path',$dir.'/sessions');session_start();$_SESSION['user']=$_SESSION['user']??['role'=>'admin','name'=>'Admin','teacher_name'=>'Admin'];$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$_SERVER['PHP_SELF']=$path;
if($path==='/as'){$role=(string)($_GET['role']??'admin');$_SESSION['user']=['role'=>$role,'teacher_name'=>'Giáo viên A','teacher_id'=>'gv1','groups'=>$role==='totruong'?['totruong']:[]];echo 'ok';return;}
if($path==='/state'){header('Content-Type: application/json');require_once $dir.'/includes/teaching_hours.php';echo json_encode(th_read($dir.'/data/teaching_hours.php'));return;}
if(!in_array($path,['/gio_day.php','/gio_day_xuat.php'],true)){http_response_code(404);return;}
require $dir.$path;
