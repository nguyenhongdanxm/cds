<?php
require __DIR__.'/../includes/json_store.php';require __DIR__.'/../includes/program_assignment.php';
require_once __DIR__.'/../includes/json_store.php';
require_once __DIR__.'/../includes/pccm_release.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function load_json($f,$d=[]){return cds_json_load($f,$d);}
function get_active_version_id(){return 'v1';}
function get_classes(){return ['8A','8B'];}
function get_subjects(){return ['GDĐP'=>[]];}
function get_teachers(){return ['A','B'];}
function cds_can_feature($code,$level){return $GLOBALS['can'];}
function flash($m,$t){}
$dir=sys_get_temp_dir().'/cds-gdp-post-'.bin2hex(random_bytes(6));mkdir($dir,0700);define('DATA_PATH',$dir);define('BASE_URL','/chuyenmon/');
$_SERVER['REQUEST_METHOD']='POST';$_SESSION=['gdp_csrf'=>'token'];$GLOBALS['can']=true;
$file=gdp_file($dir,'v1');$existing=['8B'=>['subject'=>'GDĐP','ranges'=>[['teacher'=>'A','from'=>1,'to'=>35]]]];cds_json_save($file,$existing);$hash=hash_file('sha256',$file);
$valid=['action'=>'program_save','csrf'=>'token','version_id'=>'v1','fingerprint'=>hash('sha256',json_encode($existing)),'classes'=>['8A','8B'],'subject'=>'GDĐP','teacher'=>['A','B'],'from'=>['1','8'],'to'=>['7','35']];
foreach([
 ['csrf'=>'wrong'],['version_id'=>'v2'],['fingerprint'=>'stale'],['classes'=>[]],['classes'=>['Unknown']],['subject'=>'Toán'],['teacher'=>['X','B']],['from'=>[1,7]],['to'=>[6,34]]
]as$patch){$_POST=array_replace($valid,$patch);unset($GLOBALS['gdp_form_error']);require __DIR__.'/../chuyenmon/includes/program_assignment_controller.php';check(isset($GLOBALS['gdp_form_error']),'Invalid submission reports error');check(hash_file('sha256',$file)===$hash,'Invalid submission never changes assignments');}
$GLOBALS['can']=false;$_POST=$valid;unset($GLOBALS['gdp_form_error']);require __DIR__.'/../chuyenmon/includes/program_assignment_controller.php';check(isset($GLOBALS['gdp_form_error'])&&hash_file('sha256',$file)===$hash,'View-only permission cannot write');$GLOBALS['can']=true;
register_shutdown_function(function()use($dir,$file){try{$saved=load_json($file);foreach(['8A','8B']as$c)check(gdp_teacher($saved[$c],7)==='A'&&gdp_teacher($saved[$c],8)==='B','One confirmed save applies valid ranges to both classes');echo "PASS real controller: CSRF, rights, stale version/hash, invalid classes/subject/teachers, missing/overlap rejection before writes and successful multi-class save\n";}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}});
$_POST=$valid;require __DIR__.'/../chuyenmon/includes/program_assignment_controller.php';
throw new RuntimeException('Successful controller should redirect');
