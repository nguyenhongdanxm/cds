<?php
require __DIR__.'/../chuyenmon/includes/timetable_activity_view.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$rows=[];foreach([2,3,5]as$p)foreach(['6A','6B','7A','7B','8A','8B','11B']as$c)$rows[]=['day'=>5,'session'=>'Chiều','period'=>$p,'subject'=>'Sinh hoạt <CLB>','_special'=>true,'_special_scope'=>'Lớp '.$c];
$before=serialize($rows);$compact=tkb_activity_compact_rows($rows);
check(count($compact)===3,'One card per activity/time');check(tkb_activity_scope($compact[0])==='7 lớp','Scope count');
check(count(tkb_activity_compact_rows($compact))===3,'Idempotent grouping');
$lesson=['day'=>5,'session'=>'Chiều','period'=>2,'subject'=>'Toán'];check(count(tkb_activity_compact_rows([...$rows,$lesson,$lesson]))===5,'Keep all actual lessons');
ob_start();tkb_activity_agenda($rows,'teacher');$html=ob_get_clean();check(substr_count($html,'<li>')===1,'One agenda event');check(str_contains($html,'Tiết 2–3, 5'),'Contiguous ranges only');check(str_contains($html,'Tham khảo'),'Teacher participation not inferred');check(str_contains($html,'&lt;CLB&gt;'),'Escape activity');
$card=tkb_activity_card($compact[0],'teacher');check(str_contains($card,'7 lớp</small></summary>'),'Collapsed scope visible');check(str_contains($card,'Lớp 11B'),'All scopes accessible');
$card=tkb_activity_card($compact[0],'class');check(!str_contains($card,'Lớp 11B'),'No repeated class list in class view');
check(serialize($rows)===$before,'Source immutable');
$other=$rows[0];$other['subject']='Hoạt động khác';check(count(tkb_activity_compact_rows([...$rows,$other]))===4,'Distinct activities preserved');
echo "Activity view assertions passed\n";
