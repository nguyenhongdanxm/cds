<?php
require_once __DIR__.'/includes/auth.php';
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
<style>body{background:#f3f7fb}.wrap{max-width:1180px}.card{border:0;border-radius:18px}.weekday-list,.scope-buttons{display:flex;gap:.45rem;flex-wrap:wrap}.weekday-list label{padding:.5rem .65rem;border:1px solid #d9e3ec;border-radius:10px;background:#fff}</style></head><body>
<main class="container wrap py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1"><i class="bi bi-people-fill text-primary"></i> Cài đặt vận hành hôm nay</h2><p class="text-muted mb-0">Chỉ tạo thông tin hiển thị, không cấp thêm quyền cho người được phân công.</p></div><a class="btn btn-outline-secondary" href="admin.php">Quay lại Tổng quan</a></div><?php show_flash();?>
<section class="card shadow-sm mb-4"><div class="card-body p-4"><h5>Gán nhanh theo thứ trong tuần</h5><form method="post" class="row g-3"><input type="hidden" name="action" value="add">
<div class="col-md-4"><label class="form-label fw-semibold">Nhóm hiển thị</label><select class="form-select" name="group" required><?php foreach($groups as$key=>$label):?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Cán bộ/giáo viên</label><select class="form-select" name="teacher_id" required><option value="">Chọn người</option><?php foreach($teachers as$teacher):?><option value="<?=e($teacher['id']??'')?>"><?=e($teacher['name']??'')?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Ghi chú</label><input class="form-control" name="note" placeholder="Không bắt buộc"></div>
<div class="col-12"><label class="form-label fw-semibold">Thứ áp dụng</label><div class="weekday-list"><?php foreach($dayLabels as$day=>$label):?><label><input class="form-check-input me-1" type="checkbox" name="weekdays[]" value="<?=$day?>"> <?=e($label)?></label><?php endforeach;?></div></div>
<div class="col-md-4"><label class="form-label fw-semibold">Từ ngày</label><input class="form-control" type="date" name="start_date" id="opsStart" value="<?=e($defaultStart)?>" required></div><div class="col-md-4"><label class="form-label fw-semibold">Đến ngày</label><input class="form-control" type="date" name="end_date" id="opsEnd" value="<?=e($defaultEnd)?>" required></div>
<div class="col-md-4"><label class="form-label fw-semibold">Phạm vi nhanh</label><div class="scope-buttons"><button type="button" class="btn btn-outline-primary" onclick="setMonth()">Cả tháng này</button><button type="button" class="btn btn-outline-success" onclick="setSchoolYear()">Cả năm học</button></div></div>
<div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm lịch hiển thị</button></div></form></div></section>
<section class="card shadow-sm"><div class="card-body p-4"><h5>Danh sách lịch đã cài đặt</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nhóm</th><th>Người hiển thị</th><th>Thứ</th><th>Thời gian áp dụng</th><th>Ghi chú</th><th></th></tr></thead><tbody>
<?php foreach($rows as$row):?><tr><td><?=e($groups[$row['group']??'']??'')?></td><td><strong><?=e($row['teacher_name']??'')?></strong></td><td><?=e(implode(', ',array_map(fn($day)=>$dayLabels[(int)$day]??'',(array)($row['weekdays']??[]))))?></td><td><?=e(date('d/m/Y',strtotime($row['start_date']??'')))?> – <?=e(date('d/m/Y',strtotime($row['end_date']??'')))?></td><td><?=e($row['note']??'')?></td><td><form method="post" onsubmit="return confirm('Xóa lịch hiển thị này?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6" class="text-center text-muted py-4">Chưa cài đặt lịch lực lượng bổ sung.</td></tr><?php endif;?></tbody></table></div></div></section></main>
<script>const schoolStart=<?=json_encode($defaultStart)?>,schoolEnd=<?=json_encode($defaultEnd)?>;function localDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}function setMonth(){const d=new Date(),first=new Date(d.getFullYear(),d.getMonth(),1),last=new Date(d.getFullYear(),d.getMonth()+1,0);document.getElementById('opsStart').value=localDate(first);document.getElementById('opsEnd').value=localDate(last)}function setSchoolYear(){document.getElementById('opsStart').value=schoolStart;document.getElementById('opsEnd').value=schoolEnd}</script></body></html>
