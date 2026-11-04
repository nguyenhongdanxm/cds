<?php
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
define('LB_RECORDS_FILE','fixture');
function lb_slots($w){return [$GLOBALS['slot']];}function lb_can_edit($r){return true;}function lb_locked($w,$r){return false;}function lb_teacher_name(){return 'Fixture teacher';}function lb_ppct_used_periods(...$a){return [];}function lb_rows($f){return [$GLOBALS['slot']];}function lb_signature_meta(){return ['path'=>'private-signature.png','version'=>'v1'];}function lb_audit($a,$d){$GLOBALS['audits'][]=$a;}
function lb_write($f,$r){$GLOBALS['writes']++;$GLOBALS['written']=$r[0];return !($GLOBALS['fail']??false);}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_save_record','lb_commit_record','lb_sign_date_error']);
$week=['key'=>'7'];$GLOBALS['slot']=['slot_id'=>'s1','date'=>'2020-10-19','period'=>1,'class'=>'6A','grade'=>'6','subject'=>'Toán','actual_teacher'=>'Fixture teacher','signed_snapshot'=>['signed_snapshot'=>['old'=>'nested']]];
$input=['slot_id'=>'s1','status'=>'taught','teacher_comment'=>'Good','rating'=>'Tốt','ppct_period'=>1,'lesson_title'=>'Lesson','actual_periods'=>1];
$GLOBALS['writes']=0;$r=lb_commit_record($week,$input,true);check($r['ok']&&$GLOBALS['writes']===1,'One record write per signature');$row=$GLOBALS['written'];check(isset($row['signed_at'],$row['signature_path'])&&!isset($row['signed_snapshot']['signed_snapshot']),'Content and signature written together without recursive snapshots');check($r['signed']['version']===hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),'In-place delete metadata matches exact written record');check($r['signed']['slot_id']==='s1'&&$r['signed']['signed_by']==='Fixture teacher','UI receives confirmed state');
$GLOBALS['fail']=true;$GLOBALS['writes']=0;check(!lb_commit_record($week,$input,true)['ok']&&$GLOBALS['writes']===1,'Write failure returns no success');$GLOBALS['fail']=false;
foreach(['status','teacher_comment','rating']as$f){$bad=$input;$bad[$f]='';$GLOBALS['writes']=0;check(!lb_commit_record($week,$bad,true)['ok']&&$GLOBALS['writes']===0,'Missing fields cannot write');}
echo "PASS performance: one atomic lesson write, unchanged validation, confirmed UI metadata, nonrecursive snapshot and write failure\n";
