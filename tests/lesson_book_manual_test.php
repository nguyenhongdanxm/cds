<?php
function check($v,$msg){if(!$v)throw new RuntimeException($msg);}
function lb_same($a,$b){return strtolower(trim($a))===strtolower(trim($b));}
function lb_same_subject($a,$b){return lb_same($a,$b);}
function get_assignments(){return [['class'=>'12A','subject'=>'Toán','teacher'=>'GV A']];}
function lb_is_management(){return $GLOBALS['manager'];}
function lb_teacher_name(){return 'BGH';}
function tkb_subject_canonical($s){return $s;}
function lb_grade($s){return '12';}
function lb_locked($w,$r){return $GLOBALS['locked'];}
function lb_slots($w){return $GLOBALS['slots'];}
function lb_rows($f){return $GLOBALS['records'];}
function lb_slot_id($w,$r){return hash('sha256',json_encode([$w['key'],$r['date'],$r['session'],$r['period'],$r['class'],$r['subject']]));}
function cds_json_update($f,$fn,$default){$r=$fn($GLOBALS['records']);if($r===false)return false;$GLOBALS['records']=$r;return true;}
function lb_rows_bust($f){}
function cds_lb_shadow_upsert($r){return true;}
function lb_audit($a,$r){}
if(!function_exists('mb_substr')){function mb_substr($s,$a,$b,$e){return substr($s,$a,$b);}}
define('LB_RECORDS_FILE','records');
require dirname(__DIR__).'/chuyenmon/includes/lesson_book_manual.php';
$GLOBALS['manager']=true;$GLOBALS['locked']=false;$GLOBALS['slots']=[];$GLOBALS['records']=[];
$w=['key'=>'week5','label'=>'Tuần 5','start'=>'2026-09-28','end'=>'2026-10-04'];
$i=['manual_date'=>'2026-09-30','manual_session'=>'Chiều','manual_period'=>'2','manual_class'=>'12A','manual_subject'=>'Toán','manual_teacher'=>'GV A'];
check(lb_add_manual_slot($w,$i)['ok'],'old week add');
$r=$GLOBALS['records'][0];check(!isset($r['signed_at'])&&$r['status']==='pending'&&!empty($r['manual_slot']),'unsigned independent');
check(!lb_add_manual_slot($w,$i)['ok'],'repeat blocked');
check(!lb_add_manual_slot($w,array_merge($i,['manual_date'=>'2026-02-30']))['ok'],'invalid date blocked');
check(!lb_add_manual_slot($w,array_merge($i,['manual_teacher'=>'Unknown']))['ok'],'assignment checked');
$GLOBALS['records']=[];$GLOBALS['manager']=false;check(!lb_add_manual_slot($w,$i)['ok'],'permission checked');
$GLOBALS['manager']=true;$GLOBALS['locked']=true;check(!lb_add_manual_slot($w,$i)['ok'],'lock checked');$GLOBALS['locked']=false;
$GLOBALS['slots']=[array_merge($r,['class'=>'12B'])];check(!lb_add_manual_slot($w,$i)['ok'],'teacher collision checked');
$GLOBALS['slots']=[array_merge($r,['actual_teacher'=>'GV B'])];check(!lb_add_manual_slot($w,$i)['ok'],'class collision checked');
$GLOBALS['slots']=[];
check(count(lb_merge_independent_records([],[$r],$w))===1,'no timetable still manual');
$changed=array_merge($r,['slot_id'=>'changed','manual_slot'=>false,'subject'=>'Văn']);
$merged=lb_merge_independent_records([$changed],[$r],$w);
check(count($merged)===1&&$merged[0]===$r,'replacement preserves manual');
$merged=lb_merge_independent_records([$r,$r],[$r],$w);check(count($merged)===1,'dedup manual');
check(!lb_merge_independent_records([],[$r],array_merge($w,['key'=>'other'])),'week isolation');
$signed=array_merge($r,['manual_slot'=>false,'signed_at'=>'2026-09-30T15:00:00+07:00','signed_snapshot'=>['subject'=>'Toán','teacher_comment'=>'Nội dung đã ký']]);
$merged=lb_merge_independent_records([],[$signed],$w);check(count($merged)===1&&$merged[0]['teacher_comment']==='Nội dung đã ký','orphan signed snapshot retained');
$past=array_merge($r,['manual_slot'=>false,'teacher_comment'=>'Đã ghi']);
check(lb_merge_independent_records([],[$past],$w)[0]['teacher_comment']==='Đã ghi','past record retained');
echo "PASS: 15 manual lesson and historical record regression checks\n";
