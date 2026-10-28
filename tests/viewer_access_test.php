<?php
require_once __DIR__.'/../includes/viewer_access.php';
require_once __DIR__.'/../includes/session_user.php';
require_once __DIR__.'/../includes/json_store.php';
function check($ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$tmp=sys_get_temp_dir().'/cds_viewer_'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/data');mkdir($tmp.'/includes');mkdir($tmp.'/chuyenmon');mkdir($tmp.'/chuyenmon/includes');
define('DATA_PATH',$tmp.'/data');
function current_user(){return $_SESSION['cds_user']??null;}
require_once __DIR__.'/../includes/chuyenmon_permission_runtime.php';
require_once __DIR__.'/../includes/permissions.php';
try{
 $admin=['id'=>'a','username'=>'admin','role'=>'admin','active'=>true,'password_hash'=>'KEEP_ORIGINAL'];
 $file=DATA_PATH.'/users.json';file_put_contents($file,json_encode([$admin]));
 $created=cds_viewer_create_account($admin,'khach','','',$file);$users=json_decode(file_get_contents($file),true);$viewer=$users[1];
 check($users[0]===$admin,'Existing account unchanged');check(password_verify($created['password'],$viewer['password_hash']),'Generated password matches hash');check(strlen($created['password'])>=20,'Strong generated secret');check(!str_contains(file_get_contents($file),$created['password']),'No plaintext credential in store');
 $before=file_get_contents($file);
 foreach([['KhAcH','',''],['abc','short',''],['bad name','',''],['abc','','2020-01-01']]as$args){try{cds_viewer_create_account($admin,...array_merge($args,[$file]));throw new LogicException('Expected rejection');}catch(RuntimeException$e){}check(file_get_contents($file)===$before,'Invalid/duplicate attempt atomic');}
 try{cds_viewer_create_account($viewer,'other','','',$file);throw new LogicException('Expected rejection');}catch(RuntimeException$e){}check(file_get_contents($file)===$before,'Viewer cannot create accounts');
 $hostile=array_merge($viewer,['modules'=>['csdl'=>'admin'],'groups'=>['bgh','admin'],'permission_overrides'=>array_fill_keys(array_keys(permission_features_catalog()),'delete'),'teacher_name'=>'Real teacher','classes'=>['6A'],'permission_model_version'=>1]);
 $_SESSION=['cds_user'=>$hostile];
 foreach(permission_features_catalog()as$code=>$meta){check(permission_access($code)==='view','Feature capped '.$code);foreach(['edit','delete','admin']as$level)check(!can_perm_level($code,$level),'Cannot inherit write '.$code);}
 foreach(permission_modules_catalog()as$module=>$meta){check(can_module($module,'view'),'All modules visible');check(!can_module($module,'edit')&&!can_module($module,'admin'),'Module capped');}
 foreach(cds_cm_permission_codes()as$code)check(cds_cm_feature_access_for_user($hostile,$code,DATA_PATH)==='view','CM cap');
 check(!cds_user_has_group($hostile,'bgh'),'No legacy management group');check(allowed_classes()===null,'All class data scope');
 $session=cds_session_user_from_record($hostile);check(!$session['groups']&&!$session['teacher_name']&&$session['permission_model_version']===2,'Normalize without borrowed teacher identity');
 $fixture=DATA_PATH.'/business.json';file_put_contents($fixture,'[{"id":"original"}]');$business=file_get_contents($fixture);$called=false;
 check(!cds_json_save($fixture,[]),'Save blocked');check(!cds_json_update($fixture,function()use(&$called){$called=true;return[];}),'Locked mutation blocked');check(!$called&&file_get_contents($fixture)===$business,'Mutation callback not run and source unchanged');
 $_SESSION=['cds_user'=>$admin];check(cds_json_update($fixture,fn($r)=>array_merge($r,[['id'=>'admin-update']])),'Admin still writes');check(count(cds_json_load($fixture))===2,'Admin update retained');
 foreach(cds_viewer_read_routes()as$path=>$keys){check(cds_viewer_request_allowed('GET',$path,[]),'Admitted read route');foreach(['POST','PUT','PATCH','DELETE']as$m)check(!cds_viewer_request_allowed($m,$path,[]),'All mutations denied');foreach(['action','save_drive','delete','xoa','reset','settings','oauth','token']as$key)check(!cds_viewer_request_allowed('GET',$path,[$key=>'1']),'GET mutation/secret denied');}
 check(!cds_viewer_request_allowed('GET','users.php',[]),'No users/password screen');check(!cds_viewer_request_allowed('GET','database_admin.php',[]),'No database management');check(!cds_viewer_request_allowed('GET','future_endpoint.php',[]),'Unknown endpoint fails closed');
 check(!cds_viewer_request_allowed('GET','noitru.php',['tab'=>['health']]),'Array query denied');check(!cds_viewer_request_allowed('GET','trolyai.php',['settings'=>'1']),'No API key settings');
 check(cds_viewer_request_allowed('GET','noitru.php',['tab'=>'health','health_view'=>'history','class'=>'6A']),'Health real data visible');
 check(cds_viewer_request_allowed('GET','chuyenmon/sodaubai.php',['tab'=>'daily','week'=>'6','class'=>'6A']),'Lesson-book read route');
 check(!cds_viewer_request_allowed('GET','thidua.php',['score_view'=>'settings']),'Configuration mode blocked');
 // Execute the actual root/CM bootstrap with isolated configuration and hostile session.
 foreach(['includes/auth.php','includes/viewer_access.php','includes/session_user.php','includes/json_store.php','includes/json_rows.php','includes/json_fields.php','includes/global_ui.php','chuyenmon/includes/functions.php']as$p)copy(__DIR__.'/../'.$p,$tmp.'/'.$p);
 file_put_contents($tmp.'/includes/config.php','<?php define("BASE_URL","/");define("DATA_PATH",dirname(__DIR__)."/data");define("USERS_FILE",DATA_PATH."/users.json");');
 file_put_contents($tmp.'/chuyenmon/includes/config.php','<?php define("BASE_PATH",dirname(__DIR__));define("BASE_URL","/chuyenmon/");define("DATA_PATH",BASE_PATH."/data");');
 foreach(['chuyenmon_permission_runtime.php','school_week_calendar.php']as$p)file_put_contents($tmp.'/includes/'.$p,'<?php ');
 file_put_contents($tmp.'/includes/drive_action_registry.php','<?php function cds_drive_action_register_shared(...$args){exit("AFTER_GATE");}');
 file_put_contents($tmp.'/chuyenmon/includes/subject_meta.php','<?php exit("AFTER_GATE");');
 file_put_contents($tmp.'/session.json',json_encode($viewer));
 file_put_contents($tmp.'/worker.php', <<<'PHP'
<?php
session_save_path(__DIR__.'/data');session_start();$method=$argv[1];$path=$argv[2];$_GET=json_decode($argv[3],true);$mode=$argv[4]??'';
$record=json_decode(file_get_contents(__DIR__.'/session.json'),true);if($mode==='existing')$record=['id'=>'a','username'=>'admin','role'=>'admin'];
$_SESSION=['cds_user'=>$record,'pccm_admin'=>true];if($mode==='stale')$_SESSION['cds_user']['role']='admin';
$_SERVER['SCRIPT_FILENAME']=__DIR__.'/'.$path;$_SERVER['REQUEST_METHOD']=$method;
// Route filenames are real so path validation does not rely on attacker-controlled URI.
require __DIR__.($mode==='cm'?'/chuyenmon/includes/functions.php':'/includes/auth.php');
PHP);
 foreach(['noitru.php','users.php','admin.php','trolyai.php','chuyenmon/sodaubai.php']as$p)file_put_contents($tmp.'/'.$p,'<?php ');
 $run=static function($method,$path,$query=[],$mode='')use($tmp){$p=proc_open([PHP_BINARY,$tmp.'/worker.php',$method,$path,json_encode($query),$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0,'Bootstrap: '.$err.$out);return$out;};
 $readOutput=$run('GET','noitru.php',['tab'=>'health']);check($readOutput==='AFTER_GATE','Root read reaches handler: '.$readOutput);
 foreach(['POST','DELETE','PATCH']as$m)check(str_contains($run($m,'noitru.php'),'chỉ được xem'),'Blocked before root handler');
 check(str_contains($run('POST','noitru.php',[],'stale'),'chỉ được xem'),'Stale admin session cannot bypass current viewer role');
 check($run('POST','noitru.php',[],'existing')==='AFTER_GATE','Existing administrator mutation route unchanged');
 check(str_contains($run('GET','noitru.php',['save_drive'=>'1']),'chỉ được xem'),'GET write before handler');
 check(str_contains($run('GET','users.php'),'chỉ được xem'),'No credential-bearing account list');
 check($run('GET','chuyenmon/sodaubai.php',[],'cm')==='AFTER_GATE','CM read reaches handler');
 check(str_contains($run('POST','chuyenmon/sodaubai.php',[],'cm'),'chỉ được xem'),'CM mutation before init_data');
 // Defense in depth: execute each TKB page with the shared bootstrap gate deliberately absent.
 mkdir($tmp.'/tkb_fixture');mkdir($tmp.'/tkb_fixture/chuyenmon');mkdir($tmp.'/tkb_fixture/chuyenmon/includes');mkdir($tmp.'/tkb_fixture/includes');
 foreach(['tkb_xep.php','tkb_editor.php','tkb_constraints.php']as$p)copy(__DIR__.'/../'.$p,$tmp.'/tkb_fixture/'.$p);
 file_put_contents($tmp.'/tkb_fixture/chuyenmon/includes/functions.php','<?php require_once dirname(__DIR__,3)."/includes/viewer_access.php";function cds_user(){return ["role"=>"viewer"];}function cds_can_feature(...$args){return false;}');
 file_put_contents($tmp.'/tkb_fixture/chuyenmon/includes/timetable_store.php','<?php ');
 file_put_contents($tmp.'/tkb_fixture/includes/timetable_builder.php','<?php function ttb_data(){exit("AFTER_TKB_GATE");}');
 file_put_contents($tmp.'/tkb_worker.php','<?php $_SERVER["REQUEST_METHOD"]=$argv[1];$_SESSION=["cds_user"=>["role"=>"viewer"]];require __DIR__."/tkb_fixture/".$argv[2];');
 foreach(['tkb_xep.php','tkb_editor.php','tkb_constraints.php']as$page)foreach(['GET','POST','PUT','DELETE']as$method){
  $p=proc_open([PHP_BINARY,$tmp.'/tkb_worker.php',$method,$page],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0,'TKB page '.$out.$err);
  check($method==='GET'?$out==='AFTER_TKB_GATE':str_contains($out,'không được thay đổi'),'Local TKB method guard '.$method.' '.$page);
 }
 $locked=$users;$locked[1]['active']=false;file_put_contents($file,json_encode($locked));check(str_contains($run('GET','noitru.php'),'đã khóa'),'Immediate account lock');
 $locked[1]['active']=true;$locked[1]['expires_at']='2020-01-01T00:00:00+07:00';file_put_contents($file,json_encode($locked));check(str_contains($run('GET','noitru.php'),'hết hạn'),'Expiry enforced every request');
 file_put_contents($file,'broken json');check(str_contains($run('GET','noitru.php'),'hết hạn'),'Corrupt user store fails closed');
 echo "PASS viewer: provisioning, password hashing, duplicate safety, all-feature read caps, hostile grants, class scope, JSON no-write, actual root/CM request guards, stale sessions, lock and expiry\n";
}finally{$_SESSION=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);}
