<?php
$dir=$argv[1];$root=dirname(__DIR__);mkdir($dir.'/includes',0777,true);mkdir($dir.'/assets',0777,true);mkdir($dir.'/data');copy($root.'/hoclieu_thanhtich.php',$dir.'/hoclieu_thanhtich.php');copy($root.'/includes/achievement_board.php',$dir.'/includes/achievement_board.php');
foreach(['css','js'] as $ext)copy($root.'/assets/achievement-board.'.$ext,$dir.'/assets/achievement-board.'.$ext);
file_put_contents($dir.'/includes/json_store.php','<?php require '.var_export($root.'/includes/json_store.php',true).';');
$auth=<<<'PHP'
<?php
session_start();define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');define('USERS_FILE',DATA_PATH.'/users.json');define('CSDL_SUBJECTS',DATA_PATH.'/subjects.json');
require __ROOT__.'/includes/chuyenmon_permission_runtime.php';require __ROOT__.'/includes/permissions.php';
function current_user(){return ['id'=>($_COOKIE['test_role']??'admin')==='admin'?'a':'t','role'=>$_COOKIE['test_role']??'admin','name'=>'Tài khoản kiểm thử','permission_model_version'=>2,'permission_overrides'=>['hl.thanhtich'=>'edit']];}
function require_login(){if(($_COOKIE['test_role']??'')==='guest'){http_response_code(401);exit;}}function load_json($f,$d=[]){return is_file($f)?json_decode(file_get_contents($f),true):$d;}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
PHP;
file_put_contents($dir.'/includes/auth.php',str_replace('__ROOT__',var_export($root,true),$auth));
file_put_contents($dir.'/includes/csdl_store.php',<<<'PHP'
<?php
function csdl_classes_all(){return [['id'=>'c1','name'=>'6A'],['id'=>'c2','name'=>'6B']];}function csdl_compare_class_names($a,$b){return strcmp($a,$b);}
function csdl_students_all(){return [['id'=>'s1','name'=>'An <script>window.bad=1</script>','class_name'=>'6A'],['id'=>'s2','name'=>'Bình','class_name'=>'6A'],['id'=>'s3','name'=>'Chi','class_name'=>'6B']];}
function csdl_years_all(){return [['id'=>'y','label'=>'Năm kiểm thử','start'=>'2000-01-01','end'=>'2100-12-31','is_current'=>true]];}
PHP);
file_put_contents($dir.'/data/subjects.json',json_encode([['name'=>'Toán'],['name'=>'Văn']]));file_put_contents($dir.'/data/users.json',json_encode([['id'=>'t','role'=>'teacher','name'=>'Giáo viên kiểm thử','active'=>true]]));
