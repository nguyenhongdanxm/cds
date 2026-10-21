<?php
$_SESSION=[];$_SERVER['REQUEST_METHOD']='GET';define('BASE_URL','/');
function require_login(){}
function require_perm($p){}
function csdl_classes_all(){return [['id'=>'c1','name'=>'6A','grade'=>6,'active'=>true],['id'=>'c2','name'=>'7A','grade'=>7,'active'=>true]];}
function csdl_sort_classes(&$rows){}
function csdl_year_current(){return ['label'=>'2026 - 2027'];}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$source=file_get_contents(__DIR__.'/../../csdl_student_cards.php');
foreach(['auth','student_card_store']as$name)$source=str_replace("require_once __DIR__ . '/includes/".$name.".php';",';',$source);
eval('?>'.$source);
