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
$knownGroups=array_fill_keys($data[$namesKey]??[],true);
foreach($boarders as $student){$effectiveGroup=trim((string)($student[$field]??''));if($effectiveGroup!=='')$knownGroups[$effectiveGroup]=true;}
$user=current_user();$by=(string)($user['name']??$user['username']??'');

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    $action=(string)($_POST['action']??'');
    if($action==='create_groups'){
        $count=max(1,(int)($_POST['group_count']??1));$prefix=trim((string)($_POST['prefix']??''));$defaultCap=max(1,(int)($_POST['default_capacity']??($mode==='rooms'?8:10)));
        $names=noitru_assignment_names($mode,$count,$prefix);$data[$namesKey]=$names;
        foreach($names as $name)if(!isset($data[$capacityKey][$name]))$data[$capacityKey][$name]=$defaultCap;
        if($mode==='rooms'){
            $defaultGender=(string)($_POST['default_room_gender']??'Linh hoạt');
            foreach($names as $index=>$name)$data['room_genders'][$name]=$defaultGender==='Xen kẽ'?($index%2===0?'Nam':'Nữ'):(in_array($defaultGender,['Nam','Nữ'],true)?$defaultGender:'Linh hoạt');
        }
        noitru_assignments_save($data,$by);flash('Đã tạo danh sách '.$label.'.','success');
    }elseif($action==='add_group'){
        $name=trim((string)($_POST['group_name']??''));$cap=max(1,(int)($_POST['group_capacity']??($mode==='rooms'?8:10)));
        if($name==='')flash('Tên '.$label.' không được để trống.','danger');
        else{if(!in_array($name,$data[$namesKey]??[],true))$data[$namesKey][]=$name;$data[$capacityKey][$name]=$cap;if($mode==='rooms')$data['room_genders'][$name]=in_array($_POST['room_gender']??'',['Nam','Nữ'],true)?(string)$_POST['room_gender']:'Linh hoạt';noitru_assignments_save($data,$by);flash('Đã thêm '.$name.'.','success');}
    }elseif($action==='update_group'){
        $old=trim((string)($_POST['old_name']??''));$new=trim((string)($_POST['new_name']??''));$cap=max(1,(int)($_POST['group_capacity']??1));
        if($old===''||$new==='')flash('Tên '.$label.' không hợp lệ.','danger');
        elseif($old!==$new&&isset($knownGroups[$new]))flash('Tên '.$new.' đã tồn tại.','danger');
        else{
            $data[$namesKey]=array_values(array_map(static fn($n)=>$n===$old?$new:$n,$data[$namesKey]??[]));
            if(!in_array($new,$data[$namesKey],true))$data[$namesKey][]=$new;
            // Ghi đè cả phân công lấy trực tiếp từ hồ sơ CSDL, không chỉ assignments.json.
            foreach($boarders as $student)if(trim((string)($student[$field]??''))===$old)$data[$mode][(string)$student['id']]=$new;
            unset($data[$capacityKey][$old]);$data[$capacityKey][$new]=$cap;
            if($mode==='rooms'){unset($data['room_genders'][$old]);$data['room_genders'][$new]=in_array($_POST['room_gender']??'',['Nam','Nữ'],true)?(string)$_POST['room_gender']:'Linh hoạt';}
            noitru_assignments_save($data,$by);flash('Đã cập nhật '.$label.'.','success');
        }
    }elseif($action==='delete_group'){
        $name=trim((string)($_POST['group_name']??''));
        $data[$namesKey]=array_values(array_filter($data[$namesKey]??[],static fn($n)=>$n!==$name));unset($data[$capacityKey][$name]);if($mode==='rooms')unset($data['room_genders'][$name]);
        // Dùng chuỗi rỗng làm giá trị ghi đè. Nếu unset, phòng/mâm cũ trong CSDL sẽ hiện lại.
        foreach($boarders as $student)if(trim((string)($student[$field]??''))===$name)$data[$mode][(string)$student['id']]='';
        noitru_assignments_save($data,$by);flash('Đã xóa '.$name.' và bỏ phân công học sinh trong '.$label.' này.','warning');
    }elseif($action==='reset_groups'){
        $data[$namesKey]=[];$data[$capacityKey]=[];if($mode==='rooms')$data['room_genders']=[];
        foreach($boarders as $student)$data[$mode][(string)$student['id']]='';
        noitru_assignments_save($data,$by);flash('Đã xóa toàn bộ danh sách và kết quả chia '.$label.'.','warning');
    }elseif($action==='auto_assign'){
        $names=array_values(array_keys($knownGroups));sort($names,SORT_NATURAL);$fallback=max(1,(int)($_POST['capacity']??($mode==='rooms'?8:10)));
        if(!$names)flash('Hãy tạo danh sách '.$label.' trước.','danger');
        else{
            $data[$namesKey]=$names;
            foreach($names as $name){if(!isset($data[$capacityKey][$name]))$data[$capacityKey][$name]=$fallback;if($mode==='rooms'&&!isset($data['room_genders'][$name]))$data['room_genders'][$name]='Linh hoạt';}
            $caps=[];foreach($names as $name)$caps[$name]=max(1,(int)($data[$capacityKey][$name]??$fallback));
            if($mode==='rooms'){
                $options=['enforce_gender'=>isset($_POST['enforce_gender']),'max_grade_gap'=>max(0,min(3,(int)($_POST['max_grade_gap']??1)))];
                $data['settings']['room_enforce_gender']=$options['enforce_gender'];$data['settings']['room_max_grade_gap']=$options['max_grade_gap'];
                $map=noitru_assignment_auto_rooms($boarders,$names,$caps,$data['room_genders']??[],$options);
            }else{
                $options=['balance_gender'=>isset($_POST['balance_gender']),'max_grade_gap'=>max(0,min(3,(int)($_POST['max_grade_gap']??0)))];
                $data['settings']['meal_balance_gender']=$options['balance_gender'];$data['settings']['meal_max_grade_gap']=$options['max_grade_gap'];
                $map=noitru_assignment_auto_meals($boarders,$names,$caps,$options);
            }
            // Ghi rỗng trước để các phân công cũ từ CSDL không xuất hiện lại nếu hết sức chứa.
            foreach($boarders as $student)$data[$mode][(string)$student['id']]='';
            foreach($map as $studentId=>$target)$data[$mode][$studentId]=$target;
            $notAssigned=count($boarders)-count($map);$data['history'][]=['mode'=>$mode,'action'=>'auto','by'=>$by,'at'=>date('c'),'count'=>count($map),'unassigned'=>$notAssigned,'options'=>$options];
            noitru_assignments_save($data,$by);flash('Đã tự động chia '.count($map).' học sinh'.($notAssigned>0?'; còn '.$notAssigned.' học sinh chưa có chỗ do thiếu sức chứa/phòng phù hợp giới tính':'').'.',$notAssigned>0?'warning':'success');
        }
    }elseif($action==='manual_assign'){
        $target=trim((string)($_POST['target']??''));$ids=array_values(array_filter(array_map('strval',$_POST['student_ids']??[])));
        if($target===''||!$ids)flash('Hãy chọn '.$label.' và ít nhất một học sinh.','danger');
        else{
            $currentTargetCount=0;foreach($boarders as $student)if(trim((string)($student[$field]??''))===$target&&!in_array((string)($student['id']??''),$ids,true))$currentTargetCount++;
            $targetCapacity=max(1,(int)($data[$capacityKey][$target]??($mode==='rooms'?8:10)));
            if(!isset($_POST['force_assign'])&&$currentTargetCount+count($ids)>$targetCapacity){flash('Không thể chuyển: '.$target.' sẽ vượt sức chứa '.$targetCapacity.' học sinh. Chọn “Cho phép ngoại lệ” nếu thật sự cần.','danger');header('Location: '.BASE_URL.'noitru_assign.php?mode='.$mode);exit;}
            if($mode==='rooms'&&!isset($_POST['force_assign'])){
                $required=(string)($data['room_genders'][$target]??'Linh hoạt');$invalid=[];
                foreach($boarders as $student)if(in_array((string)($student['id']??''),$ids,true)&&$required!=='Linh hoạt'&&noitru_assignment_gender($student)!==$required)$invalid[]=(string)($student['name']??'');
                if($invalid){flash('Không thể chuyển: phòng '.$target.' dành cho '.$required.' nhưng có '.count($invalid).' học sinh không phù hợp. Chọn “Cho phép ngoại lệ” nếu thật sự cần.','danger');header('Location: '.BASE_URL.'noitru_assign.php?mode='.$mode);exit;}
            }
            foreach($ids as $id)$data[$mode][$id]=$target;
            if(!in_array($target,$data[$namesKey]??[],true))$data[$namesKey][]=$target;
            if(!isset($data[$capacityKey][$target]))$data[$capacityKey][$target]=$mode==='rooms'?8:10;
            noitru_assignments_save($data,$by);flash('Đã gán '.count($ids).' học sinh vào '.$target.'.','success');
        }
    }elseif($action==='remove_students'){
        $ids=array_values(array_filter(array_map('strval',$_POST['student_ids']??[])));
        if(!$ids)flash('Hãy chọn ít nhất một học sinh cần xóa phân công.','danger');
        else{foreach($ids as $id)$data[$mode][$id]='';noitru_assignments_save($data,$by);flash('Đã bỏ chia '.$label.' cho '.count($ids).' học sinh đã chọn.','warning');}
    }elseif($action==='clear_all'){
        foreach($boarders as $student)$data[$mode][(string)$student['id']]='';
        noitru_assignments_save($data,$by);flash('Đã xóa toàn bộ kết quả chia '.$label.'.','warning');
    }
    header('Location: '.BASE_URL.'noitru_assign.php?mode='.$mode);exit;
}

