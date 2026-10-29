<?php
/** Read-only union: one name counts once per class/date/session, regardless of periods. */
function lb_attendance_summary(array $rows): array {
 $groups=[];$students=[];$total=0;
 foreach($rows as$row){
  $date=trim((string)($row['date']??''));$class=trim((string)($row['class']??''));$session=trim((string)($row['session']??''));
  if($date===''||$class==='')continue;
  $names=preg_split('/[,;\r\n]+/u',(string)($row['absent_names']??''))?:[];
  $key=json_encode([$date,lb_norm($class),lb_norm($session)]);
  foreach($names as$name){$name=trim($name);if($name==='')continue;$nk=mb_strtolower(preg_replace('/\s+/u',' ',$name),'UTF-8');
   if(!isset($groups[$key]))$groups[$key]=['date'=>$date,'class'=>$class,'session'=>$session,'names'=>[]];
   if(!isset($groups[$key]['names'][$nk])){$groups[$key]['names'][$nk]=$name;$total++;}
   $students[lb_norm($class).'|'.$nk]=true;
  }
 }
 $groups=array_values($groups);usort($groups,fn($a,$b)=>strcmp($a['date'],$b['date'])?:strnatcasecmp($a['class'],$b['class'])?:strcmp($a['session'],$b['session']));
 foreach($groups as&$g){$g['names']=array_values($g['names']);natcasesort($g['names']);$g['names']=array_values($g['names']);}unset($g);
 return ['groups'=>$groups,'student_sessions'=>$total,'students'=>count($students)];
}
