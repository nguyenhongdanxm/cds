<?php
// Synthetic fixture only: no application bootstrap or production data.
require __DIR__.'/../includes/staff_evaluation.php';
require __DIR__.'/../includes/json_store.php';
function ok($v,$label){if(!$v)throw new RuntimeException($label);}
function denied(callable $fn,$label){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection: '.$label);}
$staff=[['id'=>'s1','name'=>'GV giả lập','to_chuyen_mon'=>'Tổ A'],['id'=>'s2','name'=>'NV giả lập','to_chuyen_mon'=>'Văn phòng']];
$users=[['id'=>'u1','name'=>'Cá nhân','active'=>true],['id'=>'u2','name'=>'Thẩm định','active'=>true],['id'=>'u3','name'=>'Phê duyệt','active'=>true],['id'=>'admin','name'=>'Quản trị','role'=>'admin','active'=>true]];
$self=$users[0];$reviewer=$users[1];$approver=$users[2];$admin=$users[3];$viewer=['id'=>'viewer','role'=>'viewer'];$outsider=['id'=>'other'];
$cfg=se_defaults();$input=['config_complete'=>1,'revision'=>0,'decision'=>'Quyết định giả lập 01, dùng kiểm thử','effective'=>'2026-07-01','activate'=>1,'assignments'=>['s1'=>['user_id'=>'u1','reviewer'=>'u2','approver'=>'u3'],'s2'=>[]]];
$cfg=se_configure($cfg,$admin,$input,$staff,$users);ok($cfg['template']['active'],'Activated version');ok(!se_version($cfg,'2026-06')['active'],'Future effective rules cannot activate earlier month');
denied(fn()=>se_configure($cfg,$self,$input,$staff,$users),'nonadmin config');
$bad=$input;$bad['revision']=1;$bad['assignments']['s1']['approver']='u1';denied(fn()=>se_configure($cfg,$admin,$bad,$staff,$users),'self approval mapping');
$bad['assignments']['s1']['approver']='u3';$bad['assignments']['s2']['user_id']='u1';denied(fn()=>se_configure($cfg,$admin,$bad,$staff,$users),'duplicate identity');
$bad=$input;$bad['revision']=1;$bad['max']['regular']['A1']=11;denied(fn()=>se_configure($cfg,$admin,$bad,$staff,$users),'30/70 enforced');
$bad=$input;$bad['revision']=1;$bad['decision']='';denied(fn()=>se_configure($cfg,$admin,$bad,$staff,$users),'official basis required');
$r=se_blank($staff[0],'2026-11',$cfg);ok($r['criteria_version']['id']===$cfg['template']['id'],'Version snapshot');ok(se_total([],$r['criteria'])===null,'Missing is not zero');
$add=['revision'=>0,'title'=>'Báo cáo','product'=>'Báo cáo tháng','quality'=>'Đủ nội dung được giao','due'=>'2026-11-20','weight'=>2];
$r=se_transition($r,$self,'task_add',$add);$task=array_key_first($r['tasks']);ok(!$r['tasks'][$task]['assigned'],'Self task is proposal');
denied(fn()=>se_transition($r,$viewer,'sync',['revision'=>1]),'viewer mutation');denied(fn()=>se_transition($r,$outsider,'sync',['revision'=>1]),'IDOR');denied(fn()=>se_transition($r,$admin,'approve',['revision'=>1]),'admin not approver');
$all=[];$evidence=[];foreach($r['criteria']as$c){$all[$c['id']]=$c['max'];$evidence[$c['id']]='Căn cứ kiểm thử';}
$post=['revision'=>1,'scores'=>$all,'evidence'=>$evidence,'strengths'=>'Ưu điểm','limits'=>'Hạn chế','explanation'=>''];
denied(fn()=>se_transition($r,$self,'submit',$post),'unassigned task');
$r=se_transition($r,$reviewer,'task_accept',['revision'=>1,'task_id'=>$task,'reason'=>'Xác nhận giao việc']);
$r=se_transition($r,$self,'task_result',['revision'=>2,'task_id'=>$task,'result'=>'Đã hoàn thành','url'=>'https://example.test/evidence','completed'=>'2026-11-19']);
$r=se_transition($r,$reviewer,'task_accept',['revision'=>3,'task_id'=>$task,'reason'=>'Sản phẩm đạt yêu cầu','accept_result'=>1]);ok($r['tasks'][$task]['accepted'],'Accepted product');
$badResult=['revision'=>4,'task_id'=>$task,'result'=>'Đã hoàn thành','url'=>'javascript:alert(1)','completed'=>'2026-11-19'];denied(fn()=>se_transition($r,$self,'task_result',$badResult),'dangerous URL');
$r=se_transition($r,$self,'sync',['revision'=>4],[['id'=>'source','label'=>'Minh chứng kiểm thử']]);ok(!$r['self_scores'],'Sync does not grant points');
$post['revision']=5;$draft=$r;$r=se_transition($r,$self,'submit',$post);ok($r['status']==='submitted','Submit');
denied(fn()=>se_transition($r,$self,'save_self',$post),'stale revision');denied(fn()=>se_transition($r,$approver,'approve',['revision'=>6,'scores'=>$all,'reason'=>'Duyệt']),'skip review');
$badScore=$all;$badScore['A1']=100;denied(fn()=>se_transition($r,$reviewer,'review',['revision'=>6,'scores'=>$badScore,'reason'=>'Kiểm tra']),'out of range');
$badScore=$all;$badScore['A1']='NaN';denied(fn()=>se_transition($r,$reviewer,'review',['revision'=>6,'scores'=>$badScore,'reason'=>'Kiểm tra']),'nonfinite');
$r=se_transition($r,$reviewer,'review',['revision'=>6,'scores'=>$all,'reason'=>'Thẩm định sản phẩm và căn cứ']);ok($r['status']==='reviewed','Review');
$r=se_transition($r,$approver,'approve',['revision'=>7,'scores'=>$all,'reason'=>'Duyệt theo thẩm quyền']);ok($r['status']==='locked'&&se_total($r['approved_scores'],$r['criteria'])===100.0,'Lock approved record');
$locked=$r;denied(fn()=>se_transition($r,$self,'sync',['revision'=>8],[]),'locked sync');denied(fn()=>se_transition($r,$admin,'reopen',['revision'=>8,'reason'=>'Mở']),'technical admin cannot reopen');
$r=se_transition($r,$approver,'reopen',['revision'=>8,'reason'=>'Bổ sung theo kiến nghị']);ok($r['closed_versions'][0]['approved_scores']===$locked['approved_scores'],'Closed snapshot retained');ok($r['status']==='returned','Reopened');
$post['revision']=9;$r=se_transition($r,$self,'submit',$post);ok(!$r['approved_scores']&&!$r['review_scores'],'Resubmit clears stale stage scores');
$external=$locked;$external['assignment']['external']=true;$external['status']='reviewed';denied(fn()=>se_transition($external,$approver,'approve',['revision'=>8,'scores'=>$all,'reason'=>'Không đúng thẩm quyền']),'external authority');
$contract=$draft;$contract['assignment']['employment']='hopdong';denied(fn()=>se_transition($contract,$self,'submit',array_replace($post,['revision'=>5])),'contract basis');
$pending=se_blank($staff[0],'2026-11',se_defaults());$pending['assignment']=$draft['assignment'];denied(fn()=>se_transition($pending,$self,'submit',array_replace($post,['revision'=>0,'explanation'=>'Không có nhiệm vụ vì nghỉ theo chế độ'])),'draft criteria cannot submit');
$changed=$cfg;$changed['template']['id']='changed';$changed['versions'][0]['id']='changed';$rebased=se_transition($draft,$admin,'apply_config',['revision'=>5,'reason'=>'Kích hoạt quy chế mới'],[],$changed);ok($rebased['criteria_version']['id']==='changed'&&!$rebased['self_scores'],'Explicit rebase drafts only');denied(fn()=>se_transition($locked,$admin,'apply_config',['revision'=>8,'reason'=>'Sai'],[],$changed),'no rebase locked');
$dir=sys_get_temp_dir().'/se-test-'.bin2hex(random_bytes(5));mkdir($dir);define('DATA_PATH',$dir);$file=se_path('2026-11');
se_update($file,fn($s)=>['records'=>['s1'=>$locked]],['records'=>[]]);ok(is_file(dirname($file).'/.htaccess'),'Private storage protection');$bytes=file_get_contents($file);
denied(fn()=>se_update($file,fn($s)=>se_transition($s['records']['s1'],$outsider,'sync',['revision'=>8]),[]),'failed update');ok(file_get_contents($file)===$bytes,'Rejected writes preserve exact data');
file_put_contents($file,'{invalid');denied(fn()=>se_update($file,fn($s)=>[],[]),'corrupt store fails closed');ok(file_get_contents($file)==='{invalid','Corrupt source never overwritten');
denied(fn()=>se_month('../../data'),'path traversal');denied(fn()=>se_date('2026-02-30'),'invalid calendar');
// Concurrent increments use the real JSON transaction lock, no last-writer overwrite.
$countFile=$dir.'/counter.json';if(function_exists('pcntl_fork')){$pids=[];for($i=0;$i<4;$i++){$pid=pcntl_fork();if($pid===0){for($j=0;$j<15;$j++)se_update($countFile,fn($s)=>['count'=>$s['count']+1],['count'=>0]);exit;} $pids[]=$pid;}foreach($pids as$pid)pcntl_waitpid($pid,$st);ok(cds_json_load($countFile)['count']===60,'Concurrent writes retained');}
foreach(glob($dir.'/staff_evaluation/*')as$f)unlink($f);unlink($dir.'/staff_evaluation/.htaccess');rmdir($dir.'/staff_evaluation');foreach(glob($dir.'/*')as$f)if(is_file($f))unlink($f);if(is_file($dir.'/.htaccess'))unlink($dir.'/.htaccess');rmdir($dir);
echo "PASS: identity, role separation, 30/70, workflow, version snapshots, stale writes, task acceptance, source refresh, URLs, external authority, read-only, atomic storage and concurrent updates\n";
