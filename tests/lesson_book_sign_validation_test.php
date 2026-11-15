<?php
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$valid=['status'=>'taught','teacher_comment'=>'Lớp học nghiêm túc','rating'=>'Tốt'];
check(lb_sign_required_fields($valid)===[],'Complete lesson may sign');
foreach(['status','teacher_comment','rating']as$field){$row=$valid;$row[$field]=' ';check(count(lb_sign_required_fields($row))===1,'Each missing field blocks signing: '.$field);}
check(count(lb_sign_required_fields([]))===3,'All missing fields reported');
foreach(['pending','unknown']as$status)check(in_array('trạng thái',lb_sign_required_fields(array_replace($valid,['status'=>$status])),true),'Pending/invalid status cannot sign');
foreach(['taught','substitute','makeup','fill','online']as$status){check(lb_sign_required_fields(array_replace($valid,['status'=>$status]))===[],'Explicit nonstandard status accepted');check(in_array('nhận xét',lb_sign_required_fields(['status'=>$status,'rating'=>'Tốt']),true),'Teaching statuses need comments');}
foreach(['holiday','teacher_absent','class_absent','postponed','cancelled']as$status)check(lb_sign_required_fields(['status'=>$status])===[],'Non-teaching status needs only confirmation: '.$status);
// Exercise the actual commit entry point without loading application data or writes.
$source=file_get_contents(__DIR__.'/../chuyenmon/includes/lesson_book_store.php');$printStart=strpos($source,'function lb_print_validation(');$printEnd=strpos($source,"\n",$printStart);eval(substr($source,$printStart,$printEnd-$printStart));
foreach(['holiday','teacher_absent','class_absent','postponed','cancelled']as$status){$v=lb_print_validation([['status'=>$status,'signed_at'=>'now']]);check($v['filled']===1&&$v['missing_content']===0&&$v['missing_signature']===0,'Signed non-teaching row is ready to print: '.$status);}
check(lb_print_validation([['status'=>'taught','signed_at'=>'now']])['missing_content']===1,'Teaching row still needs content to print');
$start=strpos($source,'function lb_commit_record(');$end=strpos($source,'function lb_save_and_sign(',$start);eval(substr($source,$start,$end-$start));
function lb_slots($w){return [['slot_id'=>'fixture']];}function lb_sign_date_error($s){return '';}
function lb_save_record($w,$i){$GLOBALS['saved']=true;return ['ok'=>false,'message'=>'fixture stop'];}
foreach(['status','teacher_comment','rating']as$field){$row=$valid;$row[$field]='';$GLOBALS['saved']=false;$result=lb_commit_record([],array_merge($row,['slot_id'=>'fixture']),true);check(!$result['ok']&&!$GLOBALS['saved'],'Incomplete sign must not save or mutate existing signature');}
$GLOBALS['saved']=false;lb_commit_record([],array_merge($valid,['slot_id'=>'fixture']),true);check($GLOBALS['saved'],'Complete sign reaches existing save pipeline');
foreach(['holiday','teacher_absent','class_absent','postponed','cancelled']as$status){$GLOBALS['saved']=false;lb_commit_record([],['slot_id'=>'fixture','status'=>$status],true);check($GLOBALS['saved'],'Non-teaching confirmation reaches save pipeline: '.$status);}
echo "PASS sign validation: required status/comment/rating, all statuses, single/bulk shared entry, rejection before writes\n";
