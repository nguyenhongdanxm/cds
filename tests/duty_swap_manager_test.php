<?php
// Synthetic files only. Never bootstrap school configuration, credentials or live data.
require __DIR__.'/../includes/json_store.php';
require __DIR__.'/support/load_functions.php';
require __DIR__.'/../includes/noitru_duty_swap_access.php';
function check($ok,string$message):void{if(!$ok)throw new RuntimeException($message);}
$mode=$argv[1]??'';
$root=$argv[2]??sys_get_temp_dir().'/cds_duty_scope_'.bin2hex(random_bytes(6));
check(str_starts_with($root,sys_get_temp_dir().'/cds_duty_scope_')&&!str_contains($root,'..'),'Isolated fixture path');
if($mode===''&&!is_dir($root))mkdir($root,0700);
define('NOITRU_DIR',$root);define('NOITRU_DUTY',$root.'/duty.json');define('NOITRU_DUTY_SWAP_REQUESTS',$root.'/requests.json');define('NOITRU_DUTY_MANAGERS',$root.'/managers.json');define('BASE_URL','/');
function noitru_ensure_dir(){}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function save_json($file,$data){return cds_json_save($file,$data);}
function noitru_now(){return '2030-01-01T08:00:00+07:00';}
function current_user(){return $GLOBALS['actor']??null;}
function csdl_teachers_all(){return $GLOBALS['teachers']??[
 ['id'=>'mA','name'=>'Nguyễn Quản Lý','active'=>1],['id'=>'mB','name'=>'Trần Quản Lý','active'=>1],
 ['id'=>'t1','name'=>'Giáo viên A','active'=>1],['id'=>'t2','name'=>'Giáo viên B','active'=>1],['id'=>'t3','name'=>'Giáo viên C','active'=>1],
];}
function can_edit_perm($perm){return !empty($GLOBALS['actor']['global_edit'])||($GLOBALS['actor']['role']??'')==='admin';}
function can_module($module,$level){return $module==='noitru'&&$level==='admin'&&!empty($GLOBALS['actor']['module_admin']);}
function allowed_classes(){return $GLOBALS['actor']['scope']??null;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
load_functions(__DIR__.'/../includes/noitru_store.php',['noitru_duty_all','noitru_duty_managers_all','noitru_duty_swap_requests_all','noitru_duty_swap_request_decide']);
function actor($id,string$role='gv'):array{return ['id'=>'u-'.$id,'teacher_id'=>$id,'teacher_name'=>'','name'=>$id,'role'=>$role];}
function seed():array{
 $duties=[['id'=>'s','date'=>'2030-01-02','teacher_id'=>'t1','teacher_name'=>'Giáo viên A','note'=>'S','unknown'=>['keep'=>1]],['id'=>'t','date'=>'2030-01-04','teacher_id'=>'t2','teacher_name'=>'Giáo viên B','note'=>'T','unknown'=>['keep'=>2]],['id'=>'u','date'=>'2030-01-10','teacher_id'=>'t3','teacher_name'=>'Giáo viên C','note'=>'UNCHANGED']];
 $request=['id'=>'r1','requester_id'=>'t1','requester_name'=>'Giáo viên A','source_duty_id'=>'s','source_date'=>'2030-01-02','source_teacher_id'=>'t1','source_teacher_name'=>'Giáo viên A','target_duty_id'=>'t','target_date'=>'2030-01-04','target_teacher_id'=>'t2','target_teacher_name'=>'Giáo viên B','reason'=>'Đổi lịch <script>','status'=>'pending','created_at'=>'2030-01-01T07:00:00+07:00','extra'=>['keep'=>true]];
 save_json(NOITRU_DUTY,$duties);save_json(NOITRU_DUTY_SWAP_REQUESTS,[$request]);save_json(NOITRU_DUTY_MANAGERS,[['date'=>'2030-01-02','teacher_ids'=>['mA']],['date'=>'2030-01-04','teacher_ids'=>['mB']],['date'=>'2030-01-10','teacher_ids'=>['t3']]]);
 return [$duties,$request];
}
function hashes():array{return array_map(fn($p)=>hash_file('sha256',$p),[NOITRU_DUTY,NOITRU_DUTY_SWAP_REQUESTS,NOITRU_DUTY_MANAGERS]);}
if($mode==='worker'){$GLOBALS['actor']=actor('mA');echo json_encode(noitru_duty_swap_request_decide('r1','approved','manager'));exit;}
if($mode==='entry'){
 $GLOBALS['actor']=json_decode($argv[3],true);$_GET=json_decode($argv[4],true);
 function require_module($module,$level){if(empty($GLOBALS['actor']['module_view']))exit('BLOCKED_MODULE');}
 function can_perm($code){return false;}
 function require_perm($code){exit('BLOCKED_TAB');}
 $source=file_get_contents(__DIR__.'/../noitru.php');$start=strpos($source,'$user = current_user();');$end=strpos($source,'/** Stop before',$start);check($start!==false&&$end!==false,'Entry boundaries');eval(substr($source,$start,$end-$start));
 $start=strpos($source,"\$requestedTab = \$_GET['tab']");$end=strpos($source,"if (\$tab === 'duty' &&",$start);check($start!==false&&$end!==false,'Tab boundaries');eval(substr($source,$start,$end-$start));echo 'ENTRY_OK';exit;
}
if($mode==='filter'){
 $GLOBALS['actor']=json_decode($argv[3],true);$user=$GLOBALS['actor'];$canManageDuty=noitru_duty_swap_can_manage_all();$dutyCurrentTeacherId=(string)($user['teacher_id']??'');$dutyMonth='2030-01';
 $source=file_get_contents(__DIR__.'/../includes/noitru_duty_view.php');$start=strpos($source,'$dutyManagerDates=');$end=strpos($source,'$dutyRoster =',$start);check($start!==false&&$end!==false,'Filter boundaries');eval(substr($source,$start,$end-$start));echo json_encode(array_column($dutySwapRequests,'id'));exit;
}
if($mode==='probe'){
 $GLOBALS['actor']=json_decode($argv[3],true);$_POST=json_decode($argv[4],true);$_SERVER['REQUEST_METHOD']='POST';$_SESSION=['duty_swap_csrf'=>'fixture-valid'];
 function noitru_can_edit_duty_report($date){return false;}
 function require_perm_level($code,$level){if(($GLOBALS['actor']['role']??'')!=='admin')exit('BLOCKED_EDIT');}
 function noitru_require_global_scope(){if(($GLOBALS['actor']['role']??'')!=='admin')exit('BLOCKED_SCOPE');}
 $source=file_get_contents(__DIR__.'/../noitru.php');$start=strpos($source,"if (\$_SERVER['REQUEST_METHOD'] === 'POST') {");$end=strpos($source,"    if (in_array(\$action, ['rice_settings'",$start);check($start!==false&&$end!==false,'Actual dispatch boundaries');
 eval(substr($source,$start,$end-$start)."\n}\necho 'DISPATCH_OK';");exit;
}
if($mode==='render'){
 $GLOBALS['actor']=json_decode($argv[3],true);$user=$GLOBALS['actor'];$canManageDuty=noitru_duty_swap_can_manage_all();$dutyManagerDates=noitru_duty_swap_manager_dates();$dutySwapRequests=noitru_duty_swap_requests_all();$dutyMonth='2030-01';$_SESSION=[];
 require __DIR__.'/../includes/noitru_duty_swap_requests_view.php';exit;
}
function run_child(array$args):string{
 $p=proc_open(array_merge([PHP_BINARY,__FILE__],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0,'Fixture worker '.$out.$err);return$out;
}
try{
 [$duties,$request]=seed();
 foreach(['mA','mB']as$id){$GLOBALS['actor']=actor($id);check(noitru_duty_swap_can_decide($request),'Source or target day manager admitted');}
 $GLOBALS['actor']=actor('mA')+['scope'=>['6A']];check(noitru_duty_swap_can_decide($request),'Assigned manager with a restricted homeroom scope can decide this day only');
 foreach(['t1','t2','t3']as$id){$GLOBALS['actor']=actor($id);$before=hashes();check(!noitru_duty_swap_can_decide($request),'Ordinary participant/unrelated manager denied');check(!noitru_duty_swap_request_decide('r1','approved','forged')[0],'Direct mutator denies unrelated actor');check(hashes()===$before,'Denied actor cannot change files');}
 $GLOBALS['actor']=actor('mA','viewer');check(!noitru_duty_swap_can_decide($request),'Read-only guest denied despite assignment');check(!noitru_duty_swap_request_decide('r1','rejected','guest')[0],'Guest cannot reject');
 $GLOBALS['actor']=null;check(!noitru_duty_swap_can_decide($request),'Anonymous denied');
 $GLOBALS['actor']=['id'=>'legacy','role'=>'gv','teacher_name'=>'Nguyễn Quản Lý'];check(noitru_duty_swap_teacher_id()==='mA','Trusted legacy teacher link resolved');
 $GLOBALS['actor']=['id'=>'unlinked','role'=>'gv','name'=>'Nguyễn Quản Lý'];check(noitru_duty_swap_teacher_id()==='','Display name alone cannot impersonate manager');
 $GLOBALS['actor']=['id'=>'stale','role'=>'gv','teacher_id'=>'invalid','teacher_name'=>'Nguyễn Quản Lý'];check(noitru_duty_swap_teacher_id()==='','Invalid explicit ID cannot fall back to a privileged name');
 $GLOBALS['actor']=['id'=>'legacy','role'=>'gv','teacher_name'=>'Nguyễn Quản Lý'];$GLOBALS['teachers']=array_merge(csdl_teachers_all(),[['id'=>'duplicate','name'=>'Nguyễn Quản Lý','active'=>1]]);check(noitru_duty_swap_teacher_id()==='','Ambiguous names denied');unset($GLOBALS['teachers']);
 $GLOBALS['actor']=actor('mA');$GLOBALS['teachers']=csdl_teachers_all();$GLOBALS['teachers'][0]['active']=0;check(!noitru_duty_swap_can_decide($request),'Inactive teacher denied');unset($GLOBALS['teachers']);
 $GLOBALS['actor']=actor('outside','custom')+['global_edit'=>true];check(noitru_duty_swap_can_decide($request),'Existing global duty editor preserved');$GLOBALS['actor']['scope']=['6A'];check(!noitru_duty_swap_can_decide($request),'Existing restricted editor cannot bypass global scope');
 $GLOBALS['actor']=actor('outside','custom')+['module_admin'=>true];check(noitru_duty_swap_can_decide($request),'Legacy module administrator override is preserved');$GLOBALS['actor']['scope']=['6A'];check(!noitru_duty_swap_can_decide($request),'Legacy module admin still requires global scope');
 $GLOBALS['actor']=actor('outside','admin');check(noitru_duty_swap_can_decide($request),'Administrator authority preserved');
 $GLOBALS['actor']=actor('mA');$cachedDates=noitru_duty_swap_manager_dates();save_json(NOITRU_DUTY_MANAGERS,[]);$before=hashes();check(!noitru_duty_swap_request_decide('r1','approved','manager')[0],'Assignment removed after rendering is rechecked under lock');check(hashes()===$before,'Removed assignment leaves files unchanged');
 save_json(NOITRU_DUTY_MANAGERS,[['date'=>'2030-01-02','teacher_ids'=>['mB']],['date'=>'2030-01-02','teacher_ids'=>['mA']]]);check(!noitru_duty_swap_can_decide($request),'A stale duplicate manager row cannot override the effective first assignment');
 [$duties,$request]=seed();$GLOBALS['actor']=actor('mA');check(noitru_duty_swap_request_decide('r1','approved','Manager A',' agreed ')[0],'Assigned manager approves without global edit');$after=noitru_duty_all();check($after[0]['teacher_id']==='t2'&&$after[1]['teacher_id']==='t1','Only the same two teachers swap');check($after[0]['date']===$duties[0]['date']&&$after[1]['date']===$duties[1]['date']&&$after[2]===$duties[2],'Dates and unrelated schedule preserved');check($after[0]['unknown']===$duties[0]['unknown']&&$after[1]['unknown']===$duties[1]['unknown'],'Unknown duty metadata preserved');$saved=noitru_duty_swap_requests_all()[0];check($saved['status']==='approved'&&$saved['decision_by']==='Manager A'&&$saved['decision_note']==='agreed'&&$saved['extra']===$request['extra'],'Existing decision schema and metadata preserved');$before=hashes();check(!noitru_duty_swap_request_decide('r1','approved','second')[0]&&hashes()===$before,'Replay cannot swap a second time');
 [$duties,$request]=seed();$GLOBALS['actor']=actor('mB');check(noitru_duty_swap_request_decide('r1','rejected','Manager B')[0],'Target day manager can reject');check(noitru_duty_all()===$duties,'Rejection does not alter duties');
 foreach(['changed','deleted','collision']as$case){[$duties,$request]=seed();$GLOBALS['actor']=actor('mA');if($case==='changed')$duties[0]['teacher_id']='t3';elseif($case==='deleted')array_shift($duties);else$duties[]=['id'=>'collision','date'=>'2030-01-02','teacher_id'=>'t2','teacher_name'=>'Giáo viên B'];save_json(NOITRU_DUTY,$duties);$before=hashes();check(!noitru_duty_swap_request_decide('r1','approved','Manager A')[0]&&hashes()===$before,'Existing stale/missing/duplicate date rule '.$case);}
 seed();$_SESSION=[];check(!noitru_duty_swap_csrf_valid(''),'Empty CSRF never accepted');$token=noitru_duty_swap_csrf_token();check(noitru_duty_swap_csrf_valid($token)&&!noitru_duty_swap_csrf_valid('wrong'),'CSRF validation');
 check(run_child(['entry',$root,json_encode(actor('mA')),json_encode(['tab'=>'duty'])])==='ENTRY_OK','Assigned manager can open duty tab without broad menu permissions');
 check(run_child(['entry',$root,json_encode(actor('mA')),json_encode(['tab'=>'health'])])==='BLOCKED_MODULE','Assignment does not open other modules');
 check(run_child(['entry',$root,json_encode(actor('t2')+['module_view'=>true]),json_encode(['tab'=>'duty'])])==='BLOCKED_TAB','Unassigned staff still need ordinary duty view permission');
 $post=['action'=>'duty_swap_request_decide','request_id'=>'r1','decision'=>'approved','duty_swap_csrf'=>'fixture-valid','teacher_id'=>'mA'];
 $before=hashes();check(run_child(['probe',$root,json_encode(actor('mA')),json_encode($post)])==='DISPATCH_OK','Actual page permits scoped approval only');check(str_contains(run_child(['probe',$root,json_encode(actor('t2')),json_encode($post)]),'không có quyền'),'POST teacher ID cannot impersonate manager');$post['duty_swap_csrf']='wrong';check(str_contains(run_child(['probe',$root,json_encode(actor('mA')),json_encode($post)]),'không hợp lệ'),'Actual page rejects invalid CSRF');
 foreach(['duty_save','duty_delete','duty_swap','duty_manager_save','duty_month_clear']as$action)check(run_child(['probe',$root,json_encode(actor('mA')),json_encode(['action'=>$action])])==='BLOCKED_EDIT','No unrelated editing grant '.$action);check(hashes()===$before,'Dispatch probes never write');
 $html=run_child(['render',$root,json_encode(actor('mA'))]);check(str_contains($html,'value="approved"')&&str_contains($html,'value="rejected"')&&str_contains($html,'name="duty_swap_csrf"'),'Manager sees approve/reject plus CSRF');check(!str_contains($html,'duty_swap_request_delete'),'Assigned manager receives no delete control');check(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Request reason escaped');
 foreach([actor('t2'),actor('mA','viewer')]as$a){$html=run_child(['render',$root,json_encode($a)]);check(!str_contains($html,'value="approved"')&&!str_contains($html,'value="rejected"'),'Unassigned/guest has no decision controls');}
 $html=run_child(['render',$root,json_encode(actor('admin','admin'))]);check(str_contains($html,'duty_swap_request_delete'),'Existing admin delete control retained');
 $requests=noitru_duty_swap_requests_all();$other=$requests[0];$other['id']='r2';$other['source_date']='2030-01-10';$other['target_date']='2030-01-12';$other['requester_id']='t3';$requests[]=$other;save_json(NOITRU_DUTY_SWAP_REQUESTS,$requests);
 check(json_decode(run_child(['filter',$root,json_encode(actor('mA'))]),true)===['r1'],'Manager sees assigned-day requests, not unrelated dates');
 check(count(json_decode(run_child(['filter',$root,json_encode(actor('mA','viewer'))]),true))===2,'Experience account retains all-data read-only visibility');
 seed();$children=[];for($i=0;$i<6;$i++){$pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'worker',$root],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$children[]=[$p,$pipes];}$success=0;foreach($children as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0,'Concurrent worker '.$out.$err);$result=json_decode($out,true);check(is_array($result),'Worker response');$success+=(int)$result[0];}check($success===1&&noitru_duty_all()[0]['teacher_id']==='t2','Concurrent approvals apply exactly one swap');
 echo "PASS duty manager approval: day-scoped identity, admin compatibility, unrelated/ordinary/guest denial, fresh assignment, existing swap rules, CSRF and actual dispatch, escaped UI and six concurrent approvers\n";
}finally{$GLOBALS['actor']=null;foreach(glob($root.'/*')as$p)if(is_file($p))unlink($p);@unlink($root.'/.duty.lock');rmdir($root);}
