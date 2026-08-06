<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_assignment_store.php';

require_login();
require_perm_level('nt.danhsach','edit');

$mode=(($_GET['mode']??$_POST['mode']??'rooms')==='meals')?'meals':'rooms';
$label=$mode==='rooms'?'phòng':'mâm';
$field=$mode==='rooms'?'room_ktx':'meal_group';
$namesKey=$mode==='rooms'?'room_names':'meal_names';
$capacityKey=$mode==='rooms'?'room_capacities':'meal_capacities';
$data=noitru_assignments_data();
$boarders=noitru_assignment_apply(noitru_boarders_live());
$boarders=array_values(array_filter($boarders,static fn($s)=>function_exists('can_class')?can_class($s['class_name']??''):true));
$user=current_user();$by=(string)($user['name']??$user['username']??'');

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    $action=(string)($_POST['action']??'');
    if($action==='create_groups'){
        $count=max(1,(int)($_POST['group_count']??1));$prefix=trim((string)($_POST['prefix']??''));$defaultCap=max(1,(int)($_POST['default_capacity']??($mode==='rooms'?8:10)));
        $names=noitru_assignment_names($mode,$count,$prefix);$data[$namesKey]=$names;
        foreach($names as $name)if(!isset($data[$capacityKey][$name]))$data[$capacityKey][$name]=$defaultCap;
        noitru_assignments_save($data,$by);flash('Đã tạo danh sách '.$label.'.','success');
    }elseif($action==='add_group'){
        $name=trim((string)($_POST['group_name']??''));$cap=max(1,(int)($_POST['group_capacity']??($mode==='rooms'?8:10)));
        if($name==='')flash('Tên '.$label.' không được để trống.','danger');
        else{if(!in_array($name,$data[$namesKey]??[],true))$data[$namesKey][]=$name;$data[$capacityKey][$name]=$cap;noitru_assignments_save($data,$by);flash('Đã thêm '.$name.'.','success');}
    }elseif($action==='update_group'){
        $old=trim((string)($_POST['old_name']??''));$new=trim((string)($_POST['new_name']??''));$cap=max(1,(int)($_POST['group_capacity']??1));
        if($old===''||$new==='')flash('Tên '.$label.' không hợp lệ.','danger');
        else{
            $data[$namesKey]=array_values(array_map(static fn($n)=>$n===$old?$new:$n,$data[$namesKey]??[]));
            foreach($data[$mode]??[] as $sid=>$target)if($target===$old)$data[$mode][$sid]=$new;
            unset($data[$capacityKey][$old]);$data[$capacityKey][$new]=$cap;
            noitru_assignments_save($data,$by);flash('Đã cập nhật '.$label.'.','success');
        }
    }elseif($action==='delete_group'){
        $name=trim((string)($_POST['group_name']??''));
        $data[$namesKey]=array_values(array_filter($data[$namesKey]??[],static fn($n)=>$n!==$name));unset($data[$capacityKey][$name]);
        foreach($data[$mode]??[] as $sid=>$target)if($target===$name)unset($data[$mode][$sid]);
        noitru_assignments_save($data,$by);flash('Đã xóa '.$name.' và bỏ phân công học sinh trong '.$label.' này.','warning');
    }elseif($action==='reset_groups'){
        $data[$namesKey]=[];$data[$capacityKey]=[];$data[$mode]=[];noitru_assignments_save($data,$by);flash('Đã xóa toàn bộ danh sách và kết quả chia '.$label.'.','warning');
    }elseif($action==='auto_assign'){
        $names=$data[$namesKey]??[];$fallback=max(1,(int)($_POST['capacity']??($mode==='rooms'?8:10)));
        if(!$names)flash('Hãy tạo danh sách '.$label.' trước.','danger');
        else{
            $caps=[];foreach($names as $name)$caps[$name]=max(1,(int)($data[$capacityKey][$name]??$fallback));
            $map=$mode==='rooms'?noitru_assignment_auto_rooms($boarders,$names,$caps):noitru_assignment_auto_meals($boarders,$names,$caps);
            $data[$mode]=$map;$data['history'][]=['mode'=>$mode,'action'=>'auto','by'=>$by,'at'=>date('c'),'count'=>count($map)];
            noitru_assignments_save($data,$by);flash('Đã tự động chia '.count($map).' học sinh.','success');
        }
    }elseif($action==='manual_assign'){
        $target=trim((string)($_POST['target']??''));$ids=array_values(array_filter(array_map('strval',$_POST['student_ids']??[])));
        if($target===''||!$ids)flash('Hãy chọn '.$label.' và ít nhất một học sinh.','danger');
        else{
            foreach($ids as $id)$data[$mode][$id]=$target;
            if(!in_array($target,$data[$namesKey]??[],true))$data[$namesKey][]=$target;
            if(!isset($data[$capacityKey][$target]))$data[$capacityKey][$target]=$mode==='rooms'?8:10;
            noitru_assignments_save($data,$by);flash('Đã gán '.count($ids).' học sinh vào '.$target.'.','success');
        }
    }elseif($action==='remove_students'){
        $ids=array_values(array_filter(array_map('strval',$_POST['student_ids']??[])));foreach($ids as $id)unset($data[$mode][$id]);
        noitru_assignments_save($data,$by);flash('Đã bỏ chia '.$label.' cho '.count($ids).' học sinh đã chọn.','warning');
    }elseif($action==='clear_all'){
        $data[$mode]=[];noitru_assignments_save($data,$by);flash('Đã xóa toàn bộ kết quả chia '.$label.'.','warning');
    }
    header('Location: '.BASE_URL.'noitru_assign.php?mode='.$mode);exit;
}

