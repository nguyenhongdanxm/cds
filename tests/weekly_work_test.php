<?php
$dir=sys_get_temp_dir().'/weekly-work-'.bin2hex(random_bytes(6));mkdir($dir);
define('DATA_PATH',$dir);define('BASE_URL','/');
require __DIR__.'/../includes/chuyenmon_permission_runtime.php';
require __DIR__.'/../includes/permissions.php';
require __DIR__.'/../includes/viewer_access.php';
require __DIR__.'/../includes/weekly_work_store.php';
require __DIR__.'/../includes/weekly_work_view.php';
function e($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function current_user(){return $GLOBALS['actor'];}
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
function rejected(callable $fn){try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected rejection');}
$_SESSION=[];$admin=['role'=>'admin','id'=>'a'];$viewer=['role'=>'viewer','id'=>'v'];
$teacher=['role'=>'teacher','id'=>'t','permission_model_version'=>2,'permission_overrides'=>['school.weekly_schedule'=>'edit']];$GLOBALS['actor']=$teacher;
$input=['csrf'=>ww_csrf(),'date'=>'2026-11-23','session'=>'morning','time'=>'07:30','end_time'=>'09:00','content'=>'Họp chuyên môn <script>alert(1)</script>','people'=>'Ban giám hiệu','place'=>'Phòng họp','note'=>'Chuẩn bị hồ sơ'];
try{
 check(ww_week('2026-11-29')->format('Y-m-d')==='2026-11-23','Sunday belongs to current Monday');
 check(ww_week('2027-01-01')->format('Y-m-d')==='2026-12-28','Year boundary');
 check(!ww_date('2026-02-30')&&!ww_date('2026-2-3'),'Strict date parsing');
 check(isset(permission_features_catalog()['school.weekly_schedule']),'Feature discoverable in users screen');
 check(!ww_allowed(['role'=>'teacher','permission_model_version'=>2]),'No implicit edit access');
 check(ww_allowed($teacher)&&!ww_allowed($teacher,'delete'),'Separate edit and delete levels');
 check(!ww_allowed($viewer)&&ww_allowed($admin,'delete'),'Viewer capped; admin can manage');
 check(cds_viewer_request_allowed('GET','admin.php',['view'=>'weekly_schedule','week'=>'2026-11-23']),'Viewer reads calendar');
 check(!cds_viewer_request_allowed('POST','admin.php',['view'=>'weekly_schedule']),'Viewer cannot post');
 check(!cds_viewer_request_allowed('GET','admin.php',['view'=>'drive']),'Other admin settings still denied');
 ob_start();ww_render_week(ww_week(),[]);$todayHtml=ob_get_clean();check(substr_count($todayHtml,'aria-current="date"')===1&&str_contains($todayHtml,'Hôm nay'),'Today badge appears once even without work entries');
 ob_start();ww_render_week(ww_week('2000-01-03'),[]);$pastHtml=ob_get_clean();check(!str_contains($pastHtml,'aria-current="date"'),'No today badge in a past week');
 $r=ww_change($input,$teacher,'save');check(count(ww_all())===1&&$r['updated_by']==='t','Authorized create');
 $edit=array_merge($input,['id'=>$r['id'],'version'=>$r['version'],'content'=>'Nội dung đã sửa']);$updated=ww_change($edit,$teacher,'save');
 check($updated['version']!==$r['version'],'Version changes');$before=file_get_contents(ww_path());
 rejected(fn()=>ww_change($edit,$teacher,'save'));check(file_get_contents(ww_path())===$before,'Stale edit cannot overwrite');
 rejected(fn()=>ww_change(array_merge($edit,['version'=>$updated['version']]),$teacher,'delete'));
 rejected(fn()=>ww_change($input,$viewer,'save'));
 rejected(fn()=>ww_change(array_merge($input,['csrf'=>'wrong']),$admin,'save'));
 foreach([['date'=>'2026-02-30'],['session'=>'night'],['time'=>'25:00'],['end_time'=>'06:00'],['content'=>[]],['content'=>'']]as$bad)rejected(fn()=>ww_change(array_merge($input,$bad),$admin,'save'));
 check(file_get_contents(ww_path())===$before,'Invalid/unauthorized writes unchanged');
 $afternoon=ww_change(array_merge($input,['session'=>'afternoon','time'=>'13:30','end_time'=>'','content'=>'Sinh hoạt buổi chiều']),$teacher,'save');
 $other=ww_change(array_merge($input,['date'=>'2026-11-30']),$teacher,'save');
 $rows=ww_rows(ww_all(),ww_week('2026-11-25'));check(count($rows)===2&&$rows[0]['session']==='morning'&&$rows[1]['session']==='afternoon','Week isolation and session order');
 ob_start();ww_render_week(ww_week('2026-11-25'),[$r]);$html=ob_get_clean();check(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>'),'HTML escaped');check(str_contains($html,'Thứ Hai')&&str_contains($html,'Chủ nhật'),'Seven day headings');
 ww_change(['csrf'=>ww_csrf(),'id'=>$updated['id'],'version'=>$updated['version']],$admin,'delete');check(count(ww_all())===2,'Delete only selected row');
 file_put_contents(ww_path(),'{broken');rejected(fn()=>ww_change($input,$admin,'save'));check(file_get_contents(ww_path())==='{broken','Corrupt file never replaced');
 echo "PASS weekly work: real permissions, CSRF, CRUD, stale version protection, strict dates/times, week boundaries, escaped rendering and viewer read routes\n";
}finally{foreach(glob($dir.'/*')as$p)unlink($p);rmdir($dir);}
