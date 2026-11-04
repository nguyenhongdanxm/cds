<?php
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$valid=['status'=>'taught','teacher_comment'=>'Lớp học nghiêm túc','rating'=>'Tốt'];
check(lb_sign_required_fields($valid)===[],'Complete lesson may sign');
foreach(['status','teacher_comment','rating']as$field){$row=$valid;$row[$field]=' ';check(count(lb_sign_required_fields($row))===1,'Each missing field blocks signing: '.$field);}
check(count(lb_sign_required_fields([]))===3,'All missing fields reported');
foreach(['pending','unknown']as$status)check(in_array('trạng thái',lb_sign_required_fields(array_replace($valid,['status'=>$status])),true),'Pending/invalid status cannot sign');
foreach(['holiday','cancelled','fill','online']as$status){check(lb_sign_required_fields(array_replace($valid,['status'=>$status]))===[],'Explicit nonstandard status accepted');check(in_array('nhận xét',lb_sign_required_fields(['status'=>$status,'rating'=>'Tốt']),true),'All statuses need comments');}
// Exercise the actual commit entry point without loading application data or writes.
$source=file_get_contents(__DIR__.'/../chuyenmon/includes/lesson_book_store.php');$start=strpos($source,'function lb_commit_record(');$end=strpos($source,'function lb_save_and_sign(',$start);eval(substr($source,$start,$end-$start));
function lb_slots($w){return [['slot_id'=>'fixture']];}function lb_sign_date_error($s){return '';}
function lb_save_record($w,$i){$GLOBALS['saved']=true;return ['ok'=>false,'message'=>'fixture stop'];}
foreach(['status','teacher_comment','rating']as$field){$row=$valid;$row[$field]='';$GLOBALS['saved']=false;$result=lb_commit_record([],array_merge($row,['slot_id'=>'fixture']),true);check(!$result['ok']&&!$GLOBALS['saved'],'Incomplete sign must not save or mutate existing signature');}
$GLOBALS['saved']=false;lb_commit_record([],array_merge($valid,['slot_id'=>'fixture']),true);check($GLOBALS['saved'],'Complete sign reaches existing save pipeline');
echo "PASS sign validation: required status/comment/rating, all statuses, single/bulk shared entry, rejection before writes\n";