$data=noitru_assignments_data();$boarders=noitru_assignment_apply(noitru_boarders_live());
$boarders=array_values(array_filter($boarders,static fn($s)=>function_exists('can_class')?can_class($s['class_name']??''):true));
$names=$data[$namesKey]??[];$capacities=$data[$capacityKey]??[];$grouped=[];$unassigned=[];$classes=[];
foreach($boarders as $student){$classes[(string)($student['class_name']??'')]=true;$name=trim((string)($student[$field]??''));if($name==='')$unassigned[]=$student;else$grouped[$name][]=$student;}
foreach($names as $name)if(!isset($grouped[$name]))$grouped[$name]=[];ksort($grouped,SORT_NATURAL);$classes=array_values(array_filter(array_keys($classes)));sort($classes,SORT_NATURAL);
$page_title=$mode==='rooms'?'Chia phòng nội trú':'Chia mâm ăn';$tab='boarders';$nt_sec='boarders';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($page_title)?> – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="<?=BASE_URL?>includes/noitru_layout.css?v=20260731-4" rel="stylesheet">
<style>.assign-card{border:1px solid #dbe5ee;border-radius:14px;background:#fff;box-shadow:0 3px 12px rgba(15,23,42,.05)}.assign-summary{font-size:.78rem;color:#64748b}.assign-student{display:flex;gap:.45rem;align-items:flex-start;padding:.42rem .5rem;border-bottom:1px solid #edf2f7}.assign-student:last-child{border-bottom:0}.assign-student label{cursor:pointer;flex:1}.assign-name{font-weight:700;color:#173f65}.assign-meta{font-size:.75rem;color:#64748b}.assign-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:.8rem}.assign-group-head{padding:.7rem .85rem;background:#eef5fb;border-bottom:1px solid #dbe5ee}.assign-scroll{max-height:350px;overflow:auto}.sticky-tools{position:sticky;top:.5rem;z-index:5}.group-row{display:grid;grid-template-columns:1fr 90px auto auto 60px;gap:.45rem;align-items:center}@media(max-width:700px){.group-row{grid-template-columns:1fr 80px auto}.group-row .delete-group{grid-column:1/-1}}</style></head>
<body class="nt-body"><?php require __DIR__.'/includes/noitru_shell.php';?><main class="nt-main"><div class="nt-content">
<div class="nt-page-head"><div><h4 class="mb-0 fw-bold"><i class="bi <?=$mode==='rooms'?'bi-door-closed':'bi-egg-fried'?>"></i> <?=e($page_title)?></h4><div class="text-muted small mt-1">Quản lý danh sách, sức chứa, chia tự động, gán nhanh và xóa chọn.</div></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm <?=$mode==='rooms'?'btn-primary':'btn-outline-primary'?>" href="?mode=rooms">Chia phòng</a><a class="btn btn-sm <?=$mode==='meals'?'btn-warning':'btn-outline-warning'?>" href="?mode=meals">Chia mâm</a><a class="btn btn-sm btn-outline-secondary" href="<?=BASE_URL?>noitru_list.php?view=<?=$mode?>">Trở lại</a></div></div>
<?php show_flash(); ?>
<div class="row g-3 mb-3"><div class="col-lg-5"><div class="assign-card p-3 h-100"><h6 class="fw-bold">1. Tạo nhanh danh sách <?=e($label)?></h6><form method="post" class="row g-2 align-items-end"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="create_groups"><div class="col-3"><label class="form-label small">Số lượng</label><input class="form-control form-control-sm" type="number" min="1" max="200" name="group_count" value="<?=max(1,count($names))?>" required></div><div class="col-4"><label class="form-label small">Tiền tố</label><input class="form-control form-control-sm" name="prefix" placeholder="<?=$mode==='rooms'?'Phòng ':'Mâm '?>"></div><div class="col-3"><label class="form-label small">Sức chứa</label><input class="form-control form-control-sm" type="number" min="1" max="100" name="default_capacity" value="<?=$mode==='rooms'?8:10?>"></div><div class="col-2"><button class="btn btn-sm btn-primary w-100">Tạo</button></div></form></div></div>
<div class="col-lg-7"><div class="assign-card p-3 h-100"><h6 class="fw-bold">2. Chia tự động</h6><div class="small text-muted mb-2"><?=$mode==='rooms'?'Ưu tiên cùng giới tính → cùng lớp → cùng khối.':'Ưu tiên cùng lớp → cùng khối → cân bằng nam nữ.'?> Hệ thống dùng sức chứa riêng của từng <?=e($label)?>.</div><form method="post" onsubmit="return confirm('Tự động chia lại toàn bộ học sinh?')"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="auto_assign"><button class="btn btn-sm btn-success"><i class="bi bi-magic"></i> Chia tự động theo cài đặt</button></form></div></div></div>

<div class="assign-card p-3 mb-3"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="fw-bold mb-0">3. Danh sách <?=e($label)?> và sức chứa</h6><form method="post" onsubmit="return confirm('Xóa toàn bộ danh sách <?=e($label)?> và kết quả đã chia?')"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="reset_groups"><button class="btn btn-sm btn-outline-danger">Xóa danh sách và cài đặt lại</button></form></div>
<form method="post" class="row g-2 mb-3"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="add_group"><div class="col-md-7"><input class="form-control form-control-sm" name="group_name" required placeholder="Tên <?=e($label)?> mới"></div><div class="col-md-3"><input class="form-control form-control-sm" type="number" min="1" max="100" name="group_capacity" value="<?=$mode==='rooms'?8:10?>" required></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Thêm</button></div></form>
<div class="vstack gap-2"><?php foreach($names as $name):$count=count($grouped[$name]??[]);?><form method="post" class="group-row"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="old_name" value="<?=e($name)?>"><input class="form-control form-control-sm" name="new_name" value="<?=e($name)?>" required><input class="form-control form-control-sm" type="number" min="1" max="100" name="group_capacity" value="<?=(int)($capacities[$name]??($mode==='rooms'?8:10))?>" title="Sức chứa"><button class="btn btn-sm btn-outline-primary" name="action" value="update_group">Lưu</button><button class="btn btn-sm btn-outline-danger delete-group" name="action" value="delete_group" onclick="return confirm('Xóa <?=e($name)?> và bỏ phân công học sinh?')">Xóa</button><input type="hidden" name="group_name" value="<?=e($name)?>"><span class="small text-muted"><?=$count?> HS</span></form><?php endforeach;?><?php if(!$names):?><div class="text-muted small">Chưa có <?=e($label)?> nào.</div><?php endif;?></div></div>

<div class="assign-card p-3 mb-3 sticky-tools"><h6 class="fw-bold">4. Lọc, gán nhanh hoặc xóa chọn</h6><div class="row g-2 mb-2"><div class="col-md-3"><select id="assignClassFilter" class="form-select form-select-sm"><option value="">Tất cả lớp</option><?php foreach($classes as $c):?><option><?=e($c)?></option><?php endforeach;?></select></div><div class="col-md-2"><select id="assignGenderFilter" class="form-select form-select-sm"><option value="">Mọi giới tính</option><option>Nam</option><option>Nữ</option></select></div><div class="col-md-2"><select id="assignStatusFilter" class="form-select form-select-sm"><option value="">Đã và chưa chia</option><option value="unassigned">Chưa chia</option><option value="assigned">Đã chia</option></select></div><div class="col-md-3"><input id="assignSearchFilter" class="form-control form-control-sm" placeholder="Tìm tên học sinh"></div><div class="col-md-2 d-flex gap-1"><button type="button" id="assignSelectVisible" class="btn btn-sm btn-outline-primary flex-fill">Chọn</button><button type="button" id="assignClearSelection" class="btn btn-sm btn-outline-secondary flex-fill">Bỏ</button></div></div>
<form method="post" id="bulkAssignForm" class="row g-2 align-items-end"><input type="hidden" name="mode" value="<?=e($mode)?>"><div class="col-md-6"><label class="form-label small">Chọn <?=e($label)?></label><select class="form-select form-select-sm" name="target"><option value="">-- Chọn <?=e($label)?> --</option><?php foreach($names as $name):?><option><?=e($name)?></option><?php endforeach;?></select></div><div class="col-md-3"><button class="btn btn-sm btn-primary w-100" name="action" value="manual_assign"><i class="bi bi-person-plus"></i> Gán học sinh đã chọn</button></div><div class="col-md-3"><button class="btn btn-sm btn-outline-danger w-100" name="action" value="remove_students" formnovalidate onclick="return confirm('Bỏ chia <?=e($label)?> cho học sinh đã chọn?')"><i class="bi bi-x-circle"></i> Xóa phân công đã chọn</button></div></form></div>

<div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong><?=count($boarders)?></strong> HS · <span class="text-success"><?=count($boarders)-count($unassigned)?> đã chia</span> · <span class="text-danger"><?=count($unassigned)?> chưa chia</span></div><form method="post" onsubmit="return confirm('Xóa toàn bộ kết quả chia <?=e($label)?> nhưng giữ danh sách <?=e($label)?>?')"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="clear_all"><button class="btn btn-sm btn-outline-danger">Xóa tất cả kết quả chia</button></form></div>
<div class="assign-groups"><?php foreach($grouped as $groupName=>$students):$sum=noitru_assignment_summary($students);?><section class="assign-card overflow-hidden" data-assigned="1"><div class="assign-group-head"><div class="d-flex justify-content-between"><strong><?=e($groupName)?></strong><span class="badge bg-primary"><?=$sum['total']?> / <?=(int)($capacities[$groupName]??0)?> HS</span></div><div class="assign-summary">Nam <?=$sum['male']?> · Nữ <?=$sum['female']?> · Khối <?=e(implode(', ',array_keys($sum['grades'])))?:'—'?></div></div><div class="assign-scroll"><?php foreach($students as $student):?><div class="assign-student" data-class="<?=e($student['class_name'])?>" data-gender="<?=e(noitru_assignment_gender($student))?>" data-status="assigned" data-name="<?=e(mb_strtolower($student['name'],'UTF-8'))?>"><input form="bulkAssignForm" class="form-check-input mt-1 assign-check" type="checkbox" name="student_ids[]" value="<?=e($student['id'])?>" id="s_<?=e($student['id'])?>"><label for="s_<?=e($student['id'])?>"><div class="assign-name"><?=e($student['name'])?></div><div class="assign-meta"><?=e($student['class_name'])?> · <?=e(noitru_assignment_gender($student))?> · Khối <?=e(noitru_assignment_grade($student))?></div></label></div><?php endforeach;?><?php if(!$students):?><div class="text-muted text-center py-3 small">Chưa có học sinh.</div><?php endif;?></div></section><?php endforeach;?>
<section class="assign-card overflow-hidden" data-assigned="0"><div class="assign-group-head"><div class="d-flex justify-content-between"><strong>Chưa chia <?=e($label)?></strong><span class="badge bg-danger"><?=count($unassigned)?> HS</span></div></div><div class="assign-scroll"><?php foreach($unassigned as $student):?><div class="assign-student" data-class="<?=e($student['class_name'])?>" data-gender="<?=e(noitru_assignment_gender($student))?>" data-status="unassigned" data-name="<?=e(mb_strtolower($student['name'],'UTF-8'))?>"><input form="bulkAssignForm" class="form-check-input mt-1 assign-check" type="checkbox" name="student_ids[]" value="<?=e($student['id'])?>" id="u_<?=e($student['id'])?>"><label for="u_<?=e($student['id'])?>"><div class="assign-name"><?=e($student['name'])?></div><div class="assign-meta"><?=e($student['class_name'])?> · <?=e(noitru_assignment_gender($student))?> · Khối <?=e(noitru_assignment_grade($student))?></div></label></div><?php endforeach;?></div></section></div>
</div></main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>(function(){var cf=document.getElementById('assignClassFilter'),gf=document.getElementById('assignGenderFilter'),sf=document.getElementById('assignStatusFilter'),qf=document.getElementById('assignSearchFilter');function run(){var c=cf.value,g=gf.value,s=sf.value,q=(qf.value||'').toLocaleLowerCase('vi');document.querySelectorAll('.assign-student').forEach(function(row){var ok=(!c||row.dataset.class===c)&&(!g||row.dataset.gender===g)&&(!s||row.dataset.status===s)&&(!q||(row.dataset.name||'').includes(q));row.hidden=!ok});document.querySelectorAll('.assign-groups>section').forEach(function(sec){var visible=Array.from(sec.querySelectorAll('.assign-student')).some(function(r){return !r.hidden});sec.hidden=!visible&&sec.querySelectorAll('.assign-student').length>0})} [cf,gf,sf,qf].forEach(function(el){el.addEventListener(el===qf?'input':'change',run)});document.getElementById('assignSelectVisible').onclick=function(){document.querySelectorAll('.assign-student:not([hidden]) .assign-check').forEach(function(x){x.checked=true})};document.getElementById('assignClearSelection').onclick=function(){document.querySelectorAll('.assign-check').forEach(function(x){x.checked=false})};})();</script></body></html>
