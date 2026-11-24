<?php
require __DIR__.'/../chuyenmon/includes/teacher_daily_load.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$base=['teacher'=>'An','day'=>4,'session'=>'Sáng','period'=>1,'class'=>'6A','subject'=>'Toán'];
$slots=[$base,array_merge($base,['period'=>2,'class'=>'7B','subject'=>'Văn']),array_merge($base,['session'=>'Chiều']),array_merge($base,['period'=>3,'subject'=>'Lý','_actual_teacher'=>'Bình']),array_merge($base,['teacher'=>'Duy','session'=>'PM','period'=>4,'class'=>'8A']),array_merge($base,['teacher'=>'Chi','day'=>5,'period'=>5]),array_merge($base,['teacher'=>'Bình','day'=>5]),array_merge($base,['subject'=>'Hóa','class'=>'9A'])];
$before=serialize($slots);$report=td_daily_matrix(['An','Bình','Chi','Không có lịch'],$slots,4);$rows=array_column($report['rows'],null,'teacher');
check(count($rows)===5,'Roster keeps zero-load teachers and actual teachers outside imported roster');
check($rows['An']['day']===4&&$rows['An']['morning']===3&&$rows['An']['afternoon']===1,'Morning and afternoon use separate period grids');
check($rows['Bình']['day']===1&&$rows['Bình']['cells']['morning'][3][0]['substitution'],'Approved replacement attributed to actual teacher');
check($rows['Chi']['day']===0&&!$rows['Chi']['cells']['morning'],'Other day not counted as busy today');
check($report['periods']['morning']===5&&$report['periods']['afternoon']===4,'Columns adapt to actual week, with at least four per session');
check($report['stats']['conflicts']===1&&count($rows['An']['cells']['morning'][1])===2,'Conflicting schedules preserved visibly');
$r=td_daily_matrix(['An','Bình','Chi','Không có lịch'],$slots,4,['subject'=>'Toán','class'=>'6A']);$rows=array_column($r['rows'],null,'teacher');
check(array_keys($rows)===['An','Bình','Chi'],'Subject/class cohort determined from weekly assignments');
check($rows['Bình']['day']===1&&!$rows['Bình']['cells']['morning'][3][0]['matches'],'Filtered-out busy lessons remain busy');
check($rows['An']['matched']===2&&$rows['An']['day']===4,'Filtered matches distinguished from real load');
$r=td_daily_matrix(['An','Bình','Chi'],$slots,4,['subject'=>'Toán','class'=>'6A','lessons'=>'0']);check(count($r['rows'])===1&&$r['rows'][0]['teacher']==='Chi'&&$r['stats']['free']===1&&$r['stats']['teachers']===1,'Exact zero filter and summary align');
$r=td_daily_matrix(['An'],$slots,4,['teacher'=>'An','scope'=>'afternoon','lessons'=>'1']);check(count($r['rows'])===1&&$r['rows'][0]['matched']===1&&$r['stats']['lessons']===1,'Combined teacher/session/count filter');
$r=td_daily_matrix(['An'],$slots,4,['subject'=>'Không tồn tại']);check(!$r['rows'],'Unknown filter returns empty result');
check(serialize($slots)===$before,'Read-only report never mutates timetable slots');
echo "PASS daily load matrix: sessions/periods, true free slots, filtered busy slots, weekly cohort, substitutes, conflicts, all filters and no mutation\n";
