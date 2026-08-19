<?php
/**
 * App shell chung cho module Nội trú.
 * Desktop: sidebar cố định bên trái.
 * Mobile: thanh điều hướng cuộn ngang phía dưới.
 */
$ntPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$ntTab = $_GET['tab'] ?? '';
if (!isset($nt_sec)) {
    if ($ntPage === 'noitru_list.php' || $ntTab === 'boarders') $nt_sec = 'boarders';
    elseif ($ntPage === 'noitru_attendance.php' || $ntTab === 'attendance') $nt_sec = 'attendance';
    elseif ($ntTab === 'exits') $nt_sec = 'exits';
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
    'overview'   => [BASE_URL . 'noitru.php?tab=overview',       'bi-grid-1x2-fill',    'TỔNG QUAN',   'nt.tongquan'],
    'boarders'   => [BASE_URL . 'noitru_list.php',               'bi-people-fill',      'Danh sách',   'nt.danhsach'],
    'exits'      => [BASE_URL . 'noitru.php?tab=exits',          'bi-door-open-fill',   'Ra/vào KTX',  'nt.ravao'],
    'meals'      => [BASE_URL . 'noitru.php?tab=meals',          'bi-cup-hot-fill',     'Báo ăn',      'nt.baoan'],
    'meal_summary'=> [BASE_URL . 'noitru.php?tab=meal_summary',   'bi-clipboard-data-fill','Tổng hợp',   'nt.buaan.tonghop'],
    'rice'       => [BASE_URL . 'noitru.php?tab=rice',           'bi-box-seam-fill',    'Gạo',          'nt.gao'],
    'attendance' => [BASE_URL . 'noitru_attendance.php',         'bi-clipboard2-check-fill','Điểm danh','nt.diemdanh'],
    'duty'       => [BASE_URL . 'noitru.php?tab=duty',           'bi-calendar2-week-fill','Lịch trực',  'nt.lichtruc'],
    'duty_report'=> [BASE_URL . 'noitru.php?tab=duty_report',    'bi-file-earmark-text-fill','Biên bản trực','nt.lichtruc'],
    'health'     => [BASE_URL . 'noitru.php?tab=health',         'bi-heart-pulse-fill', 'Y TẾ',         'nt.yte'],
    'menu'       => [BASE_URL . 'noitru.php?tab=menu',           'bi-journal-text',     'Thực đơn',    'nt.thucdon'],
    'stats'      => [BASE_URL . 'noitru.php?tab=stats',          'bi-bar-chart-fill',   'THỐNG KÊ',    'nt.thongke'],
];
$ntItems = array_filter($ntItems, fn($item) => can_perm($item[3] ?? ''));
require_once __DIR__.'/module_switcher.php';
$ntGroups = [
    'boarding' => ['label'=>'Nội trú','icon'=>'bi-building-fill','items'=>['duty','duty_report','attendance','exits']],
    'meals' => ['label'=>'Bữa ăn','icon'=>'bi-basket2-fill','items'=>['meals','menu','meal_summary','rice']],
];
$ntInGroup = function ($group) use ($ntGroups, $nt_sec) { return in_array($nt_sec, $ntGroups[$group]['items'] ?? [], true); };
?>
<style id="ntMenuCaseFix">
.nt-side-parent{text-transform:uppercase!important}
.nt-side-nav a.nt-child{text-transform:none!important}
</style>
<aside class="nt-sidebar" aria-label="Điều hướng Nội trú">
  <a class="nt-brand" href="<?= e(BASE_URL . 'noitru.php') ?>">
    <span class="nt-brand-icon"><i class="bi bi-building-fill"></i></span>
    <span><strong>Quản lý Nội trú</strong><small><?= e(SCHOOL_SHORT) ?></small></span>
  </a>
  <nav class="nt-side-nav">
    <div class="nt-side-section-label">MỤC CHÍNH</div>
    <?php foreach (['overview','boarders'] as $key): if (!isset($ntItems[$key])) continue; $item=$ntItems[$key]; ?>
      <a href="<?= e($item[0]) ?>" class="nt-side-main <?= $nt_sec === $key ? 'active' : '' ?>"><i class="bi <?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span></a>
    <?php endforeach; ?>
    <?php foreach ($ntGroups as $groupKey=>$group): ?>
      <button type="button" class="nt-side-parent nt-side-main <?= $ntInGroup($groupKey)?'open':'' ?>" data-nt-group="<?= e($groupKey) ?>" aria-expanded="<?= $ntInGroup($groupKey)?'true':'false' ?>">
        <i class="bi <?= e($group['icon']) ?>"></i><span><?= e($group['label']) ?></span><i class="bi bi-chevron-down nt-chevron"></i>
      </button>
      <div class="nt-side-children <?= $ntInGroup($groupKey)?'open':'' ?>" data-nt-children="<?= e($groupKey) ?>">
        <?php foreach ($group['items'] as $key): if (!isset($ntItems[$key])) continue; $item=$ntItems[$key]; ?>
          <a href="<?= e($item[0]) ?>" class="nt-child <?= $nt_sec === $key ? 'active' : '' ?>"><i class="bi <?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php foreach (['health','stats'] as $key): if (!isset($ntItems[$key])) continue; $item=$ntItems[$key]; ?>
      <a href="<?= e($item[0]) ?>" class="nt-side-main <?= $nt_sec === $key ? 'active' : '' ?>"><i class="bi <?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="nt-side-footer">
    <a href="<?= e(BASE_URL) ?>"><i class="bi bi-house-door"></i><span>Hệ sinh thái</span></a>
    <a href="<?= e(BASE_URL . 'csdl.php') ?>"><i class="bi bi-database"></i><span>CSDL</span></a>
    <a href="<?= e(BASE_URL . 'logout.php') ?>"><i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span></a>
  </div>
