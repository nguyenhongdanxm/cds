<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cds_auth_gate.php';
$current = basename($_SERVER['PHP_SELF'], '.php');
$logged = is_logged_in();
$active_ver = get_version(get_active_version_id());
$tab_q = $_GET['tab'] ?? '';

$pccm_pages = ['tracuu','tongquan','them','danhsach','doicheo','rasoat','sua','ketqua','giaovien','monhoc','lop','kiemnhiem','xuat_bang','thongke'];
$pccm_active = in_array($current, $pccm_pages, true);
$kh_pages = ['kehoach'];
$bc_pages = ['baocao','dugio','kiemtrahoso','danhgia'];
$cmMenuCan = fn($permission) => cds_can_feature($permission, 'view');
$cmDisabled = fn($permission) => $cmMenuCan($permission) ? '' : ' permission-disabled';
$pccmCanOpen = $cmMenuCan('cm.tracuu') || $cmMenuCan('cm.pccm') || $cmMenuCan('cm.nhaplieu') || $cmMenuCan('cm.thongke');
$reportCanOpen = $cmMenuCan('cm.baocao.dinhky') || $cmMenuCan('cm.baocao.tiendo') || $cmMenuCan('cm.baocao.dugio') || $cmMenuCan('cm.baocao.kythi');
require_once __DIR__ . '/cds_manual_assignment_processor.php';
$cmUnifiedCss = dirname(__DIR__, 2) . '/assets/chuyenmon-unified.css';
$cmUnifiedCssVer = is_file($cmUnifiedCss) ? (string)filemtime($cmUnifiedCss) : '20260823-1';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= BASE_URL ?>">
<title><?= e($page_title ?? 'Chuyên môn') ?> – Chuyên môn</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/cds-global-ui.css?v=20260817-1" rel="stylesheet">
<link href="/assets/chuyenmon-unified.css?v=<?= e($cmUnifiedCssVer) ?>" rel="stylesheet">
<style>
:root{--primary:#1F4E79;--primary-light:#2E6DA4}
body{background:#f0f4f8;font-family:'Segoe UI',system-ui,sans-serif;color:#212529}
.navbar{background:var(--primary)!important}
.navbar .navbar-brand,.navbar .nav-link{color:#fff!important}
.navbar .nav-link:hover,.navbar .nav-link.active{color:#ffc107!important}
.navbar .dropdown-menu{border:none;box-shadow:0 8px 24px rgba(0,0,0,.12);border-radius:10px;min-width:230px}
.navbar .dropdown-item{font-weight:500;padding:.5rem 1rem}
.navbar .dropdown-item:hover{background:#e8f0fe;color:var(--primary)}
.navbar .dropdown-item.active{background:var(--primary);color:#fff}
.navbar .permission-disabled{opacity:.38!important;filter:grayscale(1);pointer-events:none!important;cursor:not-allowed!important}
.navbar .permission-disabled:hover{background:transparent!important;color:inherit!important}
.navbar .dropdown-header{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d}
.nav-tabs{border-bottom:2px solid #dee2e6}
.nav-tabs .nav-link,.nav-pills .nav-link{color:#1F4E79!important;font-weight:600}
.nav-tabs .nav-link{background:#fff;border:1px solid transparent}
.nav-tabs .nav-link:hover{color:#0d3a5c!important;background:#e8f0fe;border-color:#dee2e6 #dee2e6 #fff}
.nav-tabs .nav-link.active{color:#fff!important;background:var(--primary)!important;border-color:var(--primary)!important}
.nav-pills .nav-link{background:#fff;border:1px solid #dee2e6}
.nav-pills .nav-link:hover{background:#e8f0fe}
.nav-pills .nav-link.active{color:#fff!important;background:var(--primary)!important;border-color:var(--primary)}
.card{border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.card-header{background:var(--primary);color:#fff!important;border-radius:12px 12px 0 0!important;font-weight:600}
.card-header.bg-success{background:#198754!important;color:#fff!important}
.card-header.bg-info{background:#0dcaf0!important;color:#053b4a!important}
.card-header.bg-secondary{background:#6c757d!important;color:#fff!important}
.card-header.bg-warning{background:#ffc107!important;color:#664d03!important}
.card-header.bg-danger{background:#dc3545!important;color:#fff!important}
.btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-light);border-color:var(--primary-light);color:#fff}
.btn-info{color:#053b4a!important}
.table th{background:#e8f0fe;color:var(--primary);white-space:nowrap}
.stat-card{text-align:center;padding:1.25rem}
.stat-card .number{font-size:2rem;font-weight:700;color:var(--primary)}
.stat-card .label{color:#555;font-size:.9rem}
.chip{display:inline-flex;align-items:center;gap:4px;background:#e8f0fe;color:#1F4E79;border:1px solid #b6d0f0;border-radius:20px;padding:2px 8px 2px 10px;margin:2px;font-size:.85rem;font-weight:500}
.chip-role{background:#d1f0f7;border-color:#9ad8e8;color:#055160}
.chip .chip-x{border:none;background:#dc3545;color:#fff;border-radius:50%;width:18px;height:18px;line-height:16px;font-size:12px;padding:0;cursor:pointer}
.chip .chip-x:hover{background:#bb2d3b}
.version-bar{background:#fff8e1;border:1px solid #ffc107;border-radius:8px;padding:.5rem 1rem;margin-bottom:1rem;font-size:.95rem;color:#664d03}
.version-bar a{color:#1F4E79;font-weight:600}
.diff-over{color:#dc3545;font-weight:700}
.diff-under{color:#fd7e14;font-weight:700}
.diff-ok{color:#198754;font-weight:600}
.board-row{border-bottom:1px solid #e9ecef;padding:.6rem 0}
.board-row:last-child{border-bottom:none}
.warn-box{background:#fff3cd;border-left:4px solid #ffc107;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#664d03}
.danger-box{background:#f8d7da;border-left:4px solid #dc3545;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#58151c}
.info-box{background:#e8f0fe;border-left:4px solid var(--primary);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#1F4E79}
.pccm-toast{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;align-items:flex-start;gap:10px;min-width:260px;max-width:360px;padding:12px 14px;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.18);font-size:.95rem;line-height:1.4;animation:pccmToastIn .25s ease-out}
.pccm-toast-success{background:#d1e7dd;color:#0a3622;border:1px solid #a3cfbb}
.pccm-toast-danger{background:#f8d7da;color:#58151c;border:1px solid #f1aeb5}
.pccm-toast-warning{background:#fff3cd;color:#664d03;border:1px solid #ffe69c}
.pccm-toast-info{background:#cff4fc;color:#055160;border:1px solid #9eeaf9}
.pccm-toast-icon{font-size:1.2rem;flex-shrink:0;margin-top:1px}
.pccm-toast-msg{flex:1}
.pccm-toast-close{border:none;background:transparent;font-size:1.25rem;line-height:1;cursor:pointer;opacity:.6;padding:0 2px;color:inherit}
.pccm-toast-close:hover{opacity:1}
@keyframes pccmToastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@media (max-width:576px){.pccm-toast{left:12px;right:12px;bottom:12px;max-width:none;min-width:0}}
</style>
<style>.permission-disabled{display:none!important}</style></head>
<body class="cm-body">
<?php
require_once __DIR__ . '/cds_manual_save_fix.php';
require_once __DIR__ . '/cds_manual_delete_fix.php';
require_once __DIR__ . '/cds_manual_table_fix.php';
require_once __DIR__ . '/cds_manual_assignment.php';
require_once __DIR__ . '/cds_fractional_periods.php';
require_once __DIR__ . '/cds_teacher_compact.php';
require_once __DIR__ . '/cds_ui_cleanup.php';
require_once __DIR__ . '/cds_responsive_layout.php';
require_once __DIR__ . '/cds_module_switcher.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
<div class="container">
<a class="navbar-brand fw-bold" href="<?= BASE_URL ?>index.php"><i class="bi bi-journal-bookmark-fill"></i> Chuyên môn</a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="nav">
<ul class="navbar-nav me-auto">
<?php if ($logged): ?>
<li class="nav-item"><a class="nav-link <?= $current==='index'?'active':'' ?><?= $cmDisabled('cm.dashboard') ?>" href="<?= BASE_URL ?>index.php" <?= !$cmMenuCan('cm.dashboard')?'aria-disabled="true" tabindex="-1"':'' ?>><i class="bi bi-house-door"></i> Trang chủ</a></li>
<li class="nav-item dropdown"><a class="nav-link dropdown-toggle <?= $pccm_active?'active':'' ?><?= !$pccmCanOpen?' permission-disabled':'' ?>" href="#" data-bs-toggle="dropdown" <?= !$pccmCanOpen?'aria-disabled="true" tabindex="-1"':'' ?>><i class="bi bi-clipboard-check"></i> PCCM</a><ul class="dropdown-menu"><li><a class="dropdown-item <?= $current==='tracuu'?'active':'' ?><?= $cmDisabled('cm.tracuu') ?>" href="<?= BASE_URL ?>tracuu.php">Tra cứu phân công</a></li><li><a class="dropdown-item <?= $current==='tongquan'?'active':'' ?><?= $cmDisabled('cm.pccm') ?>" href="<?= BASE_URL ?>tongquan.php">Tổng quan</a></li><li><a class="dropdown-item <?= in_array($current,['them','doicheo','rasoat','sua'],true)?'active':'' ?><?= $cmDisabled('cm.pccm') ?>" href="<?= BASE_URL ?>them.php">Phân công</a></li><li><a class="dropdown-item <?= $current==='danhsach'?'active':'' ?><?= $cmDisabled('cm.pccm') ?>" href="<?= BASE_URL ?>danhsach.php">Danh sách</a></li><li><a class="dropdown-item <?= $current==='ketqua'?'active':'' ?><?= $cmDisabled('cm.tracuu') ?>" href="<?= BASE_URL ?>ketqua.php">Kết quả</a></li><li><a class="dropdown-item <?= $current==='thongke'?'active':'' ?><?= $cmDisabled('cm.thongke') ?>" href="<?= BASE_URL ?>thongke.php">Thống kê PCCM</a></li></ul></li>
<li class="nav-item dropdown"><a class="nav-link dropdown-toggle <?= in_array($current,$kh_pages,true)?'active':'' ?><?= $cmDisabled('cm.kehoach') ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-calendar2-week"></i> Kế hoạch</a><ul class="dropdown-menu"><li><a class="dropdown-item <?= ($current==='kehoach' && ($tab_q===''||$tab_q==='vanban'))?'active':'' ?>" href="<?= BASE_URL ?>kehoach.php?tab=vanban">Kế hoạch giáo dục</a></li><li><a class="dropdown-item <?= ($current==='kehoach' && $tab_q==='thongbao')?'active':'' ?>" href="<?= BASE_URL ?>kehoach.php?tab=thongbao">Thông báo</a></li><li><a class="dropdown-item <?= ($current==='kehoach' && $tab_q==='chitieu')?'active':'' ?>" href="<?= BASE_URL ?>kehoach.php?tab=chitieu">Chỉ tiêu</a></li></ul></li>
<li class="nav-item dropdown"><a class="nav-link dropdown-toggle <?= in_array($current,$bc_pages,true)?'active':'' ?><?= !$reportCanOpen?' permission-disabled':'' ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-clipboard2-check"></i> Theo dõi – Đánh giá</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="<?= BASE_URL ?>baocao.php?tab=dinhky">Báo cáo định kỳ</a></li><li><a class="dropdown-item" href="<?= BASE_URL ?>baocao.php?tab=tiendo">Tiến độ chương trình</a></li><li><a class="dropdown-item" href="<?= BASE_URL ?>dugio.php">Dự giờ</a></li><li><a class="dropdown-item" href="<?= BASE_URL ?>kiemtrahoso.php">Kiểm tra</a></li></ul></li>
<li class="nav-item"><a class="nav-link <?= $current==='thoikhoabieu'?'active':'' ?>" href="<?= BASE_URL ?>thoikhoabieu.php"><i class="bi bi-calendar3"></i> Thời khóa biểu</a></li>
<?php else: ?><li class="nav-item"><a class="nav-link <?= $current=='tracuu'?'active':'' ?>" href="<?= BASE_URL ?>tracuu.php"><i class="bi bi-search"></i> Tra cứu phân công</a></li><li class="nav-item"><a class="nav-link <?= $current=='ketqua'?'active':'' ?>" href="<?= BASE_URL ?>ketqua.php"><i class="bi bi-folder2-open"></i> Kết quả</a></li><?php endif; ?>
</ul>
<div class="d-flex flex-wrap gap-2 align-items-center"><a href="/" class="btn btn-outline-light btn-sm" title="Hệ sinh thái CDS"><i class="bi bi-house"></i></a><?php if ($logged): ?><a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark fw-semibold"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a><?php else: ?><a href="/login.php?next=<?= urlencode(BASE_URL . 'index.php') ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a><?php endif; ?></div>
</div></div></nav>
<div class="container pb-5">
<script>document.querySelectorAll('.cm-sidebar-link span,.cm-mobile-bottom span,.cm-mobile-more span').forEach(function(label){if ((label.textContent || '').trim() === 'Văn bản kế hoạch') label.textContent = 'Kế hoạch giáo dục';});</script>
<?php show_flash(); ?>
<?php if ($logged && $active_ver && in_array($current, ['them','danhsach','tongquan','sua','doicheo','rasoat'], true)): ?><div class="version-bar"><i class="bi bi-folder2-open"></i> Đang làm việc trên: <strong><?= e($active_ver['name']) ?></strong> (ngày <?= e($active_ver['date'] ?? '') ?>) · <a href="<?= BASE_URL ?>ketqua.php">Đổi phiên bản</a></div><?php endif; ?>
