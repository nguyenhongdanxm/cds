<?php
require __DIR__.'/support/load_functions.php';
function check($ok,$m){if(!$ok)throw new RuntimeException($m);}
define('LB_RECORDS_FILE','fixture');
function lb_rows($f){return $GLOBALS['records']??[];}
function lb_tkb_week($w){return ['id'=>'published','start_date'=>'2026-11-02'];}
function tkb_resolved_slots($w){return [['teacher'=>'GV A','class'=>'8A','subject'=>'GDĐP','day'=>2,'session'=>'Sáng','period'=>1,'program_period'=>7]];}
function tkb_week_slot_date($w,$d){return '2026-11-02';}
function tkb_substitution_for_slot($w,$s){return null;}
function tkb_substitutions(){return [];}
function lb_norm($v){return strtolower($v);}
function lb_same($a,$b){return $a===$b;}
function lb_same_subject($a,$b){return $a===$b;}
function lb_grade($v){return '8';}
function tkb_subject_canonical($s){return $s;}
function lb_slot_id($w,$r){return 'slot1';}
function lb_merge_independent_records($rows,$records,$w){return $rows;}
function lb_ppct_enrich($r){return array_merge($r,['school_year_key'=>'2026','class_key'=>$r['class']??'8A','subject_key'=>$r['subject']??'GDĐP']);}
function cds_lb_claims_ppct($r){return !empty($r['signed_at'])&&in_array($r['status']??'',['taught','substitute','makeup','online'],true);}
function lb_record_before_week($r,$w){return ($r['date']??'')<$w['start'];}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_slots','lb_saved_row_for_base','lb_ppct_next_period']);
$week=['key'=>'9','label'=>'Tuần 9','start'=>'2026-11-02','end'=>'2026-11-08'];
function renderRows($w){unset($GLOBALS['lb_slots_cache']);return lb_slots($w,null,false,false);}
$row=renderRows($week)[0];check($row['actual_teacher']==='GV A'&&$row['ppct_period']===7&&$row['suggested_ppct_period']===7,'Book gets pinned teacher and period 7 in week 9');
$GLOBALS['records']=[array_merge($row,['ppct_period'=>0,'status'=>'pending','signed_at'=>''])];$row=renderRows($week)[0];check($row['ppct_period']===7,'Draft zero does not erase published program period');
$GLOBALS['records']=[array_merge($row,['ppct_period'=>8,'actual_teacher'=>'GV B','status'=>'taught','signed_at'=>'2026-11-02','signed_snapshot'=>['ppct_period'=>8,'actual_teacher'=>'GV B']])];$row=renderRows($week)[0];check($row['ppct_period']===8&&$row['actual_teacher']==='GV B','Existing signed snapshot is immutable even if timetable metadata differs');
echo "PASS lesson book program integration: published week9/period7, draft persistence and signed snapshot preservation\n";
