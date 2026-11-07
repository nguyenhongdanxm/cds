<?php
// Run the actual page with a disposable CSDL/auth fixture; never access production stores.
$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/cds-muc-'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/includes');
function check_muc($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
copy($root.'/hoclieu_game_muc.php',$tmp.'/hoclieu_game_muc.php');file_put_contents($tmp.'/includes/game_credit.php','');
file_put_contents($tmp.'/includes/auth.php', <<<'PHP'
<?php
function require_login(){if(getenv('MUC_ROLE')==='anonymous'){http_response_code(401);exit('LOGIN_REQUIRED');}}
function current_user(){return ['id'=>'qa-user','role'=>getenv('MUC_ROLE')?:'teacher'];}
function can_perm_level($p,$l){return getenv('MUC_ROLE')==='teacher';}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
define('BASE_URL','/cds/');
PHP
);
file_put_contents($tmp.'/includes/csdl_store.php', <<<'PHP'
<?php
function csdl_classes_all(){return [['id'=>'class-6','name'=>'6A','active'=>true],['id'=>'empty','name'=>'7A','active'=>true],['id'=>'off','name'=>'8A','active'=>false]];}
function csdl_students_all(){return [
 ['id'=>'one','name'=>'Nguyễn An','class_id'=>'class-6','active'=>true,'gender'=>'Nam','cccd'=>'DO_NOT_EXPORT'],
 ['id'=>'two','ho_ten'=>'Nguyễn An','lop'=>'6A','active'=>true,'gioi_tinh'=>'Nữ'],
 ['code'=>'three','name'=>'</script><script>alert(1)</script>','class_name'=>'6A','active'=>true],
 ['id'=>'inactive','name'=>'Không hoạt động','class_name'=>'6A','active'=>false],
 ['id'=>'noclass','name'=>'Thiếu lớp'],
 ['name'=>'Không có mã','class'=>'6A'],
];}
function csdl_compare_class_names($a,$b){return strnatcasecmp($a,$b);}
function csdl_compare_person_names($a,$b){return strcmp($a,$b);}
PHP
);
$run=static function($role)use($tmp){$pipes=[];$process=proc_open([PHP_BINARY,$tmp.'/hoclieu_game_muc.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,array_merge(getenv(),['MUC_ROLE'=>$role]));$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);check_muc($code===0&&$err==='','Page runtime failed: '.$err);return $out;};
$html=$run('teacher');check_muc((bool)preg_match('#<script id="rosterData" type="application/json">(.*?)</script>#s',$html,$m),'Roster JSON exists');$data=json_decode($m[1],true,512,JSON_THROW_ON_ERROR);
check_muc(count($data['6A'])===4,'Active and class fallback filtering');check_muc(isset($data['7A'])&&count($data['7A'])===0,'Empty active class is present');check_muc(!isset($data['8A']),'Inactive class absent');
$byId=array_column($data['6A'],null,'id');check_muc($byId['one']['gender']==='Nam'&&$byId['two']['gender']==='Nữ','Gender normalized');check_muc(isset($byId['row_5']),'Missing student ID gets stable row fallback');
check_muc(!str_contains($html,'DO_NOT_EXPORT'),'Private CSDL fields are not exported');check_muc(!str_contains($html,'<script>alert(1)</script>'),'Names safely serialized into script JSON');check_muc($byId['three']['name']==='</script><script>alert(1)</script>','Escaping preserves original display name');
check_muc(str_contains($html,'/cds/assets/cds-muc.js'),'Subdirectory BASE_URL supported');check_muc($run('anonymous')==='LOGIN_REQUIRED','Login required');check_muc($run('denied')==='Không có quyền xem học liệu.','Permission enforced');check_muc(str_contains($run('admin'),'rosterData'),'Admin can access');
echo "PASS Mực PHP: actual page, CSDL class/active/ID fallbacks, duplicate names, gender, safe JSON, minimal fields, BASE_URL, login and permission\n";
}finally{foreach(glob($tmp.'/includes/*')?:[] as $f)unlink($f);@unlink($tmp.'/hoclieu_game_muc.php');@rmdir($tmp.'/includes');@rmdir($tmp);}