</aside>
<nav class="nt-bottom-nav" aria-label="Điều hướng Nội trú trên điện thoại">
  <?php if (isset($ntItems['overview'])): ?><a href="<?= e($ntItems['overview'][0]) ?>" class="<?= $nt_sec==='overview'?'active':'' ?>"><i class="bi bi-grid-1x2-fill"></i><span>TỔNG QUAN</span></a><?php endif; ?>
  <?php if (isset($ntItems['boarders'])): ?><a href="<?= e($ntItems['boarders'][0]) ?>" class="<?= $nt_sec==='boarders'?'active':'' ?>"><i class="bi bi-people-fill"></i><span>Danh sách</span></a><?php endif; ?>
  <button type="button" class="<?= $ntInGroup('boarding')?'active':'' ?>" data-nt-sheet="boarding"><i class="bi bi-building-fill"></i><span>Nội trú</span></button>
  <button type="button" class="<?= $ntInGroup('meals')?'active':'' ?>" data-nt-sheet="meals"><i class="bi bi-basket2-fill"></i><span>Bữa ăn</span></button>
  <?php if (isset($ntItems['health'])): ?><a href="<?= e($ntItems['health'][0]) ?>" class="<?= $nt_sec==='health'?'active':'' ?>"><i class="bi bi-heart-pulse-fill"></i><span>Y tế</span></a><?php endif; ?>
  <?php if (isset($ntItems['stats'])): ?><a href="<?= e($ntItems['stats'][0]) ?>" class="<?= $nt_sec==='stats'?'active':'' ?>"><i class="bi bi-bar-chart-fill"></i><span>Thống kê</span></a><?php endif; ?>
</nav>
<div class="nt-sheet-backdrop" data-nt-close></div>
<?php $mobileSheets=['boarding'=>['title'=>'NỘI TRÚ','items'=>$ntGroups['boarding']['items']],'meals'=>['title'=>'BỮA ĂN','items'=>$ntGroups['meals']['items']]]; foreach ($mobileSheets as $sheetKey=>$sheet): ?>
<section class="nt-sheet" data-nt-panel="<?= e($sheetKey) ?>" aria-hidden="true">
  <div class="nt-sheet-head"><strong><?= e($sheet['title']) ?></strong><button type="button" data-nt-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button></div>
  <div class="nt-sheet-grid"><?php foreach ($sheet['items'] as $key): if (!isset($ntItems[$key])) continue; $item=$ntItems[$key]; ?><a href="<?= e($item[0]) ?>" class="<?= $nt_sec===$key?'active':'' ?>"><i class="bi <?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span></a><?php endforeach; ?></div>
