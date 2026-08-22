<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/noitru_assignment_store.php';
require_login();
require_module('thidua', 'view');

$user = current_user() ?? [];
$userName = trim((string)($user['name'] ?? $user['teacher_name'] ?? $user['username'] ?? ''));
$permissions = ['input'=>'td.student_room_input','stats'=>'td.student_room_stats','settings'=>'td.student_room_settings'];
$allowedTabs = [];
foreach ($permissions as $key=>$code) if (can_perm_level($code,'view')) $allowedTabs[]=$key;
if (!$allowedTabs) { http_response_code(403); exit('Tài khoản chưa được cấp quyền cho Phòng nội trú.'); }
$tab = (string)($_GET['tab'] ?? $allowedTabs[0]);
if (!in_array($tab,$allowedTabs,true)) { http_response_code(403); exit('Tài khoản chưa được cấp quyền cho tab này.'); }
$canEdit = can_perm_level($permissions[$tab],'edit');
$canDelete = can_perm_level($permissions[$tab],'delete');

$dataFile = DATA_PATH.'/thidua_rooms.json';
$data = load_json($dataFile,[]);
$data = array_merge(['settings'=>[],'criteria'=>[],'entries'=>[]],is_array($data)?$data:[]);
$data['settings'] = array_merge(['weekly_max'=>100],is_array($data['settings']??null)?$data['settings']:[]);
$data['criteria'] = array_values(is_array($data['criteria']??null)?$data['criteria']:[]);
$data['entries'] = array_values(is_array($data['entries']??null)?$data['entries']:[]);
if (empty($data['settings']['ratings']) || !is_array($data['settings']['ratings'])) {
    $data['settings']['ratings'] = [
        ['id'=>'excellent','name'=>'Tốt','min_percent'=>(float)($data['settings']['excellent']??90),'color'=>'success'],
        ['id'=>'good','name'=>'Khá','min_percent'=>(float)($data['settings']['good']??80),'color'=>'primary'],
        ['id'=>'pass','name'=>'Đạt','min_percent'=>(float)($data['settings']['pass']??65),'color'=>'warning'],
        ['id'=>'fail','name'=>'Chưa đạt','min_percent'=>0,'color'=>'danger'],
    ];
}
if (!$data['criteria']) $data['criteria'] = [
    ['id'=>'rc_clean','name'=>'Vệ sinh phòng bẩn','points'=>5,'active'=>true],
    ['id'=>'rc_bed','name'=>'Chăn, màn, đồ dùng sắp xếp chưa gọn','points'=>3,'active'=>true],
    ['id'=>'rc_discipline','name'=>'Vi phạm giờ giấc, nội quy phòng ở','points'=>5,'active'=>true],
];

$assignment = noitru_assignments_data();
$rooms = array_values(array_unique(array_filter(array_map('strval',(array)($assignment['room_names']??[])))));
foreach ((array)($assignment['rooms']??[]) as $room) {
    $room=trim((string)$room);
    if ($room!=='' && !in_array($room,$rooms,true)) $rooms[]=$room;
}
sort($rooms,SORT_NATURAL|SORT_FLAG_CASE);

function td_room_redirect(string $tab,array $extra=[]): void { header('Location: '.BASE_URL.'thidua_phongnoitru.php?'.http_build_query(['tab'=>$tab]+$extra)); exit; }
function td_room_week(string $date): array {
    $shared=csdl_week_for_date($date); if ($shared) return $shared;
    $time=strtotime($date)?:time(); $start=date('Y-m-d',strtotime('monday this week',$time));
    return ['start'=>$start,'end'=>date('Y-m-d',strtotime($start.' +6 days')),'label'=>date('d/m',strtotime($start)).' - '.date('d/m/Y',strtotime($start.' +6 days'))];
}
function td_room_ratings(array $settings): array {
    $ratings=array_values(array_filter((array)($settings['ratings']??[]),fn($row)=>trim((string)($row['name']??''))!==''));
    $max=(float)($settings['weekly_max']??100);
    foreach($ratings as &$rating) if(!isset($rating['min_score'])) $rating['min_score']=round($max*(float)($rating['min_percent']??0)/100,2); unset($rating);
    usort($ratings,fn($a,$b)=>(float)($b['min_score']??0)<=>(float)($a['min_score']??0)); return $ratings;
}
function td_room_rating(float $score,float $max,array $settings): array {
    foreach (td_room_ratings($settings) as $rating) if ($score>=(float)($rating['min_score']??0)) return $rating;
    return ['name'=>'Chưa xếp loại','color'=>'secondary','min_score'=>0];
}
function td_room_entry_key(array $entry): string { return (string)($entry['week_start']??'').'|'.(string)($entry['room']??''); }
function td_room_recalculate(array &$entry,array $settings): void {
    $deduction=0.0; foreach ((array)($entry['items']??[]) as $item) $deduction+=(float)($item['points']??0)*max(1,(int)($item['quantity']??1));
    $max=(float)($entry['max_score']??$settings['weekly_max']??100); $score=max(0,$max-$deduction); $rating=td_room_rating($score,$max,$settings);
    $entry['deduction']=round($deduction,2); $entry['score']=round($score,2); $entry['rating']=(string)$rating['name'];
}

