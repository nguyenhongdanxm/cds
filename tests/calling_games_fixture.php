<?php
$dir=$argv[1];$root=dirname(__DIR__);mkdir($dir.'/includes',0777,true);mkdir($dir.'/assets',0777,true);
copy($root.'/hoclieu_game_goiten.php',$dir.'/hoclieu_game_goiten.php');symlink($root.'/assets/goi-ten',$dir.'/assets/goi-ten');
file_put_contents($dir.'/includes/auth.php', <<<'AUTH'
<?php
session_start();define('BASE_URL','/');function require_login(){if(isset($_GET['guest'])){http_response_code(401);exit('Login');}}function current_user(){return ['id'=>$_GET['user']??'teacher1','role'=>'teacher'];}function can_perm_level($p,$l){return !isset($_GET['denied']);}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
AUTH);
file_put_contents($dir.'/includes/csdl_store.php', <<<'CSDL'
<?php
function csdl_classes_all(){return [['id'=>'c1','name'=>'12A'],['id'=>'c2','name'=>'12B'],['id'=>'old','name'=>'Ẩn','active'=>false]];}
function csdl_students_all(){return [['id'=>'s1','name'=>'Nguyễn An','class_id'=>'c1'],['id'=>'s2','name'=>'Nguyễn An','class_id'=>'c1'],['id'=>'s3','name'=>'Bình','class_id'=>'c1'],['id'=>'s4','name'=>'Chi','class_id'=>'c2'],['id'=>'s5','name'=>'Không hoạt động','class_id'=>'c1','active'=>false]];}
function csdl_compare_class_names($a,$b){return strcmp($a,$b);}function csdl_compare_person_names($a,$b){return strcmp($a,$b);}
CSDL);
