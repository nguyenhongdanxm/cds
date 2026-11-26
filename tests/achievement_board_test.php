<?php
$dir=sys_get_temp_dir().'/achievement-'.bin2hex(random_bytes(6));mkdir($dir);define('DATA_PATH',$dir);
require __DIR__.'/../includes/chuyenmon_permission_runtime.php';require __DIR__.'/../includes/permissions.php';require __DIR__.'/../includes/achievement_board.php';require __DIR__.'/../includes/viewer_access.php';
function load_json($f,$d=[]){return cds_json_load($f,$d);}function current_user(){return $GLOBALS['actor'];}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}function denied(callable $fn){try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected denial');}
$admin=['id'=>'a','role'=>'admin','name'=>'Quản trị'];$teacher=['id'=>'t','name'=>'Giáo viên A','role'=>'teacher','permission_model_version'=>2,'permission_overrides'=>['hl.thanhtich'=>'edit']];$other=array_merge($teacher,['id'=>'t2']);$viewer=['id'=>'v','role'=>'viewer','permission_overrides'=>['hl.thanhtich'=>'delete']];$GLOBALS['actor']=$admin;$_SESSION=[];
$today=ab_now()->format('Y-m-d');$year=ab_now()->format('Y');$ctx=['classes'=>['6A'=>'6A','6B'=>'6B'],'subjects'=>['Toán','Văn'],'years'=>['y'=>['id'=>'y','start'=>$year.'-01-01','end'=>$year.'-12-31']],'users'=>['t'=>['id'=>'t'],'t2'=>['id'=>'t2']],'students'=>['s1'=>['id'=>'s1','name'=>'An','class'=>'6A'],'s2'=>['id'=>'s2','name'=>'Bình','class'=>'6A'],'s3'=>['id'=>'s3','name'=>'Chi','class'=>'6B']]];
$scope=['year'=>'y','class'=>'6A','subject'=>'Toán'];$s=ab_empty();
try{
 check(!ab_scope_allowed($s,$teacher,'6A','Toán'),'No implicit class/subject grant');
 $grant=['action'=>'grant_save','user_id'=>'t','class'=>'6A','subject'=>'Toán'];$s=ab_apply($s,$grant,$admin,$ctx);$s=ab_apply($s,array_merge($grant,['user_id'=>'t2']),$admin,$ctx);
 check(ab_scope_allowed($s,$teacher,'6A','Toán','edit')&&!ab_scope_allowed($s,$teacher,'6A','Văn','edit'),'Subject scope enforced');
 check(!ab_scope_allowed($s,array_merge($teacher,['classes'=>['6B']]),'6A','Toán','edit'),'Central class restriction still applies');
 check(!ab_scope_allowed($s,$viewer,'6A','Toán','edit')&&ab_scope_allowed($s,$viewer,'6A','Toán','view'),'Viewer cap');
 denied(fn()=>ab_apply($s,$grant,$teacher,$ctx));
 $award=$scope+['action'=>'award','criterion_id'=>'homework','date'=>$today,'students'=>['s1','s2'],'request_id'=>'request_00000000001','note'=>'Chuẩn bị tốt'];
 $s=ab_apply($s,$award,$teacher,$ctx);check(count($s['events'])===2,'Bulk award creates one immutable snapshot per student');
 check(ab_apply($s,$award,$teacher,$ctx)===$s,'Retry idempotent');$before=$s;
 foreach([['students'=>['s1','s3']],['subject'=>'Văn'],['class'=>'6B'],['date'=>'2026-02-30'],['date'=>ab_now()->modify('+1 day')->format('Y-m-d')],['criterion_id'=>'unknown'],['year'=>'missing']] as $bad)denied(fn()=>ab_apply($s,array_merge($award,$bad,['request_id'=>'request_00000000002']),$teacher,$ctx));
 check($s===$before,'Bad bulk award cannot partially mutate');
 $s=ab_apply($s,$scope+['action'=>'award','criterion_id'=>'missing','date'=>$today,'students'=>['s1'],'request_id'=>'request_00000000003'],$teacher,$ctx);
 $key=ab_key('y','6A','Toán');$roster=array_filter($ctx['students'],fn($r)=>$r['class']==='6A');$r=ab_report($s,$roster,$key,$today,$today);check($r['reward']===4&&$r['penalty']===2&&$r['total']===2,'Reward, penalty and net statistics');check($r['scores'][0]['id']==='s2','Ranking order');
 $event=$s['events'][0];$undo=$scope+['action'=>'undo','event_id'=>$event['id']];denied(fn()=>ab_apply($s,$undo,$other,$ctx));$s=ab_apply($s,$undo,$teacher,$ctx);check(count($s['events'])===3&&!empty($s['events'][0]['voided_at']),'Undo keeps audit record');denied(fn()=>ab_apply($s,$undo,$teacher,$ctx));
 $r=ab_report($s,$roster,$key,$today,$today);check($r['total']===0&&$r['reward']===2,'Voided point excluded from totals');
 $s=ab_apply($s,$scope+['action'=>'criterion_save','criterion_id'=>'homework','name'=>'Bài tập mới','points'=>5,'icon'=>'🌟'],$teacher,$ctx);check($s['events'][1]['points']===2&&$s['events'][1]['criterion']==='Hoàn thành bài tập','Criterion edits do not rewrite history');
 check(ab_report($s,[],$key,$today,$today)['scores'][0]['archived']??false,'History preserved after student leaves roster');
 check(ab_report($s,$roster,ab_key('other-year','6A','Toán'),$today,$today)['total']===0,'Academic year isolated');
 check(ab_range(['period'=>'week','anchor'=>'2027-01-01'])===['2026-12-28','2027-01-03'],'Week across year');check(ab_range(['period'=>'month','anchor'=>'2028-02-15'])===['2028-02-01','2028-02-29'],'Leap month');denied(fn()=>ab_range(['period'=>'range','from'=>'2026-11-26','to'=>'2026-11-01']));
 check(cds_viewer_request_allowed('GET','hoclieu_thanhtich.php',['api'=>'data','class'=>'6A','subject'=>'Toán','year'=>'y']),'Viewer admits reviewed GET');check(!cds_viewer_request_allowed('POST','hoclieu_thanhtich.php',[]),'Viewer blocks POST');
 file_put_contents(ab_path(),json_encode($s));$GLOBALS['actor']=$teacher;$snapshot=file_get_contents(ab_path());denied(fn()=>ab_save($award+['csrf'=>'bad'],$teacher,$ctx));check(file_get_contents(ab_path())===$snapshot,'CSRF cannot write');
 $GLOBALS['actor']=$admin;file_put_contents(ab_path(),json_encode(ab_empty()));ab_save($grant+['csrf'=>ab_csrf()],$admin,$ctx);ab_save(array_merge($award,['csrf'=>ab_csrf()]),$teacher,$ctx);check(count(ab_load($key)['events'])===2&&ab_load()['events']===[],'Events isolated in board shard, not school-wide metadata');
 ab_save(array_merge($award,['csrf'=>ab_csrf(),'class'=>'6B','students'=>['s3'],'request_id'=>'another_board_00001']),$admin,$ctx);check(count(ab_load($key)['events'])===2&&count(ab_load(ab_key('y','6B','Toán'))['events'])===1,'Writing another board preserves the first board');
 $GLOBALS['actor']=$admin;file_put_contents(ab_path(),'{broken');denied(fn()=>ab_save($award+['csrf'=>ab_csrf()],$admin,$ctx));check(file_get_contents(ab_path())==='{broken','Corrupt data preserved');
 echo "PASS achievement board: class/subject permissions, viewer caps, bulk atomicity, idempotency, year isolation, statistics, immutable history, undo, dates and CSRF\n";
}finally{foreach(glob($dir.'/achievement_boards/*') as $p)unlink($p);if(is_dir($dir.'/achievement_boards'))rmdir($dir.'/achievement_boards');foreach(glob($dir.'/*') as $p)unlink($p);rmdir($dir);}
