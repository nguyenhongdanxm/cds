<?php
require __DIR__.'/../includes/buzzer_game.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function reject(callable $fn,$label){try{$fn();}catch(BuzzerError $e){return;}throw new LogicException('Not rejected: '.$label);}
function hostMove($s,$action,$extra=[],$now=10000){return bz_transition($s,$action,['version'=>$s['version']]+$extra,$now);}
$s=bz_initial('Phòng giả lập');$tokens=[];$ids=[];
for($i=0;$i<100;$i++){$token=hash('sha256','device-'.$i);$s=bz_transition($s,'join',['name'=>'Thí sinh '.($i+1),'join_token'=>$token],1000);$tokens[]=$token;$ids[]=array_key_last($s['players']);}
check(count($s['players'])===100,'100 players');
reject(fn()=>bz_transition($s,'join',['name'=>'Over','join_token'=>str_repeat('a',64)],1000),'Room capacity');
check(bz_transition($s,'join',['name'=>'ignored','join_token'=>$tokens[0]],1000)===$s,'Join retry is idempotent');
reject(fn()=>bz_player($s,$ids[0],$tokens[1]),'Cannot impersonate another contestant');
reject(fn()=>bz_transition($s,'press',['player_id'=>$ids[0],'token'=>$tokens[0],'question'=>0,'race'=>0],1200),'Early press');
$s=hostMove($s,'question',['seconds'=>30]);check($s['question']===1&&$s['status']==='reading','Question preparation');
$s=hostMove($s,'start');check($s['status']==='running','Teacher starts timer');
$before=$s;
for($i=0;$i<100;$i++){$s=bz_transition($s,'press',['player_id'=>$ids[$i],'token'=>$tokens[$i],'question'=>1,'race'=>$s['race']],11000+$i);}
check(array_column($s['queue'],'rank')===range(1,100),'One deterministic server order for all presses');
check(count(array_unique(array_column($s['queue'],'id')))===100&&$s['winner_id']===$ids[0]&&$s['winner_event']===1,'Only first contestant wins and emits sound event');
check(bz_remaining($s,99000)===29000,'Timer freezes on first buzz');
$duplicate=['player_id'=>$ids[0],'token'=>$tokens[0],'question'=>1,'race'=>$s['race']];
check(bz_transition($s,'press',$duplicate,15000)===$s,'Duplicate press cannot change order or sound');
$oldRace=$s['race'];$staleVersion=$s['version'];$s=hostMove($s,'wrong',[],100000);
check($s['players'][$ids[0]]['blocked']&&$s['status']==='running'&&!$s['queue']&&$s['race']===$oldRace+1,'Wrong locks winner and opens fresh race');
check(bz_remaining($s,101000)===28000,'Wrong continues remaining time');
reject(fn()=>bz_transition($s,'press',$duplicate,101000),'Wrong winner excluded');
$stale=['player_id'=>$ids[1],'token'=>$tokens[1],'question'=>1,'race'=>$oldRace];reject(fn()=>bz_transition($s,'press',$stale,101000),'Old queued network press cannot join new race');
$s=bz_transition($s,'press',['player_id'=>$ids[1],'token'=>$tokens[1],'question'=>1,'race'=>$s['race']],101000);
check($s['winner_id']===$ids[1]&&$s['winner_event']===2&&count($s['queue'])===1,'Others can press again and new winner rings once');
reject(fn()=>bz_transition($s,'correct',['version'=>$staleVersion],101010),'Stale teacher action');
$s=hostMove($s,'correct',[],102000);check($s['status']==='finished','Correct ends question');
reject(fn()=>bz_transition($s,'press',['player_id'=>$ids[2],'token'=>$tokens[2],'question'=>1,'race'=>$s['race']],103000),'Finished rejects press');
$s=hostMove($s,'question',['seconds'=>5],104000);check(!array_filter($s['players'],fn($p)=>$p['blocked'])&&$s['question']===2,'New question restores everyone');
$s=hostMove($s,'start',[],105000);reject(fn()=>bz_transition($s,'press',['player_id'=>$ids[0],'token'=>$tokens[0],'question'=>2,'race'=>$s['race']],110000),'Deadline enforced exactly by server');
check(bz_view($s,110000,'screen')['status']==='finished','Read-only timeout projection');
$host=bz_view($s,106000,'host');$student=bz_view($s,106000,'player',$ids[0]);
check(count($host['players'])===100&&count($student['players'])===1,'Student sees only own contestant');
check(!str_contains(json_encode($host),'key_hash')&&!str_contains(json_encode($host),$tokens[0]),'Snapshots never expose participant secrets');
$s=hostMove($s,'close',[],110000);reject(fn()=>bz_transition($s,'join',['name'=>'No','join_token'=>str_repeat('c',64)],111000),'Closed room cannot join');
// All wrong, no one left; no forced promotion of old queued players.
$one=bz_initial('Một thí sinh');$one=bz_transition($one,'join',['name'=>'Bạn A','join_token'=>$tokens[0]],1000);$only=array_key_first($one['players']);$one=hostMove($one,'question');$one=hostMove($one,'start');$one=bz_transition($one,'press',['player_id'=>$only,'token'=>$tokens[0],'question'=>1,'race'=>$one['race']],10001);$one=hostMove($one,'wrong',[],10002);check($one['status']==='finished','All excluded ends question');
foreach(['',str_repeat('a',81),"\0bad"]as$title)reject(fn()=>bz_initial($title),'Invalid title');
check(bz_text('  Nhóm   1 ',60)==='Nhóm 1','Normalized name');
require __DIR__.'/../includes/buzzer_access.php';
function can_perm_level($key,$level){return $GLOBALS['allowed']??true;}
function qp_game_visibility(){return ['buzzer'=>$GLOBALS['visible']??true];}
check(bz_can_host(['id'=>'1','role'=>'admin']),'Admin allowed');
foreach(['student','hs','parent','phhs']as$role)check(!bz_can_host(['id'=>'1','role'=>$role]),'Student/parent cannot host');
$GLOBALS['allowed']=false;check(!bz_can_host(['id'=>'gv','role'=>'gv']),'Permission enforced');$GLOBALS['allowed']=true;$GLOBALS['visible']=false;check(!bz_can_host(['id'=>'gv','role'=>'gv']),'Visibility enforced');
echo "PASS Bấm chuông: 100 contestants, authoritative order, one sound, duplicate/stale presses, wrong lock/reopen, timer/deadline, new question, privacy and host permissions\n";
