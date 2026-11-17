<?php
require_once __DIR__.'/../includes/csdl_statistics_filters.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$map=['c6'=>['id'=>'c6','name'=>'6A','grade'=>6],'c10'=>['id'=>'c10','name'=>'10A','grade'=>10]];
$t=['id'=>'t','name'=>'Nguyễn Lan','teaching_level'=>'THCS, THPT','gender'=>'nữ','ethnicity'=>'Tày','specialty'=>'Toán','phone'=>''];
$s=['id'=>'s','name'=>'Học sinh A','class_id'=>'c6','gender'=>'Nam','boarder'=>false,'parent_phone'=>''];
foreach(['THCS','THPT'] as $level)check(csdl_sf_match($t,true,$map,csdl_sf_filters(['stat_level'=>$level])),'Shared teacher must belong to both levels');
check(csdl_sf_level($map['c6'])==='THCS'&&csdl_sf_level($map['c10'])==='THPT','Grade boundaries');
check(csdl_sf_level(['grade'=>5])==='unknown','Unknown grade must not be guessed');
check(!csdl_sf_match($s,false,$map,csdl_sf_filters(['stat_level'=>'THPT'])),'Student level isolation');
check(csdl_sf_match($s,false,$map,csdl_sf_filters(['stat_boarder'=>'no'])),'Non-boarder filter');
check(!csdl_sf_match($s,false,$map,csdl_sf_filters(['stat_boarder'=>'yes'])),'Boarder exclusion');
check(csdl_sf_match($s,false,$map,csdl_sf_filters(['stat_specialty'=>'Toán'])),'Teacher-only filter must not affect students');
check(csdl_sf_match($t,true,$map,csdl_sf_filters(['stat_room'=>'A1'])),'Student-only filter must not affect teachers');
check(csdl_sf_match($t,true,$map,csdl_sf_filters(['stat_gender'=>'Nữ','stat_specialty'=>'Toán','stat_missing'=>'teachers:phone'])),'Combined filters');
check(!csdl_sf_match($t,true,$map,csdl_sf_filters(['stat_gender'=>'Nam'])),'Gender filtering');
check(csdl_sf_match(['gender'=>''],true,$map,csdl_sf_filters(['stat_missing'=>'teachers:gender'])),'Missing gender');
check(!csdl_sf_match($t,true,$map,csdl_sf_filters(['stat_missing'=>'teachers:gender'])),'Present gender');
check(csdl_sf_match($s,false,$map,csdl_sf_filters(['stat_q'=>'6A'])),'Class name search');
check(!csdl_sf_match($s,false,[] ,csdl_sf_filters(['stat_level'=>'THCS'])),'Do not infer inaccessible class');
check(csdl_sf_filters(['stat_q'=>['invalid']])['stat_q']==='','Reject non-scalar inputs');
// Render the actual view with fixtures; warnings are failures.
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function teacher_name_by_id($id,$teachers){return '';}
define('BASE_URL','/');$canCsdlExport=true;
$teachers=[$t,array_merge($t,['id'=>'t2','teaching_level'=>'THCS','gender'=>'Nam','active'=>true])];$teachers[0]['active']=true;
$classes=array_values($map);$students=[$s,array_merge($s,['id'=>'s2','class_id'=>'c10','boarder'=>true,'gender'=>'Nữ'])];foreach($students as &$r)$r['active']=true;unset($r);
foreach([[],['stat_level'=>'THCS'],['stat_level'=>'THPT','stat_gender'=>'Nữ'],['stat_missing'=>'students:parent_phone'],['stat_q'=>'<script>'],['stat_scope'=>'teachers','stat_level'=>'both']] as $query){
 $_GET=$query;ob_start();include __DIR__.'/../includes/csdl_tab_statistics.php';$html=ob_get_clean();
 check(strpos($html,'So sánh theo cấp học')!==false,'Summary missing');
 check(strpos($html,'<script>\"')===false,'Escaping failure');
 foreach(csdl_sf_filters($query) as $key=>$value)if($value!=='')check(strpos($exportQuery,rawurlencode($key).'='.urlencode($value))!==false,'Export lost filter '.$key);
}
echo "PASS: combined filters, level isolation, shared teachers, missing data, scope, view rendering, export query\n";
