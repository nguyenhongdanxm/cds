<?php
/** Full-year synthetic fixture; never loads authentication or school configuration. */
require __DIR__ . '/../includes/json_store.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
require __DIR__ . '/support/load_functions.php';
require __DIR__ . '/../includes/noitru_report_stream.php';
$root=sys_get_temp_dir().'/cds-year-memory-'.bin2hex(random_bytes(8));mkdir($root,0700);
define('NOITRU_DIR',$root);define('NOITRU_MEALS',$root.'/meals.json');define('NOITRU_MEAL_REPORTS',$root.'/reports.json');
function noitru_ensure_dir(){}
function noitru_now(){return '2027-06-30T10:00:00+07:00';}
function noitru_uid($prefix){return $prefix.'_'.bin2hex(random_bytes(8));}
function cds_meal_pending_status(){return null;}
function cds_meal_shadow_daily_row($row){return true;}
function cds_meal_shadow_reports_data($data){foreach($data['reports'] as $row){} return true;}
function cds_meal_sql_read_effective(){return false;}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function save_json($file,$data){return cds_json_save($file,$data);}
function cds_meal_shadow_notify_failure(){throw new RuntimeException('Unexpected mirror failure');}
load_functions(__DIR__.'/../includes/noitru_store.php',['noitru_meal_write','noitru_meal_read_cache_clear','noitru_meals_replace_rows','noitru_meal_upsert_many','noitru_meal_upsert_many_unlocked','noitru_meal_target_key','noitru_meal_deleted_targets','noitru_meal_deleted_targets_save','noitru_meal_deleted_targets_save_unlocked','noitru_meal_report_snapshot']);
$dates=[];$first=new DateTimeImmutable('2026-08-01');for($d=0;$d<334;$d++)$dates[]=$first->modify('+'.$d.' days')->format('Y-m-d');
function fixture_row($date,$student){return ['id'=>$date.'-'.$student,'date'=>$date,'student_id'=>'s'.$student,'sang'=>'yes','trua'=>'yes','toi'=>'yes','locked'=>false,'note'=>'Giữ nguyên tiếng Việt 😀','custom'=>['value'=>$student,'bool'=>true]];}
try{
    $rows=(static function()use($dates){foreach($dates as$date)for($s=0;$s<400;$s++)yield fixture_row($date,$s);})();
    check(cds_json_rows_save(NOITRU_MEALS,$rows),'Create full-year fixture incrementally');
    // Every report keeps its original historical pupil snapshot, not just a count.
    function report_fixture($date,$class,$meal){
        $students=[];for($i=0;$i<($class<8?29:28);$i++)$students[]=['id'=>'s'.$class.'-'.$i,'name'=>'Học sinh giả lập '.$i,'class_name'=>'L'.$class,'meal_group'=>'Nội trú','admission_date'=>'2026-08-01','departure_date'=>''];
        return ['id'=>$date.'-'.$class.'-'.$meal,'date'=>$date,'class_name'=>'L'.$class,'meal'=>$meal,'student_count'=>count($students),'eat_count'=>count($students),'student_snapshot'=>$students,'unknown'=>['preserve'=>true]];
    }
    check(cds_json_field_save(NOITRU_MEAL_REPORTS,'reports',static function($old)use($dates){foreach($dates as$date)for($c=0;$c<14;$c++)foreach(['sang','trua','toi']as$meal)yield report_fixture($date,$c,$meal);}), 'Full-year historical snapshots');
    check(cds_json_field_save(NOITRU_MEAL_REPORTS,'settings',static fn($old)=>['sang_lock_time'=>'20:00','trua_lock_time'=>'09:00','toi_lock_time'=>'15:00'],true),'Report settings');
    check(cds_json_field_save(NOITRU_MEAL_REPORTS,'states',static fn($old)=>[['date'=>'2027-06-30','meal'=>'toi','status'=>'off']]),'Historical state');
    $updates=[];foreach(['2027-06-29','2027-06-30']as$date)for($s=0;$s<30;$s++)$updates[]=['date'=>$date,'student_id'=>'s'.$s,'sang'=>'no'];
    $start=microtime(true);check(noitru_meal_upsert_many($updates),'One atomic class batch on full-year history');$seconds=microtime(true)-$start;
    $count=0;
    foreach(cds_json_rows(NOITRU_MEALS)as$row){
        $date=$dates[intdiv($count,400)];$student=$count%400;$expected=fixture_row($date,$student);
        if(in_array($date,['2027-06-29','2027-06-30'],true)&&$student<30){$expected['sang']='no';$expected['updated_at']=noitru_now();}
        check($row===$expected,'Every original field/order/row preserved except targeted changes at '.$count);$count++;
    }
    check($count===133600,'Complete August through June history retained');
    $target=report_fixture('2027-06-30',13,'toi');$target['eat_count']=3;
    check(noitru_meal_report_upsert_many([$target]),'Bounded full-year report update');
    $reportCount=0;$snapshotCount=0;
    foreach(cds_json_field_rows(NOITRU_MEAL_REPORTS,'reports')as$row){
        $d=intdiv($reportCount,42);$c=intdiv($reportCount%42,3);$meal=['sang','trua','toi'][$reportCount%3];$expected=report_fixture($dates[$d],$c,$meal);
        if($row['id']===$target['id']){$expected['eat_count']=3;$expected['updated_at']=noitru_now();}
        check($row===$expected,'Every historical snapshot/unknown field preserved at '.$reportCount);
        $snapshotCount+=count($row['student_snapshot']);$reportCount++;
    }
    check($reportCount===14028&&$snapshotCount===400800,'All year reports and student snapshots retained');
    check(cds_json_field_value(NOITRU_MEAL_REPORTS,'states')===[['date'=>'2027-06-30','meal'=>'toi','status'=>'off']],'State unchanged by report update');
    echo 'PASS full-year reports: '.$reportCount.' reports, '.$snapshotCount.' historical student snapshots retained'.PHP_EOL;
    $peak=memory_get_peak_usage(true)/1048576;check($peak<64,'Memory must remain bounded under 64 MiB');
    echo 'PASS full year: '.$count.' rows, 60 updates in one batch; peak '.round($peak,1).' MiB; '.round($seconds,3)." seconds (CI fixture only)\n";
}finally{foreach(glob($root.'/*')as$p)if(is_file($p))unlink($p);@unlink($root.'/.meal-write.lock');rmdir($root);}
