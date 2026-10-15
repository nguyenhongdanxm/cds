<?php
/** Synthetic reference-model checks for the historical first-match/merge rules. */
require __DIR__.'/../includes/json_store.php';
require __DIR__.'/../includes/noitru_report_stream.php';
require __DIR__.'/support/load_functions.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/cds-report-parity-'.bin2hex(random_bytes(8));mkdir($root,0700);
define('NOITRU_DIR',$root);define('NOITRU_MEALS',$root.'/daily.json');define('NOITRU_MEAL_REPORTS',$root.'/reports.json');
function noitru_ensure_dir(){}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function save_json($file,$data){return cds_json_save($file,$data);}
function noitru_now(){return '2026-10-15T12:00:00+07:00';}
function noitru_uid($prefix){return $prefix.'_fixture';}
function cds_meal_sql_read_effective(){return false;}
function cds_meal_pending_status(){return null;}
function cds_meal_shadow_reports_data($data){foreach($data['reports']as$row){}return true;}
function cds_meal_shadow_notify_failure(){throw new RuntimeException('Unexpected SQL fallback');}
function noitru_boarders_on_date($date){return [['id'=>'p1','name'=>'Việt','class_name'=>'6A','custom'=>'not snapshotted'],['id'=>'p2','class_name'=>'7A']];}
load_functions(__DIR__.'/../includes/noitru_store.php',['noitru_meal_write','noitru_meal_read_cache_clear','noitru_meal_target_key','noitru_meal_deleted_targets','noitru_meal_deleted_targets_save','noitru_meal_deleted_targets_save_unlocked','noitru_meal_report_snapshot']);
function noitru_meal_state($date,$meal){return ['status'=>(($date==='2026-10-16'&&$meal==='toi')?'off':($meal==='trua'?'locked':'open'))];}
function noitru_meal_effective_student_context(){return $GLOBALS['fixture_context'];}
function noitru_student_is_active($student){return ($student['status']??'active')==='active';}
require __DIR__.'/support/meal_summary_reference.php';
load_functions(__DIR__.'/../includes/noitru_store.php',['noitru_meals_all','noitru_meals_for_date','noitru_meal_reports_data','noitru_meal_period_summary','noitru_meal_status_index','noitru_meal_status_day','noitru_rice_usage_summary','noitru_meal_report_effective_counts','noitru_student_is_active_on_date']);
try{
    $expected=[['id'=>'first','date'=>'2026-10-15','class_name'=>'6A','meal'=>'sang','eat_count'=>2,'custom'=>['keep'=>true]],
        ['id'=>'duplicate','date'=>'2026-10-15','class_name'=>'6A','meal'=>'sang','eat_count'=>9],
        ['id'=>'hidden-id','date'=>'2026-10-15','class_name'=>'7A','meal'=>'toi'],
        ['id'=>'hidden-target','date'=>'2026-10-16','class_name'=>'6A','meal'=>'trua']];
    cds_json_save(NOITRU_MEAL_REPORTS,['reports'=>$expected,'states'=>[['date'=>'2026-10-15','meal'=>'toi','status'=>'off']],'settings'=>['custom'=>false],'unknown'=>['preserve'=>true]]);
    cds_json_save(NOITRU_DIR.'/meal_history_deleted.json',['hidden-id']);
    cds_json_save(NOITRU_DIR.'/meal_history_deleted_targets.json',['2026-10-16|6A|trua']);
    $deleted=['2026-10-16|6A|trua'=>true];
    mt_srand(20261015);
    for($case=0;$case<60;$case++){
        $updates=[];
        for($i=0,$n=1+$case%5;$i<$n;$i++){
            $row=['date'=>'2026-10-'.(15+mt_rand(0,2)),'class_name'=>mt_rand(0,1)?'6A':'7A','meal'=>['sang','trua','toi'][mt_rand(0,2)],'eat_count'=>mt_rand(0,30),'note'=>'Ghi chú '.$case];
            if($case%3===0)$row['student_snapshot']=[['id'=>'historical','name'=>'Giữ nguyên']];
            $updates[]=$row;
            // The original algorithm filters tombstones, then updates the first matching row.
            $expected=array_values(array_filter($expected,static fn($saved)=>($saved['id']??'')!=='hidden-id'&&!isset($deleted[noitru_meal_target_key($saved['date']??'',$saved['class_name']??'',$saved['meal']??'')])));
            unset($deleted[noitru_meal_target_key($row['date'],$row['class_name'],$row['meal'])]);
            $row=noitru_meal_report_snapshot($row);$found=false;
            foreach($expected as&$saved){if(($saved['date']??'')===$row['date']&&($saved['class_name']??'')===$row['class_name']&&($saved['meal']??'')===$row['meal']){$saved=array_merge($saved,$row,['updated_at'=>noitru_now()]);$found=true;break;}}unset($saved);
            if(!$found)$expected[]=array_merge(['id'=>noitru_uid('mr'),'created_at'=>noitru_now()],$row);
        }
        check(noitru_meal_report_upsert_many($updates),'Batch save');
        $actual=cds_json_load(NOITRU_MEAL_REPORTS);
        check($actual['reports']===$expected,'Exact sequential report parity at case '.$case);
        check($actual['unknown']===['preserve'=>true]&&$actual['states']===[['date'=>'2026-10-15','meal'=>'toi','status'=>'off']]&&$actual['settings']['custom']===false,'Metadata and unknown fields preserved');
    }
    // Check the full original summaries, including duplicates, missing pupils, date eligibility and off states.
    $students=[['id'=>'p1','class_name'=>'6A','admission_date'=>'2026-10-16'],['id'=>'p2','class_name'=>'7A','departure_date'=>'2026-10-16'],['id'=>'p3','class_name'=>'6A','status'=>'inactive']];
    $GLOBALS['fixture_context']=['students'=>array_column($students,null,'id'),'classes'=>['6A'=>['p1','p3'],'7A'=>['p2']]];
    for($case=0;$case<40;$case++){
        $meals=[];foreach(['2026-10-15','2026-10-16','2026-10-17']as$date)foreach(['p1','p2','p3','historical','']as$id)for($duplicate=0;$duplicate<2;$duplicate++)$meals[]=['date'=>$date,'student_id'=>$id,'sang'=>mt_rand(0,1)?'yes':'no','trua'=>mt_rand(0,1)?'yes':'no','toi'=>mt_rand(0,1)?'yes':'no'];
        cds_json_save(NOITRU_MEALS,$meals);noitru_meal_read_cache_clear();
        check(noitru_meal_period_summary('2026-10-15','2026-10-17',$students)===reference_noitru_meal_period_summary('2026-10-15','2026-10-17',$students),'Original period summary exact parity at '.$case);
        $rice=['settings'=>['sang_grams'=>100,'trua_grams'=>180,'toi_grams'=>175]];
        check(noitru_rice_usage_summary('2026-10-15','2026-10-17',$rice)===reference_noitru_rice_usage_summary('2026-10-15','2026-10-17',$rice),'Original rice counts/rounding exact parity at '.$case);
    }
    echo "PASS: 40 original period/rice summary fixtures, duplicate rows, admission/departure dates, snapshots, off states and rounding\n";
    echo "PASS: 60 report batches match historical sequential rules, duplicate first-match, snapshots, tombstones, metadata and unknown fields\n";
}finally{foreach(glob($root.'/*')as$p)if(is_file($p))unlink($p);@unlink($root.'/.meal-write.lock');rmdir($root);}