if (empty($_SESSION['td_room_csrf'])) $_SESSION['td_room_csrf']=bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['td_room_csrf'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($csrf,(string)($_POST['csrf']??''))) { http_response_code(403); exit('Phiên làm việc không hợp lệ.'); }
    $action=(string)($_POST['action']??'');
    $actionTab=['batch_save'=>'input','entry_delete'=>'input','settings_save'=>'settings'][$action]??'';
    if ($actionTab==='' || !in_array($actionTab,$allowedTabs,true)) { http_response_code(403); exit('Không có quyền thực hiện thao tác này.'); }
    $needed=$action==='entry_delete'?'delete':'edit';
    if (!can_perm_level($permissions[$actionTab],$needed)) { http_response_code(403); exit('Tài khoản chưa có quyền '.$needed.' tại tab này.'); }

    if ($action==='settings_save') {
        $weeklyMax=max(1,(float)($_POST['weekly_max']??100));
        $ratingNames=(array)($_POST['rating_name']??[]); $ratingMins=(array)($_POST['rating_min']??[]); $ratingColors=(array)($_POST['rating_color']??[]); $ratings=[];
        foreach ($ratingNames as $index=>$name) {
            $name=trim((string)$name); if ($name==='') continue;
            $color=(string)($ratingColors[$index]??'secondary');
            if (!in_array($color,['success','primary','info','warning','danger','secondary'],true)) $color='secondary';
            $ratings[]=['id'=>'rr_'.bin2hex(random_bytes(4)),'name'=>$name,'min_score'=>max(0,min($weeklyMax,(float)($ratingMins[$index]??0))),'color'=>$color];
        }
        if (!$ratings) { flash('Cần tạo ít nhất một mức xếp loại.','danger'); td_room_redirect('settings'); }
        usort($ratings,fn($a,$b)=>$b['min_score']<=>$a['min_score']);
        $ids=(array)($_POST['criterion_id']??[]); $names=(array)($_POST['criterion_name']??[]); $points=(array)($_POST['criterion_points']??[]); $active=(array)($_POST['criterion_active']??[]); $criteria=[];
        foreach ($names as $index=>$name) {
            $name=trim((string)$name); if ($name==='') continue;
            $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($ids[$index]??'')); if ($id==='') $id='rc_'.bin2hex(random_bytes(5));
            $criteria[]=['id'=>$id,'name'=>$name,'points'=>max(.01,(float)($points[$index]??1)),'active'=>(string)($active[$index]??'1')==='1'];
        }
        if (!$criteria) { flash('Cần ít nhất một mục điểm trừ.','danger'); td_room_redirect('settings'); }
        $data['settings']['weekly_max']=$weeklyMax; $data['settings']['ratings']=$ratings; $data['criteria']=$criteria;
        save_json($dataFile,$data); flash('Đã lưu thang điểm, tên xếp loại và các mục chấm.','success'); td_room_redirect('settings');
    }

    if ($action==='batch_save') {
        $date=trim((string)($_POST['date']??date('Y-m-d'))); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date=date('Y-m-d');
        $week=td_room_week($date); $criterionId=(string)($_POST['criterion_id']??''); $criterion=null;
        foreach ($data['criteria'] as $row) if (!empty($row['active']) && (string)$row['id']===$criterionId) $criterion=$row;
        if (!$criterion) { flash('Hãy chọn một mục chấm đã được bật trong Cài đặt.','danger'); td_room_redirect('input',['week'=>$week['start']]); }
        $selectedRooms=array_values(array_intersect($rooms,array_map('strval',(array)($_POST['rooms']??[]))));
        if (!$selectedRooms) { flash('Hãy tích ít nhất một phòng bị trừ điểm.','warning'); td_room_redirect('input',['week'=>$week['start']]); }
        $roomPoints=(array)($_POST['room_points']??[]); $roomNotes=(array)($_POST['room_note']??[]); $max=(float)$data['settings']['weekly_max'];
        foreach ($selectedRooms as $room) {
            $entryIndex=null;
            foreach ($data['entries'] as $index=>$entry) if (td_room_entry_key($entry)===$week['start'].'|'.$room) { $entryIndex=$index; break; }
            if ($entryIndex===null) {
                $data['entries'][]=['id'=>'tre_'.bin2hex(random_bytes(7)),'room'=>$room,'date'=>$date,'week_start'=>$week['start'],'week_end'=>$week['end'],'week_label'=>$week['label']??'','items'=>[],'max_score'=>$max,'created_by'=>$userName,'created_at'=>date('c')];
                $entryIndex=array_key_last($data['entries']);
            }
            $pointsValue=max(.01,(float)($roomPoints[$room]??$criterion['points']));
            $data['entries'][$entryIndex]['items'][]=['id'=>'ri_'.bin2hex(random_bytes(5)),'criterion_id'=>$criterionId,'name'=>(string)$criterion['name'],'points'=>$pointsValue,'quantity'=>1,'date'=>$date,'note'=>trim((string)($roomNotes[$room]??'')),'created_by'=>$userName,'created_at'=>date('c')];
            $data['entries'][$entryIndex]['date']=$date; $data['entries'][$entryIndex]['updated_by']=$userName; $data['entries'][$entryIndex]['updated_at']=date('c');
            td_room_recalculate($data['entries'][$entryIndex],$data['settings']);
        }
        save_json($dataFile,$data); flash('Đã ghi mục “'.$criterion['name'].'” cho '.count($selectedRooms).' phòng.','success'); td_room_redirect('input',['week'=>$week['start']]);
    }

    if ($action==='entry_delete') {
        $id=trim((string)($_POST['id']??'')); $before=count($data['entries']);
        $data['entries']=array_values(array_filter($data['entries'],fn($row)=>(string)($row['id']??'')!==$id));
        if (count($data['entries'])===$before) flash('Không tìm thấy dữ liệu cần xóa.','warning');
        else { save_json($dataFile,$data); flash('Đã xóa toàn bộ dữ liệu chấm của phòng trong tuần.','warning'); }
        td_room_redirect('input',['week'=>(string)($_POST['week']??'')]);
    }
}

