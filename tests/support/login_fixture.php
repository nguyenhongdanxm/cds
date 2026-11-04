<?php
function login_fixture():string{
 $root=dirname(__DIR__,2);$dir=sys_get_temp_dir().'/cds-login-'.bin2hex(random_bytes(6));mkdir($dir.'/includes',0700,true);mkdir($dir.'/data',0700);mkdir($dir.'/sessions',0700);
 foreach(['auth.php','permissions.php','session_user.php','viewer_access.php','json_store.php','json_rows.php','json_fields.php','chuyenmon_permission_runtime.php','drive_action_registry.php','audit.php']as$f)copy($root.'/includes/'.$f,$dir.'/includes/'.$f);
 copy($root.'/login.php',$dir.'/login.php');
 file_put_contents($dir.'/includes/config.php','<?php define("BASE_URL","/");define("DATA_PATH",dirname(__DIR__)."/data");define("USERS_FILE",DATA_PATH."/users.json");define("SCHOOL_NAME","Fixture school");define("SCHOOL_SHORT","Fixture");');
 $users=[['id'=>'u1','username'=>'teacher','name'=>'Fixture teacher','password_hash'=>password_hash('FixturePass!123',PASSWORD_DEFAULT),'active'=>true,'role'=>'gv','groups'=>['gv'],'permission_model_version'=>2],['id'=>'u2','username'=>'inactive','active'=>false,'password_hash'=>password_hash('FixturePass!123',PASSWORD_DEFAULT)]];
 file_put_contents($dir.'/data/users.json',json_encode($users));file_put_contents($dir.'/admin.php','<?php define("CDS_SKIP_DRIVE_ACTION_REGISTRATION",true);define("CDS_AUTH_LOGIN_ONLY",true);require __DIR__."/includes/auth.php";require_login();echo "SIGNED_IN ".e(current_user()["username"]);');return $dir;
}
function login_wipe(string $dir):void{foreach(scandir($dir)as$f){if($f==='.'||$f==='..')continue;$p=$dir.'/'.$f;is_dir($p)?login_wipe($p):unlink($p);}rmdir($dir);}
