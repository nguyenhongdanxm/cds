<?php
$_SERVER['REQUEST_METHOD']='GET';$_SESSION=[];
$_GET=['tab'=>'numbers','export_numbers'=>'csv','number_book'=>'decision','number_q'=>'match','number_sort'=>'issued_date','number_dir'=>$argv[2]??'desc'];
$GLOBALS['fixture_allowed']=($argv[1]??'allowed')==='allowed';
function require_login(){}
function current_user(){return ['role'=>'teacher','name'=>'GV thử nghiệm'];}
function can_perm_level($key,$level){return $GLOBALS['fixture_allowed']&&$key==='vb.layso';}
function can_module($module,$level){return false;}
function get_users(){return [];}
function vb_user_key($user){return 'fixture';}
function vb_engagement_group_labels(){return [];}
function vb_norm($s){return strtolower(trim($s));}
function save_json(...$args){throw new RuntimeException('Export must never write');}
foreach(['DOCUMENTS','NUMBERS','ARCHIVES','POLLS','SURVEYS','FEEDBACK']as$type)define('VANBAN_'.$type.'_FILE',$type);
function vb_rows($file){if($file!=='NUMBERS')return [];return [
 ['symbol'=>'1/QĐ','title'=>'match first','book'=>'decision','number'=>1,'issued_date'=>'2026-10-20'],
 ['symbol'=>'2/QĐ','title'=>'match second','book'=>'decision','number'=>2,'issued_date'=>'2026-10-21'],
 ['symbol'=>'3/QĐ','title'=>'unrelated','book'=>'decision','number'=>3,'issued_date'=>'2026-10-22'],
 ['symbol'=>'1/KH','title'=>'match other book','book'=>'other','number'=>1,'issued_date'=>'2026-10-23']];}
$path=__DIR__.'/../../vanban.php';$s=file_get_contents($path);
foreach(['auth','vanban_store','vanban_engagement','push_notifications']as$name)$s=str_replace("require_once __DIR__ . '/includes/".$name.".php';",';',$s);
$s=str_replace('__DIR__',var_export(realpath(dirname($path)),true),$s);eval('?>'.$s);
