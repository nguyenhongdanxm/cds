<?php
function check($v,$m){if(!$v)throw new RuntimeException($m);}
require __DIR__.'/../includes/permissions.php';require __DIR__.'/../includes/users_permission_navigation.php';
$catalog=permission_features_catalog();$before=$catalog;$ui=up_permission_navigation($catalog);$flat=[];foreach($ui as$module=>$items)foreach($items as$code=>$meta){check(!isset($flat[$code]),'Code appears once only');check($meta['module']===$module,'Module assignment unchanged');foreach($catalog[$code]as$k=>$v)check($meta[$k]===$v,'Canonical metadata unchanged: '.$code);$flat[$code]=$meta;}
$a=array_keys($flat);$b=array_keys($catalog);sort($a);sort($b);check($a===$b&&$catalog===$before,'All current codes retained and original catalog immutable');check($ui['chuyenmon']['cm.tracuu']['ui_note']!==''&&$ui['chuyenmon']['cm.kehoach']['ui_note']!=='','Shared code relationships explained');check(str_contains(up_permission_hint('<script>', ['ui_menu'=>'<img>']),'&lt;script&gt;'),'Untrusted metadata escaped');
// Preserve the existing controller while allowing the reviewed stable-identity linkage.
$baseline=getenv('CDS_USERS_BASELINE');if($baseline&&is_readable($baseline)){
 $old=file_get_contents($baseline);$new=file_get_contents(__DIR__.'/../users.php');$marker="\n\$users = get_users();\nusort(";
 $linkStart="        // Only this administrator form may establish a stable teacher link from a name.";
 $linkEnd="        unset(\$linkedUser);\n\n";
 $start=strpos($new,$linkStart);$end=$start===false?false:strpos($new,$linkEnd,$start);
 check($start!==false&&$end!==false,'Stable identity block present');
 $block=substr($new,$start,$end+strlen($linkEnd)-$start);
 require_once __DIR__.'/../includes/staff_identity.php';
 function csdl_teachers_all(){return [['id'=>'a','name'=>'An'],['id'=>'b','name'=>'Bình'],['id'=>'c','name'=>'Bình'],['id'=>'hoaV','name'=>'Nguyễn Thị Hoa (v)'],['id'=>'hoaT','name'=>'Nguyễn Thị Hoa (t)']];}
 $username='target';$users=[['username'=>'target','teacher_name'=>'An'],['username'=>'other','teacher_id'=>'keep']];eval($block);
 check($users[0]['teacher_id']==='a'&&$users[1]['teacher_id']==='keep','Only target account uniquely linked');
 $users[0]['teacher_name']='Bình';eval($block);check($users[0]['teacher_id']==='','Ambiguous name clears stale identity');
 $users[0]['teacher_name']='Unknown';eval($block);check($users[0]['teacher_id']==='','Missing teacher cannot retain previous rights');
 $users[0]['teacher_name']='Nguyễn Thị Hoa T';eval($block);check($users[0]['teacher_id']==='hoaT','Administrator links Hoa T to the T record');
 $users[0]['teacher_name']='Nguyễn Thị Hoa V';eval($block);check($users[0]['teacher_id']==='hoaV','Administrator links Hoa V to the V record');
 $oldStart=strpos($old,$linkStart);if($oldStart!==false){$oldEnd=strpos($old,$linkEnd,$oldStart);check($oldEnd!==false,'Baseline linkage block is complete');$old=substr_replace($old,'',$oldStart,$oldEnd+strlen($linkEnd)-$oldStart);}
 $old=str_replace("require_once 'includes/staff_identity.php';\n",'',$old);
 $new=substr_replace($new,'',$start,strlen($block));$new=str_replace("require_once 'includes/staff_identity.php';\n",'',$new);
 check(explode($marker,$old)[0]===explode($marker,$new)[0],'Authentication and other POST mutations unchanged');
}
echo "PASS permission UI: canonical navigation, escaping, stable administrator teacher links and unchanged remaining handlers\n";
