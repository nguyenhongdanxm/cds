<?php
/** Isolated synthetic benchmark and exact-output regression. No config/auth/DB. */
require __DIR__.'/../includes/noitru_meal_month_export.php';
require __DIR__.'/support/meal_month_sheet_reference.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function noitru_meal_report_rows():Generator{$GLOBALS['reads']['report_passes']++;foreach($GLOBALS['reports']as$r){$GLOBALS['reads']['report_rows']++;yield$r;}}
function noitru_meal_report_for($date,$class,$meal){foreach(noitru_meal_report_rows()as$r)if($r['date']===$date&&$r['class_name']===$class&&$r['meal']===$meal)return$r;return null;}
function noitru_meals_for_date($date){if(isset($GLOBALS['daily_cache'][$date]))return$GLOBALS['daily_cache'][$date];$GLOBALS['reads']['daily_scans']++;$map=[];foreach($GLOBALS['meals']as$r)if($r['date']===$date)$map[$r['student_id']]=$r;return$GLOBALS['daily_cache'][$date]=$map;}
function noitru_meals_for_range($from,$to){$GLOBALS['reads']['range_scans']++;return array_values(array_filter($GLOBALS['meals'],fn($r)=>$r['date']>=$from&&$r['date']<=$to));}
function noitru_meal_state($date,$meal){$GLOBALS['reads']['state_calls']++;return ['status'=>(int)substr($date,-2)%7===0&&$meal==='toi'?'off':'locked'];}
function reset_reads(){$GLOBALS['reads']=['report_passes'=>0,'report_rows'=>0,'daily_scans'=>0,'range_scans'=>0,'state_calls'=>0];$GLOBALS['daily_cache']=[];}
$classes=[];for($c=0;$c<14;$c++){for($i=0;$i<4;$i++)$classes['Lớp '.$c][]=['id'=>'s'.$c.'-'.$i,'name'=>'HS '.$c.' '.$i,'class_name'=>'Lớp '.$c,'admission_date'=>$i===1?'2027-06-10':'','departure_date'=>$i===2?'2027-06-20':''];}
$month='2027-06';$GLOBALS['reports']=[];$GLOBALS['meals']=[];
for($d=new DateTimeImmutable('2026-08-01');$d<=new DateTimeImmutable('2027-06-30');$d=$d->modify('+1 day')){
 $date=$d->format('Y-m-d');foreach($classes as$class=>$students){foreach(['sang','trua','toi']as$k)if(!($d->format('d')==='03'&&$k==='trua'))$GLOBALS['reports'][]=['date'=>$date,'class_name'=>$class,'meal'=>$k];
 if(substr($date,0,7)===$month)foreach($students as$i=>$s)$GLOBALS['meals'][]=['date'=>$date,'student_id'=>$s['id'],'sang'=>$i%2?'no':'yes','trua'=>$i%3?'yes':'no','toi'=>$i%2?'yes':'no'];}
}
$before=hash('sha256',serialize([$classes,$GLOBALS['meals'],$GLOBALS['reports']]));$baseline=[];reset_reads();$start=microtime(true);
foreach(['breakfast','lunch_dinner']as$type){$baseline[$type]['school']=reference_meal_summary_xml($classes,$month,$type,'2026-2027');foreach($classes as$class=>$students)$baseline[$type][$class]=reference_meal_sheet_xml($class,$students,$month,$type,'2026-2027','GV chủ nhiệm');}
$oldTime=microtime(true)-$start;$oldReads=$GLOBALS['reads'];reset_reads();$start=microtime(true);$context=nt_xlsx_month_context($month);
foreach(['breakfast','lunch_dinner']as$type){check($baseline[$type]['school']===nt_xlsx_school_summary_xml($classes,$month,$type,'2026-2027',$context),'School totals/layout byte-identical '.$type);foreach($classes as$class=>$students)check($baseline[$type][$class]===nt_xlsx_sheet_xml($class,$students,$month,$type,'2026-2027','GV chủ nhiệm',$context),'Class symbols/totals/layout byte-identical '.$class.' '.$type);}
$newTime=microtime(true)-$start;$newReads=$GLOBALS['reads'];
check($newReads['report_passes']===1&&$newReads['range_scans']===1&&$newReads['daily_scans']===0,'Single report pass and single month meal read');
check($newReads['state_calls']===90,'States resolved once per day/meal');
check(hash('sha256',serialize([$classes,$GLOBALS['meals'],$GLOBALS['reports']]))===$before,'No source mutation');
// Empty leap month and no reports: same blanks, 29-day boundary and sheet layout.
$GLOBALS['reports']=[];$GLOBALS['meals']=[];reset_reads();$context=nt_xlsx_month_context('2028-02');
check(reference_meal_sheet_xml('6A',[], '2028-02','breakfast','2027-2028')===nt_xlsx_sheet_xml('6A',[],'2028-02','breakfast','2027-2028','',$context),'Leap month empty output retained');
echo json_encode(['result'=>'PASS exact sheet XML: 14 classes, breakfast/lunch/dinner, meal-off, absent class reports, admissions/departures, leap month and unchanged source','old_seconds'=>round($oldTime,4),'new_seconds'=>round($newTime,4),'old_reads'=>$oldReads,'new_reads'=>$newReads],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";

function csdl_compare_class_names($a,$b){return strnatcasecmp($a,$b);}
function csdl_teachers_all(){return [];}
function csdl_classes_all(){return [];}
function cds_drive_save_generated($bytes,$name,$mime,$category){$GLOBALS['workbook_bytes']=$bytes;return ['ok'=>true,'name'=>$name,'mime'=>$mime];}
reset_reads();$students=[];foreach($classes as$list)$students=array_merge($students,$list);
$result=nt_export_meal_month_xlsx($students,'2027-06','lunch_dinner','Fixture',true);
check($result['ok']&&str_starts_with($GLOBALS['workbook_bytes'],"PK\x03\x04"),'Actual exporter builds XLSX without real Drive');
check($GLOBALS['reads']['report_passes']===1&&$GLOBALS['reads']['range_scans']===1,'Actual multi-sheet exporter shares one month context');
echo "PASS full workbook export: single shared context, valid ZIP signature, isolated fake Drive sink\n";
