<?php
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function removeFixture($dir){foreach(scandir($dir)as$f){if($f==='.'||$f==='..')continue;$p=$dir.'/'.$f;if(is_dir($p))removeFixture($p);else unlink($p);}rmdir($dir);}
$dir=sys_get_temp_dir().'/cds_lb_roster_'.bin2hex(random_bytes(5));mkdir($dir.'/chuyenmon/includes',0700,true);mkdir($dir.'/chuyenmon/data',0700,true);mkdir($dir.'/includes',0700,true);mkdir($dir.'/data',0700,true);mkdir($dir.'/sessions',0700,true);
try{
$source=file_get_contents(__DIR__.'/../chuyenmon/includes/lesson_book_store.php');$start=strpos($source,'function lb_class_student_names(');$end=strpos($source,'function lb_id(',$start);check($start!==false&&$end!==false,'Production roster helpers found');
file_put_contents($dir.'/chuyenmon/includes/roster.php',"<?php\n".substr($source,$start,$end-$start));copy(__DIR__.'/../chuyenmon/includes/lesson_book_student_roster.php',$dir.'/chuyenmon/includes/lesson_book_student_roster.php');
// Load the actual full Chuyên môn function file, not a substitute Drive declaration.
copy(__DIR__.'/../chuyenmon/includes/functions.php',$dir.'/chuyenmon/includes/functions.php');
copy(__DIR__.'/../includes/json_store.php',$dir.'/includes/json_store.php');copy(__DIR__.'/../includes/json_rows.php',$dir.'/includes/json_rows.php');copy(__DIR__.'/../includes/json_fields.php',$dir.'/includes/json_fields.php');
file_put_contents($dir.'/chuyenmon/includes/config.php', <<<'CONFIG'
<?php
define('DATA_PATH',dirname(__DIR__).'/data');define('BASE_URL','/chuyenmon/');
foreach(['TEACHERS_FILE'=>'teachers','TEACHER_META_FILE'=>'teacher_meta','SUBJECTS_FILE'=>'subjects','SUBJECT_META_FILE'=>'subject_meta','CLASSES_FILE'=>'classes','ROLES_FILE'=>'roles','GROUPS_FILE'=>'groups','SETTINGS_FILE'=>'settings','VERSIONS_FILE'=>'versions','ACTIVE_VERSION_FILE'=>'active_version','LEGACY_ASSIGNMENTS_FILE'=>'assignments','LEGACY_ROLE_ASSIGNMENTS_FILE'=>'role_assignments']as$key=>$value)define($key,DATA_PATH.'/'.$value.'.json');
define('DEFAULT_QUOTA_THCS',17);define('DEFAULT_QUOTA_THPT',15);
$DEFAULT_TEACHERS=$DEFAULT_CLASSES=$DEFAULT_SUBJECTS=$DEFAULT_GROUPS=$DEFAULT_ROLES=[];
CONFIG);
foreach(['session_user.php','chuyenmon_permission_runtime.php','school_week_calendar.php']as$f)file_put_contents($dir.'/includes/'.$f,'<?php');
file_put_contents($dir.'/includes/viewer_access.php','<?php function cds_viewer_guard($root){} function cds_is_viewer($user=null){return false;}');
file_put_contents($dir.'/includes/drive_action_registry.php','<?php function cds_drive_action_register_shared(...$args){}');
file_put_contents($dir.'/chuyenmon/includes/subject_meta.php','<?php');
// This is the exact collision if root auth is mistakenly loaded after CM functions.
file_put_contents($dir.'/includes/auth.php','<?php function cds_drive_settings():array{return [];}');
file_put_contents($dir.'/includes/csdl_store.php',"<?php require __DIR__.'/auth.php';");
file_put_contents($dir.'/includes/database_sql_read.php', <<<'SQL'
<?php
function cds_core_sql_rows($entity){$GLOBALS['sqlReads'][]=$entity;if(($GLOBALS['sqlMode']??'json')==='throw')throw new RuntimeException('fixture SQL unavailable');if(($GLOBALS['sqlMode']??'json')==='sql')return $GLOBALS['sqlRows'][$entity];return null;}
SQL);
$students=[['id'=>'1','name'=>'Nguyễn An','class_id'=>'c6','active'=>true],['id'=>'2','name'=>'Trần Bình','class_name'=>'Lớp 6A','active'=>true],['id'=>'3','name'=>'Đã nghỉ','class_id'=>'c6','active'=>false],['id'=>'4','ho_ten'=>'Lê Chi','lop'=>'6B'],['id'=>'5','name'=>'<script>test</script>','class'=>'6A'],['id'=>'6','name'=>'Nguyễn An','class_id'=>'c6'],['id'=>'7','name'=>'Đã chuyển','class_id'=>'c6','departure_date'=>'2000-01-01'],['id'=>'8','name'=>'Chưa chuyển','class_id'=>'c6','departure_date'=>'2099-01-01']];
file_put_contents($dir.'/data/students.json',json_encode($students,JSON_UNESCAPED_UNICODE));file_put_contents($dir.'/data/classes.json',json_encode([['id'=>'c6','name'=>'6A']]));
file_put_contents($dir.'/chuyenmon/data/students.json',json_encode([['name'=>'SAI NGUỒN CM','class'=>'6A']]));
session_save_path($dir.'/sessions');require $dir.'/chuyenmon/includes/functions.php';check(function_exists('cds_drive_settings'),'Actual CM Drive functions initialized');
function lb_norm($s){$s=mb_strtolower(trim($s),'UTF-8');return preg_replace('/[^\p{L}\p{N}]/u','',$s);}
require $dir.'/chuyenmon/includes/roster.php';
$before=[];foreach(array_merge(glob($dir.'/data/*'),glob($dir.'/chuyenmon/data/*'))as$f)$before[$f]=hash_file('sha256',$f);
$names=lb_class_student_names('6A');check(count($names)===4&&in_array('Nguyễn An',$names,true)&&in_array('Trần Bình',$names,true)&&in_array('Chưa chuyển',$names,true),'Root shared roster, class IDs, aliases, effective departure state');check(!in_array('SAI NGUỒN CM',$names,true),'Never use chuyenmon/data roster');check(lb_class_student_names('Lớp 6A')===$names,'Class alias works');
$map=lb_visible_class_students([['class'=>'6A'],['class'=>'6B'],['class'=>'6A']]);check(count($map)===2&&$map['6B']===['Lê Chi']&&$GLOBALS['sqlReads']===['students','classes'],'One shared roster scan for all visible classes');
foreach($before as$f=>$hash)check(hash_file('sha256',$f)===$hash,'Lookup never rewrites school/CM data');check(!in_array($dir.'/includes/auth.php',get_included_files(),true)&&!in_array($dir.'/includes/csdl_store.php',get_included_files(),true),'Root auth and CSDL bootstrap never loaded');
// Execute the actual students AJAX block after the actual CM bootstrap.
$page=file_get_contents(__DIR__.'/../chuyenmon/sodaubai.php');$a=strpos($page,"if($"."ajax==='students'||");
// Locate the next statement without interpreting source variables in the fixture.
$b=strpos($page,'if(empty($_SESSION',$a);$ajax='students';$_GET=['class'=>'6A'];ob_start();eval(str_replace('exit;','return;',substr($page,$a,$b-$a)));$payload=json_decode(ob_get_clean(),true);check($payload['ok']===true&&$payload['names']===$names,'Actual students AJAX returns JSON without bootstrap collision');
$GLOBALS['sqlMode']='sql';$GLOBALS['sqlRows']=['students'=>[['id'=>'sql','name'=>'SQL Student','class_id'=>'sqlclass']], 'classes'=>[['id'=>'sqlclass','name'=>'7A']]];unset($GLOBALS['lb_class_students']);check(lb_class_student_names('7A')===['SQL Student'],'Enabled SQL source preferred over JSON');$GLOBALS['sqlRows']['students']=[];unset($GLOBALS['lb_class_students']);check(lb_class_student_names('6A')===[],'Authoritative empty SQL roster stays empty');
$GLOBALS['sqlMode']='throw';unset($GLOBALS['lb_class_students']);check(lb_class_student_names('6A')===$names,'SQL failure falls back to shared root JSON');
$GLOBALS['sqlMode']='json';file_put_contents($dir.'/data/students.json','{bad');unset($GLOBALS['lb_class_students']);check(lb_class_student_names('6A')===[]&&file_get_contents($dir.'/data/students.json')==='{bad','Corrupt data neither seeded nor overwritten');
$page=file_get_contents(__DIR__.'/../chuyenmon/sodaubai.php');check(!str_contains($page,'/includes/csdl_store.php'),'Actual students and PPCT AJAX do not import root auth');check(str_contains($page,'json_encode($lbClassStudents,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT'),'Inline JSON HTML-safe');
// A separate process loads the real shared SQL dependency chain alongside real CM functions.
foreach(['database_sql_read.php','database.php','database_shadow.php','database_read_verify.php','database_batch_primary.php','instance_config.php']as$f)copy(__DIR__.'/../includes/'.$f,$dir.'/includes/'.$f);
file_put_contents($dir.'/real-bootstrap.php', '<?php session_save_path(__DIR__."/sessions");$GLOBALS["cds_force_json_core_read"]=true;require __DIR__."/chuyenmon/includes/functions.php";require __DIR__."/chuyenmon/includes/lesson_book_student_roster.php";$rows=lb_student_roster_rows("classes");if(count($rows)!==1||in_array(__DIR__."/includes/auth.php",get_included_files(),true))exit(2);echo "REAL_BOOTSTRAP_OK";');
$proc=proc_open([PHP_BINARY,$dir.'/real-bootstrap.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);check($code===0&&$out==='REAL_BOOTSTRAP_OK','Real SQL/module bootstrap must not redeclare functions: '.$err);
echo "PASS roster integration: actual full CM bootstrap, no Drive/auth redeclarations, shared root data not CM data, SQL and safe JSON fallback, class aliases, active/departure status, one scan, no lookup writes\n";
}finally{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();removeFixture($dir);}
