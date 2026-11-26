<?php
require __DIR__.'/../includes/learning_dashboard.php';require __DIR__.'/../includes/learning_game_catalog.php';
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$catalog=lh_game_catalog();$items=[];for($i=0;$i<9;$i++)$items[]=['id'=>'r'.$i,'section'=>'resource','title'=>'Resource '.$i,'approved'=>true,'featured'=>$i===1,'created_at'=>'2026-11-'.sprintf('%02d',$i+1)];$items[]=['section'=>'exam','title'=>'Exam','approved'=>true];
$all=['resources'=>true,'exams'=>true,'games'=>true,'links'=>true];$d=lh_dashboard_model($items,[['title'=>'Link']],$catalog,['secret'=>0,'quiz'=>false],true,$all,false);
check(array_keys($d)===['resources','exams','games','links'],'Independent sections');check($d['resources']['count']===9&&count($d['resources']['items'])===6,'Preview bounded, full count retained');check($d['resources']['items'][0]['id']==='r1'&&$d['resources']['items'][1]['id']==='r8','Featured first, then newest');check($d['exams']['count']===1,'Exam section is separate');check($d['games']['count']===15&&count($d['games']['items'])===8,'Visible game preview');check(!array_intersect(['secret','quiz'],array_column($d['games']['items'],'key')),'Hidden games omitted');
$admin=lh_dashboard_model([],[],$catalog,['secret'=>0],true,$all,true);check($admin['games']['count']===17,'Admin gallery parity');
$examOnly=lh_dashboard_model($items,[],$catalog,[],true,['exams'=>true],false);check(array_keys($examOnly)===['exams'],'No resource/link/game leak to exam-only account');
check(lh_dashboard_model($items,[],$catalog,[],true,[],false)===[],'No permissions means no content');
$failure=lh_dashboard_model([],[],$catalog,[],false,$all,false);check($failure['games']['items']===[]&&$failure['games']['unavailable'],'Database failure does not reveal hidden games');
$empty=lh_dashboard_model([],[],[],[],true,$all,false);check($empty['resources']['count']===0&&$empty['exams']['items']===[]&&!$empty['games']['unavailable'],'Empty sections remain present');
foreach(['javascript:alert(1)','data:text/html,x','//evil.test','https://user:pass@example.com','https://example.com/ bad',"/\\evil.test"]as$url)check(lh_dashboard_safe_url($url)==='#','Unsafe preview URL rejected');foreach(['/cds/hoclieu_file.php?id=1','https://example.com/file','http://example.com/file']as$url)check(lh_dashboard_safe_url($url)===$url,'Safe content URL retained');
echo "PASS learning dashboard: section permissions, featured/recent fallback, counts/limits, game visibility, database failure, empty states and safe links\n";
