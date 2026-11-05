<?php
/**
 * Bố cục điều hướng Chuyên môn thống nhất với các module CDS:
 * - Desktop: sidebar cố định bên trái.
 * - Điện thoại: thanh menu dưới đáy + offcanvas danh mục đầy đủ.
 *
 * File này được chép vào chuyenmon/includes khi deploy và nạp ngay sau <body>.
 */
if (!isset($current)) return;

$cmLayoutLogged = isset($logged) ? (bool)$logged : false;
$cmLayoutTab = $current === 'danhgia' ? (string)($_GET['view'] ?? 'overview') : (string)($tab_q ?? ($_GET['tab'] ?? ''));
$cmLayoutCan = static function (string $permission) use ($cmLayoutLogged): bool {
    if (!$cmLayoutLogged) return in_array($permission, ['cm.tracuu'], true);
    if ($permission === 'cm.support') return true;
    if ($permission === 'cm.home') { $u=function_exists('cds_user')?(cds_user()??[]):[]; return ($u['role']??'')==='admin' || (function_exists('cds_user_has_group')&&cds_user_has_group($u,'gvcn')) || !empty($u['homeroom_classes']); }
    return function_exists('cds_can_feature') ? cds_can_feature($permission, 'view') : true;
};
if($current==='activities'&&$cmLayoutTab==='')$cmLayoutTab='clubs';
if($current==='kehoach'&&$cmLayoutTab==='')$cmLayoutTab='vanban';
if($current==='baocao'&&$cmLayoutTab==='thang')$cmLayoutTab='dinhky';
$cmLayoutActive = static function (array $pages, ?string $tab = null) use ($current, $cmLayoutTab): bool {
    if (!in_array($current, $pages, true)) return false;
    return $tab === null || $cmLayoutTab === $tab || ($cmLayoutTab === '' && $tab === 'dinhky') || ($current === 'kehoach' && $tab === 'vanban' && $cmLayoutTab === 'chitieu');
};

$cmSidebarStartsCollapsed = $current === 'sodaubai';

$cmNavGroups = [
    ['label'=>'Tổng quan','direct'=>true,'icon'=>'bi-speedometer2','items'=>[
        ['permission'=>'cm.support','pages'=>['index'],'href'=>'index.php','icon'=>'bi-speedometer2','label'=>'Tổng quan'],
    ]],
    ['label'=>'Giảng dạy','icon'=>'bi-journal-bookmark','items'=>[
        ['permission'=>'cm.dashboard','pages'=>['sodaubai'],'href'=>'sodaubai.php','icon'=>'bi-journal-check','label'=>'Sổ đầu bài'],
        ['permission'=>'cm.tracuu','pages'=>['thoikhoabieu'],'href'=>'thoikhoabieu.php','icon'=>'bi-calendar3','label'=>'Thời khóa biểu'],
        ['permission'=>'cm.tracuu','pages'=>['tracuu'],'href'=>'tracuu.php','icon'=>'bi-search','label'=>'Tra cứu phân công'],
        ['permission'=>'cm.baocao.tiendo','pages'=>['baocao'],'tab'=>'tiendo','href'=>'baocao.php?tab=tiendo','icon'=>'bi-graph-up-arrow','label'=>'Tiến độ chương trình'],
    ]],
    ['label'=>'Học tập','icon'=>'bi-mortarboard','items'=>[
        ['permission'=>'cm.support','pages'=>['boiduong'],'href'=>'boiduong.php','icon'=>'bi-mortarboard','label'=>'Bồi dưỡng học sinh'],
        ['permission'=>'cm.kehoach','pages'=>['activities'],'tab'=>'clubs','href'=>'activities.php?tab=clubs&view=list','icon'=>'bi-people-fill','label'=>'Các câu lạc bộ'],
        ['permission'=>'cm.kehoach','pages'=>['activities'],'tab'=>'online','href'=>'activities.php?tab=online&view=students','icon'=>'bi-laptop','label'=>'Học Online'],
    ]],
    ['label'=>'Đánh giá','icon'=>'bi-clipboard2-check','items'=>[
        ['permission'=>'cm.baocao.dugio','pages'=>['dugio'],'href'=>'dugio.php','icon'=>'bi-eye','label'=>'Dự giờ'],
        ['permission'=>'cm.baocao.kythi','pages'=>['kiemtrahoso'],'href'=>'kiemtrahoso.php','icon'=>'bi-folder-check','label'=>'Kiểm tra chuyên môn'],
    ]],
    ['label'=>'Phân công chuyên môn','short_label'=>'Phân công','icon'=>'bi-diagram-3','items'=>[
        ['permission'=>'cm.nhaplieu','pages'=>['monhoc','lop','giaovien','kiemnhiem'],'href'=>'monhoc.php','icon'=>'bi-database','label'=>'Nhập liệu'],
        ['permission'=>'cm.pccm','pages'=>['them','danhsach','doicheo','rasoat','sua','tongquan'],'href'=>'them.php','icon'=>'bi-pencil-square','label'=>'Phân công'],
        ['permission'=>$cmLayoutLogged&&$cmLayoutCan('cm.tracuu')?'cm.tracuu':'cm.thongke','pages'=>['ketqua','thongke','xuat_bang'],'href'=>$cmLayoutLogged&&$cmLayoutCan('cm.tracuu')?'ketqua.php':'thongke.php','icon'=>'bi-folder2-open','label'=>'Kết quả phiên bản'],
    ]],
    ['label'=>'Báo cáo','icon'=>'bi-file-earmark-text','items'=>[
        ['permission'=>'cm.kehoach','pages'=>['kehoach'],'tab'=>'vanban','href'=>'kehoach.php?tab=vanban','icon'=>'bi-file-earmark-check','label'=>'Kế hoạch giáo dục'],
        ['permission'=>'cm.baocao.dinhky','pages'=>['baocao'],'tab'=>'dinhky','href'=>'baocao.php?tab=dinhky','icon'=>'bi-calendar-check','label'=>'Báo cáo định kỳ'],
    ]],
];