</section>
<?php endforeach; ?>
<script>
document.addEventListener('click',function(e){var trigger=e.target.closest('[data-nt-sheet]'),close=e.target.closest('[data-nt-close]'),group=e.target.closest('[data-nt-group]');if(trigger){document.body.classList.add('nt-sheet-open');document.querySelectorAll('[data-nt-panel]').forEach(function(p){p.classList.toggle('open',p.dataset.ntPanel===trigger.dataset.ntSheet);p.setAttribute('aria-hidden',p.dataset.ntPanel===trigger.dataset.ntSheet?'false':'true')});}if(close){document.body.classList.remove('nt-sheet-open');document.querySelectorAll('[data-nt-panel]').forEach(function(p){p.classList.remove('open');p.setAttribute('aria-hidden','true')});}if(group){var key=group.dataset.ntGroup,panel=document.querySelector('[data-nt-children="'+key+'"]'),open=!group.classList.contains('open');group.classList.toggle('open',open);group.setAttribute('aria-expanded',open?'true':'false');if(panel)panel.classList.toggle('open',open);}});
</script>
<?php if ($ntPage === 'noitru_assign.php' && (($_GET['mode'] ?? $_POST['mode'] ?? 'rooms') === 'rooms')): ?>
<style>.nt-import-errors{border:1px solid #fecaca;background:#fff7f7;border-radius:12px;padding:.8rem;margin:.75rem 0 1rem}.nt-import-errors h6{color:#b91c1c}.nt-import-errors table{font-size:.78rem;min-width:1220px}.nt-import-errors thead th{background:#fee2e2;color:#7f1d1d;white-space:nowrap}.nt-import-errors td{vertical-align:top}.nt-import-errors .err-text{color:#991b1b;font-weight:600;min-width:220px}.nt-import-errors .excel-cell{background:#fff}.nt-import-errors .db-cell{background:#ecfdf5;color:#166534}.nt-import-errors .diff-cell{background:#fff7ed!important;color:#9a3412;font-weight:700}.nt-import-errors .copy-cell{white-space:nowrap}.nt-import-errors .copy-mini{padding:.1rem .32rem;font-size:.68rem}</style>
<script>
document.addEventListener('DOMContentLoaded',function(){var endpoint='<?= e(BASE_URL . 'noitru_assign_enhanced.php') ?>',forms=Array.from(document.querySelectorAll('form')),createForm=forms.find(function(f){var a=f.querySelector('input[name="action"]');return a&&a.value==='create_groups';}),importForm=forms.find(function(f){var a=f.querySelector('input[name="action"]');return a&&a.value==='import_rooms_excel';});if(createForm){createForm.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(createForm);fd.set('action','create_groups_append');var btn=createForm.querySelector('button[type="submit"],button:not([type])');if(btn){btn.disabled=true;btn.dataset.oldText=btn.innerHTML;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Đang thêm...';}fetch(endpoint,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(data){alert(data.message||'Đã xử lý.');if(data.ok)location.reload();else if(btn){btn.disabled=false;btn.innerHTML=btn.dataset.oldText||'Tạo danh sách';}}).catch(function(){alert('Không tạo được danh sách phòng.');if(btn){btn.disabled=false;btn.innerHTML=btn.dataset.oldText||'Tạo danh sách';}});});}}
});
</script>
<script src="<?= e(BASE_URL . 'assets/noitru_assign_resolve.js?v=20260819-3') ?>"></script>
<script src="<?= e(BASE_URL . 'assets/noitru_room_compact.js?v=20260819-4') ?>"></script>
<?php endif; ?>
<?php if ($ntPage === 'noitru_list.php' && (($_GET['view'] ?? '') === 'rooms') && trim((string)($_GET['room'] ?? '')) !== ''): ?>
<script src="<?= e(BASE_URL . 'assets/noitru_room_list_roles.js?v=20260819-1') ?>"></script>
<?php endif; ?>