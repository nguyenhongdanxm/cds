<?php
// Synthetic data only. No application bootstrap, storage, session, or network access.
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$root=dirname(__DIR__);
require $root.'/tests/support/load_functions.php';
load_functions($root.'/includes/timetable_builder.php',['ttb_slot_key','ttb_class_grade','ttb_scope_blocked']);
require $root.'/includes/timetable_lesson_constraints.php';
$assignments=[['id'=>'a','class'=>'6A','subject'=>'Toán','teacher'=>'GV A','periods'=>4],['id'=>'b','class'=>'6B','subject'=>'Văn','teacher'=>'GV B','periods'=>4],['id'=>'c','class'=>'7A','subject'=>'Toán','teacher'=>'GV C','periods'=>4]];
$data=['settings'=>['days'=>[2,3,4,5,6,7],'morning_periods'=>5,'afternoon_periods'=>4],'special_slots'=>['old'=>['id'=>'old','scope'=>'school','value'=>'','label'=>'Cũ','day'=>2,'session'=>'Sáng','period'=>1]],'scope_blocks'=>[['scope'=>'class','value'=>'7A','key'=>'old','special_id'=>'old']],'fixed_lessons'=>[],'double_lessons'=>[],'subject_sequences'=>[],'plans'=>[['id'=>'published','entries'=>[['teacher'=>'GV A']]]],'groups'=>[],'_workspace_id'=>'test'];
$original=$data;
$input=['scope'=>'classes','classes'=>['6A','6B'],'label'=>'Chào cờ','slots'=>[['day'=>'2','session'=>'Sáng','period'=>'2'],['day'=>'3','session'=>'Chiều','period'=>'4']]];
$rows=tlc_special_batch($data,$assignments,$input);
check(count($rows)===4,'2 classes x 2 slots');
check($data===$original,'helper cannot mutate stored data');
$blocked=$data;foreach($rows as$r)$blocked['scope_blocks'][]=$r+['special_id'=>$r['id']];
foreach(['6A','6B'] as$c)foreach($input['slots']as$s)check(ttb_scope_blocked($blocked,[$c],ttb_slot_key((int)$s['day'],$s['session'],(int)$s['period'])),'existing solver recognizes each target');
check(!ttb_scope_blocked($blocked,['7A'],ttb_slot_key(2,'Sáng',2)),'other class unaffected');
$dedup=$input;$dedup['classes'][]='6A';$dedup['slots'][]=$dedup['slots'][0];
// Bound input count is conservative; unique selection UI cannot send duplicate classes.
$dedup['classes']=['6A','6A'];check(count(tlc_special_batch($data,$assignments,$dedup))===2,'duplicate slots/classes collapse');
foreach([
 array_replace($input,['classes'=>['6A','missing']]),
 array_replace($input,['classes'=>[]]),
 array_replace($input,['classes'=>[['6A']]]),
 array_replace($input,['slots'=>[]]),
 array_replace($input,['slots'=>[['day'=>8,'session'=>'Sáng','period'=>1]]]),
 array_replace($input,['slots'=>[['day'=>2,'session'=>'Chiều','period'=>5]]]),
 array_replace($input,['slots'=>[['day'=>2,'session'=>'Đêm','period'=>1]]]),
 array_replace($input,['slots'=>[['day'=>2,'session'=>'Sáng','period'=>1.9]]]),
 array_replace($input,['slots'=>[['day'=>2,'session'=>[],'period'=>1]]]),
 array_replace($input,['scope'=>'invalid']),
 array_replace($input,['label'=>[]])
]as$bad){$failed=false;try{tlc_special_batch($data,$assignments,$bad);}catch(Throwable$e){$failed=true;}check($failed,'reject entire invalid batch');check($data===$original,'invalid batch preserves data');}
foreach(['school'=>'','grade'=>'6','class'=>'6A']as$scope=>$value){
 $legacy=tlc_special_batch($data,$assignments,['scope'=>$scope,'scope_value'=>$value,'day'=>2,'session'=>'Sáng','period'=>3,'label'=>'Nghỉ']);
 check(count($legacy)===1&&reset($legacy)['scope']===$scope,'legacy single slot schema retained');
}
check(tlc_return_url(['return_double_q'=>"GV A\r\nLocation: evil",'return_url'=>'https://evil.test'],'double')==='/chuyenmon/tkb_lesson_constraints.php?panel=double&double_q=GV+A%0D%0ALocation%3A+evil','local encoded redirect only');
// Execute the exact production save action with storage replaced by an in-memory spy.
$controller=file_get_contents($root.'/tkb_xep.php');
$start=strpos($controller,"  if(\$action==='special_slot_save')");
$block=substr($controller,$start,strpos($controller,"\n",$start)-$start);
function ttb_save($d){$GLOBALS['saved']=$d;}
function flash(...$args){}
class Redirected extends Exception{}
function tlc_go($panel){$GLOBALS['panel']=$panel;throw new Redirected;}
$action='special_slot_save';$_POST=$input;
try{eval($block);}catch(Redirected$e){}
check(isset($GLOBALS['saved'])&&count($GLOBALS['saved']['special_slots'])===5,'one save for entire batch plus old record');
check($GLOBALS['saved']['plans']===$original['plans']&&$GLOBALS['saved']['fixed_lessons']===$original['fixed_lessons'],'published plans and fixed lessons unchanged');
check($GLOBALS['panel']==='special','save returns to special panel');
check(str_contains($controller,"hash_equals(\$csrf")&&str_contains($controller,"if(!\$canManage"),'existing CSRF/permission guards retained');
foreach(['fixed_lesson_delete'=>'fixed','fixed_scope_save'=>'fixed','special_slot_delete'=>'special','double_lesson_save'=>'double','double_lesson_delete'=>'double','double_lesson_selected_save'=>'double','subject_sequence_delete'=>'sequence']as$actionName=>$panel){
 $a=strpos($controller,"  if(\$action==='".$actionName."')");
 check(str_contains(substr($controller,$a,strpos($controller,"\n",$a)-$a),"tlc_go('".$panel."')"),'correct return panel '.$actionName);
}
// Render the actual page against stub read functions for browser tests.
function cds_user(){return ['role'=>'admin'];}
function cds_can_feature(...$args){return true;}
function ttb_data(){return $GLOBALS['original'];}
function ttb_assignments(){return $GLOBALS['assignments'];}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function show_flash(){}
$_GET=['panel'=>'special'];$_SESSION=['ttb_csrf'=>'synthetic-csrf'];
$page=file_get_contents($root.'/chuyenmon/tkb_lesson_constraints.php');
$page=preg_replace("~require_once [^;]+;~",'',$page,3);
ob_start();eval('?>'.$page);$html=ob_get_clean();
check(str_contains($html,'id="specialClasses"')&&str_contains($html,'name="classes[]"'),'actual page has class selection');
if(in_array('--html',$argv,true))file_put_contents(sys_get_temp_dir().'/lesson-constraints.html',$html);
echo "PASS batch class/slot validation, existing solver scope, legacy compatibility, unchanged published data, actual save action, panel redirects and page render\n";
