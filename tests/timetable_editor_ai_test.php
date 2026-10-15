<?php
/** Actual scheduler/intent validators, synthetic state only, no provider or live data. */
require __DIR__.'/support/load_functions.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function rejects(callable $call,$label){$writes=$GLOBALS['writes'];try{$call();}catch(RuntimeException $e){check($writes===$GLOBALS['writes'],'Rejected operation wrote: '.$label);return;}throw new LogicException('Expected rejection: '.$label);}
function tkb_subject_canonical($s){return $s;}
function ttb_data(){return $GLOBALS['state'];}
function ttb_assignments(){return $GLOBALS['assignments'];}
function ttb_save($data){if(!empty($GLOBALS['fail_save']))return false;$GLOBALS['state']=$data;$GLOBALS['writes']++;return true;}
function ttb_optimizer_lock($id){$GLOBALS['locks']++;return empty($GLOBALS['busy']);}
function ttb_optimizer_unlock($id){$GLOBALS['unlocks']++;}
$builder=__DIR__.'/../includes/timetable_builder.php';preg_match_all('/^function (ttb_\w+)\(/m',file_get_contents($builder),$matches);
load_functions($builder,array_values(array_diff($matches[1],['ttb_data','ttb_assignments','ttb_save'])));
require __DIR__.'/../includes/timetable_editor_ai.php';
$GLOBALS['writes']=0;$GLOBALS['locks']=0;$GLOBALS['unlocks']=0;
$assignments=[['id'=>'a','teacher'=>'Cô An','subject'=>'Toán','class'=>'10A','periods'=>1],['id'=>'b','teacher'=>'Thầy Bình','subject'=>'Văn','class'=>'10B','periods'=>1]];
$data=ttb_default();$data['_workspace_id']='week-test';$data['active_plan']='p';
$activities=ttb_apply_lesson_rules(ttb_activities($assignments,[]),$data);$entries=[];foreach($activities as$a)$entries[]=array_merge($a,['activity_id'=>$a['id'],'day'=>4,'session'=>'Chiều','period'=>1,'locked'=>false]);
$plan=['id'=>'p','name'=>'Fixture','entries'=>$entries,'unplaced'=>[],'score'=>0,'errors'=>[],'custom'=>'kept'];$data['plans']=[$plan];$GLOBALS['state']=$data;$GLOBALS['assignments']=$assignments;
function postFor($data,$plan){return ['csrf'=>'csrf','workspace_id'=>$data['_workspace_id'],'plan_id'=>$plan['id'],'fingerprint'=>ttb_ai_fingerprint($data,$GLOBALS['assignments'],$plan),'ai_command'=>'Chuyển toàn bộ buổi chiều thứ 4 sang chiều thứ 5 của toàn trường'];}
$post=postFor($data,$plan);$preview=tte_ai_prepare($post,$data,$assignments,'csrf');
check($GLOBALS['writes']===0&&$GLOBALS['state']===$data,'Preview never writes');check(count($preview['changes'])===2&&$preview['candidate']['entries'][0]['day']===5,'Actual Vietnamese transfer interpreted and checked');
$apply=$post+['preview_token'=>$preview['token']];tte_ai_apply($apply,$preview,'csrf');$saved=$GLOBALS['state'];$after=ttb_plan($saved,'p');check($after['entries'][0]['day']===5&&$after['custom']==='kept','Explicit apply updates draft only');check(count($after['undo_stack'])===1,'One undo checkpoint');ttb_plan_undo($after);check($after['entries']===$plan['entries'],'Undo restores exact previous entries');
rejects(fn()=>tte_ai_apply($apply,$preview,'csrf'),'Replay against changed draft');$GLOBALS['state']=$data;
foreach(['csrf'=>'wrong','workspace_id'=>'other','plan_id'=>'other','fingerprint'=>'stale']as$key=>$value){$bad=$post;$bad[$key]=$value;rejects(fn()=>tte_ai_prepare($bad,$data,$assignments,'csrf'),'Preview '.$key);$bad=$apply;$bad[$key]=$value;rejects(fn()=>tte_ai_apply($bad,$preview,'csrf'),'Apply '.$key);}
$old=$preview;$old['created_at']=time()-901;rejects(fn()=>tte_ai_apply($apply,$old,'csrf'),'Expired');$bad=$apply;$bad['preview_token']='';rejects(fn()=>tte_ai_apply($bad,$preview,'csrf'),'Empty token');
$changed=$data;$changed['settings']['max_teacher_daily']=1;$GLOBALS['state']=$changed;rejects(fn()=>tte_ai_apply($apply,$preview,'csrf'),'Changed constraints');$GLOBALS['state']=$data;
$GLOBALS['assignments'][0]['periods']=2;rejects(fn()=>tte_ai_apply($apply,$preview,'csrf'),'Changed PCCM');$GLOBALS['assignments']=$assignments;
$GLOBALS['busy']=true;rejects(fn()=>tte_ai_apply($apply,$preview,'csrf'),'Optimizer busy');unset($GLOBALS['busy']);
$GLOBALS['fail_save']=true;rejects(fn()=>tte_ai_apply($apply,$preview,'csrf'),'Save failure');unset($GLOBALS['fail_save']);check($GLOBALS['state']===$data,'Failed save leaves synthetic state unchanged');
$invalid=$preview;$invalid['candidate']['entries'][0]['day']=9;rejects(fn()=>tte_ai_apply($apply,$invalid,'csrf'),'Revalidate invalid candidate');
$invalid=$preview;array_pop($invalid['candidate']['entries']);rejects(fn()=>tte_ai_apply($apply,$invalid,'csrf'),'Inventory loss');
foreach(['fixed','busy','room','closed','session','scope']as$case){$fixture=$data;
 if($case==='fixed')$fixture['fixed_lessons']['a_1']=['day'=>4,'session'=>'Chiều','period'=>1];
 if($case==='busy'){$key='5|Chiều|1';$fixture['blocked'][hash('sha256','Cô An|'.$key)]=['key'=>$key];}
 if($case==='room'){$fixture['rooms']=[['id'=>'r','name'=>'Phòng 1']];$fixture['room_assignments']=['a_1'=>'r','b_1'=>'r'];$fixture['plans'][0]['entries'][1]['day']=5;}
 if($case==='closed'){$fixture['plans'][0]['manual_mode']=true;$fixture['plans'][0]['manual_open_sessions']=['4|Chiều'];}
 if($case==='session')$fixture['subject_rules']['Toán']=['session'=>'Sáng'];
 if($case==='scope')$fixture['scope_blocks']=[['key'=>'5|Chiều|1','scope'=>'grade','value'=>'10']];
 $p=$fixture['plans'][0];rejects(fn()=>tte_ai_prepare(postFor($fixture,$p),$fixture,$assignments,'csrf'),'Constraint '.$case);
}
// Other supported commands use the same real intent executor, without any paid API.
$move=['action'=>'move','selector'=>['class'=>'10A','subject'=>'Toán'],'target'=>['day'=>3,'session'=>'Sáng','period'=>2]];
$result=ttb_ai_execute_intent($data,$assignments,$plan,$move);check($result['changed']&&$result['plan']['entries'][0]['day']===3,'Move');
$locked=$plan;$locked['entries'][0]['locked']=true;rejects(fn()=>ttb_ai_execute_intent($data,$assignments,$locked,$move),'Locked move');
$collision=$move;$collision['target']=['day'=>4,'session'=>'Chiều','period'=>1];$sameTeacher=$assignments;$sameTeacher[1]['teacher']='Cô An';$samePlan=$plan;$samePlan['entries'][1]['teacher']='Cô An';$samePlan['entries'][1]['day']=3; $collision['target']['day']=3;
rejects(fn()=>ttb_ai_execute_intent($data,$sameTeacher,$samePlan,$collision),'Teacher collision');
$swap=['action'=>'swap','selector'=>['class'=>'10A'],'other'=>['class'=>'10B']];$swapPlan=$plan;$swapPlan['entries'][1]['day']=3;check(ttb_ai_execute_intent($data,$assignments,$swapPlan,$swap)['changed'],'Swap');
check(!ttb_ai_execute_intent($data,$assignments,$plan,['action'=>'check'])['changed'],'Check read-only');
rejects(fn()=>ttb_ai_execute_intent($data,$assignments,$plan,['action'=>'clarify','question'=>'Cần thêm thông tin']),'Clarify');
rejects(fn()=>ttb_ai_execute_intent($data,$assignments,$plan,['action'=>'sql','sql'=>'DELETE']),'Untrusted intent');
echo "PASS editor AI: preview without writes, actual constraints, explicit apply, stale/replay/CSRF/scope/TTL/PCCM, failure, undo, move/swap/check and invalid intent\n";

