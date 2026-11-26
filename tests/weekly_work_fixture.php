<?php
/** Isolated HTTP fixture: only synthetic accounts and a temporary calendar. */
$target=$argv[1];$root=dirname(__DIR__);mkdir($target.'/includes',0777,true);mkdir($target.'/assets',0777,true);
foreach(['weekly_work_store.php','weekly_work_view.php','weekly_work_dashboard.php','weekly_work_page.php']as$f)copy($root.'/includes/'.$f,$target.'/includes/'.$f);
copy($root.'/assets/weekly-work.css',$target.'/assets/weekly-work.css');
file_put_contents($target.'/includes/json_store.php','<?php require '.var_export($root.'/includes/json_store.php',true).';');
$entry=<<<'PHP'
<?php
session_start();define('DATA_PATH',__DIR__.'/data');define('BASE_URL','/');define('SCHOOL_NAME','Trường kiểm thử');
require __ROOT__.'/includes/chuyenmon_permission_runtime.php';require __ROOT__.'/includes/permissions.php';
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function current_user(){return ['id'=>'test','role'=>$_SESSION['role']??'viewer','permission_model_version'=>2,'permission_overrides'=>['school.weekly_schedule'=>($_SESSION['role']??'')==='teacher'?'edit':'none']];}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function flash($s,$type){$_SESSION['cds_flash']=['message'=>$s,'type'=>$type];}
if(isset($_GET['test_role'])){$_SESSION['role']=$_GET['test_role'];header('Location: /admin.php?view=weekly_schedule&week=2026-11-23');exit;}
require __DIR__.'/includes/weekly_work_store.php';
if(($_GET['view']??'')==='weekly_schedule'){require __DIR__.'/includes/weekly_work_page.php';exit;}
?><!doctype html><html lang="vi"><head><link rel="stylesheet" href="/assets/weekly-work.css"></head><body><?php require __DIR__.'/includes/weekly_work_dashboard.php';?></body></html>
PHP;
file_put_contents($target.'/admin.php',str_replace('__ROOT__',var_export($root,true),$entry));
