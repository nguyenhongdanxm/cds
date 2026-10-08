<?php
error_reporting(E_ALL);
define('BASE_URL','/chuyenmon/');define('DATA_PATH','/fixture/cm');define('CM_DOCS_FILE','/fixture/cm/cm_docs.json');
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function cds_user_has_group($u,$g){return in_array($g,$u['groups']??[],true)||($u['role']??'')===$g;}
function cds_can_feature($f,$l){return true;}
function get_teacher_group($name){return ['An'=>'Tổ A','Bình'=>'Tổ A','Chi'=>'Tổ B'][$name]??'';}
function lb_record_map_range($f,$t){return array_filter($GLOBALS['lessons'],fn($r)=>$r['date']>=$f&&$r['date']<=$t);}
function load_json($file,$default=[]){return $GLOBALS['files'][$file]??$default;}
function save_json($file,$rows){if(!empty($GLOBALS['fail_write']))return false;$GLOBALS['files'][$file]=$rows;return true;}
function cm_docs_all(){return load_json(CM_DOCS_FILE,[]);}
function cm_doc_uid(){return 'test_new';}
require __DIR__.'/../chuyenmon/includes/periodic_report.php';
require __DIR__.'/../chuyenmon/includes/periodic_report_docx.php';
require __DIR__.'/../chuyenmon/includes/observation_form.php';
$teachers=['An','Bình','Chi'];$scope=pr_scope(['name'=>'An','groups'=>['totruong']],$teachers);
check($scope['groups']===['Tổ A']&&$scope['write'],'TTCM restricted to own team');
check(!pr_visible(['section'=>'bc_dinhky','report_group'=>'Tổ B'],$scope),'cross-team read denied');
check(!pr_visible(['section'=>'bc_dinhky'],$scope),'unscoped legacy denied to team');
check(pr_visible(['section'=>'bc_dinhky','report_group'=>'Tổ A'],$scope),'own report visible');
check(!pr_scope(['name'=>'An','groups'=>['gv']],$teachers)['write'],'ordinary teacher cannot write');
check(count(pr_scope(['role'=>'admin','name'=>'Boss'],$teachers)['groups'])===2,'admin sees teams');
check(pr_month('2026-12')&&!pr_month('2026-13')&&pr_date('2026-10-07')&&!pr_date('2026-02-30'),'date validation');
$clean=pr_clean('<p onclick="alert(1)"><strong>Đậm</strong><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a><span style="text-align:center;position:fixed;color:#ff0000">đỏ</span></p>');
check(strpos($clean,'onclick')===false&&strpos($clean,'script')===false&&strpos($clean,'javascript:')===false&&strpos($clean,'position:')===false,'active HTML removed');
check(strpos($clean,'<strong>Đậm</strong>')!==false&&strpos($clean,'color:#ff0000')!==false,'rich formatting retained');
$GLOBALS['lessons']=[['date'=>'2026-10-01','actual_teacher'=>'An','signed_at'=>'now','status'=>'taught','actual_periods'=>1],['date'=>'2026-10-02','actual_teacher'=>'An','status'=>'taught'],['date'=>'2026-10-01','actual_teacher'=>'Chi','signed_at'=>'now','status'=>'taught'],['date'=>'2026-09-30','actual_teacher'=>'An','signed_at'=>'now','status'=>'taught']];
$GLOBALS['files']=[DATA_PATH.'/observations.json'=>[['date'=>'2026-10-03','teacher'=>'An','observers'=>['Bình'],'evaluations'=>[['completed'=>true,'observer'=>'Bình','total'=>18]],'class'=>'10A','subject'=>'Toán','lesson_title'=>'Bài 1'],['date'=>'2026-10-03','teacher'=>'Chi'],['date'=>'2026-09-03','teacher'=>'An']],DATA_PATH.'/professional_file_checks.json'=>[['date'=>'2026-10-04','teacher_name'=>'Bình','content'=>'Hồ sơ','result'=>'Đầy đủ','rating'=>'Tốt'],['date'=>'2026-10-04','teacher_name'=>'Chi']],dirname(__DIR__).'/data/thidua.json'=>[]];
$attendanceFile=dirname(__DIR__.'/../chuyenmon/includes',2).'/data/thidua.json';
// Source root uses dirname(__DIR__, 2), normalize to the actual helper root.
$attendanceFile=dirname(realpath(__DIR__.'/../chuyenmon/includes'),2).'/data/thidua.json';
$GLOBALS['files'][$attendanceFile]=['records'=>[['type'=>'teacher_attendance','person_name'=>'An','from_date'=>'2026-09-29','to_date'=>'2026-10-02','permission'=>'Có phép'],['type'=>'teacher_attendance','person_name'=>'Chi','date'=>'2026-10-02']]];
$snapshot=pr_snapshot('Tổ A','2026-10',$teachers);
check(count($snapshot['teachers'])===2&&$snapshot['teachers'][0]['saved']===2&&$snapshot['teachers'][0]['signed']===1&&$snapshot['teachers'][0]['taught']===1.0,'signed lessons and month/team boundaries');
check($snapshot['teachers'][0]['rated']===1&&count($snapshot['details']['checks'])===1,'observation/check team scope');
check(count($snapshot['details']['attendance'])===1&&$snapshot['details']['attendance'][0][0]==='2026-10-01','attendance overlap clipped to month');
$r=['id'=>'test_new','section'=>'bc_dinhky','title'=>'Báo cáo tháng 10 — Tổ A','school'=>'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN','report_group'=>'Tổ A','month'=>'2026-10','next_month'=>'2026-11','date'=>'2026-10-07','number'=>'01/BC-TCM','place'=>'Xín Mần','recipient'=>'Ban Giám hiệu','signer'=>'Nguyễn Văn An','report_sections'=>['implementation'=>'<p>Thực hiện kế hoạch <strong>tháng 10</strong>.</p>','results'=>'<ol><li>Đã thực hiện dạy học.</li><li>Đã kiểm tra hồ sơ.</li></ol>','issues'=>'<p>Cần tăng cường hỗ trợ.</p>','next_plan'=>'<p>Chuẩn bị tháng 11.</p>','manual_appendix'=>'<table><tr><th>Nội dung</th><th>Kết quả</th></tr><tr><td>Bồi dưỡng</td><td>5 học sinh</td></tr></table>'],'snapshot'=>$snapshot,'report_revision'=>'v1'];
check(pr_store($r)==='test_new','save new report');check(cm_docs_all()[0]['snapshot']===$snapshot,'snapshot preserved');
try{pr_store(['id'=>'test_new','title'=>'overwrite'],'old');throw new RuntimeException('revision check missing');}catch(RuntimeException $e){check(strpos($e->getMessage(),'thay đổi')!==false,'stale revision blocked');}
$GLOBALS['fail_write']=true;try{pr_store(['id'=>'test_new'],'v1');throw new RuntimeException('write error missing');}catch(RuntimeException $e){check(strpos($e->getMessage(),'Không lưu')!==false,'storage failure detected');}$GLOBALS['fail_write']=false;
check(strpos(pr_html($r),'Kính gửi:')===false&&strpos(pr_html($r),'Nơi nhận:')!==false,'greeting removed and footer recipients retained');
$userA=['id'=>'teacher-a','name'=>'An'];$userB=['id'=>'teacher-b','name'=>'Bình'];
check(pr_default_place($userA)==='','no saved place initially');
check(pr_save_default_place($userA,'  Xín Mần  ')&&pr_default_place($userA)==='Xín Mần','default place persisted and trimmed');
check(pr_default_place($userB)==='','place preference isolated by account');
check(pr_save_default_place($userB,'Hà Giang')&&pr_default_place($userA)==='Xín Mần','other account cannot overwrite preference');
$GLOBALS['fail_write']=true;check(!pr_save_default_place($userA,'Khác')&&pr_default_place($userA)==='Xín Mần','preference write failure retains previous value');$GLOBALS['fail_write']=false;
$bytes=pr_docx($r);check(substr($bytes,0,2)==='PK','real DOCX archive');$out=getenv('REPORT_TEST_OUTPUT_DIR')?:sys_get_temp_dir().'/cds-periodic-report-test';if(!is_dir($out))mkdir($out,0755,true);file_put_contents($out.'/sample-report.docx',$bytes);file_put_contents($out.'/sample-report.html',pr_html($r));
echo "PASS: team permissions, dates, XSS sanitizer, monthly source aggregation, snapshots, revision/storage failures, DOCX generation.\n";
