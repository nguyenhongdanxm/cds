<?php
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/program_assignment.php';
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($ok,$m){if(!$ok)throw new RuntimeException($m);}
$dir=sys_get_temp_dir().'/cds-flex-'.bin2hex(random_bytes(5));mkdir($dir);define('DATA_PATH',$dir);define('LB_RECORDS_FILE',$dir.'/records.json');
function load_json($f,$d=[]){return is_file($f)?json_decode(file_get_contents($f),true):$d;}
function lb_slots($w){return [$GLOBALS['slot']];}
function lb_can_edit($s){return $GLOBALS['authorized'];}
function lb_locked($w,$s){return false;}
function lb_teacher_name(){return 'Actual';}
function lb_rows($f){return $GLOBALS['records']??[];}
function lb_ppct_used_periods($c,$s,$id){return $GLOBALS['used']??[];}
function lb_write($f,$rows){$GLOBALS['records']=$rows;return true;}
function lb_audit($a,$b){}
function lb_signature_meta(){return ['path'=>'signature.png','version'=>'v1'];}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_save_record','lb_commit_record','lb_sign','lb_sign_date_error']);
try {
 file_put_contents($dir.'/active_version.json',json_encode(['id'=>'v1']));
 file_put_contents($dir.'/program_assignments_v1.json',json_encode(['8A'=>['subject'=>'GDĐP','ranges'=>[['from'=>1,'to'=>35,'teacher'=>'Assigned']]]]));
 $GLOBALS['slot']=['slot_id'=>'s','subject'=>'GDĐP','class'=>'8A','grade'=>'8','date'=>'2026-01-01','period'=>1,'planned_ppct_period'=>7,'scheduled_teacher'=>'Original','actual_teacher'=>'Actual'];
 $GLOBALS['authorized']=true;
 $input=['slot_id'=>'s','ppct_period'=>11,'lesson_title'=>'Bài thực tế','status'=>'substitute','rating'=>'Tốt','teacher_comment'=>'Đã hoàn thành','actual_periods'=>1];
 foreach(['taught','substitute','makeup','online']as$status){
  $GLOBALS['records']=[];$input['status']=$status;
  $r=lb_commit_record(['key'=>'w'],$input,true);
  check($r['ok']&&$r['type']==='warning'&&str_contains($r['message'],'Assigned'),'Offset PPCT signs with teacher advisory: '.$status);
  $row=$GLOBALS['records'][0];check($row['ppct_period']===11&&$row['actual_teacher']==='Actual'&&!empty($row['signed_at']),'Keep actual PPCT/teacher/signature');
 }
 $GLOBALS['records']=[];$r=lb_save_record([],$input);check($r['ok'],'Save without signing allows offset');
 $r=lb_sign(['key'=>'w'],'s');check($r['ok']&&$r['type']==='warning','Separate signing path also allows offset with advisory');
 $GLOBALS['authorized']=false;$before=$GLOBALS['records'];
 check(!lb_save_record([],$input,true)['ok']&&!lb_sign([],'s')['ok']&&$before===$GLOBALS['records'],'Advisory cannot bypass signing ownership');
 $GLOBALS['authorized']=true;$GLOBALS['slot']['subject']='Toán';$GLOBALS['records']=[];
 check(!lb_save_record([],$input)['ok'],'Other subjects retain sequential PPCT validation');
 $GLOBALS['slot']['subject']='GDĐP';$GLOBALS['used']=[11=>['date'=>'2025-12-01','period'=>2,'slot_id'=>'other']];
 check(!lb_save_record([],$input)['ok'],'Already signed duplicate PPCT remains protected');
 echo "PASS actual GDĐP save/sign: taught, substitute, makeup, online, offset PPCT, warnings, actual ownership, non-GDĐP sequence and signed duplicate protection\n";
} finally {foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
