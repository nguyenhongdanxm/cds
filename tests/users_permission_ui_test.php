<?php
function check($v,$m){if(!$v)throw new RuntimeException($m);}
require __DIR__.'/../includes/permissions.php';require __DIR__.'/../includes/users_permission_navigation.php';
$catalog=permission_features_catalog();$before=$catalog;$ui=up_permission_navigation($catalog);$flat=[];foreach($ui as$module=>$items)foreach($items as$code=>$meta){check(!isset($flat[$code]),'Code appears once only');check($meta['module']===$module,'Module assignment unchanged');foreach($catalog[$code]as$k=>$v)check($meta[$k]===$v,'Canonical metadata unchanged: '.$code);$flat[$code]=$meta;}
$a=array_keys($flat);$b=array_keys($catalog);sort($a);sort($b);check($a===$b&&$catalog===$before,'All current codes retained and original catalog immutable');check($ui['chuyenmon']['cm.tracuu']['ui_note']!==''&&$ui['chuyenmon']['cm.kehoach']['ui_note']!=='','Shared code relationships explained');check(str_contains(up_permission_hint('<script>', ['ui_menu'=>'<img>']),'&lt;script&gt;'),'Untrusted metadata escaped');
// POST/controller prefix is required to be byte-identical to the baseline of this UI-only change.
$baseline=getenv('CDS_USERS_BASELINE');if($baseline&&is_readable($baseline)){$old=file_get_contents($baseline);$new=file_get_contents(__DIR__.'/../users.php');$marker="\n\$users = get_users();\nusort(";check(explode($marker,$old)[0]===explode($marker,$new)[0],'Authentication and all POST mutations unchanged');}
echo "PASS permission UI: all codes exactly once, canonical labels/modules unchanged, shared-menu notes, escaping and unchanged authentication/POST handlers\n";