// Real panel render: escaped provider/user text, stale apply disabled, no cross-week preview.
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$_SESSION=['tte_ai_preview'=>$preview];$_GET=['assistant'=>'1'];$csrf='csrf';$mode='class';$class='10A';$teacher='Cô An';
$preview['message']='<script>alert(1)</script>';$_SESSION['tte_ai_preview']=$preview;
ob_start();require __DIR__.'/../includes/timetable_editor_ai_panel.php';$html=ob_get_clean();
check(str_contains($html,'editorAiApplyForm')&&!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;'),'Panel escapes text and renders apply');
$preview['fingerprint']='stale';$_SESSION['tte_ai_preview']=$preview;ob_start();require __DIR__.'/../includes/timetable_editor_ai_panel.php';$html=ob_get_clean();check((bool)preg_match('/class="btn btn-success btn-sm" disabled/',$html),'Stale apply disabled');
$preview['workspace']='another-week';$_SESSION['tte_ai_preview']=$preview;ob_start();require __DIR__.'/../includes/timetable_editor_ai_panel.php';$html=ob_get_clean();check(!str_contains($html,'editorAiApplyForm'),'No cross-workspace apply shown');
echo "PASS panel: escaped text, stale proposal, cross-workspace isolation\n";
$fillPlan=$plan;$fillPlan['entries']=[$entries[0]];$fillPlan['unplaced']=[$activities[1]];
$fill=ttb_ai_execute_intent($data,$assignments,$fillPlan,['action'=>'fill','scope'=>'school','selector'=>[]]);
check($fill['changed']&&count($fill['plan']['entries'])===2&&!$fill['plan']['unplaced'],'Fill retains all activities');
$gapAssignments=[['id'=>'g','teacher'=>'Cô An','subject'=>'Toán','class'=>'10A','periods'=>2]];$gapData=$data;$gapData['settings']['max_subject_class_session']=2;
$gapActivities=ttb_apply_lesson_rules(ttb_activities($gapAssignments,[]),$gapData);$gapPlan=$plan;$gapPlan['entries']=[];
foreach($gapActivities as$i=>$a)$gapPlan['entries'][]=$a+['activity_id'=>$a['id'],'day'=>2,'session'=>'Sáng','period'=>$i?3:1];
$optimized=ttb_ai_execute_intent($gapData,$gapAssignments,$gapPlan,['action'=>'optimize_gaps','selector'=>['teacher'=>'Cô An']]);
check($optimized['changed']&&ttb_ai_gap_count($optimized['plan']['entries'])<ttb_ai_gap_count($gapPlan['entries']),'Optimize gaps improves actual schedule');
$disabledPost=$post;$disabledPost['ai_command']='Kiểm tra lỗi TKB';rejects(fn()=>tte_ai_prepare($disabledPost,$data,$assignments,'csrf'),'Disabled AI service');
// Render the actual editor with only bootstrap/config includes replaced by fixture state.
function cds_user(){return ['role'=>'admin'];}
function show_flash(){}
$_SERVER['REQUEST_METHOD']='GET';$_GET=['mode'=>'class','class'=>'10A','assistant'=>'1'];$_REQUEST=[];$_SESSION['ttb_csrf']='csrf';
$path=__DIR__.'/../tkb_editor.php';$page=file_get_contents($path);
foreach(["require_once __DIR__.'/chuyenmon/includes/functions.php';","require_once __DIR__.'/chuyenmon/includes/timetable_store.php';","require_once __DIR__.'/includes/timetable_builder.php';"]as$line)$page=str_replace($line,'',$page);
$page=str_replace('__DIR__',var_export(dirname(realpath($path)),true),$page);
ob_start();eval('?>'.$page);$editorHtml=ob_get_clean();
check(str_contains($editorHtml,'data-side="ai"')&&str_contains($editorHtml,'id="editorAiCommandForm"')&&str_contains($editorHtml,'/assets/timetable-editor-ai.js'),'Actual editor renders integrated AI panel');
echo "PASS: fill, gap optimization and actual editor rendering without live config\n";