/*
 * Một chức năng chỉ xuất hiện một lần trong sidebar. Dữ liệu cũ có thể khai
 * báo cùng đường dẫn ở nhiều nhóm (ví dụ hồ sơ đánh giá); giữ vị trí đầu tiên.
 */
$cmVisibleNavGroups = [];
$cmSeenTargets = [];
$cmSeenLabels = [];
foreach ($cmNavGroups as $groupIndex=>$group) {
    $visibleItems = [];
    foreach ($group['items'] as $item) {
        if (!$cmLayoutCan($item['permission'])) continue;
        $target = strtolower(trim((string)($item['href'] ?? '')));
        $target = preg_replace('~/+~', '/', $target);
        $labelKey = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string)($item['label'] ?? '')), 'UTF-8')
            : strtolower(trim((string)($item['label'] ?? '')));
        $labelKey = preg_replace('/[^\pL\pN]+/u', '', $labelKey);
        if (($target !== '' && isset($cmSeenTargets[$target])) || ($labelKey !== '' && isset($cmSeenLabels[$labelKey]))) continue;
        if ($target !== '') $cmSeenTargets[$target] = true;
        if ($labelKey !== '') $cmSeenLabels[$labelKey] = true;
        $visibleItems[] = $item;
    }
    if (!$visibleItems) continue;
    $group['items'] = $visibleItems;
    $group['id'] = 'cmGroup' . $groupIndex;
    $group['icon'] = $group['icon'] ?? 'bi-folder2';
    $group['open'] = false;
    foreach ($visibleItems as $item) {
        if ($cmLayoutActive($item['pages'], $item['tab'] ?? null)) { $group['open'] = true; break; }
    }
    $cmVisibleNavGroups[] = $group;
}
?>
<style id="cdsCmResponsiveLayout">
:root{--cm-sidebar-width:244px;--cm-nav-blue:#173f65;--cm-nav-blue-2:#245f91;--cm-mobile-nav-height:68px}
.cm-desktop-sidebar{position:fixed;inset:0 auto 0 0;width:var(--cm-sidebar-width);z-index:1040;display:flex;flex-direction:column;background:linear-gradient(180deg,var(--cm-nav-blue),#102f4d);color:#fff;box-shadow:8px 0 28px rgba(15,23,42,.13)}
.cm-sidebar-brand{display:flex;align-items:center;gap:.7rem;padding:.85rem .85rem .78rem;color:#fff;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.12)}
.cm-sidebar-brand .cm-logo{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,.13);font-size:1.1rem}
.cm-sidebar-brand strong{display:block;font-size:.94rem}.cm-sidebar-brand small{display:block;color:#bfdbfe;font-size:.68rem;margin-top:.08rem}
.cm-sidebar-home{margin:.3rem 0 .5rem;min-height:44px;padding:.65rem .8rem;border:1px solid #ffffff20;border-radius:12px;font-size:.82rem}.cm-sidebar-home>i{width:28px;font-size:1.1rem}.cm-sidebar-scroll{flex:1;overflow-y:auto;padding:.55rem .55rem .8rem;scrollbar-width:thin}
.cm-sidebar-group{margin:.28rem 0;border:1px solid rgba(255,255,255,.09);border-radius:12px;background:rgba(255,255,255,.025);overflow:hidden}
.cm-sidebar-group.open{background:rgba(255,255,255,.055);border-color:rgba(147,197,253,.25)}
.cm-sidebar-group-toggle{display:flex;align-items:center;width:100%;min-height:42px;padding:.52rem .65rem;border:0;background:transparent;color:#dbeafe;text-align:left;font-size:.76rem;font-weight:800;letter-spacing:.025em;text-transform:uppercase;cursor:pointer}
.cm-sidebar-group-toggle>i:first-child{display:grid;place-items:center;width:28px;height:28px;margin-right:.55rem;border-radius:9px;background:rgba(255,255,255,.09);font-size:.9rem}
.cm-sidebar-group-toggle span{flex:1}.cm-sidebar-chevron{font-size:.72rem;transition:transform .18s ease}.cm-sidebar-group.open .cm-sidebar-chevron{transform:rotate(180deg)}
.cm-sidebar-items{display:grid;grid-template-rows:0fr;transition:grid-template-rows .2s ease}.cm-sidebar-items>div{min-height:0;overflow:hidden;padding:0 .35rem}.cm-sidebar-group.open .cm-sidebar-items{grid-template-rows:1fr}.cm-sidebar-group.open .cm-sidebar-items>div{padding-bottom:.38rem}
.cm-sidebar-link{display:flex;align-items:center;gap:.62rem;min-height:37px;margin:.1rem 0;padding:.43rem .58rem;border-radius:9px;color:#e8f3fb;text-decoration:none;font-size:.78rem;font-weight:650;transition:.16s ease}
.cm-sidebar-link i{width:19px;text-align:center;font-size:1rem}.cm-sidebar-link:hover{background:rgba(255,255,255,.1);color:#fff;transform:translateX(2px)}
.cm-sidebar-link.active{background:#fff;color:var(--cm-nav-blue);box-shadow:0 5px 16px rgba(0,0,0,.16)}
.cm-sidebar-link.disabled{opacity:.35;pointer-events:none}
.cm-sidebar-footer{padding:.58rem;border-top:1px solid rgba(255,255,255,.12)}.cm-sidebar-footer .btn{border-radius:10px;font-size:.76rem}
.cm-sidebar-collapse-toggle{position:absolute;top:50%;right:-15px;z-index:2;display:grid;place-items:center;width:30px;height:44px;padding:0;border:2px solid #fff;border-radius:0 12px 12px 0;background:#1f5d8d;color:#fff;box-shadow:4px 2px 12px rgba(15,23,42,.2);transition:.2s ease}
.cm-sidebar-collapse-toggle:hover{background:#287bb6}.cm-sidebar-collapse-toggle i{transition:transform .2s ease}
.cm-mobile-bottom,.cm-mobile-more{display:none}
@media(min-width:992px){
 body{padding-left:var(--cm-sidebar-width)}
 body.cm-sidebar-collapsed{padding-left:64px}
 .cm-desktop-sidebar,.cm-desktop-sidebar *{transition-duration:.2s;transition-property:width,padding,margin,opacity,transform}
 .cm-desktop-sidebar.is-collapsed{width:64px}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-brand{justify-content:center;padding-left:.35rem;padding-right:.35rem}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-home span,.cm-desktop-sidebar.is-collapsed .cm-sidebar-brand>span:last-child,.cm-desktop-sidebar.is-collapsed .cm-sidebar-group-toggle span,.cm-desktop-sidebar.is-collapsed .cm-sidebar-chevron,.cm-desktop-sidebar.is-collapsed .cm-sidebar-items,.cm-desktop-sidebar.is-collapsed .cm-sidebar-footer{display:none!important}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-scroll{padding:.55rem .35rem}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-home{justify-content:center;padding:.6rem 0;border-color:transparent}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-group{border-color:transparent;background:transparent}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-group-toggle{justify-content:center;padding:.42rem .2rem}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-group-toggle>i:first-child{margin:0;width:38px;height:38px;font-size:1.05rem}
 .cm-desktop-sidebar.is-collapsed .cm-sidebar-collapse-toggle i{transform:rotate(180deg)}
 body>nav.navbar{display:none!important}
 body>.container{max-width:none!important;width:auto!important;margin:0!important;padding-left:1.4rem!important;padding-right:1.4rem!important;padding-top:1.2rem!important}
 .pccm-toast{left:auto}
}
@media(max-width:991.98px){
 body{padding-bottom:calc(var(--cm-mobile-nav-height) + env(safe-area-inset-bottom) + 12px)}
 body>nav.navbar{display:none!important}
 body>.container{padding-top:.85rem!important;padding-left:.75rem!important;padding-right:.75rem!important}
 .cm-desktop-sidebar{display:none}
 .cm-mobile-bottom{position:fixed;left:0;right:0;bottom:0;z-index:1050;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));height:calc(var(--cm-mobile-nav-height) + env(safe-area-inset-bottom));padding:7px 6px calc(7px + env(safe-area-inset-bottom));border-top:1px solid #dce6ee;background:rgba(255,255,255,.97);box-shadow:0 -8px 24px rgba(15,23,42,.12);backdrop-filter:blur(15px)}
 .cm-mobile-bottom a,.cm-mobile-bottom button{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-width:0;border:0;background:transparent;color:#64748b;text-decoration:none;font-size:.65rem;font-weight:750;line-height:1.1}
 .cm-mobile-bottom i{font-size:1.25rem}.cm-mobile-bottom .active{color:#1f5d8d}.cm-mobile-bottom .disabled{opacity:.3;pointer-events:none}
 .cm-mobile-more{display:block}.cm-mobile-more .offcanvas-header{background:linear-gradient(135deg,var(--cm-nav-blue),var(--cm-nav-blue-2));color:#fff}.cm-mobile-more .btn-close{filter:invert(1)}
 .cm-mobile-group{margin-bottom:0}.cm-mobile-group-title{margin:0 0 .45rem;color:#64748b;font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
 .cm-mobile-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.cm-mobile-link{display:flex;align-items:center;gap:.62rem;min-height:50px;padding:.65rem;border:1px solid #e2e8f0;border-radius:13px;background:#fff;color:#334155;text-decoration:none;font-size:.78rem;font-weight:700}.cm-mobile-link i{color:#1f5d8d;font-size:1.05rem}.cm-mobile-link.active{border-color:#60a5fa;background:#eff6ff;color:#174b73}.cm-mobile-link.disabled{opacity:.35;pointer-events:none}
 .cm-mobile-more.offcanvas-bottom{display:flex;flex-direction:column;height:auto;max-height:min(70vh,520px);max-height:min(70dvh,520px);bottom:calc(var(--cm-mobile-nav-height) + env(safe-area-inset-bottom));border-radius:16px 16px 0 0;border:0;box-shadow:0 -8px 28px #0f274526}
 .cm-mobile-more .offcanvas-header{padding:8px 14px;min-height:52px;flex-shrink:0}.cm-mobile-more .offcanvas-title{font-size:1rem;font-weight:700;line-height:1.3}.cm-mobile-more .offcanvas-header .btn-close{width:44px;height:44px;padding:12px;margin:-4px -6px -4px 8px;flex-shrink:0;box-sizing:border-box;background-size:13px}
 .cm-mobile-more .offcanvas-body{flex:0 1 auto;min-height:0;padding:12px;overflow-y:auto;overscroll-behavior:contain}.cm-mobile-more .cm-mobile-grid{gap:8px}.cm-mobile-more .cm-mobile-link{min-height:46px;padding:10px;border-radius:9px;gap:8px;font-size:.8rem;line-height:1.35;font-weight:600}.cm-mobile-more .cm-mobile-link i{font-size:1.05rem;flex-shrink:0}.cm-mobile-more .cm-mobile-link span{min-width:0;overflow-wrap:anywhere}
 .cm-mobile-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding-top:10px;margin-top:10px;border-top:1px solid #e2e8f0}.cm-mobile-more .cm-mobile-actions .btn{display:flex;align-items:center;justify-content:center;min-height:44px;padding:7px 8px;font-size:.75rem;line-height:1.3;border-radius:9px}.cm-mobile-menu-open .cds-launcher{visibility:hidden;pointer-events:none}
 .pccm-toast{bottom:calc(var(--cm-mobile-nav-height) + 16px)!important}
}
</style>

<style>.cm-sidebar-link.disabled,.cm-mobile-bottom .disabled,.cm-mobile-link.disabled{display:none!important}</style>
<?php if ($cmSidebarStartsCollapsed): ?><script>document.body.classList.add('cm-sidebar-collapsed')</script><?php endif; ?>
<aside class="cm-desktop-sidebar <?= $cmSidebarStartsCollapsed?'is-collapsed':'' ?>" aria-label="Điều hướng Chuyên môn">
  <button class="cm-sidebar-collapse-toggle" type="button" aria-label="<?= $cmSidebarStartsCollapsed?'Mở menu':'Thu gọn menu' ?>" title="<?= $cmSidebarStartsCollapsed?'Mở menu':'Thu gọn menu' ?>" aria-expanded="<?= $cmSidebarStartsCollapsed?'false':'true' ?>"><i class="bi bi-chevron-left"></i></button>
  <a class="cm-sidebar-brand" href="<?= BASE_URL ?>index.php">
    <span class="cm-logo"><i class="bi bi-journal-bookmark-fill"></i></span>
    <span><strong>Chuyên môn</strong><small>Cổng dữ liệu số CDS</small></span>
  </a>
  <div class="cm-sidebar-scroll">
    <?php foreach ($cmVisibleNavGroups as $group): ?>
      <?php if(!empty($group['direct'])):$home=$group['items'][0];?><a class="cm-sidebar-link cm-sidebar-home <?=$group['open']?'active':''?>" href="<?=BASE_URL.e($home['href'])?>" title="<?=e($group['label'])?>" <?=$group['open']?'aria-current="page"':''?>><i class="bi <?=e($group['icon'])?>"></i><span><?=e($group['label'])?></span></a><?php continue;endif;?>
      <section class="cm-sidebar-group <?= $group['open']?'open':'' ?>" data-cm-nav-group>
        <button type="button" class="cm-sidebar-group-toggle" aria-expanded="<?= $group['open']?'true':'false' ?>" aria-controls="<?= e($group['id']) ?>">
          <i class="bi <?= e($group['icon']) ?>"></i><span><?= e($group['label']) ?></span><i class="bi bi-chevron-down cm-sidebar-chevron"></i>
        </button>
        <div class="cm-sidebar-items" id="<?= e($group['id']) ?>" aria-hidden="<?=$group['open']?'false':'true'?>" <?=$group['open']?'':'inert'?>><div>
          <?php foreach ($group['items'] as $item):
            $allowed = $cmLayoutCan($item['permission']);
            $active = $cmLayoutActive($item['pages'], $item['tab'] ?? null);
          ?>
            <a class="cm-sidebar-link <?= $active?'active':'' ?> <?= !$allowed?'disabled':'' ?>" href="<?= BASE_URL . e($item['href']) ?>" <?= !$allowed?'aria-disabled="true" tabindex="-1"':'' ?>><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span></a>
          <?php endforeach; ?>
        </div></div>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="cm-sidebar-footer">
    <div class="d-grid gap-2">
      <a href="/" class="btn btn-outline-light btn-sm"><i class="bi bi-grid me-1"></i> Hệ sinh thái CDS</a>
      <?php if ($cmLayoutLogged): ?><a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm fw-semibold"><i class="bi bi-box-arrow-right me-1"></i> Đăng xuất</a><?php else: ?><a href="/login.php?next=<?= urlencode(BASE_URL . 'index.php') ?>" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập</a><?php endif; ?>
    </div>
  </div>
</aside>

<nav class="cm-mobile-bottom" aria-label="Menu Chuyên môn trên điện thoại" style="grid-template-columns:repeat(<?=max(1,count($cmVisibleNavGroups))?>,minmax(0,1fr))">
  <?php foreach ($cmVisibleNavGroups as $groupIndex=>$group):
    $groupAllowed = false;
    foreach ($group['items'] as $item) { if ($cmLayoutCan($item['permission'])) { $groupAllowed = true; break; } }
  ?>
    <?php if(!empty($group['direct'])):?><a href="<?=BASE_URL.e($group['items'][0]['href'])?>" class="<?=$group['open']?'active':''?>" <?=$group['open']?'aria-current="page"':''?>><i class="bi <?=e($group['icon'])?>"></i><span><?=e($group['label'])?></span></a><?php continue;endif;?>
    <button class="<?= $group['open']?'active':'' ?> <?= !$groupAllowed?'disabled':'' ?>" type="button" data-cm-mobile-parent="<?= (int)$groupIndex ?>" data-cm-mobile-label="<?= e($group['label']) ?>" data-bs-toggle="offcanvas" data-bs-target="#cmMobileMore" aria-controls="cmMobileMore"><i class="bi <?= e($group['icon']) ?>"></i><span><?= e($group['short_label']??$group['label']) ?></span></button>
  <?php endforeach; ?>
</nav>

<div class="offcanvas offcanvas-bottom cm-mobile-more" tabindex="-1" id="cmMobileMore" aria-labelledby="cmMobileMoreLabel" >
  <div class="offcanvas-header"><div><h5 class="offcanvas-title" id="cmMobileMoreLabel"><i class="bi bi-journal-bookmark-fill me-2"></i><span data-cm-mobile-title>Chuyên môn</span></h5></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button></div>
  <div class="offcanvas-body">
    <?php foreach ($cmVisibleNavGroups as $groupIndex=>$group): if(!empty($group['direct']))continue;?>
      <section class="cm-mobile-group" data-cm-mobile-group="<?= (int)$groupIndex ?>" hidden>
        <div class="cm-mobile-grid">
          <?php foreach ($group['items'] as $item):
            $allowed = $cmLayoutCan($item['permission']);
            $active = $cmLayoutActive($item['pages'], $item['tab'] ?? null);
          ?>
            <a class="cm-mobile-link <?= $active?'active':'' ?> <?= !$allowed?'disabled':'' ?>" href="<?= BASE_URL . e($item['href']) ?>" <?= !$allowed?'aria-disabled="true" tabindex="-1"':'' ?>><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="cm-mobile-actions">
      <a href="/" class="btn btn-outline-primary"><i class="bi bi-grid me-1"></i> Hệ sinh thái CDS</a>
      <?php if ($cmLayoutLogged): ?><a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i> Đăng xuất</a><?php endif; ?>
    </div>
  </div>
</div>
<script id="cdsCmSidebarGroups">
(function(){
  var sidebar=document.querySelector('.cm-desktop-sidebar'),collapseButton=document.querySelector('.cm-sidebar-collapse-toggle');
  function setSidebarCollapsed(collapsed){
    if(!sidebar)return;
    sidebar.classList.toggle('is-collapsed',collapsed);document.body.classList.toggle('cm-sidebar-collapsed',collapsed);
    if(collapseButton){collapseButton.setAttribute('aria-expanded',collapsed?'false':'true');collapseButton.setAttribute('aria-label',collapsed?'Mở menu':'Thu gọn menu');collapseButton.title=collapsed?'Mở menu':'Thu gọn menu'}
  }
  if(collapseButton)collapseButton.addEventListener('click',function(){setSidebarCollapsed(!sidebar.classList.contains('is-collapsed'))});
  document.querySelectorAll('[data-cm-nav-group]>.cm-sidebar-group-toggle').forEach(function(button){
    button.addEventListener('click',function(){
      if(sidebar&&sidebar.classList.contains('is-collapsed')){setSidebarCollapsed(false);return;}
      var group=button.closest('[data-cm-nav-group]'),open=!group.classList.contains('open');
      document.querySelectorAll('[data-cm-nav-group]').forEach(function(other){
        var keep=other===group&&open;other.classList.toggle('open',keep);
        var toggle=other.querySelector(':scope>.cm-sidebar-group-toggle');if(toggle)toggle.setAttribute('aria-expanded',keep?'true':'false');var items=other.querySelector('.cm-sidebar-items');if(items){items.inert=!keep;items.setAttribute('aria-hidden',keep?'false':'true');}
      });
    });
  });
  var mobileSheet=document.getElementById('cmMobileMore');if(mobileSheet){mobileSheet.addEventListener('show.bs.offcanvas',function(){document.body.classList.add('cm-mobile-menu-open')});mobileSheet.addEventListener('hidden.bs.offcanvas',function(){document.body.classList.remove('cm-mobile-menu-open')});}
  document.querySelectorAll('[data-cm-mobile-parent]').forEach(function(button){
    button.addEventListener('click',function(){
      var wanted=button.getAttribute('data-cm-mobile-parent');
      document.querySelectorAll('[data-cm-mobile-group]').forEach(function(section){section.hidden=section.getAttribute('data-cm-mobile-group')!==wanted});
      var title=document.querySelector('[data-cm-mobile-title]');if(title)title.textContent=button.getAttribute('data-cm-mobile-label')||'Chuyên môn';
    });
  });
})();
</script>
