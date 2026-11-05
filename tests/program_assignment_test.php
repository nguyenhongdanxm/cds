<?php
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/json_store.php';
require __DIR__.'/../includes/program_assignment.php';
require __DIR__.'/../chuyenmon/includes/lesson_book_sign_validation.php';
function check($ok,$m){if(!$ok)throw new RuntimeException($m);}
function rejects($fn,$m){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection: '.$m);}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function save_json($file,$data){return cds_json_save($file,$data);}
$dir=sys_get_temp_dir().'/cds-gdp-'.bin2hex(random_bytes(6));mkdir($dir,0700);define('DATA_PATH',$dir);define('PCCM_DATA_PATH',$dir);define('BASE_PATH',dirname(__DIR__));define('TKB_WEEKS_FILE',$dir.'/weeks.json');
function cds_school_week_calendar(){return [['key'=>'1','start'=>'2026-09-07','end'=>'2026-09-13'],['key'=>'9','start'=>'2026-11-02','end'=>'2026-11-08']];}
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
try{
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
 $data=['_workspace_id'=>'w9','_workspace'=>['week_key'=>'9']];rejects(fn()=>gdp_week_rows($data,$configs,'v1'),'Confirmation required');
 $data['program_week']=['week_key'=>'9','version_id'=>'v1','classes'=>['8A'=>['period'=>7,'teacher'=>'A','hash'=>gdp_hash($configs['8A'])]]];$data['program_week']['progress_hash']=gdp_progress_hash($configs,gdp_week_context($data),[]);$GLOBALS['CDS_TTB_CURRENT_DATA']=$data;
 $weekly=ttb_assignments();$gdp=array_values(array_filter($weekly,fn($r)=>isset($r['program_period'])));check(count($gdp)===1&&$gdp[0]['teacher']==='A'&&$gdp[0]['periods']===1&&$gdp[0]['program_period']===7,'Actual builder receives one integer period for A in week nine');
 check(hash_file('sha256',$dir.'/assignments_v1.json')===$hash,'Legacy data untouched');
 $copy=$data;$copy['_workspace']['week_key']='1';rejects(fn()=>gdp_week_rows($copy,$configs,'v1'),'Copied week must be reconfirmed');
 $changed=$configs;$changed['8A']['ranges'][0]['teacher']='C';rejects(fn()=>gdp_week_rows($data,$changed,'v1'),'Changed range invalidates confirmation');rejects(fn()=>gdp_week_rows($data,$configs,'v2'),'Changed version invalidates confirmation');
 $activities=ttb_activities($gdp,[]);check(count($activities)===1&&$activities[0]['teacher']==='A','One scheduler activity with correct teacher');
 $entry=['activity_id'=>$activities[0]['id'],'teacher'=>'A','subject'=>'GDĐP','classes'=>['8A'],'day'=>2,'session'=>'Sáng','period'=>1];$plan=['id'=>'p','entries'=>[$entry],'unplaced'=>[],'errors'=>[]];
 $periods=gdp_publish_periods($data,$plan,'9','2026-11-02');check($periods[$entry['activity_id']]===7,'Publish carries pinned program period');
 save_json($dir.'/lesson_book_records.json',[['class'=>'8A','subject'=>'GDĐP','date'=>'2026-10-01','status'=>'taught','signed_at'=>'late','ppct_period'=>1]]);rejects(fn()=>gdp_publish_periods($data,$plan,'9','2026-11-02'),'Late signing requires explicit progress review');save_json($dir.'/lesson_book_records.json',[]);
 $bad=$plan;$bad['entries'][0]['teacher']='B';rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'Stale teacher not silently changed');
 rejects(fn()=>gdp_publish_periods($data,$plan,'1','2026-09-07'),'Publish different week blocked');
 $bad=$plan;$bad['entries'][]=['activity_id'=>'math','teacher'=>'A','subject'=>'Toán','classes'=>['8B'],'day'=>2,'session'=>'Sáng','period'=>1];rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'Teacher collision blocked even incomplete publishing');
 $bad=$plan;$bad['entries']=[];rejects(fn()=>gdp_publish_periods($data,$bad,'9','2026-11-02'),'Missing configured class cannot silently publish');
 $result=ttb_publish($plan,'Tuần 9','2026-11-02','2026-11-08',['name'=>'fixture'],false,'9');check(!empty($result['ok']),'Real publish succeeds');check($GLOBALS['weeks'][0]['slots'][0]['program_period']===7&&$GLOBALS['weeks'][0]['slots'][0]['teacher_raw']==='A','Published slot freezes teacher and program period');

 $data['_workspace_id']='w9';$user=['name'=>'Fixture'];$_POST=['program_workspace'=>'w9','program_week_key'=>'9','program_version'=>'v1','program_hash'=>hash('sha256',json_encode($configs)),'program_period'=>['8A'=>'8']];
 require __DIR__.'/../includes/program_week_confirm.php';
 check($GLOBALS['saved_workspace']['program_week']['classes']['8A']['teacher']==='B','Real weekly confirmation selects teacher B at period 8');
 $before=$GLOBALS['saved_workspace'];$_POST['program_workspace']='stale';rejects(function()use(&$data,&$user){require __DIR__.'/../includes/program_week_confirm.php';},'Stale workspace confirmation');check($GLOBALS['saved_workspace']===$before,'Stale confirmation does not write');
 $published=$GLOBALS['weeks'];$changed['8A']['ranges'][0]['teacher']='C';save_json(gdp_file($dir,'v1'),$changed);check($GLOBALS['weeks']===$published,'Config save cannot rewrite published weeks');
 check(lb_program_period_error(['planned_ppct_period'=>7],['status'=>'taught','ppct_period'=>8])!=='','Cannot change pinned lesson to another teacher range while signing');check(lb_program_period_error(['planned_ppct_period'=>7],['status'=>'postponed','ppct_period'=>0])==='','Postponement permitted without consuming progress');check(lb_program_period_error([],['ppct_period'=>8])==='','Other subjects unchanged');
 save_json(gdp_file($dir,'v1'),[]);check(count(ttb_assignments())===3,'Disabling restores untouched legacy assignments');
 echo "PASS GDĐP: validation, 35-period coverage, totals, version isolation, week9/period7, makeup, gaps, snapshot invalidation, real scheduler/publish integration, collisions, signed progress and legacy restoration\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