$today=date('Y-m-d'); $weekRequest=(string)($_GET['week']??$today); $inputWeek=td_room_week($weekRequest); $inputDateDefault=($today>=$inputWeek['start']&&$today<=$inputWeek['end'])?$today:$inputWeek['start']; $inputMap=[];
foreach ($data['entries'] as $entry) if (($entry['week_start']??'')===$inputWeek['start']) $inputMap[(string)($entry['room']??'')]=$entry;
$statsMode=($_GET['mode']??'week')==='range'?'range':'week'; $statsAnchor=(string)($_GET['anchor']??$today); $statsWeek=td_room_week($statsAnchor);
$from=$statsMode==='week'?$statsWeek['start']:(string)($_GET['from']??date('Y-m-01')); $to=$statsMode==='week'?$statsWeek['end']:(string)($_GET['to']??$today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from=date('Y-m-01'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$to<$from) $to=$from;
$summary=[]; foreach ($rooms as $room) $summary[$room]=['room'=>$room,'weeks'=>0,'total'=>0.0,'average'=>0.0,'deduction'=>0.0];
foreach ($data['entries'] as $entry) {
    $entryStart=(string)($entry['week_start']??$entry['date']??''); $entryEnd=(string)($entry['week_end']??$entryStart); $room=(string)($entry['room']??'');
    if ($entryEnd<$from||$entryStart>$to||!isset($summary[$room])) continue;
    $summary[$room]['weeks']++; $summary[$room]['total']+=(float)($entry['score']??0); $summary[$room]['deduction']+=(float)($entry['deduction']??0);
}
foreach ($summary as &$row) if ($row['weeks']) { $row['average']=round($row['total']/$row['weeks'],2); $row['rating_data']=td_room_rating($row['average'],(float)$data['settings']['weekly_max'],$data['settings']); } unset($row);
uasort($summary,function($a,$b){ if(!$a['weeks']&&!$b['weeks'])return strnatcasecmp($a['room'],$b['room']);if(!$a['weeks'])return 1;if(!$b['weeks'])return -1;return ($b['average']<=>$a['average'])?:strnatcasecmp($a['room'],$b['room']); });
$activeCriteria=array_values(array_filter($data['criteria'],fn($row)=>!empty($row['active']))); $ratings=td_room_ratings($data['settings']); $defaultCriterion=$activeCriteria[0]??null;
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Thi đua phòng nội trú – CDS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>
:root{--gold:#d99a00;--soft:#fff8df;--ink:#172033}*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:var(--ink);font-family:system-ui,-apple-system,"Segoe UI",sans-serif}.shell{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100vh}.side{position:sticky;top:0;height:100vh;padding:1rem;background:linear-gradient(180deg,#704d00,#3d2a00);color:#fff;overflow:auto}.brand{display:flex;gap:.7rem;align-items:center;color:#fff;text-decoration:none;padding:.4rem .35rem 1.1rem}.brand i{font-size:1.4rem;background:#ffffff22;border-radius:12px;padding:.65rem}.brand small{display:block;opacity:.7}.side-title{margin:1rem .7rem .35rem;font-size:.68rem;text-transform:uppercase;opacity:.55;font-weight:800}.side a.nav-link{display:flex;align-items:center;gap:.6rem;color:#ffffffd5;border-radius:11px;padding:.68rem .72rem}.side a.nav-link.active,.side a.nav-link:hover{background:#fff;color:#674700}.subtabs{margin:.25rem 0 .5rem .7rem;padding-left:.55rem;border-left:1px solid #ffffff44}.subtabs .nav-link{font-size:.82rem;padding:.52rem!important}.main{min-width:0;padding:1.2rem}.head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}.head h2{font-weight:850;margin:0}.cardx{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 4px 16px #0f172a0a}.toolbar{display:flex;gap:.7rem;align-items:end;flex-wrap:wrap;padding:1rem;margin-bottom:1rem}.batch-layout{display:grid;grid-template-columns:minmax(260px,.65fr) minmax(0,1.35fr);gap:1rem}.batch-form{padding:1rem;align-self:start;position:sticky;top:1rem}.room-select-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.65rem}.room-select{display:grid;grid-template-columns:28px 1fr;gap:.25rem .55rem;padding:.75rem;border:1px solid #dbe3ed;border-radius:13px;background:#fff}.room-select:has(input:checked){border-color:#e0a400;background:#fffaf0}.room-select .room-fields{grid-column:1/-1;display:grid;grid-template-columns:90px minmax(0,1fr);gap:.4rem}.week-summary{margin-top:1rem;padding:1rem}.summary-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.65rem}.summary-card{padding:.8rem;border:1px solid #e2e8f0;border-radius:12px}.summary-card ul{margin:.5rem 0 0;padding-left:1.1rem;font-size:.82rem}.form-control,.form-select,.btn{min-height:42px;border-radius:10px}.table th{background:var(--soft);color:#654600;white-space:nowrap}.rank{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:50%;background:#edf2f7;font-weight:800}.rank.first{background:#ffd95d}.setting-row{display:grid;grid-template-columns:90px minmax(0,1fr) 140px 100px 42px;gap:.55rem;align-items:center;margin-bottom:.55rem}.rating-row{display:grid;grid-template-columns:minmax(0,1fr) 160px 150px 42px;gap:.55rem;align-items:center;margin-bottom:.55rem}.empty{padding:2rem;text-align:center;color:#64748b}.mobile-nav{display:none}@media(max-width:900px){.shell{display:block}.side{display:none}.main{padding:.75rem;padding-bottom:78px}.batch-layout{grid-template-columns:1fr}.batch-form{position:static}.room-select-grid{grid-template-columns:1fr 1fr}.mobile-nav{position:fixed;display:flex;inset:auto 0 0;z-index:1000;height:68px;background:#fff;border-top:1px solid #ddd;overflow:auto}.mobile-nav a{flex:1;min-width:92px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#64748b;text-decoration:none;font-size:.68rem}.mobile-nav a.active{color:#7a5500;background:#fff8df}.setting-row{grid-template-columns:1fr 110px 90px 42px}.setting-row .criterion-id{display:none}.rating-row{grid-template-columns:1fr 100px 110px 42px}}@media(max-width:575px){.room-select-grid{grid-template-columns:1fr}.head{align-items:flex-start;flex-direction:column}}@media print{.side,.no-print,.mobile-nav{display:none!important}.shell{display:block}.main{padding:0}.cardx{box-shadow:none}.table{font-size:11pt}}
</style></head><body><div class="shell"><aside class="side">
<a class="brand" href="<?=e(BASE_URL)?>thidua.php"><i class="bi bi-trophy-fill"></i><div><strong>THI ĐUA – ĐÁNH GIÁ</strong><small><?=e(SCHOOL_SHORT)?></small></div></a>
<div class="side-title">Đánh giá giáo viên, nhân viên</div>
<?php foreach(['teacher_attendance'=>['Chấm công','bi-calendar-check'],'teacher_achievement'=>['Thành tích','bi-award'],'teacher_rating'=>['Hồ sơ đánh giá','bi-person-vcard']] as $key=>$meta):if(!can_perm_level('td.'.$key,'view'))continue;?><a class="nav-link" href="<?=$key==='teacher_rating'?e(BASE_URL.'danhgia.php?view=overview'):e(BASE_URL.'thidua.php?section='.$key)?>"><i class="bi <?=e($meta[1])?>"></i><?=e($meta[0])?></a><?php endforeach;?>
<div class="side-title">Thi đua học sinh</div><?php if(can_perm_level('td.student_score','view')):?><a class="nav-link" href="<?=e(BASE_URL)?>thidua.php?section=student_score"><i class="bi bi-table"></i>Nề nếp - Học tập</a><?php endif;?><a class="nav-link active" href="<?=e(BASE_URL)?>thidua_phongnoitru.php"><i class="bi bi-door-closed"></i>Phòng nội trú</a>
<div class="subtabs"><?php if(in_array('input',$allowedTabs,true)):?><a class="nav-link <?=$tab==='input'?'active':''?>" href="?tab=input"><i class="bi bi-pencil-square"></i>Nhập liệu</a><?php endif;?><?php if(in_array('stats',$allowedTabs,true)):?><a class="nav-link <?=$tab==='stats'?'active':''?>" href="?tab=stats"><i class="bi bi-bar-chart-line"></i>Thống kê - Xếp loại</a><?php endif;?><?php if(in_array('settings',$allowedTabs,true)):?><a class="nav-link <?=$tab==='settings'?'active':''?>" href="?tab=settings"><i class="bi bi-gear"></i>Cài đặt</a><?php endif;?></div>
<?php if(can_perm_level('td.student_profile','view')):?><a class="nav-link" href="<?=e(BASE_URL)?>thidua.php?section=student_profile"><i class="bi bi-folder2-open"></i>Hồ sơ thi đua</a><?php endif;?><?php if(can_perm_level('td.stats','view')):?><div class="side-title">Báo cáo</div><a class="nav-link" href="<?=e(BASE_URL)?>thidua.php?section=stats"><i class="bi bi-bar-chart-line"></i>Thống kê</a><?php endif;?></aside>
<main class="main"><?php show_flash();?><header class="head"><div><h2><i class="bi bi-door-closed text-warning"></i> Phòng nội trú</h2><div class="text-muted">Chấm điểm, tổng hợp và xếp loại phòng ở</div></div><span class="badge text-bg-warning p-2"><?=e($userName)?></span></header>

<?php if($tab==='input'):?>
<form class="cardx toolbar no-print" method="get"><input type="hidden" name="tab" value="input"><div><label class="form-label">Tuần chấm</label><input class="form-control" type="date" name="week" value="<?=e($inputWeek['start'])?>"></div><button class="btn btn-warning"><i class="bi bi-calendar-week"></i> Xem tuần</button><div class="ms-auto"><strong>Tuần: <?=e($inputWeek['label']??($inputWeek['start'].' - '.$inputWeek['end']))?></strong><div class="small text-muted">Theo tuần đã cài đặt trong CSDL · Điểm chuẩn <?=number_format((float)$data['settings']['weekly_max'],1,',','.')?></div></div></form>
<?php if(!$rooms):?><div class="cardx empty">Chưa có danh sách phòng. Hãy đồng bộ phòng trong Quản lý nội trú → Chia phòng.</div><?php else:?><form method="post" id="roomBatchForm"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="batch_save"><div class="batch-layout"><section class="cardx batch-form"><h5 class="fw-bold"><i class="bi bi-ui-checks-grid text-warning"></i> Thông tin lần chấm</h5><label class="form-label mt-2">Ngày chấm</label><input class="form-control" type="date" name="date" value="<?=e($inputDateDefault)?>" min="<?=e($inputWeek['start'])?>" max="<?=e($inputWeek['end'])?>" required><label class="form-label mt-3">Mục chấm chung</label><select class="form-select" id="batchCriterion" name="criterion_id" required onchange="syncDefaultPoints()"><option value="">Chọn nội dung chấm</option><?php foreach($activeCriteria as $criterion):?><option value="<?=e($criterion['id'])?>" data-points="<?=e($criterion['points'])?>"><?=e($criterion['name'])?> · trừ <?=number_format((float)$criterion['points'],1,',','.')?> điểm</option><?php endforeach;?></select><div class="form-text">Chọn một nội dung rồi tích các phòng vi phạm. Điểm từng phòng có thể sửa riêng.</div><button class="btn btn-warning w-100 mt-3" <?=$canEdit?'':'disabled'?>><i class="bi bi-floppy"></i> Lưu lần chấm</button></section><section class="cardx p-3"><div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h5 class="fw-bold mb-0">Phòng bị trừ điểm</h5><small class="text-muted">Chỉ tích những phòng vi phạm</small></div><button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleRooms(true)">Tích tất cả</button></div><div class="room-select-grid"><?php foreach($rooms as $room):?><label class="room-select"><input class="form-check-input room-check" type="checkbox" name="rooms[]" value="<?=e($room)?>" onchange="toggleRoomFields(this)"><strong><?=e($room)?></strong><div class="room-fields"><input class="form-control room-points" type="number" min=".01" step=".01" name="room_points[<?=e($room)?>]" value="<?=e($defaultCriterion['points']??1)?>" disabled title="Điểm trừ"><input class="form-control" name="room_note[<?=e($room)?>]" placeholder="Ghi chú riêng" disabled></div></label><?php endforeach;?></div></section></div></form>
<section class="cardx week-summary"><h5 class="fw-bold">Dữ liệu đã chấm trong tuần</h5><div class="summary-cards"><?php foreach($rooms as $room):$saved=$inputMap[$room]??null;if(!$saved)continue;?><article class="summary-card"><div class="d-flex justify-content-between"><strong>Phòng <?=e($room)?></strong><span class="badge text-bg-warning"><?=number_format((float)$saved['score'],1,',','.')?>/<?=number_format((float)$saved['max_score'],1,',','.')?></span></div><ul><?php foreach((array)($saved['items']??[]) as $item):?><li><?=!empty($item['date'])?date('d/m',strtotime($item['date'])).' · ':''?><?=e($item['name']??'')?>: −<?=number_format((float)($item['points']??0)*max(1,(int)($item['quantity']??1)),1,',','.')?><?=!empty($item['note'])?' · '.e($item['note']):''?></li><?php endforeach;?></ul><?php if($canDelete):?><form method="post" class="mt-2" onsubmit="return confirm('Xóa toàn bộ dữ liệu phòng <?=e($room)?> trong tuần này?')"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="entry_delete"><input type="hidden" name="id" value="<?=e($saved['id'])?>"><input type="hidden" name="week" value="<?=e($inputWeek['start'])?>"><button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Xóa dữ liệu tuần</button></form><?php endif;?></article><?php endforeach;?></div><?php if(!$inputMap):?><div class="empty">Tuần này chưa có lần chấm nào.</div><?php endif;?></section><?php endif;?>

<?php elseif($tab==='stats'):?>
<form class="cardx toolbar no-print" method="get"><input type="hidden" name="tab" value="stats"><div><label class="form-label">Xem kết quả</label><select class="form-select" name="mode" onchange="this.form.submit()"><option value="week" <?=$statsMode==='week'?'selected':''?>>Theo tuần CSDL</option><option value="range" <?=$statsMode==='range'?'selected':''?>>Theo giai đoạn</option></select></div><?php if($statsMode==='week'):?><div><label class="form-label">Ngày thuộc tuần</label><input class="form-control" type="date" name="anchor" value="<?=e($statsAnchor)?>"></div><?php else:?><div><label class="form-label">Từ ngày</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div><div><label class="form-label">Đến ngày</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div><?php endif;?><button class="btn btn-warning"><i class="bi bi-filter"></i> Xem kết quả</button><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> In</button></form><section class="cardx p-3"><div class="d-flex justify-content-between gap-2 flex-wrap mb-3"><div><h5 class="fw-bold mb-1">Xếp loại phòng ở</h5><span class="text-muted"><?=$statsMode==='week'?'Tuần CSDL: ':'Giai đoạn: '?><?=date('d/m/Y',strtotime($from))?> – <?=date('d/m/Y',strtotime($to))?></span></div><div class="small text-muted">Xếp theo điểm trung bình giảm dần · Chỉ tính tuần đã nhập</div></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Thứ hạng</th><th>Phòng</th><th>Số tuần</th><th>Tổng điểm trừ</th><th>Tổng điểm</th><th>Điểm trung bình</th><th>Xếp loại</th></tr></thead><tbody><?php $rank=0;foreach($summary as $row):?><tr><td><?php if($row['weeks']):$rank++;?><span class="rank <?=$rank===1?'first':''?>"><?=$rank?></span><?php else:?>—<?php endif;?></td><td><strong><?=e($row['room'])?></strong></td><td><?=e($row['weeks'])?></td><td><?=$row['weeks']?number_format($row['deduction'],1,',','.'):'—'?></td><td><?=$row['weeks']?number_format($row['total'],1,',','.'):'—'?></td><td><?=$row['weeks']?'<strong>'.number_format($row['average'],2,',','.').'</strong>':'<span class="text-muted">Chưa nhập</span>'?></td><td><?php if($row['weeks']):$rd=$row['rating_data'];?><span class="badge text-bg-<?=e($rd['color']??'secondary')?>"><?=e($rd['name']??'')?></span><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php else:?>
<form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="settings_save"><section class="cardx p-3 mb-3"><h5 class="fw-bold">Thang điểm và tên xếp loại</h5><div class="row g-3 mb-3"><div class="col-md-4"><label class="form-label">Tổng điểm một phòng/tuần</label><input class="form-control" type="number" min="1" step=".1" name="weekly_max" value="<?=e($data['settings']['weekly_max'])?>" required></div></div><div class="d-flex justify-content-between align-items-center mb-2"><small class="text-muted">Tự đặt tên, mức điểm tối thiểu và màu. Hệ thống xét từ điểm cao xuống thấp.</small><button class="btn btn-outline-warning" type="button" onclick="addRatingRow()"><i class="bi bi-plus"></i> Thêm mức</button></div><div id="ratingRows"><?php foreach($ratings as $rating):?><div class="rating-row"><input class="form-control" name="rating_name[]" value="<?=e($rating['name'])?>" placeholder="Tên xếp loại" required><input class="form-control" type="number" min="0" max="<?=e($data['settings']['weekly_max'])?>" step=".1" name="rating_min[]" value="<?=e($rating['min_score'])?>" title="Từ điểm" required><select class="form-select" name="rating_color[]"><?php foreach(['success'=>'Xanh lá','primary'=>'Xanh dương','info'=>'Xanh nhạt','warning'=>'Vàng','danger'=>'Đỏ','secondary'=>'Xám'] as $color=>$label):?><option value="<?=$color?>" <?=($rating['color']??'')===$color?'selected':''?>><?=$label?></option><?php endforeach;?></select><button class="btn btn-outline-danger" type="button" onclick="this.closest('.rating-row').remove()"><i class="bi bi-trash"></i></button></div><?php endforeach;?></div></section><section class="cardx p-3"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1">Các mục chấm và điểm trừ mặc định</h5><span class="text-muted small">Điểm tự điền khi nhập liệu nhưng có thể sửa riêng cho từng phòng.</span></div><button class="btn btn-outline-warning" type="button" onclick="addSettingRow()"><i class="bi bi-plus"></i> Thêm mục</button></div><div id="settingRows"><?php foreach($data['criteria'] as $criterion):?><div class="setting-row"><input class="form-control criterion-id" name="criterion_id[]" value="<?=e($criterion['id'])?>" readonly><input class="form-control" name="criterion_name[]" value="<?=e($criterion['name'])?>" placeholder="Nội dung chấm" required><input class="form-control" type="number" min=".01" step=".01" name="criterion_points[]" value="<?=e($criterion['points'])?>" required><select class="form-select" name="criterion_active[]"><option value="1" <?=!empty($criterion['active'])?'selected':''?>>Hiển thị</option><option value="0" <?=empty($criterion['active'])?'selected':''?>>Ẩn</option></select><button class="btn btn-outline-danger" type="button" onclick="this.closest('.setting-row').remove()"><i class="bi bi-trash"></i></button></div><?php endforeach;?></div><div class="text-end mt-3"><button class="btn btn-warning px-4" <?=$canEdit?'':'disabled'?>><i class="bi bi-floppy"></i> Lưu toàn bộ cài đặt</button></div></section></form>
<?php endif;?></main></div>
<nav class="mobile-nav"><?php if(in_array('input',$allowedTabs,true)):?><a class="<?=$tab==='input'?'active':''?>" href="?tab=input"><i class="bi bi-pencil-square"></i><span>Nhập liệu</span></a><?php endif;?><?php if(in_array('stats',$allowedTabs,true)):?><a class="<?=$tab==='stats'?'active':''?>" href="?tab=stats"><i class="bi bi-bar-chart-line"></i><span>Thống kê</span></a><?php endif;?><?php if(in_array('settings',$allowedTabs,true)):?><a class="<?=$tab==='settings'?'active':''?>" href="?tab=settings"><i class="bi bi-gear"></i><span>Cài đặt</span></a><?php endif;?><a href="<?=e(BASE_URL)?>thidua.php?section=student_score"><i class="bi bi-table"></i><span>Nề nếp</span></a></nav>
<script>
function toggleRoomFields(check){check.closest('.room-select').querySelectorAll('.room-fields input').forEach(function(input){input.disabled=!check.checked})}
function toggleRooms(on){document.querySelectorAll('.room-check').forEach(function(check){check.checked=on;toggleRoomFields(check)})}
function syncDefaultPoints(){var s=document.getElementById('batchCriterion'),o=s.options[s.selectedIndex],p=o?o.dataset.points:'';if(!p)return;document.querySelectorAll('.room-points').forEach(function(i){i.value=p})}
function addRatingRow(){var r=document.createElement('div');r.className='rating-row';r.innerHTML='<input class="form-control" name="rating_name[]" placeholder="Tên xếp loại" required><input class="form-control" type="number" min="0" max="100" step=".1" name="rating_min[]" value="0" required><select class="form-select" name="rating_color[]"><option value="success">Xanh lá</option><option value="primary">Xanh dương</option><option value="info">Xanh nhạt</option><option value="warning">Vàng</option><option value="danger">Đỏ</option><option value="secondary">Xám</option></select><button class="btn btn-outline-danger" type="button" onclick="this.closest(\'.rating-row\').remove()"><i class="bi bi-trash"></i></button>';document.getElementById('ratingRows').appendChild(r)}
function addSettingRow(){var r=document.createElement('div');r.className='setting-row';r.innerHTML='<input class="form-control criterion-id" name="criterion_id[]" readonly placeholder="Tự tạo"><input class="form-control" name="criterion_name[]" placeholder="Nội dung chấm" required><input class="form-control" type="number" min=".01" step=".01" name="criterion_points[]" value="1" required><select class="form-select" name="criterion_active[]"><option value="1">Hiển thị</option><option value="0">Ẩn</option></select><button class="btn btn-outline-danger" type="button" onclick="this.closest(\'.setting-row\').remove()"><i class="bi bi-trash"></i></button>';document.getElementById('settingRows').appendChild(r)}
</script></body></html>
