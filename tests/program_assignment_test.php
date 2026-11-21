<?php
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/json_store.php';
require __DIR__.'/../includes/program_assignment.php';
require_once __DIR__.'/../includes/json_store.php';
require_once __DIR__.'/../includes/pccm_release.php';
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($ok,$m){if(!$ok)throw new RuntimeException($m);}
function rejects($fn,$m){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection: '.$m);}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function save_json($file,$data){return cds_json_save($file,$data);}
$dir=sys_get_temp_dir().'/cds-gdp-'.bin2hex(random_bytes(6));mkdir($dir,0700);define('DATA_PATH',$dir);define('PCCM_DATA_PATH',$dir);define('BASE_PATH',dirname(__DIR__));define('TKB_WEEKS_FILE',$dir.'/weeks.json');
function cds_school_week_calendar(){return [['key'=>'1','start'=>'2026-09-07','end'=>'2026-09-13'],['key'=>'9','start'=>'2026-11-02','end'=>'2026-11-08'],['key'=>'10','start'=>'2026-11-09','end'=>'2026-11-15']];}
function cds_lb_read_effective(){return false;}
function tkb_subject_canonical($s){return $s;}
function tkb_weeks(){return $GLOBALS['weeks']??[];}
function tkb_save($file,$rows){$GLOBALS['weeks']=$rows;return true;}
function tkb_save_mapping($a,$b){return true;}
function tkb_normalize_weeks($rows){return $rows;}
function ttb_save($data){$GLOBALS['saved_workspace']=$data;return true;}
function flash($m,$type){}
function tkb_mapping(){return ['teachers'=>[],'classes'=>[]];}
function tkb_resolve_slot(array $slot,array $map):array{$slot['teacher']=$slot['teacher_raw'];$slot['class']=$slot['class_raw'];return $slot;}
load_functions(__DIR__.'/../includes/timetable_builder.php',['ttb_pccm_dir','ttb_assignments','ttb_activities','ttb_room_name','ttb_entry_room','ttb_room_conflicts','ttb_slot_key','ttb_publish']);
function get_active_version_id(){return load_json(DATA_PATH.'/active_version.json',[])['id']??'';}
function assignments_file($version){return DATA_PATH.'/assignments_'.$version.'.json';}
function role_assignments_file($version){return DATA_PATH.'/roles_'.$version.'.json';}
try{
 check(gdp_teacher_names(['A',' B ',['name'=>'C'],'A',[],null])===['A','B','C'],'Teacher names accept real CM strings and compatible named records without changing stored data');
 $teachers=['A','B','C'];$ranges=gdp_validate([['teacher'=>'A','from'=>'1','to'=>'7'],['teacher'=>'B','from'=>8,'to'=>20],['teacher'=>'A','from'=>21,'to'=>35]],$teachers);
 check(gdp_teacher(['ranges'=>$ranges],7)==='A'&&gdp_teacher(['ranges'=>$ranges],8)==='B','Teacher follows program period boundary');
 foreach([
 [['teacher'=>'A','from'=>1,'to'=>34]],
 [['teacher'=>'A','from'=>1,'to'=>35],['teacher'=>'B','from'=>7,'to'=>8]],
 [['teacher'=>'X','from'=>1,'to'=>35]],
 [['teacher'=>'A','from'=>1.5,'to'=>35]],
 [['teacher'=>'A','from'=>0,'to'=>35]],
 [['teacher'=>'A','from'=>1,'to'=>36]]
 ]as$bad)rejects(fn()=>gdp_validate($bad,$teachers),'missing/duplicate/teacher/decimal/out of range');
 $configs=['8A'=>['subject'=>'GDĐP','ranges'=>$ranges]];
 save_json($dir.'/active_version.json',['id'=>'v1']);save_json(gdp_file($dir,'v1'),$configs);
 check(gdp_load($dir,'v2')===[],'Version isolation');rejects(fn()=>gdp_file($dir,'../bad'),'path traversal');
 $legacy=[['id'=>'old','teacher'=>'C','subject'=>'GDĐP','class'=>'8A','periods'=>0.3],['id'=>'math','teacher'=>'B','subject'=>'Toán','class'=>'8A','periods'=>4],['id'=>'other','teacher'=>'C','subject'=>'GDĐP','class'=>'8B','periods'=>1]];
 save_json($dir.'/assignments_v1.json',$legacy);$hash=hash_file('sha256',$dir.'/assignments_v1.json');$view=gdp_overlay($legacy,$configs);
 check(count($view)===4&&array_sum(array_column(array_filter($view,fn($r)=>!empty($r['program_assignment'])),'annual_periods'))===35,'Overlay replaces only configured class and totals 35 annual periods');
 check(abs(array_sum(array_column(array_filter($view,fn($r)=>!empty($r['program_assignment'])),'periods'))-1)<0.000001,'Total weekly equivalent is one');
 $complete=[];for($p=1;$p<=35;$p++)$complete[]=['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-01','status'=>'taught','signed_at'=>'signed','ppct_period'=>$p];check(gdp_next($complete,'8A','2026-11-02','2026-09-07')===36,'Completed course has no phantom period 36');
 $records=[];for($p=1;$p<=6;$p++)$records[]=['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-01','status'=>'taught','signed_at'=>'signed','ppct_period'=>$p];
 $records[]=['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-05','status'=>'postponed','signed_at'=>'signed','ppct_period'=>7];
 foreach([['signed_at'=>''],['status'=>'cancelled'],['class'=>'8B'],['date'=>'2025-10-01'],['date'=>'2026-11-02']]as$patch)$records[]=array_replace(['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-06','status'=>'taught','signed_at'=>'signed','ppct_period'=>7],$patch);
 check(gdp_next($records,'8A','2026-11-02','2026-09-07')===7,'Week nine with six completed periods proposes seven, excluding drafts, holidays, other classes, old years and current/future week');
 $records[]=['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-31','status'=>'makeup','signed_at'=>'signed','ppct_period'=>7];check(gdp_next($records,'8A','2026-11-02','2026-09-07')===8,'Signed makeup advances progress');
 unset($records[2]);check(gdp_next($records,'8A','2026-11-02','2026-09-07')===3,'Missing earlier period is never skipped by MAX');
 $data=['_workspace_id'=>'w9','_workspace'=>['week_key'=>'9']];
 check(count(gdp_week_rows($data,$configs,'v1'))===1,'No mandatory weekly confirmation');
 $data['program_week']=['week_key'=>'9','version_id'=>'v1','classes'=>['8A'=>['period'=>7,'teacher'=>'A','hash'=>gdp_hash($configs['8A'])]]];
 $GLOBALS['CDS_TTB_CURRENT_DATA']=$data;$weekly=ttb_assignments();$gdp=array_values(array_filter($weekly,fn($r)=>isset($r['program_period'])));
 check(count($gdp)===1&&$gdp[0]['teacher']==='A'&&$gdp[0]['program_period']===7,'Weekly pick suggests teacher');
 check(hash_file('sha256',$dir.'/assignments_v1.json')===$hash,'Legacy data untouched');
 $activities=ttb_activities($gdp,[]);
 $entry=['activity_id'=>'legacy_copied_id','teacher'=>'B','subject'=>'GDĐP','classes'=>['8A'],'day'=>2,'session'=>'Sáng','period'=>1,'locked'=>true,'room_id'=>'r1'];
 $plan=['id'=>'p','entries'=>[$entry],'unplaced'=>[],'errors'=>[]];$before=$plan;
 $periods=gdp_publish_periods($data,$plan,'9','2026-11-02');
 check($periods['legacy_copied_id']===7&&count($GLOBALS['gdp_publish_warnings'])===1,'Mismatched teacher is advisory; copied identity needs no binding');
 check($plan===$before,'No rewriting actual teacher, room, lock or draft');
 $review=gdp_publish_review($data,$plan,'9','2026-11-02');
 $consent=['token'=>$review['token'],'accepted'=>array_column($review['rows'],'key')];
 $beforeWeeks=$GLOBALS['weeks']??[];
 $rejected=ttb_publish($plan,'Tuần 9','2026-11-02','2026-11-08',['name'=>'fixture'],false,'9');
 check(!$rejected['ok']&&!empty($rejected['needs_program_review'])&&($GLOBALS['weeks']??[])===$beforeWeeks,'Missing consent rejects before writes');
 check(gdp_publish_consent_error($review,['token'=>$review['token'],'accepted'=>[]])!=='','Every mismatched lesson requires own acknowledgement');
 $changedPlan=$plan;$changedPlan['entries'][0]['day']=4;
 check(gdp_publish_consent_error(gdp_publish_review($data,$changedPlan,'9','2026-11-02'),$consent)!=='','Moved lesson invalidates prior review');
 check(gdp_publish_consent_error(gdp_publish_review($data,$plan,'10','2026-11-09'),$consent)!=='','Other week cannot reuse consent');
 $result=ttb_publish($plan,'Tuần 9','2026-11-02','2026-11-08',['name'=>'fixture','id'=>'admin1'],false,'9',false,[],$consent);
 check($result['ok']&&$result['warnings']&&$result['version']['slots'][0]['teacher_raw']==='B','Actual publish succeeds and retains actual teacher with warning');
 check($result['version']['program_assignment_review']['confirmed_user_id']==='admin1'&&count($result['version']['program_assignment_review']['rows'])===1,'Published week records who confirmed and which differences');
 $sourceWeeks=$GLOBALS['weeks'];
 $extra=$plan;$second=$entry;$second['activity_id']='extra';$second['day']=3;$extra['entries'][]=$second;
 $periods=gdp_publish_periods($data,$extra,'9','2026-11-02');check($periods['extra']===8,'Additional lesson allowed, sequential suggestion by slot order');
 $multi=$extra;$multi['entries'][1]['teacher']='C';$multiReview=gdp_publish_review($data,$multi,'9','2026-11-02');
 check(count($multiReview['rows'])===2&&gdp_publish_consent_error($multiReview,['token'=>$multiReview['token'],'accepted'=>[$multiReview['rows'][0]['key']]])!=='','Partial acknowledgement cannot publish multiple mismatches');
 check(gdp_publish_consent_error($multiReview,['token'=>$multiReview['token'],'accepted'=>array_column($multiReview['rows'],'key')])==='','All current rows accepted');
 $savedConfig=$configs;$changedConfig=$configs;$changedConfig['8A']['ranges'][0]['teacher']='C';save_json(gdp_file($dir,'v1'),$changedConfig);
 check(gdp_publish_consent_error(gdp_publish_review($data,$plan,'9','2026-11-02'),$consent)!=='','Changed assignment invalidates consent');save_json(gdp_file($dir,'v1'),$savedConfig);
 $reverse=$extra;$reverse['entries']=array_reverse($reverse['entries']);check(gdp_publish_periods($data,$reverse,'9','2026-11-02')===$periods,'Suggestion independent of entry array order');
 $bad=$extra;$bad['entries'][1]['day']=2;rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'Actual duplicate class/teacher remains blocked');
 $bad=$plan;$bad['entries'][]=['activity_id'=>'math','teacher'=>'B','subject'=>'Toán','classes'=>['8B'],'day'=>2,'session'=>'Sáng','period'=>1];rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'Cross-subject teacher collision still blocks');
 $bad=$plan;$bad['entries'][]=['activity_id'=>'math','teacher'=>'C','subject'=>'Toán','classes'=>['8A'],'day'=>2,'session'=>'Sáng','period'=>1];rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'Class collision still blocks');
 $bad=$plan;$bad['entries'][]=array_replace($entry,['subject'=>'Toán','day'=>4]);rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'IDs shared with another subject remain invalid');
 $late=[['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-01','status'=>'taught','signed_at'=>'late','ppct_period'=>1]];
 save_json($dir.'/lesson_book_records.json',$late);gdp_publish_periods($data,$plan,'9','2026-11-02');check($GLOBALS['weeks']===$sourceWeeks,'Late signing no longer blocks or rewrites published weeks');
 $copy=$data;$copy['_workspace']['week_key']='10';unset($copy['program_week']);
 check(count(gdp_week_rows($copy,$configs,'v1'))===1,'Copy uses current progress without mandatory reconfirmation');
 gdp_publish_periods($copy,$plan,'10','2026-11-09');check($GLOBALS['weeks']===$sourceWeeks,'Cross-week legacy ID accepted without changing source');
 $done=$data;$done['program_week']['classes']['8A']['period']=36;
 check(gdp_publish_periods($done,$plan,'9','2026-11-02')['legacy_copied_id']===0,'Extra lesson after completion remains publishable without fictitious PPCT36');
 check(gdp_publish_periods($data,array_replace($plan,['entries'=>[]]),'9','2026-11-02')===[],'Weekly pick does not force one GDĐP lesson');
 check(lb_program_period_error(['planned_ppct_period'=>7],['status'=>'taught','ppct_period'=>8])==='','PPCT suggestion does not lock signing');
 check(lb_program_warning(['subject'=>'GDĐP','class'=>'8A','ppct_period'=>8,'actual_teacher'=>'A','status'=>'substitute'])!=='','Actual PPCT checks assigned teacher');
 check(lb_program_warning(['subject'=>'GDĐP','class'=>'8A','ppct_period'=>8,'actual_teacher'=>'B'])==='','Correct teacher has no warning');
 check(lb_program_warning(['subject'=>'Toán','class'=>'8A','ppct_period'=>8,'actual_teacher'=>'A'])==='','Other subjects unchanged');
 $user=['name'=>'Fixture'];$_POST=['program_workspace'=>'w9','program_week_key'=>'9','program_version'=>'v1','program_hash'=>hash('sha256',json_encode($configs)),'program_period'=>['8A'=>'8']];
 require __DIR__.'/../includes/program_week_confirm.php';check($GLOBALS['saved_workspace']['program_week']['classes']['8A']['teacher']==='B','Optional confirmation still selects teacher');
 $before=$GLOBALS['saved_workspace'];$_POST['program_workspace']='stale';rejects(function()use(&$data,&$user){require __DIR__.'/../includes/program_week_confirm.php';},'Stale workspace write');check($GLOBALS['saved_workspace']===$before,'Stale request cannot write');
 echo "PASS flexible GDĐP: optional suggestion, copied weeks, late signing, mismatch warning, extra lessons, completion, immutable drafts, duplicate IDs and real collisions\n";
 save_json(gdp_file($dir,'v1'),[]);check(count(ttb_assignments())===3,'Disabling restores untouched legacy assignments');
 $manualFile=$dir.'/manual_assignments.json';
 $manualRows=[
  ['id'=>'m1','version_id'=>'v1','teacher'=>'A','subject'=>'Toán','class_name'=>'8A','periods'=>1],
  ['id'=>'m2','teacher'=>'B','subject'=>'Văn','class_name'=>'8A','periods'=>1],
  ['id'=>'other','version_id'=>'v2','teacher'=>'B','subject'=>'Văn','class_name'=>'8A','periods'=>1],
 ];
 save_json($manualFile,$manualRows);
 rejects(fn()=>pccm_delete_manual_rows($dir,'v1',['m1','other']),'Cross-version deletion is atomic');
 check(load_json($manualFile)===$manualRows,'Failed selection retains every row');
 pccm_delete_manual_rows($dir,'v1',['m1','m2']);
 check(load_json($manualFile)===[$manualRows[2]],'Bulk deletion retains other versions');
 $before=ttb_assignments();
 pccm_publish_version($dir,'v1','Bản thử',false);
 check(!pccm_assignment_write(assignments_file('v1'),fn()=>save_json(assignments_file('v1'),[])),'Locked assignment rejects write');
 rejects(fn()=>pccm_delete_manual_rows($dir,'v1',['m1']),'Locked manual delete rejected');
 pccm_publish_version($dir,'v1','Bản thử',true);
 check(pccm_assignment_write(assignments_file('v1'),fn()=>save_json(assignments_file('v1'),[])),'Unlock allows draft writes');
 save_json($dir.'/active_version.json',['id'=>'v2']);
 check(ttb_assignments()===$before,'TKB still reads published rows after draft edit and version switch');
 check(gdp_builder_configs()['version']==='v1','GDĐP uses same published source');
 echo "PASS PCCM release: bulk scope, atomic rejection, frozen source and unlock\n";
 echo "PASS GDĐP: validation, 35-period coverage, totals, version isolation, week9/period7, makeup, gaps, snapshot invalidation, real scheduler/publish integration, collisions, signed progress and legacy restoration\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}