$data=noitru_assignments_data();$boarders=noitru_assignment_apply(noitru_boarders_live());
$boarders=array_values(array_filter($boarders,static fn($s)=>function_exists('can_class')?can_class($s['class_name']??''):true));
$names=$data[$namesKey]??[];$capacities=$data[$capacityKey]??[];$grouped=[];$unassigned=[];$classes=[];
foreach($boarders as $student){$classes[(string)($student['class_name']??'')]=true;$name=trim((string)($student[$field]??''));if($name==='')$unassigned[]=$student;else$grouped[$name][]=$student;}
// Phòng/mâm đã có sẵn trong hồ sơ CSDL cũng phải xuất hiện ở khu vực sửa/xóa.
$names=array_values(array_unique(array_merge($names,array_keys($grouped))));sort($names,SORT_NATURAL);
foreach($names as $name)if(!isset($grouped[$name]))$grouped[$name]=[];ksort($grouped,SORT_NATURAL);$classes=array_values(array_filter(array_keys($classes)));sort($classes,SORT_NATURAL);
$roomGenders=is_array($data['room_genders']??null)?$data['room_genders']:[];$settings=is_array($data['settings']??null)?$data['settings']:[];
$page_title=$mode==='rooms'?'Chia phòng nội trú':'Chia mâm ăn';$tab='boarders';$nt_sec='boarders';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($page_title)?> – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="<?=BASE_URL?>includes/noitru_layout.css?v=20260731-4" rel="stylesheet">
<style>.assign-card{border:1px solid #dbe5ee;border-radius:14px;background:#fff;box-shadow:0 3px 12px rgba(15,23,42,.05)}.assign-summary{font-size:.78rem;color:#64748b}.assign-student{display:flex;gap:.45rem;align-items:flex-start;padding:.42rem .5rem;border-bottom:1px solid #edf2f7}.assign-student:last-child{border-bottom:0}.assign-student label{cursor:pointer;flex:1}.assign-name{font-weight:700;color:#173f65}.assign-meta{font-size:.75rem;color:#64748b}.assign-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:.8rem}.assign-group-head{padding:.7rem .85rem;background:#eef5fb;border-bottom:1px solid #dbe5ee}.assign-scroll{max-height:350px;overflow:auto}.sticky-tools{position:sticky;top:.5rem;z-index:5}.group-row{display:grid;grid-template-columns:minmax(130px,1fr) 85px 105px auto auto 55px;gap:.45rem;align-items:center}.rule-box{border:1px solid #dbe5ee;border-radius:10px;padding:.55rem .7rem;background:#f8fafc}.gender-nam{background:#e0f2fe;color:#075985}.gender-nữ{background:#fce7f3;color:#9d174d}.gender-linh-hoạt{background:#e2e8f0;color:#334155}@media(max-width:700px){.group-row{grid-template-columns:1fr 78px 100px}.group-row button{width:100%}.group-row .delete-group{grid-column:auto}.group-row .group-count{grid-column:1/-1}.sticky-tools{position:static}}</style></head>
<body class="nt-body"><?php require __DIR__.'/includes/noitru_shell.php';?><main class="nt-main"><div class="nt-content">
<div class="nt-page-head"><div><h4 class="mb-0 fw-bold"><i class="bi <?=$mode==='rooms'?'bi-door-closed':'bi-egg-fried'?>"></i> <?=e($page_title)?></h4><div class="text-muted small mt-1">Quản lý danh sách, sức chứa, chia tự động, gán nhanh và xóa chọn.</div></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm <?=$mode==='rooms'?'btn-primary':'btn-outline-primary'?>" href="?mode=rooms">Chia phòng</a><a class="btn btn-sm <?=$mode==='meals'?'btn-warning':'btn-outline-warning'?>" href="?mode=meals">Chia mâm</a><a class="btn btn-sm btn-outline-secondary" href="<?=BASE_URL?>noitru_list.php?view=<?=$mode?>">Trở lại</a></div></div>
<?php show_flash(); ?>
<div class="row g-3 mb-3"><div class="col-lg-5"><div class="assign-card p-3 h-100"><h6 class="fw-bold">1. Tạo nhanh danh sách <?=e($label)?></h6><form method="post" class="row g-2 align-items-end"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="create_groups"><div class="col-4 col-md-3"><label class="form-label small">Số lượng</label><input class="form-control form-control-sm" type="number" min="1" max="200" name="group_count" value="<?=max(1,count($names))?>" required></div><div class="col-8 col-md-4"><label class="form-label small">Tiền tố</label><input class="form-control form-control-sm" name="prefix" placeholder="<?=$mode==='rooms'?'Phòng ':'Mâm '?>"></div><div class="col-4 col-md-3"><label class="form-label small">Sức chứa</label><input class="form-control form-control-sm" type="number" min="1" max="100" name="default_capacity" value="<?=$mode==='rooms'?8:10?>"></div><?php if($mode==='rooms'):?><div class="col-8 col-md-4"><label class="form-label small">Loại phòng</label><select class="form-select form-select-sm" name="default_room_gender"><option value="Xen kẽ">Xen kẽ Nam/Nữ</option><option>Nam</option><option>Nữ</option><option>Linh hoạt</option></select></div><?php endif;?><div class="<?=$mode==='rooms'?'col-md-8':'col-8 col-md-2'?>"><button class="btn btn-sm btn-primary w-100">Tạo danh sách</button></div></form></div></div>
<div class="col-lg-7"><div class="assign-card p-3 h-100"><h6 class="fw-bold">2. Quy tắc chia tự động</h6><form method="post" onsubmit="return confirm('Chia lại toàn bộ học sinh theo các quy tắc đã chọn?')"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="auto_assign"><div class="rule-box mb-2"><div class="fw-semibold small mb-1"><?=$mode==='rooms'?'Cùng lớp → chia đều phòng → cùng khối gần nhau':'Cùng lớp → cùng khối → cân bằng tỷ lệ nam nữ'?></div><div class="row g-2 align-items-center"><?php if($mode==='rooms'):?><div class="col-md-7"><label class="form-check small"><input class="form-check-input" type="checkbox" name="enforce_gender" value="1" <?=($settings['room_enforce_gender']??true)?'checked':''?>> Không trộn học sinh nam và nữ</label></div><div class="col-md-5"><label class="small">Khối cách nhau tối đa</label><select class="form-select form-select-sm" name="max_grade_gap"><option value="0" <?=((int)($settings['room_max_grade_gap']??1)===0)?'selected':''?>>Chỉ cùng khối</option><option value="1" <?=((int)($settings['room_max_grade_gap']??1)===1)?'selected':''?>>1 khối (6–7, 7–8…)</option><option value="2" <?=((int)($settings['room_max_grade_gap']??1)===2)?'selected':''?>>Tối đa 2 khối</option></select></div><?php else:?><div class="col-md-7"><label class="form-check small"><input class="form-check-input" type="checkbox" name="balance_gender" value="1" <?=($settings['meal_balance_gender']??true)?'checked':''?>> Chia đều tỷ lệ nam/nữ trong lớp hoặc khối</label></div><div class="col-md-5"><label class="small">Ghép lớp khi thiếu chỗ</label><select class="form-select form-select-sm" name="max_grade_gap"><option value="0" <?=((int)($settings['meal_max_grade_gap']??0)===0)?'selected':''?>>Chỉ cùng khối</option><option value="1" <?=((int)($settings['meal_max_grade_gap']??0)===1)?'selected':''?>>Cho phép khối liền kề</option></select></div><?php endif;?></div></div><button class="btn btn-sm btn-success w-100"><i class="bi bi-magic"></i> Chia tự động theo quy tắc</button></form></div></div></div>

<div class="assign-card p-3 mb-3"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="fw-bold mb-0">3. Danh sách <?=e($label)?> và sức chứa</h6><form method="post" onsubmit="return confirm('Xóa toàn bộ danh sách <?=e($label)?> và kết quả đã chia?')"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="reset_groups"><button class="btn btn-sm btn-outline-danger">Xóa danh sách và cài đặt lại</button></form></div>
<form method="post" class="row g-2 mb-3 align-items-center"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="add_group"><div class="<?=$mode==='rooms'?'col-md-5':'col-md-7'?>"><input class="form-control form-control-sm" name="group_name" required placeholder="Tên <?=e($label)?> mới"></div><div class="col-4 col-md-2"><input class="form-control form-control-sm" type="number" min="1" max="100" name="group_capacity" value="<?=$mode==='rooms'?8:10?>" required title="Sức chứa"></div><?php if($mode==='rooms'):?><div class="col-8 col-md-3"><select class="form-select form-select-sm" name="room_gender"><option>Nam</option><option>Nữ</option><option selected>Linh hoạt</option></select></div><?php endif;?><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Thêm</button></div></form>
<div class="vstack gap-2"><?php foreach($names as $name):$count=count($grouped[$name]??[]);$roomGender=(string)($roomGenders[$name]??'Linh hoạt');?><form method="post" class="group-row"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="old_name" value="<?=e($name)?>"><input class="form-control form-control-sm" name="new_name" value="<?=e($name)?>" required title="Tên <?=e($label)?>"><input class="form-control form-control-sm" type="number" min="1" max="100" name="group_capacity" value="<?=(int)($capacities[$name]??($mode==='rooms'?8:10))?>" title="Sức chứa"><?php if($mode==='rooms'):?><select class="form-select form-select-sm" name="room_gender" title="Loại phòng"><option <?=$roomGender==='Nam'?'selected':''?>>Nam</option><option <?=$roomGender==='Nữ'?'selected':''?>>Nữ</option><option <?=$roomGender==='Linh hoạt'?'selected':''?>>Linh hoạt</option></select><?php else:?><span class="small text-muted text-center">—</span><?php endif;?><button class="btn btn-sm btn-outline-primary" name="action" value="update_group">Lưu</button><button class="btn btn-sm btn-outline-danger delete-group" name="action" value="delete_group" onclick="return confirm('Xóa <?=e($name)?> và bỏ phân công học sinh?')">Xóa</button><input type="hidden" name="group_name" value="<?=e($name)?>"><span class="small text-muted group-count"><?=$count?> HS</span></form><?php endforeach;?><?php if(!$names):?><div class="text-muted small">Chưa có <?=e($label)?> nào.</div><?php endif;?></div></div>

<div class="assign-card p-3 mb-3 sticky-tools"><h6 class="fw-bold">4. Lọc, gán nhanh hoặc xóa chọn</h6><div class="row g-2 mb-2"><div class="col-md-3"><select id="assignClassFilter" class="form-select form-select-sm"><option value="">Tất cả lớp</option><?php foreach($classes as $c):?><option><?=e($c)?></option><?php endforeach;?></select></div><div class="col-md-2"><select id="assignGenderFilter" class="form-select form-select-sm"><option value="">Mọi giới tính</option><option>Nam</option><option>Nữ</option></select></div><div class="col-md-2"><select id="assignStatusFilter" class="form-select form-select-sm"><option value="">Đã và chưa chia</option><option value="unassigned">Chưa chia</option><option value="assigned">Đã chia</option></select></div><div class="col-md-3"><input id="assignSearchFilter" class="form-control form-control-sm" placeholder="Tìm tên học sinh"></div><div class="col-md-2 d-flex gap-1"><button type="button" id="assignSelectVisible" class="btn btn-sm btn-outline-primary flex-fill">Chọn</button><button type="button" id="assignClearSelection" class="btn btn-sm btn-outline-secondary flex-fill">Bỏ</button></div></div>
<form method="post" id="bulkAssignForm" class="row g-2 align-items-end"><input type="hidden" name="mode" value="<?=e($mode)?>"><div class="col-md-5"><label class="form-label small">Chuyển/Gán vào <?=e($label)?></label><select class="form-select form-select-sm" name="target"><option value="">-- Chọn <?=e($label)?> đích --</option><?php foreach($names as $name):?><option value="<?=e($name)?>"><?=e($name)?><?php if($mode==='rooms'):?> · <?=e($roomGenders[$name]??'Linh hoạt')?><?php endif;?> · <?=count($grouped[$name]??[])?>/<?=(int)($capacities[$name]??($mode==='rooms'?8:10))?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="force_assign" value="1"> Cho phép ngoại lệ</label><div class="text-muted" style="font-size:.68rem">Vượt sức chứa/khác giới tính phòng</div></div><div class="col-md-3"><button class="btn btn-sm btn-primary w-100" name="action" value="manual_assign"><i class="bi bi-arrow-left-right"></i> Chuyển/Gán đã chọn</button></div><div class="col-md-2"><button class="btn btn-sm btn-outline-danger w-100" name="action" value="remove_students" formnovalidate onclick="return confirm('Bỏ chia <?=e($label)?> cho học sinh đã chọn?')"><i class="bi bi-x-circle"></i> Bỏ phân công</button></div></form></div>

<div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong><?=count($boarders)?></strong> HS · <span class="text-success"><?=count($boarders)-count($unassigned)?> đã chia</span> · <span class="text-danger"><?=count($unassigned)?> chưa chia</span></div><form method="post" onsubmit="return confirm('Xóa toàn bộ kết quả chia <?=e($label)?> nhưng giữ danh sách <?=e($label)?>?')"><input type="hidden" name="mode" value="<?=e($mode)?>"><input type="hidden" name="action" value="clear_all"><button class="btn btn-sm btn-outline-danger">Xóa tất cả kết quả chia</button></form></div>
<div class="assign-groups"><?php foreach($grouped as $groupName=>$students):$sum=noitru_assignment_summary($students);$roomGender=(string)($roomGenders[$groupName]??'Linh hoạt');?><section class="assign-card overflow-hidden" data-assigned="1" data-group="<?=e($groupName)?>"><div class="assign-group-head"><div class="d-flex justify-content-between gap-2"><div><strong><?=e($groupName)?></strong><?php if($mode==='rooms'):?> <span class="badge gender-<?=e(mb_strtolower(str_replace(' ','-',$roomGender),'UTF-8'))?>"><?=e($roomGender)?></span><?php endif;?></div><div class="d-flex align-items-center gap-1"><button type="button" class="btn btn-sm btn-link py-0 select-group" title="Chọn toàn bộ học sinh trong <?=e($groupName)?>">Chọn cả</button><span class="badge bg-primary"><?=$sum['total']?> / <?=(int)($capacities[$groupName]??0)?> HS</span></div></div><div class="assign-summary">Nam <?=$sum['male']?> · Nữ <?=$sum['female']?> · Khối <?=e(implode(', ',array_keys($sum['grades'])))?:'—'?> · Lớp <?=e(implode(', ',array_keys($sum['classes'])))?:'—'?></div></div><div class="assign-scroll"><?php foreach($students as $student):?><div class="assign-student" data-class="<?=e($student['class_name'])?>" data-gender="<?=e(noitru_assignment_gender($student))?>" data-status="assigned" data-name="<?=e(mb_strtolower($student['name'],'UTF-8'))?>"><input form="bulkAssignForm" class="form-check-input mt-1 assign-check" type="checkbox" name="student_ids[]" value="<?=e($student['id'])?>" id="s_<?=e($student['id'])?>"><label for="s_<?=e($student['id'])?>"><div class="assign-name"><?=e($student['name'])?></div><div class="assign-meta"><?=e($student['class_name'])?> · <?=e(noitru_assignment_gender($student))?> · Khối <?=e(noitru_assignment_grade($student))?></div></label></div><?php endforeach;?><?php if(!$students):?><div class="text-muted text-center py-3 small">Chưa có học sinh.</div><?php endif;?></div></section><?php endforeach;?>
<section class="assign-card overflow-hidden" data-assigned="0"><div class="assign-group-head"><div class="d-flex justify-content-between"><strong>Chưa chia <?=e($label)?></strong><span class="badge bg-danger"><?=count($unassigned)?> HS</span></div></div><div class="assign-scroll"><?php foreach($unassigned as $student):?><div class="assign-student" data-class="<?=e($student['class_name'])?>" data-gender="<?=e(noitru_assignment_gender($student))?>" data-status="unassigned" data-name="<?=e(mb_strtolower($student['name'],'UTF-8'))?>"><input form="bulkAssignForm" class="form-check-input mt-1 assign-check" type="checkbox" name="student_ids[]" value="<?=e($student['id'])?>" id="u_<?=e($student['id'])?>"><label for="u_<?=e($student['id'])?>"><div class="assign-name"><?=e($student['name'])?></div><div class="assign-meta"><?=e($student['class_name'])?> · <?=e(noitru_assignment_gender($student))?> · Khối <?=e(noitru_assignment_grade($student))?></div></label></div><?php endforeach;?></div></section></div>
</div></main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>(function(){var cf=document.getElementById('assignClassFilter'),gf=document.getElementById('assignGenderFilter'),sf=document.getElementById('assignStatusFilter'),qf=document.getElementById('assignSearchFilter');function run(){var c=cf.value,g=gf.value,s=sf.value,q=(qf.value||'').toLocaleLowerCase('vi');document.querySelectorAll('.assign-student').forEach(function(row){var ok=(!c||row.dataset.class===c)&&(!g||row.dataset.gender===g)&&(!s||row.dataset.status===s)&&(!q||(row.dataset.name||'').includes(q));row.hidden=!ok});document.querySelectorAll('.assign-groups>section').forEach(function(sec){var visible=Array.from(sec.querySelectorAll('.assign-student')).some(function(r){return !r.hidden});sec.hidden=!visible&&sec.querySelectorAll('.assign-student').length>0})} [cf,gf,sf,qf].forEach(function(el){el.addEventListener(el===qf?'input':'change',run)});document.getElementById('assignSelectVisible').onclick=function(){document.querySelectorAll('.assign-student:not([hidden]) .assign-check').forEach(function(x){x.checked=true})};document.getElementById('assignClearSelection').onclick=function(){document.querySelectorAll('.assign-check').forEach(function(x){x.checked=false})};document.querySelectorAll('.select-group').forEach(function(button){button.onclick=function(){button.closest('section').querySelectorAll('.assign-student:not([hidden]) .assign-check').forEach(function(x){x.checked=true});document.querySelector('[name=target]').focus()}});})();</script></body></html>
