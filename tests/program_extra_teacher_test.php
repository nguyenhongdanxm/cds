<?php
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/program_assignment.php';
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$dir=sys_get_temp_dir().'/gdp-extra-'.bin2hex(random_bytes(5));mkdir($dir);define('DATA_PATH',$dir);define('LB_RECORDS_FILE',$dir.'/records.json');
function load_json($f,$d=[]){return is_file($f)?json_decode(file_get_contents($f),true):$d;}
function tkb_key($v){return strtolower(trim($v));}
function tkb_subject_canonical($s){return $s;}
function tkb_subject_related($a,$b){return $a===$b;}
function get_assignments(){return [];}
function tkb_resolved_slots($w){return [['teacher'=>'A','class'=>'8A','subject'=>'GDĐP','day'=>2,'session'=>'Sáng','period'=>1,'program_period'=>11],['teacher'=>'M','class'=>'8A','subject'=>'Toán','day'=>4,'session'=>'Sáng','period'=>1]];}
function tkb_week_by_id($id){return ['id'=>'w11'];}
function lb_tkb_week($w){return ['id'=>'w11','start_date'=>'2026-11-16'];}
function tkb_week_slot_date($w,$d){return date('Y-m-d',strtotime('2026-11-16 +'.($d-2).' days'));}
function tkb_substitutions(){return $GLOBALS['registrations'];}
function tkb_substitution_status($r){return $r['status']??'approved';}
function tkb_substitution_for_slot($w,$s){foreach(tkb_substitutions()as$r)if($r['status']==='approved'&&$r['date']===tkb_week_slot_date($w,$s['day'])&&$r['period']===$s['period']&&$r['type']==='fill')return$r;return null;}
function lb_rows($f){return $GLOBALS['records'];}
function lb_norm($v){return strtolower($v);}
function lb_same($a,$b){return $a===$b;}
function lb_same_subject($a,$b){return $a===$b;}
function lb_grade($v){return '8';}
function lb_slot_id($w,$r){return implode('|',[$r['date'],$r['session'],$r['period'],$r['class'],$r['subject'],$r['actual_teacher']]);}
function lb_ppct_enrich($r){return array_merge($r,['school_year_key'=>'2026','class_key'=>$r['class'],'subject_key'=>$r['subject']]);}
function cds_lb_claims_ppct($r){return !empty($r['signed_at'])&&in_array($r['status']??'',['taught','substitute','makeup','online'],true);}
function lb_record_before_week($r,$w){return $r['date']<$w['start'];}
function lb_is_admin(){return false;}
function lb_teacher_name(){return $GLOBALS['teacher'];}
function lb_locked($w,$r){return false;}
function lb_signature_meta(){return ['path'=>'B.png','version'=>'1'];}
function lb_audit($a,$d){}
function lb_ppct_used_periods($c,$s,$id){$out=[];foreach($GLOBALS['records']as$r)if(($r['slot_id']??'')!==$id&&$r['class']===$c&&$r['subject']===$s&&cds_lb_claims_ppct($r))$out[$r['ppct_period']]=$r;return$out;}
function lb_write($f,$rows){$by=[];foreach(array_merge($GLOBALS['records'],$rows)as$r)$by[$r['slot_id']]=$r;$GLOBALS['records']=array_values($by);unset($GLOBALS['lb_slots_cache']);return true;}
load_functions(__DIR__.'/../chuyenmon/thoikhoabieu.php',['tkb_same_class','tkb_makeup_course_options','tkb_fill_subject_options','tkb_teacher_has_course']);
load_functions(__DIR__.'/../chuyenmon/tkb_substitution_manage.php',['tkbm_fill_subjects','tkbm_same_class']);
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_manual.php',['lb_merge_independent_records']);
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_slots','lb_saved_row_for_base','lb_ppct_next_period','lb_can_edit','lb_save_record','lb_commit_record','lb_sign_date_error']);
try{
 file_put_contents($dir.'/active_version.json',json_encode(['id'=>'v1']));
 $configs=['8A'=>['subject'=>'GDĐP','ranges'=>[['from'=>1,'to'=>11,'teacher'=>'A'],['from'=>12,'to'=>35,'teacher'=>'B']]]];
 file_put_contents($dir.'/program_assignments_v1.json',json_encode($configs));
 $GLOBALS['records']=[];for($p=1;$p<=10;$p++)$GLOBALS['records'][]=['slot_id'=>'old'.$p,'class'=>'8A','subject'=>'GDĐP','date'=>'2026-11-01','session'=>'Sáng','period'=>$p,'status'=>'taught','signed_at'=>'signed','ppct_period'=>$p];
 $base=['week_id'=>'w11','class'=>'8A','subject'=>'GDĐP','substitute_teacher'=>'B','assigned_to'=>'B','session'=>'Sáng','period'=>1,'status'=>'approved','absent_teacher'=>'B'];
 $GLOBALS['registrations']=[array_merge($base,['id'=>'extra12','type'=>'makeup','date'=>'2026-11-17','day'=>3,'program_period'=>12]),array_merge($base,['id'=>'fill13','type'=>'fill','date'=>'2026-11-18','day'=>4,'program_period'=>13,'replacement_subject'=>'GDĐP','original_subject'=>'Toán','absent_teacher'=>'M']),array_merge($base,['id'=>'pending','type'=>'makeup','date'=>'2026-11-19','day'=>5,'program_period'=>14,'status'=>'pending'])];
 $week=['key'=>'11','label'=>'Tuần 11','start'=>'2026-11-16','end'=>'2026-11-22'];
 check(tkb_teacher_has_course([], 'B','8A','GDĐP'),'B may teach GDĐP in week where only A is on timetable');
 check(count(tkb_makeup_course_options(tkb_resolved_slots([]),'B'))===1,'Makeup UI offers annual GDĐP assignment');
 check(in_array('GDĐP',tkb_fill_subject_options([],'B','8A'),true)&&in_array('GDĐP',tkbm_fill_subjects($GLOBALS['registrations'][1]),true),'Single and bulk fill approval see B annual assignment');
 check(!tkb_teacher_has_course([],'C','8A','GDĐP')&&!tkb_teacher_has_course([],'B','8B','GDĐP')&&!tkb_teacher_has_course([],'B','8A','Toán'),'No unrelated teacher/class/subject permission expansion');
 check(gdp_registration_metadata($base,'12')['program_expected_teacher']==='B','Per-slot period 12 resolves to B');
 foreach(['0','36','1.5','bad']as$bad){try{gdp_registration_metadata($base,$bad);throw new LogicException('Expected invalid PPCT');}catch(RuntimeException $e){}}
 $rows=lb_slots($week,null,false,false);check(count($rows)===3,'Only main lesson plus approved makeup/fill; pending hidden');
 $by=[];foreach($rows as$row)$by[$row['ppct_period']??$row['suggested_ppct_period']]=$row;
 check($by[11]['actual_teacher']==='A'&&$by[12]['actual_teacher']==='B'&&$by[13]['actual_teacher']==='B','Same week: main11 A, extra12 B, fill13 B');
 $GLOBALS['teacher']='A';check(lb_can_edit($by[11])&&!lb_can_edit($by[12])&&!lb_can_edit($by[13]),'A owns main11 only');
 $GLOBALS['teacher']='B';check(!lb_can_edit($by[11])&&lb_can_edit($by[12])&&lb_can_edit($by[13]),'B owns only approved supplemental lessons');
 $input=['slot_id'=>$by[12]['slot_id'],'ppct_period'=>12,'lesson_title'=>'Bài 12','status'=>'makeup','teacher_comment'=>'Hoàn thành','rating'=>'Tốt','actual_periods'=>1];
 $result=lb_commit_record($week,$input,true);check($result['ok'],'B can save/sign real supplemental12 in week11');
 $saved=array_values(array_filter($GLOBALS['records'],fn($r)=>($r['slot_id']??'')===$input['slot_id']))[0];check($saved['ppct_period']===12&&$saved['actual_teacher']==='B'&&$saved['signed_by']==='B','Saved signature remains with B/PPCT12');
 $GLOBALS['teacher']='A';$before=$GLOBALS['records'];check(!lb_commit_record($week,$input,true)['ok']&&$GLOBALS['records']===$before,'A cannot sign B lesson using forged posted teacher');
 $GLOBALS['registrations'][0]['substitute_teacher']='A';unset($GLOBALS['lb_slots_cache']);$rows=lb_slots($week,null,false,false);$signed=array_values(array_filter($rows,fn($r)=>!empty($r['signed_at'])));check($signed[0]['actual_teacher']==='B'&&$signed[0]['ppct_period']===12,'Signed snapshot immutable after schedule changes');
 echo "PASS same-week GDĐP: main11 A, approved extra12/fill13 B, absent-week annual eligibility, per-slot PPCT, pending exclusion, real save/sign, ownership and signed snapshot\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
