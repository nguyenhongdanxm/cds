<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/csdl_store.php';
require_once __DIR__.'/includes/dashboard_operation_store.php';
require_once __DIR__.'/includes/school_week_calendar.php';
require_login();
$user=current_user();
if(($user['role']??'')!=='admin'){http_response_code(403);exit('Chỉ quản trị viên được cài đặt lịch hiển thị vận hành.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');
    if($action==='delete'){$ok=cds_operation_delete_assignment(trim((string)($_POST['id']??'')));flash($ok?'Đã xóa lịch hiển thị.':'Không xóa được lịch hiển thị.',$ok?'success':'danger');}
    elseif($action==='add'){
        $teacherId=trim((string)($_POST['teacher_id']??''));$teacherName='';
        foreach(csdl_teachers_all()as$teacher)if((string)($teacher['id']??'')===$teacherId){$teacherName=trim((string)($teacher['name']??''));break;}
        [$ok,$message]=cds_operation_add_assignment(['group'=>$_POST['group']??'','teacher_id'=>$teacherId,'teacher_name'=>$teacherName,'weekdays'=>$_POST['weekdays']??[],'start_date'=>$_POST['start_date']??'','end_date'=>$_POST['end_date']??'','note'=>$_POST['note']??'','created_by'=>$user['name']??'']);
        flash($message,$ok?'success':'danger');
    }
    header('Location: '.BASE_URL.'admin_operation_settings.php');exit;
}
$teachers=array_values(array_filter(csdl_teachers_all(),fn($row)=>!isset($row['active'])||!empty($row['active'])));
usort($teachers,fn($a,$b)=>strnatcasecmp((string)($a['name']??''),(string)($b['name']??'')));
$currentYear=cds_school_year_resolve();
$defaultStart=(string)($currentYear['start_date']??$currentYear['start']??date('Y').'-08-01');
$defaultEnd=(string)($currentYear['end_date']??$currentYear['end']??((int)date('Y')+1).'-07-31');
$groups=cds_operation_groups();
$rows=cds_operation_assignments();
$dayLabels=[1=>'Thứ 2',2=>'Thứ 3',3=>'Thứ 4',4=>'Thứ 5',5=>'Thứ 6',6=>'Thứ 7',7=>'Chủ nhật'];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cài đặt vận hành hôm nay</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--ops-blue:#1677e8;--ops-ink:#17243b;--ops-muted:#667892;--ops-line:#dce7f1}body{background:#f3f7fb;color:var(--ops-ink)}.wrap{max-width:1180px}.page-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.4rem;border-radius:20px;background:linear-gradient(125deg,#154f83,#2789c4);color:#fff;box-shadow:0 10px 28px rgba(26,84,132,.17)}.page-head h2{font-weight:750}.page-head p{color:#dceeff}.page-head .btn{background:#fff;border-color:#fff;color:#24577d}.card{border:1px solid var(--ops-line);border-radius:18px;box-shadow:0 8px 24px rgba(31,70,105,.07)!important}.section-title{display:flex;align-items:center;gap:.55rem;margin-bottom:1rem;font-weight:750}.section-title i{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#e9f4ff;color:var(--ops-blue)}.form-label{margin-bottom:.35rem}.weekday-list,.scope-buttons{display:flex;gap:.45rem;flex-wrap:wrap}.weekday-list label{padding:.48rem .65rem;border:1px solid var(--ops-line);border-radius:10px;background:#f9fbfd;cursor:pointer}.weekday-list label:has(input:checked){border-color:var(--ops-blue);background:#eaf4ff;color:#075eb8}.table thead th{white-space:nowrap;color:#49617c;background:#f3f7fb}.table td{vertical-align:middle}.empty-row{padding:2.5rem!important}.hint{font-size:.88rem;color:var(--ops-muted)}@media(max-width:767px){.page-head{align-items:flex-start;flex-direction:column}.page-head .btn{width:100%}.card-body{padding:1rem!important}.scope-buttons .btn{flex:1}}
</style></head><body>
<main class="container wrap py-4"><div class="page-head mb-4"><div><h2 class="mb-1"><i class="bi bi-people-fill"></i> Cài đặt vận hành hôm nay</h2><p class="mb-0">Gán lực lượng hiển thị theo lịch tuần; không phát sinh quyền truy cập hệ thống.</p></div><a class="btn btn-light" href="admin.php"><i class="bi bi-arrow-left"></i> Quay lại Tổng quan</a></div><?php show_flash();?>
<section class="card mb-4"><div class="card-body p-4"><h5 class="section-title"><i class="bi bi-calendar-week"></i> Gán nhanh theo thứ trong tuần</h5><form method="post" class="row g-3"><input type="hidden" name="action" value="add">
<div class="col-md-4"><label class="form-label fw-semibold">Nhóm hiển thị</label><select class="form-select" name="group" required><?php foreach($groups as$key=>$label):?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Cán bộ/giáo viên</label><select class="form-select" name="teacher_id" required><option value="">Chọn người</option><?php foreach($teachers as$teacher):?><option value="<?=e($teacher['id']??'')?>"><?=e($teacher['name']??'')?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Ghi chú</label><input class="form-control" name="note" placeholder="Không bắt buộc"></div>
<div class="col-12"><label class="form-label fw-semibold">Thứ áp dụng</label><div class="weekday-list"><?php foreach($dayLabels as$day=>$label):?><label><input class="form-check-input me-1" type="checkbox" name="weekdays[]" value="<?=$day?>"> <?=e($label)?></label><?php endforeach;?></div></div>
<div class="col-md-4"><label class="form-label fw-semibold">Từ ngày</label><input class="form-control" type="date" name="start_date" id="opsStart" value="<?=e($defaultStart)?>" required></div><div class="col-md-4"><label class="form-label fw-semibold">Đến ngày</label><input class="form-control" type="date" name="end_date" id="opsEnd" value="<?=e($defaultEnd)?>" required></div>
<div class="col-md-4"><label class="form-label fw-semibold">Phạm vi nhanh</label><div class="scope-buttons"><button type="button" class="btn btn-outline-primary" onclick="setMonth()">Cả tháng này</button><button type="button" class="btn btn-outline-success" onclick="setSchoolYear()">Cả năm học</button></div></div>
<div class="col-12 d-flex align-items-center gap-3 flex-wrap"><button class="btn btn-primary px-4"><i class="bi bi-plus-lg"></i> Thêm lịch hiển thị</button><span class="hint"><i class="bi bi-info-circle"></i> Có thể tạo nhiều người trong cùng một nhóm bằng cách thêm từng lịch.</span></div></form></div></section>
<section class="card"><div class="card-body p-4"><h5 class="section-title"><i class="bi bi-list-check"></i> Danh sách lịch đã cài đặt</h5><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nhóm</th><th>Người hiển thị</th><th>Thứ</th><th>Thời gian áp dụng</th><th>Ghi chú</th><th></th></tr></thead><tbody>
<?php foreach($rows as$row):?><tr><td><?=e($groups[$row['group']??'']??'')?></td><td><strong><?=e($row['teacher_name']??'')?></strong></td><td><?=e(implode(', ',array_map(fn($day)=>$dayLabels[(int)$day]??'',(array)($row['weekdays']??[]))))?></td><td><?=e(date('d/m/Y',strtotime($row['start_date']??'')))?> – <?=e(date('d/m/Y',strtotime($row['end_date']??'')))?></td><td><?=e($row['note']??'')?></td><td><form method="post" onsubmit="return confirm('Xóa lịch hiển thị này?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6" class="text-center text-muted empty-row"><i class="bi bi-calendar2-plus fs-3 d-block mb-2"></i>Chưa cài đặt lịch lực lượng bổ sung.</td></tr><?php endif;?></tbody></table></div></div></section></main>
<script>const schoolStart=<?=json_encode($defaultStart)?>,schoolEnd=<?=json_encode($defaultEnd)?>;function localDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}function setMonth(){const d=new Date(),first=new Date(d.getFullYear(),d.getMonth(),1),last=new Date(d.getFullYear(),d.getMonth()+1,0);document.getElementById('opsStart').value=localDate(first);document.getElementById('opsEnd').value=localDate(last)}function setSchoolYear(){document.getElementById('opsStart').value=schoolStart;document.getElementById('opsEnd').value=schoolEnd}</script></body></html>
