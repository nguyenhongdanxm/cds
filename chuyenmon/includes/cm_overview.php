<?php
/** Read-only overview. Shared CSDL is authoritative; never seed missing data. */
function cmov_roster(string $entity):?array {
 if(!in_array($entity,['teachers','classes','students'],true))return null;
 $root=dirname(__DIR__,2);$reader=$root.'/includes/database_sql_read.php';if(is_file($reader))require_once $reader;
 if(function_exists('cds_core_sql_rows')){try{$rows=cds_core_sql_rows($entity);if(is_array($rows))return array_values(array_filter($rows,'is_array'));}catch(Throwable $e){error_log('[CDS CM overview] Shared roster SQL unavailable');}}
 $path=$root.'/data/'.$entity.'.json';if(!is_readable($path))return null;$raw=file_get_contents($path);$rows=is_string($raw)?json_decode($raw,true):null;return is_array($rows)?array_values(array_filter($rows,'is_array')):null;
}
function cmov_count_active(?array $rows,string $today,bool $students=false):?int {
 if($rows===null)return null;$ids=[];foreach($rows as$i=>$r){if(array_key_exists('active',$r)&&empty($r['active']))continue;if($students&&!empty($r['departure_date'])&&(string)$r['departure_date']<=$today)continue;$id=(string)($r['id']??'');$ids[$id!==''?'id:'.$id:'row:'.$i]=true;}return count($ids);
}
function cmov_select_week(array $weeks,string $key,string $today):?array {
 if(!$weeks)return null;foreach($weeks as$w)if($key!==''&&(string)$w['key']===$key)return $w;
 foreach($weeks as$w)if($today>=(string)$w['start']&&$today<=(string)$w['end'])return $w;
 usort($weeks,fn($a,$b)=>abs(strtotime($a['start'])-strtotime($today))<=>abs(strtotime($b['start'])-strtotime($today)));return $weeks[0];
}
function cmov_week_rows(array $week):array {
 // Project only the selected week's timetable; no full PPCT derivation or past/future weeks.
 $saved=lb_record_map_range((string)$week['start'],(string)$week['end']);$all=array_values($saved);$matched=[];$rows=[];
 foreach(lb_slots($week,[],true)as$base){$old=lb_saved_row_for_base($saved,$all,$base,$matched);$row=array_merge($base,$old);if(!empty($old['signed_snapshot'])&&is_array($old['signed_snapshot']))$row=array_merge($row,$old['signed_snapshot']);$rows[]=$row;}
 $rows=lb_merge_independent_records($rows,$all,$week);
 return array_values(array_filter($rows,fn($r)=>(string)($r['date']??'')>=(string)$week['start']&&(string)($r['date']??'')<=(string)$week['end']&&lb_stat_can_view($r)));
}
function cmov_week_summary(array $rows):array {
 $unique=[];foreach($rows as$r){$id=(string)($r['slot_id']??'');$key=$id!==''?'id:'.$id:json_encode([$r['date']??'',$r['session']??'',$r['period']??'',$r['class']??'',$r['subject']??'']);$unique[$key]=$r;}
 $taught=[];foreach($unique as$r)if(!empty($r['signed_at'])&&in_array(lb_stat_status($r),['taught','substitute','makeup','online','fill'],true))$taught[]=$r;
 $attendance=lb_attendance_summary($taught);$total=count($unique);return ['total'=>$total,'taught'=>count($taught),'percent'=>$total?round(count($taught)*100/$total):0,'students_absent'=>$attendance['students'],'absence_sessions'=>$attendance['student_sessions']];
}
function cmov_sub_summary(array $registrations,array $week,callable $visible):array {
 $counts=['substitution'=>0,'makeup'=>0,'fill'=>0];$seen=[];
 foreach($registrations as$r){$date=(string)($r['date']??'');if(tkb_substitution_status($r)!=='approved'||$date<(string)$week['start']||$date>(string)$week['end']||!$visible($r))continue;
  $id=(string)($r['id']??'');$key=$id!==''?'id:'.$id:json_encode([$date,$r['session']??'',$r['period']??'',$r['class']??'',$r['substitute_teacher']??'']);if(isset($seen[$key]))continue;$seen[$key]=true;
  $kind=tkb_key((string)($r['registration_type']??$r['type']??$r['kind']??''));$type=str_contains($kind,'fill')||str_contains($kind,'laptiet')?'fill':(str_contains($kind,'makeup')||str_contains($kind,'daybu')?'makeup':'substitution');$counts[$type]++;
 }return $counts;
}
function cmov_recent_reports(array $rows,array $scope):array {
 $rows=array_values(array_filter($rows,fn($r)=>is_array($r)&&pr_visible($r,$scope)));
 usort($rows,fn($a,$b)=>strcmp((string)($b['updated_at']??$b['created_at']??$b['date']??''),(string)($a['updated_at']??$a['created_at']??$a['date']??''))?:strcmp((string)($b['id']??''),(string)($a['id']??'')));return array_slice($rows,0,4);
}
