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
$cmLayoutTab = (string)($tab_q ?? ($_GET['tab'] ?? ''));
$cmLayoutCan = static function (string $permission) use ($cmLayoutLogged): bool {
    if (!$cmLayoutLogged) return in_array($permission, ['cm.tracuu'], true);
    return function_exists('cds_can_feature') ? cds_can_feature($permission, 'view') : true;
};
$cmLayoutActive = static function (array $pages, ?string $tab = null) use ($current, $cmLayoutTab): bool {
    if (!in_array($current, $pages, true)) return false;
    return $tab === null || $cmLayoutTab === $tab || ($cmLayoutTab === '' && $tab === 'dinhky');
};

$cmPccmActive = in_array($current, ['tracuu','tongquan','them','danhsach','doicheo','rasoat','sua','ketqua','giaovien','monhoc','lop','kiemnhiem','xuat_bang','thongke'], true);
$cmPlanActive = $current === 'kehoach' || ($current === 'baocao' && in_array($cmLayoutTab,['dinhky','tiendo'],true));
$cmReportActive = in_array($current,['dugio','kiemtrahoso'],true);

$cmNavGroups = [
    ['label'=>'Tổng quan','items'=>[
        ['permission'=>'cm.dashboard','pages'=>['index'],'href'=>'index.php','icon'=>'bi-house-door','label'=>'Tổng quan & công việc'],
        ['permission'=>'cm.tracuu','pages'=>['tracuu'],'href'=>'tracuu.php','icon'=>'bi-search','label'=>'Tra cứu phân công'],
        ['permission'=>'cm.tracuu','pages'=>['ketqua'],'href'=>'ketqua.php','icon'=>'bi-folder2-open','label'=>'Kết quả phiên bản'],
        ['permission'=>'cm.baocao.dugio','pages'=>[],'href'=>'../danhgia.php?view=profile','icon'=>'bi-person-vcard','label'=>'Hồ sơ chuyên môn'],
    ]],
    ['label'=>'Phân công – Danh mục','items'=>[
        ['permission'=>'cm.pccm','pages'=>['tongquan'],'href'=>'tongquan.php','icon'=>'bi-grid','label'=>'Tổng quan PCCM'],
        ['permission'=>'cm.pccm','pages'=>['them','doicheo','rasoat','sua'],'href'=>'them.php','icon'=>'bi-pencil-square','label'=>'Phân công'],
        ['permission'=>'cm.pccm','pages'=>['danhsach'],'href'=>'danhsach.php','icon'=>'bi-list-ul','label'=>'Danh sách'],
        ['permission'=>'cm.nhaplieu','pages'=>['giaovien'],'href'=>'giaovien.php','icon'=>'bi-person-badge','label'=>'Giáo viên'],
        ['permission'=>'cm.nhaplieu','pages'=>['monhoc'],'href'=>'monhoc.php','icon'=>'bi-journal-text','label'=>'Môn học & số tiết'],
        ['permission'=>'cm.nhaplieu','pages'=>['lop'],'href'=>'lop.php','icon'=>'bi-people','label'=>'Lớp'],
        ['permission'=>'cm.nhaplieu','pages'=>['kiemnhiem'],'href'=>'kiemnhiem.php','icon'=>'bi-person-workspace','label'=>'Kiêm nhiệm & số tiết'],
        ['permission'=>'cm.thongke','pages'=>['thongke'],'href'=>'thongke.php','icon'=>'bi-bar-chart-line','label'=>'Thống kê PCCM'],
        ['permission'=>'cm.thongke','pages'=>['xuat_bang'],'href'=>'xuat_bang.php','icon'=>'bi-printer','label'=>'Xuất bảng'],
    ]],
    ['label'=>'Kế hoạch – Thực hiện','items'=>[
        ['permission'=>'cm.kehoach','pages'=>['kehoach'],'tab'=>'vanban','href'=>'kehoach.php?tab=vanban','icon'=>'bi-file-earmark-check','label'=>'Kế hoạch giáo dục'],
        ['permission'=>'cm.kehoach','pages'=>['kehoach'],'tab'=>'thongbao','href'=>'kehoach.php?tab=thongbao','icon'=>'bi-megaphone','label'=>'Thông báo'],
        ['permission'=>'cm.kehoach','pages'=>['kehoach'],'tab'=>'chitieu','href'=>'kehoach.php?tab=chitieu','icon'=>'bi-bullseye','label'=>'Chỉ tiêu'],
        ['permission'=>'cm.baocao.tiendo','pages'=>['baocao'],'tab'=>'tiendo','href'=>'baocao.php?tab=tiendo','icon'=>'bi-graph-up-arrow','label'=>'Tiến độ chương trình'],
        ['permission'=>'cm.baocao.dinhky','pages'=>['baocao'],'tab'=>'dinhky','href'=>'baocao.php?tab=dinhky','icon'=>'bi-calendar-check','label'=>'Báo cáo định kỳ'],
    ]],
    ['label'=>'Theo dõi – Đánh giá','items'=>[
        ['permission'=>'cm.baocao.dugio','pages'=>['dugio'],'href'=>'dugio.php','icon'=>'bi-eye','label'=>'Dự giờ'],
        ['permission'=>'cm.baocao.kythi','pages'=>['kiemtrahoso'],'href'=>'kiemtrahoso.php','icon'=>'bi-folder-check','label'=>'Kiểm tra'],
        ['permission'=>'cm.baocao.dugio','pages'=>[],'href'=>'../danhgia.php?view=profile','icon'=>'bi-person-lines-fill','label'=>'Hồ sơ đánh giá'],
        ['permission'=>'cm.baocao.dugio','pages'=>[],'href'=>'../danhgia.php?view=overview','icon'=>'bi-bar-chart-line','label'=>'Tổng hợp đánh giá'],
    ]],
];

