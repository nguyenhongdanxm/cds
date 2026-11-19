<?php
require_once __DIR__.'/../includes/viewer_access.php';
require_once __DIR__.'/../includes/json_store.php';
$root=sys_get_temp_dir().'/cds_notice_'.bin2hex(random_bytes(6));mkdir($root);define('DATA_PATH',$root);define('BASE_URL','/cds/');
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function rejects(callable $fn,$message){try{$fn();}catch(RuntimeException $e){return;}throw new LogicException($message);}
require_once __DIR__.'/../includes/login_notice_store.php';
$_SESSION=['cds_user'=>['id'=>'admin','role'=>'admin']];$admin=$_SESSION['cds_user'];$a=['id'=>'a','username'=>'old'];$b=['id'=>'b'];
try{
    check(cds_ln_pending($a)===null,'Disabled by default');check(strlen(cds_ln_csrf())===48&&cds_ln_csrf()===cds_ln_csrf(),'CSRF stable within session');
    $input=['enabled'=>'1','title'=>'Lịch công tác','body'=>'<p>📌 <strong>Lưu ý</strong> <a href="https://example.org">Xem lịch</a></p>','icon'=>'📢','theme'=>'blue','starts_at'=>'','ends_at'=>'','version'=>''];
    rejects(fn()=>cds_ln_save($input,['role'=>'teacher']),'Only admin may save');
    $first=cds_ln_save($input,$admin);check(cds_ln_pending($a)!==null,'Active announcement appears');
    $id=$first['id'];check(cds_ln_dismiss($a,$id),'Receipt saved');check(cds_ln_dismiss($a,$id),'Receipt idempotent');
    check(cds_ln_pending(['id'=>'a','username'=>'renamed'])===null,'Receipt follows stable account ID');check(cds_ln_pending($b)!==null,'Other account unaffected');
    $_SESSION=['cds_user'=>['id'=>'a','role'=>'teacher']];check(cds_ln_pending($a)===null,'Survives new login session');
    $_SESSION=['cds_user'=>$admin];$input['version']=$first['version'];$input['body']='<p>Nội dung chỉnh sửa</p>';
    $second=cds_ln_save($input,$admin);check($second['id']===$id&&cds_ln_pending($a)===null,'Editing preserves dismissal');
    $before=file_get_contents(cds_ln_path());rejects(fn()=>cds_ln_save($input,$admin),'Stale version rejected');check(file_get_contents(cds_ln_path())===$before,'No overwrite on conflict');
    $input['version']=$second['version'];$input['new_notice']='1';$third=cds_ln_save($input,$admin);
    check($third['id']!==$id&&cds_ln_pending($a)!==null,'Explicit new announcement reappears');
    $n=$third;$n['starts_at']='2026-11-19T08:00:00+07:00';$n['ends_at']='2026-11-19T09:00:00+07:00';
    check(!cds_ln_active($n,strtotime('2026-11-19T07:59:59+07:00')),'Before schedule hidden');check(cds_ln_active($n,strtotime($n['starts_at'])),'Start inclusive');check(!cds_ln_active($n,strtotime($n['ends_at'])),'End exclusive');
    $n['enabled']=false;check(!cds_ln_active($n,strtotime('2026-11-19T08:30:00+07:00')),'Disabled hidden');
    $bad=$input;$bad['starts_at']='2026-11-19T09:00';$bad['ends_at']='2026-11-19T08:00';rejects(fn()=>cds_ln_save($bad,$admin),'Bad range rejected');rejects(fn()=>cds_ln_date('2026-02-30T10:00'),'Bad date rejected');
    check(cds_ln_date('2026-11-19T08:00')==='2026-11-19T08:00:00+07:00','Vietnam schedule timezone');
    $unsafe='<p style="color:#123456;position:fixed;background-color:url(https://bad)">Text<script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">Link</a><img src="x" onerror="x()"></p><table><tr><td>📚</td></tr></table>';
    $clean=cds_ln_clean($unsafe);foreach(['<script','onclick','onerror','javascript:','position:','url(','<img'] as $bad)check(stripos($clean,$bad)===false,'Sanitize '.$bad);check(strpos($clean,'#123456')!==false&&strpos($clean,'📚')!==false&&strpos($clean,'<table>')!==false,'Formatting retained');
    check(!cds_ln_has_text('<p>&nbsp; </p>'),'Empty editor content');
    foreach(['https://evil.example/','//evil.example/','/\\evil.example/','/admin.php\r\nLocation: x'] as $url)check(cds_ln_safe_next($url)==='/cds/admin.php','Local redirects only');check(cds_ln_safe_next('/cds/csdl.php?tab=statistics')==='/cds/csdl.php?tab=statistics','Deep link preserved');
    $_SESSION=['cds_user'=>['id'=>'v','role'=>'viewer']];check(cds_ln_dismiss($_SESSION['cds_user'],$third['id']),'Viewer can save own preference');check(cds_ln_pending($_SESSION['cds_user'])===null,'Viewer preference persisted');
    $business=$root.'/business.json';file_put_contents($business,'[]');check(!cds_json_save($business,['bad']),'Viewer business writes still blocked');
    check(cds_viewer_request_allowed('POST','login_notice.php',[]),'Exact self-receipt route');check(!cds_viewer_request_allowed('POST','admin.php',[]),'No admin writes');check(!cds_viewer_request_allowed('POST','login_notice.php',['action'=>'save']),'No extra query route');
    echo "PASS: scheduling, account isolation, persistence, new notice, edit semantics, CSRF token, sanitization, version conflict, viewer write isolation\n";
}finally{
    $_SESSION=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);
}
