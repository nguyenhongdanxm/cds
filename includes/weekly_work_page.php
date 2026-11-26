<?php
require_once __DIR__.'/weekly_work_store.php';
require_once __DIR__.'/weekly_work_view.php';
header('Cache-Control: private, no-store');
$workUser=current_user();$canEdit=ww_allowed($workUser);$canDelete=ww_allowed($workUser,'delete');
$workWeek=ww_week(is_string($_GET['week']??null)?$_GET['week']:'');$error='';$form=[];
$workDay=is_string($_GET['date']??null)?$_GET['date']:'';
if(!ww_date($workDay)||$workDay<$workWeek->format('Y-m-d')||$workDay>$workWeek->modify('+6 days')->format('Y-m-d'))$workDay='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=is_string($_POST['work_action']??null)?$_POST['work_action']:'';
    if(!ww_allowed($workUser,$action==='delete'?'delete':'edit')){http_response_code(403);exit('Bạn chưa được cấp quyền nhập hoặc xóa lịch công tác.');}
    try{$saved=ww_change($_POST,$workUser,$action);flash($action==='delete'?'Đã xóa công việc.':'Đã lưu lịch công tác.','success');header('Location: '.BASE_URL.'admin.php?view=weekly_schedule&week='.ww_week($saved['date'])->format('Y-m-d'));exit;}
    catch(RuntimeException $e){$error=$e->getMessage();http_response_code(422);if($action==='save')$form=$_POST;}
}
$workItems=ww_all();$edit=is_string($_GET['edit']??null)?$_GET['edit']:'';
if(!$form&&$edit!==''&&$canEdit){foreach($workItems as $row)if(($row['id']??'')===$edit){$form=$row;break;}if(!$form)$error='Không tìm thấy công việc. Có thể lịch đã bị xóa.';}
$form=array_merge(['id'=>'','version'=>'','date'=>$workDay!==''?$workDay:$workWeek->format('Y-m-d'),'session'=>'morning','time'=>'','end_time'=>'','content'=>'','people'=>'','place'=>'','note'=>''],$form);
// Keep malformed requests out of HTML attributes while preserving valid input after errors.
foreach($form as $key=>$value)if(!is_string($value))$form[$key]='';
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lịch công tác tuần</title><link rel="stylesheet" href="<?=e(BASE_URL)?>assets/weekly-work.css?v=3"></head><body class="ww-page"><main>
<header class="ww-page-header"><div><a href="<?=e(BASE_URL)?>admin.php">← Trang chủ</a><h1><?=$workDay!==''?'Lịch công tác ngày '.e(date('d/m/Y',strtotime($workDay))):'Lịch công tác tuần'?></h1><p><?=e(SCHOOL_NAME)?> · <?=e($workWeek->format('d/m'))?> – <?=e($workWeek->modify('+6 days')->format('d/m/Y'))?></p></div><?php if(($workUser['role']??'')==='admin'):?><a class="ww-button" href="<?=e(BASE_URL)?>users.php">Phân quyền tài khoản</a><?php endif;?></header>
<?php if($error):?><div class="ww-error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if(!empty($_SESSION['cds_flash'])):$workFlash=$_SESSION['cds_flash'];unset($_SESSION['cds_flash']);?><p role="status"><?=e($workFlash['message']??'')?></p><?php endif;?>
<?php ww_navigation($workWeek,true);?>
<?php if($workDay!==''):?><p><a class="ww-button" href="<?=e(BASE_URL.'admin.php?view=weekly_schedule&week='.$workWeek->format('Y-m-d'))?>">← Xem cả tuần</a></p><?php endif;?>
<?php if($canEdit):?><details class="ww-editor" <?=($edit!==''||$error!==''||!ww_rows($workItems,$workWeek))?'open':''?>><summary><?=$form['id']!==''?'Sửa công việc':'Nhập lịch công tác'?></summary>
<form method="post" action="<?=e(BASE_URL.'admin.php?view=weekly_schedule&week='.$workWeek->format('Y-m-d'))?>" class="ww-form"><input type="hidden" name="work_action" value="save"><input type="hidden" name="csrf" value="<?=e(ww_csrf())?>"><input type="hidden" name="id" value="<?=e($form['id'])?>"><input type="hidden" name="version" value="<?=e($form['version'])?>">
<label>Ngày thực hiện *<input type="date" name="date" min="2000-01-01" max="2100-12-31" value="<?=e($form['date'])?>" required></label><label>Buổi *<select name="session"><?php foreach(ww_sessions() as $key=>$label):?><option value="<?=e($key)?>" <?=$form['session']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>Giờ bắt đầu<input type="time" name="time" value="<?=e($form['time'])?>"></label><label>Giờ kết thúc<input type="time" name="end_time" value="<?=e($form['end_time'])?>"></label>
<label class="ww-wide">Nội dung công tác *<textarea name="content" rows="3" maxlength="2000" required><?=e($form['content'])?></textarea></label><label class="ww-half">Chủ trì / người, bộ phận thực hiện<textarea name="people" rows="2" maxlength="500" placeholder="Ví dụ: Ban giám hiệu, tổ chuyên môn…"><?=e($form['people'])?></textarea></label><label class="ww-half">Địa điểm<input name="place" maxlength="250" value="<?=e($form['place'])?>"></label><label class="ww-wide">Ghi chú<textarea name="note" rows="2" maxlength="650"><?=e($form['note'])?></textarea></label><div class="ww-wide ww-form-actions"><button class="ww-button ww-primary" type="submit">Lưu công việc</button><a class="ww-button" href="<?=e(BASE_URL.'admin.php?view=weekly_schedule&week='.$workWeek->format('Y-m-d'))?>">Nhập công việc mới</a></div></form></details>
<?php else:?><p class="ww-readonly">Bạn đang xem lịch công tác. Quản trị viên có thể cấp quyền nhập lịch trong mục Tài khoản và quyền.</p><?php endif;?>
<section class="ww-calendar"><?php ww_render_week($workWeek,$workItems,$canEdit||$canDelete,$workDay);?></section>
<?php if(($workUser['role']??'')==='admin'):?><p class="ww-help">Để giao người nhập lịch: vào <a href="<?=e(BASE_URL)?>users.php">Tài khoản và quyền</a> → sửa tài khoản → <strong>Lịch công tác tuần</strong> → chọn quyền <strong>Sửa</strong> (thêm, sửa) hoặc <strong>Xóa</strong> (thêm, sửa, xóa), rồi lưu tài khoản.</p><?php endif;?>
</main></body></html>
