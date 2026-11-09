<?php
$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/kc-page-'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/includes');
try{copy($root.'/hoclieu_game_keoco.php',$tmp.'/hoclieu_game_keoco.php');file_put_contents($tmp.'/includes/game_credit.php','');file_put_contents($tmp.'/includes/auth.php', <<<'AUTH'
<?php
$_SESSION=['keoco_question_bank_csrf'=>'fixture-csrf'];
function require_login(){}
function current_user(){return ['id'=>'fixture','role'=>'admin'];}
function can_perm_level($p,$l){return true;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
define('BASE_URL','/cds/');
AUTH
);require $tmp.'/hoclieu_game_keoco.php';}finally{unlink($tmp.'/hoclieu_game_keoco.php');unlink($tmp.'/includes/auth.php');unlink($tmp.'/includes/game_credit.php');rmdir($tmp.'/includes');rmdir($tmp);}
