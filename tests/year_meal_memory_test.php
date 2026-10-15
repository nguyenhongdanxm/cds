<?php
/** Full-year synthetic fixture; never loads authentication or school configuration. */
require __DIR__ . '/../includes/json_store.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
require __DIR__ . '/support/load_functions.php';
$root=sys_get_temp_dir().'/cds-year-memory-'.bin2hex(random_bytes(8));mkdir($root,0700);
define('NOITRU_DIR',$root);define('NOITRU_MEALS',$root.'/meals.json');define('NOITRU_MEAL_REPORTS',$root.'/reports.json');
function noitru_ensure_dir(){}
function noitru_now(){return '2027-06-30T10:00:00+07:00';}
function noitru_uid($prefix){return $prefix.'_'.bin2hex(random_bytes(8));}
function cds_meal_pending_status(){return null;}
function cds_meal_shadow_daily_row($row){return true;}
function cds_meal_shadow_notify_failure(){throw new RuntimeException('Unexpected mirror failure');}
load_functions(__DIR__.'/../includes/noitru_store.php',['noitru_meal_write','noitru_meal_read_cache_clear','noitru_meals_replace_rows','noitru_meal_upsert_many','noitru_meal_upsert_many_unlocked']);
$dates=[];$first=new DateTimeImmutable('2026-08-01');for($d=0;$d<334;$d++)$dates[]=$first->modify('+'.$d.' days')->format('Y-m-d');
function fixture_row($date,$student){return ['id'=>$date.'-'.$student,'date'=>$date,'student_id'=>'s'.$student,'sang'=>'yes','trua'=>'yes','toi'=>'yes','locked'=>false,'note'=>'Giữ nguyên tiếng Việt 😀','custom'=>['value'=>$student,'bool'=>true]];}
try{
    $rows=(static function()use($dates){foreach($dates as$date)for($s=0;$s<400;$s++)yield fixture_row($date,$s);})();
    check(cds_json_rows_save(NOITRU_MEALS,$rows),'Create full-year fixture incrementally');
    $updates=[];foreach(['2027-06-29','2027-06-30']as$date)for($s=0;$s<30;$s++)$updates[]=['date'=>$date,'student_id'=>'s'.$s,'sang'=>'no'];
    $start=microtime(true);check(noitru_meal_upsert_many($updates),'One atomic class batch on full-year history');$seconds=microtime(true)-$start;
    $count=0;
    foreach(cds_json_rows(NOITRU_MEALS)as$row){
        $date=$dates[intdiv($count,400)];$student=$count%400;$expected=fixture_row($date,$student);
        if(in_array($date,['2027-06-29','2027-06-30'],true)&&$student<30){$expected['sang']='no';$expected['updated_at']=noitru_now();}
        check($row===$expected,'Every original field/order/row preserved except targeted changes at '.$count);$count++;
    }
    check($count===133600,'Complete August through June history retained');
    $peak=memory_get_peak_usage(true)/1048576;check($peak<64,'Memory must remain bounded under 64 MiB');
    echo 'PASS full year: '.$count.' rows, 60 updates in one batch; peak '.round($peak,1).' MiB; '.round($seconds,3)." seconds (CI fixture only)\n";
}finally{foreach(glob($root.'/*')as$p)if(is_file($p))unlink($p);@unlink($root.'/.meal-write.lock');rmdir($root);}
