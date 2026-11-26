<?php
$dir=sys_get_temp_dir().'/achievement-pccm-'.bin2hex(random_bytes(6));mkdir($dir);mkdir($dir.'/pccm');define('DATA_PATH',$dir);define('PCCM_DATA_PATH',$dir.'/pccm');
require __DIR__.'/../includes/chuyenmon_permission_runtime.php';require __DIR__.'/../includes/permissions.php';require __DIR__.'/../includes/achievement_board.php';
function load_json($f,$d=[]){return cds_json_load($f,$d);}function current_user(){return $GLOBALS['actor'];}
function check($ok,$m){if(!$ok)throw new RuntimeException($m);}function denied(callable $fn){try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected denial');}
$admin=['id'=>'admin','role'=>'admin','name'=>'Quản trị'];$a=['id'=>'uA','role'=>'teacher','name'=>'Trần An','teacher_name'=>'','teacher_id'=>'gvA','permission_model_version'=>2];$b=['id'=>'uB','role'=>'teacher','name'=>'Lê Bình','teacher_id'=>'gvB','permission_model_version'=>2];$GLOBALS['actor']=$admin;$_SESSION=[];
$teachers=[['id'=>'gvA','code'=>'001','name'=>'Trần An','active'=>true],['id'=>'gvB','code'=>'002','name'=>'Lê Bình','active'=>true]];
$today=ab_now()->format('Y-m-d');$year=ab_now()->format('Y');$ctx=['classes'=>['6A'=>'6A','6B'=>'6B'],'subjects'=>['Toán','Văn','Tin học','GDĐP'],'years'=>['y'=>['start'=>$year.'-01-01','end'=>$year.'-12-31'],'old'=>['start'=>'2000-01-01','end'=>'2000-12-31']],'users'=>['uA'=>$a,'uB'=>$b],'students'=>['s1'=>['id'=>'s1','name'=>'An','class'=>'6A'],'s2'=>['id'=>'s2','name'=>'Bình','class'=>'6A'],'s3'=>['id'=>'s3','name'=>'Chi','class'=>'6B']]];
$rows=[['class'=>'6A','subject'=>'Toán','teacher'=>'Trần An','periods'=>4],['class'=>'6A','subject'=>'Văn','teacher_code'=>'002','teacher'=>'Lê Bình','periods'=>4],['class'=>'6B','subject'=>'Toán','teacher_id'=>'gvB','teacher'=>'Lê Bình','periods'=>4]];
$put=fn($file,$value)=>file_put_contents(PCCM_DATA_PATH.'/'.$file,json_encode($value,JSON_UNESCAPED_UNICODE));
try{
 $put('active_version.json',['id'=>'v1']);$put('assignments_v1.json',$rows);$put('manual_assignments.json',[['version_id'=>'v1','class_name'=>'6A','subject'=>'Tin học','teacher'=>'Trần An'],['version_id'=>'v2','class'=>'6B','subject'=>'Văn','teacher'=>'Trần An']]);
 $source=ab_pccm_source(PCCM_DATA_PATH);ab_pccm_prepare($source,$teachers,'y',$ctx['classes'],$ctx['subjects']);$meta=ab_empty();
 check($source['available']&&count($source['rows'])===4,'Active version and version-owned manual assignments');
 check(ab_scope_allowed($meta,$a,'6A','Toán','delete','y')&&ab_scope_allowed($meta,$a,'6A','Tin học','edit','y'),'PCCM automatically grants create/edit/delete without manual feature grants');
 check(!ab_scope_allowed($meta,$a,'6A','Văn','edit','y')&&!ab_scope_allowed($meta,$a,'6B','Văn','edit','y'),'No subject or version scope leakage');
 check(ab_scope_allowed($meta,array_merge($a,['groups'=>['gvcn'],'homeroom_classes'=>['6B']]),'6A','Toán','edit','y'),'Actual teaching assignments are independent of homeroom duty');
 check(!ab_scope_allowed($meta,$a,'6A','Toán','edit','old')&&ab_scope_allowed($meta,$a,'6A','Toán','view','old'),'Current assignment cannot change a previous academic year');
 check(!ab_feature(array_merge($a,['permission_overrides'=>['hl.thanhtich'=>'none']]),'view'),'Explicit account deny wins');
 check(!ab_scope_allowed($meta,array_merge($a,['permission_overrides'=>['hl.thanhtich'=>'view']]),'6A','Toán','edit','y'),'Explicit view-only cap wins');
 check(!ab_scope_allowed($meta,array_merge($a,['role'=>'viewer']),'6A','Toán','edit','y'),'Viewer never inherits assignment writes');
 check(ab_pccm_navigation_hint($a)&&!ab_pccm_navigation_hint(array_merge($a,['permission_overrides'=>['hl.thanhtich'=>'none']])),'Hub navigation follows PCCM without ignoring a deny');
 check(ab_pccm_pairs(array_diff_key($a,['teacher_id'=>true]))===[],'Editable display name cannot grant PCCM rights');
 check(ab_pccm_pairs(array_merge($a,['teacher_id'=>'unknown']))===[],'Invalid explicit staff identity never falls back to name');
 $dupes=array_merge($teachers,[['id'=>'gvOther','code'=>'003','name'=>'Trần An']]);ab_pccm_prepare($source,$dupes,'y',$ctx['classes'],$ctx['subjects']);check(ab_pccm_pairs($a)===[],'Duplicate names do not grant privileges');
 ab_pccm_prepare($source,$teachers,'y',$ctx['classes'],$ctx['subjects']);
 $award=['action'=>'award','year'=>'y','class'=>'6A','criterion_id'=>'homework','date'=>$today,'students'=>['s1'],'csrf'=>ab_csrf(),'request_id'=>'math_reward_000001','subject'=>'Toán'];
 ab_save($award,$admin,$ctx);ab_save(array_merge($award,['students'=>['s2'],'criterion_id'=>'participate','request_id'=>'math_reward_000002']),$a,$ctx);
 ab_save(array_merge($award,['subject'=>'Văn','criterion_id'=>'missing','request_id'=>'lit_reward_000001']),$b,$ctx);ab_save(array_merge($award,['subject'=>'Văn','students'=>['s2'],'criterion_id'=>'progress','request_id'=>'lit_reward_000002']),$b,$ctx);
 $summary=ab_class_summary(ab_load(),$ctx,'y','6A',$today,$today);$byId=array_column($summary['rows'],null,'id');
 check($byId['s1']['subjects']['Toán']===2&&$byId['s1']['subjects']['Văn']===-2&&$byId['s1']['total']===0,'All-subject net per student');
 check($byId['s2']['total']===4&&$byId['s2']['rank']===1&&$summary['reward']===6&&$summary['penalty']===2&&$summary['total']===4,'Class totals and rank include all subjects');
 check(ab_class_summary_allowed(ab_load(),$a,'6A')&&!ab_class_summary_allowed(ab_load(),$a,'6B'),'Teacher aggregate read is confined to taught classes');
 check(!array_key_exists('history',$summary)&&!array_key_exists('note',$byId['s1']),'Aggregate does not disclose other-subject event notes');
 $key=ab_key('y','6A','Toán');$original=ab_load($key)['events'][0];$edit=['action'=>'edit_event','year'=>'y','class'=>'6A','subject'=>'Toán','event_id'=>$original['id'],'criterion_id'=>'homework','points'=>4,'date'=>$today,'note'=>'Đã sửa','request_id'=>'edit_request_000001','csrf'=>ab_csrf()];
 ab_save($edit,$a,$ctx);$edited=ab_load($key);check(count($edited['events'])===3&&$edited['events'][0]['points']===2&&$edited['events'][0]['void_reason']==='edited'&&$edited['events'][2]['replaces']===$original['id'],'Assigned teacher can revise an earlier teacher entry without erasing original');
 ab_save($edit,$a,$ctx);check(count(ab_load($key)['events'])===3,'Editing retry is idempotent');
 $summary=ab_class_summary(ab_load(),$ctx,'y','6A',$today,$today);check($summary['total']===6,'Revision counted once in combined totals');
 ab_save(['action'=>'undo','year'=>'y','class'=>'6A','subject'=>'Toán','event_id'=>$edited['events'][2]['id'],'csrf'=>ab_csrf()],$a,$ctx);check(ab_class_summary(ab_load(),$ctx,'y','6A',$today,$today)['total']===2,'Deleting revised points updates combined totals');
 denied(fn()=>ab_save(array_merge($edit,['subject'=>'Văn','request_id'=>'forged_edit_000001']),$a,$ctx));
 $published=['version'=>'v2','name'=>'Bản chính thức','rows'=>[['class'=>'6A','subject'=>'Toán','teacher'=>'Lê Bình'],['class'=>'6B','subject'=>'Toán','teacher'=>'Trần An']],'manual'=>[],'configs'=>['6A'=>['subject'=>'GDĐP','ranges'=>[['teacher'=>'Trần An','from'=>1,'to'=>35]]]]];
 check(pccm_save_release_state(PCCM_DATA_PATH,['published'=>$published]),'Publish fixture');file_put_contents(PCCM_DATA_PATH.'/active_version.json','{broken draft');
 $next=ab_pccm_source(PCCM_DATA_PATH);ab_pccm_prepare($next,$teachers,'y',$ctx['classes'],$ctx['subjects']);
 $legacy=ab_load();$legacy['grants'][]=['user_id'=>'uA','class'=>'6A','subject'=>'Toán'];
 check(!ab_scope_allowed($legacy,$a,'6A','Toán','edit','y')&&ab_scope_allowed($legacy,$a,'6B','Toán','delete','y')&&ab_scope_allowed($legacy,$a,'6A','GDĐP','edit','y'),'Published change revokes stale/manual scopes and includes program-range teachers');
 $bytes=file_get_contents(ab_board_path($key));denied(fn()=>ab_save($edit,$a,$ctx));check(file_get_contents(ab_board_path($key))===$bytes,'Revoked teacher cannot edit existing points');
 echo "PASS PCCM achievement sync: published/active/manual/program scopes, identity ambiguity, explicit caps, live revocation, edits with history, all-subject class totals and year boundaries\n";
}finally{$walk=function($d)use(&$walk){foreach(glob($d.'/*') as $p){if(is_dir($p))$walk($p);else unlink($p);}rmdir($d);};$walk($dir);unset($GLOBALS['ab_pccm_context']);}
