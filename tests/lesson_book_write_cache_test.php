<?php
require __DIR__.'/../includes/json_store.php';require __DIR__.'/support/load_functions.php';require __DIR__.'/../chuyenmon/includes/lesson_book_performance.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$dir=sys_get_temp_dir().'/cds-lb-cache-'.bin2hex(random_bytes(5));mkdir($dir,0700);define('LB_RECORDS_FILE',$dir.'/records.json');define('LB_CURRICULUM_FILE',$dir.'/curriculum.json');
function load_json($f,$d=[]){$GLOBALS['reads']++;return cds_json_load($f,$d);}
function save_json($f,$r){return cds_json_save($f,$r);}
function cds_lb_read_effective(){return false;}
function cds_lb_claims_ppct($r){return false;}
function cds_lb_shadow_upsert($r){return !($GLOBALS['shadowFail']??false);}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_rows','lb_rows_bust','lb_write']);
$original=['slot_id'=>'signed','signed_at'=>'2026-09-01','signed_by'=>'GV','signature_path'=>'private.png','signed_snapshot'=>['lesson_title'=>'Original'],'updated_at'=>'2026-09-01'];
try{
 cds_json_save(LB_RECORDS_FILE,[$original]);$GLOBALS['reads']=0;lb_rows(LB_RECORDS_FILE);$new=['slot_id'=>'new','updated_at'=>'2026-09-02'];check(lb_write(LB_RECORDS_FILE,[$new]),'Atomic save succeeds');$reads=$GLOBALS['reads'];check(lb_rows(LB_RECORDS_FILE)===cds_json_load(LB_RECORDS_FILE),'Cache equals exact committed JSON');check($reads===$GLOBALS['reads'],'Audit follow-up uses committed snapshot without reread');check(lb_rows(LB_RECORDS_FILE)[0]===$original,'Unrelated signed data unchanged');
 $GLOBALS['shadowFail']=true;$new['updated_at']='2026-09-03';check(lb_write(LB_RECORDS_FILE,[$new])&&$GLOBALS['cds_force_json_lb_read'],'Shadow failure keeps JSON authority');check(lb_rows(LB_RECORDS_FILE)===cds_json_load(LB_RECORDS_FILE),'Mirror failure still exposes exact committed JSON');
 $before=lb_rows(LB_RECORDS_FILE);file_put_contents(LB_RECORDS_FILE,'{broken');check(!lb_write(LB_RECORDS_FILE,[['slot_id'=>'failed']]),'Corrupt source rejects write');check(lb_rows(LB_RECORDS_FILE)===$before,'Failed write never publishes uncommitted cache');check(file_get_contents(LB_RECORDS_FILE)==='{broken','Corrupt source preserved');
 lb_perf_record('arbitrary-private-name',1);check(!isset(lb_perf_snapshot()['arbitrary-private-name']),'Fixed diagnostic names only');check(str_contains(lb_perf_header(),'lb_write;dur=')&&!str_contains(lb_perf_header(),'private.png'),'Timing headers contain no record payload');
 echo "PASS committed cache: no reread after save; signed snapshots, shadow fallback, failed write and bounded timings\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