/*
 * Một chức năng chỉ xuất hiện một lần trong sidebar. Dữ liệu cũ có thể khai
 * báo cùng đường dẫn ở nhiều nhóm (ví dụ hồ sơ đánh giá); giữ vị trí đầu tiên.
 */
$cmVisibleNavGroups = [];
$cmSeenTargets = [];
$cmSeenLabels = [];
$cmGroupIcons = ['bi-speedometer2','bi-diagram-3','bi-calendar2-week','bi-clipboard2-check'];
foreach ($cmNavGroups as $groupIndex=>$group) {
    $visibleItems = [];
    foreach ($group['items'] as $item) {
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
    $group['icon'] = $cmGroupIcons[$groupIndex] ?? 'bi-folder2';
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
.cm-sidebar-scroll{flex:1;overflow-y:auto;padding:.55rem .55rem .8rem;scrollbar-width:thin}
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
.cm-mobile-bottom,.cm-mobile-more{display:none}
@media(min-width:992px){
 body{padding-left:var(--cm-sidebar-width)}
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
 .cm-mobile-group{margin-bottom:1rem}.cm-mobile-group-title{margin:0 0 .45rem;color:#64748b;font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
 .cm-mobile-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.cm-mobile-link{display:flex;align-items:center;gap:.62rem;min-height:50px;padding:.65rem;border:1px solid #e2e8f0;border-radius:13px;background:#fff;color:#334155;text-decoration:none;font-size:.78rem;font-weight:700}.cm-mobile-link i{color:#1f5d8d;font-size:1.05rem}.cm-mobile-link.active{border-color:#60a5fa;background:#eff6ff;color:#174b73}.cm-mobile-link.disabled{opacity:.35;pointer-events:none}
 .pccm-toast{bottom:calc(var(--cm-mobile-nav-height) + 16px)!important}
}
</style>

<aside class="cm-desktop-sidebar" aria-label="Điều hướng Chuyên môn">
  <a class="cm-sidebar-brand" href="<?= BASE_URL ?>index.php">
    <span class="cm-logo"><i class="bi bi-journal-bookmark-fill"></i></span>
    <span><strong>Chuyên môn</strong><small>Cổng dữ liệu số CDS</small></span>
  </a>
  <div class="cm-sidebar-scroll">
    <?php foreach ($cmVisibleNavGroups as $group): ?>
      <section class="cm-sidebar-group <?= $group['open']?'open':'' ?>" data-cm-nav-group>
        <button type="button" class="cm-sidebar-group-toggle" aria-expanded="<?= $group['open']?'true':'false' ?>" aria-controls="<?= e($group['id']) ?>">
          <i class="bi <?= e($group['icon']) ?>"></i><span><?= e($group['label']) ?></span><i class="bi bi-chevron-down cm-sidebar-chevron"></i>
        </button>
        <div class="cm-sidebar-items" id="<?= e($group['id']) ?>"><div>
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

<nav class="cm-mobile-bottom" aria-label="Menu Chuyên môn trên điện thoại">
  <a class="<?= $current==='index'?'active':'' ?> <?= !$cmLayoutCan('cm.dashboard')?'disabled':'' ?>" href="<?= BASE_URL ?>index.php"><i class="bi bi-house-door"></i><span>Trang chủ</span></a>
  <a href="/danhgia.php?view=profile"><i class="bi bi-person-vcard"></i><span>Hồ sơ</span></a>
  <a class="<?= $cmPccmActive?'active':'' ?> <?= !$cmLayoutCan('cm.pccm')&&!$cmLayoutCan('cm.tracuu')?'disabled':'' ?>" href="<?= BASE_URL ?>tongquan.php"><i class="bi bi-clipboard-check"></i><span>PCCM</span></a>
  <a class="<?= $current==='dugio'?'active':'' ?> <?= !$cmLayoutCan('cm.baocao.dugio')?'disabled':'' ?>" href="<?= BASE_URL ?>dugio.php"><i class="bi bi-eye"></i><span>Dự giờ</span></a>
  <button class="<?= $cmReportActive?'active':'' ?>" type="button" data-bs-toggle="offcanvas" data-bs-target="#cmMobileMore" aria-controls="cmMobileMore"><i class="bi bi-grid"></i><span>Thêm</span></button>
</nav>

<div class="offcanvas offcanvas-bottom cm-mobile-more" tabindex="-1" id="cmMobileMore" aria-labelledby="cmMobileMoreLabel" style="height:min(82vh,720px);border-radius:22px 22px 0 0">
  <div class="offcanvas-header"><div><h5 class="offcanvas-title" id="cmMobileMoreLabel"><i class="bi bi-journal-bookmark-fill me-2"></i>Chuyên môn</h5><div class="small text-white-50">Chọn chức năng cần sử dụng</div></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button></div>
  <div class="offcanvas-body">
    <?php foreach ($cmVisibleNavGroups as $group): ?>
      <section class="cm-mobile-group">
        <h6 class="cm-mobile-group-title"><?= e($group['label']) ?></h6>
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
    <div class="d-grid gap-2 mt-3">
      <a href="/" class="btn btn-outline-primary"><i class="bi bi-grid me-1"></i> Hệ sinh thái CDS</a>
      <?php if ($cmLayoutLogged): ?><a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i> Đăng xuất</a><?php endif; ?>
    </div>
  </div>
</div>
<script id="cdsCmSidebarGroups">
(function(){
  document.querySelectorAll('[data-cm-nav-group]>.cm-sidebar-group-toggle').forEach(function(button){
    button.addEventListener('click',function(){
      var group=button.closest('[data-cm-nav-group]'),open=!group.classList.contains('open');
      document.querySelectorAll('[data-cm-nav-group]').forEach(function(other){
        var keep=other===group&&open;other.classList.toggle('open',keep);
        var toggle=other.querySelector(':scope>.cm-sidebar-group-toggle');if(toggle)toggle.setAttribute('aria-expanded',keep?'true':'false');
      });
    });
  });
})();
</script>
