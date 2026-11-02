<?php
// Creates an isolated app with production controller/service; synthetic auth/data only.
$root=dirname(__DIR__,2);$dir=$argv[1]??'';if($dir===''||!str_starts_with($dir,sys_get_temp_dir().'/se-browser-'))throw new RuntimeException('Use a temporary fixture directory.');
@mkdir($dir.'/includes',0750,true);@mkdir($dir.'/assets',0750,true);@mkdir($dir.'/data/staff_evaluation',0750,true);
foreach(['thidua_danhgia.php','includes/staff_evaluation.php','includes/staff_evaluation_month_print.php','assets/staff-evaluation.css','assets/staff-evaluation-print.css','assets/staff-evaluation-form.js']as$f)copy($root.'/'.$f,$dir.'/'.$f);
file_put_contents($dir.'/includes/auth.php','<?php' . "\n" . 'session_start();define("DATA_PATH",dirname(__DIR__)."/data");define("USERS_FILE",DATA_PATH."/users.json");define("SCHOOL_NAME","TRƯỜNG KIỂM THỬ");require_once '.var_export($root.'/includes/json_store.php',true).';
function require_login(){}
function current_user(){return ["id"=>($_GET["as"]??"u1"),"name"=>"Tài khoản kiểm thử","role"=>(($_GET["as"]??"")==="admin"?"admin":(($_GET["as"]??"")==="viewer"?"viewer":"teacher"))];}
function load_json($p,$d=[]){return cds_json_load($p,$d);}
function flash($v,$t=""){$_SESSION["flash"]=$v;}
function show_flash(){if(!empty($_SESSION["flash"])){echo "<p>".htmlspecialchars($_SESSION["flash"])."</p>";unset($_SESSION["flash"]);}}
');
file_put_contents($dir.'/includes/teacher_evaluation.php','<?php
function te_teachers(){return [["id"=>"s1","name"=>"Giáo viên kiểm thử A","to_chuyen_mon"=>"Tổ A"],["id"=>"s2","name"=>"Nhân viên kiểm thử B","to_chuyen_mon"=>"Văn phòng"]];}
function te_norm($s){return strtolower(trim($s));}
function te_teacher_team($s){return $s["to_chuyen_mon"]??"";}
function te_observations(){return [["id"=>"o1","teacher_id"=>"s1","date"=>"2026-11-02","rating"=>"Giỏi"]];}
function te_file_checks(){return [];}
');
require $root.'/includes/staff_evaluation.php';$cfg=se_defaults();$v=$cfg['template'];$v['id']='test-v1';$v['number']=1;$v['active']=true;$v['effective']='2026-07-01';$v['decision']='Chỉ dùng kiểm thử';$cfg['template']=$v;$cfg['versions']=[$v];$cfg['assignments']=['s1'=>['user_id'=>'u1','reviewer'=>'u2','approver'=>'u3','profile'=>'regular','employment'=>'vienchuc','position'=>'Giáo viên bộ môn','external'=>false],'s2'=>['user_id'=>'u4','reviewer'=>'u2','approver'=>'u3','profile'=>'regular','employment'=>'vienchuc','position'=>'Văn thư','external'=>false]];
file_put_contents($dir.'/data/staff_evaluation/config.json',json_encode($cfg,JSON_UNESCAPED_UNICODE));file_put_contents($dir.'/data/users.json',json_encode(array_map(fn($id)=>['id'=>$id,'name'=>$id,'username'=>$id,'role'=>$id==='admin'?'admin':'teacher','active'=>true],['u1','u2','u3','u4','admin'])));
// Explicitly deny fixture storage even on the PHP development server.
file_put_contents($dir.'/router.php','<?php if(str_starts_with(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH),"/data/")){http_response_code(403);exit;}return false;');
echo $dir;
