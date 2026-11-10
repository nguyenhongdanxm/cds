<?php
$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/sd-page-'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/includes');
try{copy($root.'/hoclieu_game_secret.php',$tmp.'/hoclieu_game_secret.php');file_put_contents($tmp.'/includes/game_credit.php','');file_put_contents($tmp.'/includes/auth.php', <<<'AUTH'
<?php
$_SESSION=['secret_doors_csrf'=>'fixture-csrf','keoco_question_bank_csrf'=>'legacy-csrf'];
function require_login(){}function current_user(){return ['id'=>'fixture','role'=>'admin'];}function can_perm_level($p,$l){return true;}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}define('BASE_URL','/cds/');
AUTH
);file_put_contents($tmp.'/includes/csdl_store.php', <<<'CSDL'
<?php
function csdl_classes_all(){return [['id'=>'8a','name'=>'8A']];}function csdl_students_all(){return [['id'=>'s1','name'=>'Bạn A','class_id'=>'8a'],['id'=>'s2','name'=>'Bạn B','class_id'=>'8a']];}function csdl_compare_class_names($a,$b){return strnatcasecmp($a,$b);}
CSDL
);require $tmp.'/hoclieu_game_secret.php';}finally{foreach(glob($tmp.'/includes/*') as $f)unlink($f);unlink($tmp.'/hoclieu_game_secret.php');rmdir($tmp.'/includes');rmdir($tmp);}
