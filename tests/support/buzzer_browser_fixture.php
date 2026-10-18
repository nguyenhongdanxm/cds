<?php
/** Test-only renderer. No application credentials, configuration or database. */
function e($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function require_login(){}
function current_user(){return ['id'=>'fixture','role'=>'admin'];}
function can_perm_level($key,$level){return true;}
function qp_game_visibility(){return [];}
define('BASE_URL','/');define('SCHOOL_NAME','Trường kiểm thử');$_SESSION=[];$_GET=['view'=>$argv[1]??'host'];
$source=file_get_contents(__DIR__.'/../../hoclieu_game_buzzer.php');
foreach(["require_once __DIR__.'/includes/auth.php';","require_once __DIR__.'/includes/quiz_paper_store.php';"]as$line)$source=str_replace($line,'',$source);
$source=str_replace("__DIR__.'/includes/buzzer_access.php'",var_export(realpath(__DIR__.'/../../includes/buzzer_access.php'),true),$source);
eval('?>'.$source);
