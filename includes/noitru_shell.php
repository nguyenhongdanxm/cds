<?php
/** App shell chung cho module Nội trú. */
$ntPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$ntTab = $_GET['tab'] ?? '';
if (!isset($nt_sec)) {
    if ($ntPage === 'noitru_list.php' || $ntTab === 'boarders') $nt_sec = 'boarders';
    elseif ($ntPage === 'noitru_attendance.php' || $ntTab === 'attendance') $nt_sec = 'attendance';
    elseif ($ntPage === 'noitru_exit_manager.php' || $ntTab === 'exits') $nt_sec = 'exits';
    elseif ($ntTab === 'meals') $nt_sec = 'meals';
    elseif ($ntTab === 'meal_summary') $nt_sec = 'meal_summary';
    elseif ($ntTab === 'rice') $nt_sec = 'rice';
    elseif ($ntTab === 'duty') $nt_sec = 'duty';
    elseif ($ntTab === 'duty_report') $nt_sec = 'duty_report';
    elseif ($ntTab === 'health') $nt_sec = 'health';
    elseif ($ntTab === 'menu') $nt_sec = 'menu';
    elseif ($ntTab === 'stats') $nt_sec = 'stats';
    else $nt_sec = 'overview';
}
$ntItems = [
    'overview'=>[BASE_URL.'noitru.php?tab=overview','bi-grid-1x2-fill','TỔNG QUAN','nt.tongquan'],
    'boarders'=>[BASE_URL.'noitru_list.php','bi-people-fill','DANH SÁCH','nt.danhsach'],
    'exits'=>[BASE_URL.'noitru_exit_manager.php','bi-door-open-fill','Ra/vào KTX','nt.ravao'],
    'meals'=>[BASE_URL.'noitru.php?tab=meals','bi-cup-hot-fill','Báo ăn','nt.baoan'],
    'meal_summary'=>[BASE_URL.'noitru.php?tab=meal_summary','bi-clipboard-data-fill','Tổng hợp','nt.buaan.tonghop'],
    'rice'=>[BASE_URL.'noitru.php?tab=rice','bi-box-seam-fill','Gạo','nt.gao'],
    'attendance'=>[BASE_URL.'noitru_attendance.php','bi-clipboard2-check-fill','Điểm danh','nt.diemdanh'],
    'duty'=>[BASE_URL.'noitru.php?tab=duty','bi-calendar2-week-fill','Lịch trực','nt.lichtruc'],
    'duty_report'=>[BASE_URL.'noitru.php?tab=duty_report','bi-file-earmark-text-fill','Biên bản trực','nt.lichtruc'],
    'health'=>[BASE_URL.'noitru.php?tab=health','bi-heart-pulse-fill','Y TẾ','nt.yte'],
    'menu'=>[BASE_URL.'noitru.php?tab=menu','bi-journal-text','Thực đơn','nt.thucdon'],
    'stats'=>[BASE_URL.'noitru.php?tab=stats','bi-bar-chart-fill','THỐNG KÊ','nt.thongke'],
];
$ntItems=array_filter($ntItems,fn($item)=>can_perm($item[3]??''));
require_once __DIR__.'/module_switcher.php';
$ntGroups=['boarding'=>['label'=>'Nội trú','icon'=>'bi-building-fill','items'=>['duty','duty_report','attendance','exits']],'meals'=>['label'=>'Bữa ăn','icon'=>'bi-basket2-fill','items'=>['meals','menu','meal_summary','rice']]];
$ntInGroup=function($group)use($ntGroups,$nt_sec){return in_array($nt_sec,$ntGroups[$group]['items']??[],true);};
?>
<style id="ntMenuCaseFix">.nt-side-parent{text-transform:uppercase!important}.nt-side-nav a.nt-child{text-transform:none!important}</style>
<aside class="nt-sidebar" aria-label="Điều hướng Nội trú"><a class="nt-brand" href="<?=e(BASE_URL.'noitru.php')?>"><span class="nt-brand-icon"><i class="bi bi-building-fill"></i></span><span><strong>Quản lý Nội trú</strong><small><?=e(SCHOOL_SHORT)?></small></span></a><nav class="nt-side-nav"><div class="nt-side-section-label">MỤC CHÍNH</div>
<?php foreach(['overview','boarders'] as $key):if(!isset($ntItems[$key]))continue;$item=$ntItems[$key];?><a href="<?=e($item[0])?>" class="nt-side-main <?=$nt_sec===$key?'active':''?>"><i class="bi <?=e($item[1])?>"></i><span><?=e($item[2])?></span></a><?php endforeach;?>
<?php foreach($ntGroups as $groupKey=>$group):?><button type="button" class="nt-side-parent nt-side-main <?=$ntInGroup($groupKey)?'open':''?>" data-nt-group="<?=e($groupKey)?>" aria-expanded="<?=$ntInGroup($groupKey)?'true':'false'?>"><i class="bi <?=e($group['icon'])?>"></i><span><?=e($group['label'])?></span><i class="bi bi-chevron-down nt-chevron"></i></button><div class="nt-side-children <?=$ntInGroup($groupKey)?'open':''?>" data-nt-children="<?=e($groupKey)?>"><?php foreach($group['items'] as $key):if(!isset($ntItems[$key]))continue;$item=$ntItems[$key];?><a href="<?=e($item[0])?>" class="nt-child <?=$nt_sec===$key?'active':''?>"><i class="bi <?=e($item[1])?>"></i><span><?=e($item[2])?></span></a><?php endforeach;?></div><?php endforeach;?>
<?php foreach(['health','stats'] as $key):if(!isset($ntItems[$key]))continue;$item=$ntItems[$key];?><a href="<?=e($item[0])?>" class="nt-side-main <?=$nt_sec===$key?'active':''?>"><i class="bi <?=e($item[1])?>"></i><span><?=e($item[2])?></span></a><?php endforeach;?></nav><div class="nt-side-footer"><a href="<?=e(BASE_URL)?>"><i class="bi bi-house-door"></i><span>Hệ sinh thái</span></a><a href="<?=e(BASE_URL.'csdl.php')?>"><i class="bi bi-database"></i><span>CSDL</span></a><a href="<?=e(BASE_URL.'logout.php')?>"><i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span></a></div></aside>
<nav class="nt-bottom-nav"><?php if(isset($ntItems['overview'])):?><a href="<?=e($ntItems['overview'][0])?>" class="<?=$nt_sec==='overview'?'active':''?>"><i class="bi bi-grid-1x2-fill"></i><span>TỔNG QUAN</span></a><?php endif;?><?php if(isset($ntItems['boarders'])):?><a href="<?=e($ntItems['boarders'][0])?>" class="<?=$nt_sec==='boarders'?'active':''?>"><i class="bi bi-people-fill"></i><span>DANH SÁCH</span></a><?php endif;?><button type="button" class="<?=$ntInGroup('boarding')?'active':''?>" data-nt-sheet="boarding"><i class="bi bi-building-fill"></i><span>Nội trú</span></button><button type="button" class="<?=$ntInGroup('meals')?'active':''?>" data-nt-sheet="meals"><i class="bi bi-basket2-fill"></i><span>Bữa ăn</span></button><?php if(isset($ntItems['health'])):?><a href="<?=e($ntItems['health'][0])?>" class="<?=$nt_sec==='health'?'active':''?>"><i class="bi bi-heart-pulse-fill"></i><span>Y tế</span></a><?php endif;?></nav>
<div class="nt-sheet-backdrop" data-nt-close></div><?php $mobileSheets=['boarding'=>['title'=>'NỘI TRÚ','items'=>$ntGroups['boarding']['items']],'meals'=>['title'=>'BỮA ĂN','items'=>$ntGroups['meals']['items']]];foreach($mobileSheets as $sheetKey=>$sheet):?><section class="nt-sheet" data-nt-panel="<?=e($sheetKey)?>"><div class="nt-sheet-head"><strong><?=e($sheet['title'])?></strong><button type="button" data-nt-close><i class="bi bi-x-lg"></i></button></div><div class="nt-sheet-grid"><?php foreach($sheet['items'] as $key):if(!isset($ntItems[$key]))continue;$item=$ntItems[$key];?><a href="<?=e($item[0])?>" class="<?=$nt_sec===$key?'active':''?>"><i class="bi <?=e($item[1])?>"></i><span><?=e($item[2])?></span></a><?php endforeach;?></div></section><?php endforeach;?>
<script>document.addEventListener('click',function(e){var trigger=e.target.closest('[data-nt-sheet]'),close=e.target.closest('[data-nt-close]'),group=e.target.closest('[data-nt-group]');if(trigger){document.body.classList.add('nt-sheet-open');document.querySelectorAll('[data-nt-panel]').forEach(function(p){p.classList.toggle('open',p.dataset.ntPanel===trigger.dataset.ntSheet)})}if(close){document.body.classList.remove('nt-sheet-open');document.querySelectorAll('[data-nt-panel]').forEach(p=>p.classList.remove('open'))}if(group){var key=group.dataset.ntGroup,panel=document.querySelector('[data-nt-children="'+key+'"]'),open=!group.classList.contains('open');group.classList.toggle('open',open);if(panel)panel.classList.toggle('open',open)}});</script>
<?php if ($ntPage === 'noitru.php' && $nt_sec === 'overview'): ?><script src="<?=e(BASE_URL.'assets/noitru-overview-sync.js?v=20260824-1')?>" defer></script><?php endif; ?>
<?php if ($ntPage === 'noitru.php' && $ntTab === 'meal_summary'): ?>
<script>window.BASE_URL=<?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= e(BASE_URL . 'assets/noitru_meal_quantity_image.js?v=20260825-5') ?>" defer></script>
<script src="<?= e(BASE_URL . 'assets/noitru_meal_report_layout_v2.js?v=20260825-3') ?>" defer></script>
<?php endif; ?>
<?php if ($ntPage === 'noitru_assign.php' && (($_GET['mode'] ?? '') === 'meals')): ?><script src="<?= e(BASE_URL . 'assets/noitru_meal_assign_rules.js?v=20260824-1') ?>"></script><?php endif; ?>
<?php if ($ntPage === 'noitru_attendance.php'): ?>
<?php $ntAttRoomMap=[]; if(isset($boarders)&&is_array($boarders)){foreach($boarders as $ntAttStudent){$ntAttSid=(string)($ntAttStudent['id']??'');if($ntAttSid!=='')$ntAttRoomMap[$ntAttSid]=trim((string)($ntAttStudent['room_ktx']??''));}} ?>
<script>window.NT_ATT_ROOM_MAP=<?= json_encode($ntAttRoomMap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;window.NT_ATT_REPORT_SCHOOL=<?= json_encode($school??SCHOOL_NAME,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;window.NT_ATT_REPORT_SHIFT=<?= json_encode(function_exists('mb_strtoupper')?mb_strtoupper((string)($shiftLabel??''),'UTF-8'):strtoupper((string)($shiftLabel??'')),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;window.NT_ATT_REPORT_DATE=<?= json_encode((string)($weekdayVi??'').', ngày '.(string)($dateLabel??''),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;window.NT_ATT_REPORT_REPORTER=<?= json_encode((string)($reporter??''),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(BASE_URL . 'assets/noitru_att_report_layout.js?v=20260903-4') ?>" defer></script>
<script src="<?= e(BASE_URL . 'assets/noitru_att_reason_v2.js?v=20260903-4') ?>" defer></script>
<script src="<?= e(BASE_URL . 'assets/noitru_att_absence_groups.js?v=20260903-4') ?>" defer></script>
<script src="<?= e(BASE_URL . 'assets/noitru_meal_attendance_only.js?v=20260903-1') ?>" defer></script>
<?php endif; ?>
<?php if ($ntPage === 'noitru.php' && $ntTab === 'duty_report'): ?><script src="<?= e(BASE_URL . 'assets/noitru_duty_report_enhancements.js?v=20260903-4') ?>" defer></script><?php endif; ?>
<?php if ($ntPage === 'noitru.php' && $ntTab === 'health' && (($_GET['health_view'] ?? 'record') === 'inventory')): ?><script>window.BASE_URL=<?= json_encode(BASE_URL,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;</script><script src="<?= e(BASE_URL . 'assets/noitru_medicine_excel.js?v=20260828-2') ?>" defer></script><?php endif; ?>
