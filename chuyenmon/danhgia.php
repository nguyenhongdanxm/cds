<?php
$page_title = 'Đánh giá giáo viên';
require_once 'includes/functions.php';
require_login();
$view = in_array($_GET['view'] ?? 'overview', ['overview','profile','settings'], true) ? ($_GET['view'] ?? 'overview') : 'overview';
$user = cds_user() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';
if ($view === 'settings' && !$isAdmin) $view = 'overview';
require_once 'includes/header.php';
$embedUrl = '/danhgia.php?' . http_build_query(['view'=>$view,'embed'=>1]);
?>
<style>
.cm-evaluation-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:.8rem}.cm-evaluation-head h3{margin:0;color:#173f65}.cm-evaluation-tabs{display:flex;gap:.4rem;flex-wrap:wrap;padding:.32rem;border:1px solid #dce6ef;border-radius:13px;background:#fff}.cm-evaluation-tabs a{display:flex;align-items:center;gap:.42rem;padding:.58rem .82rem;border-radius:9px;color:#173f65;text-decoration:none;font-size:.88rem;font-weight:700}.cm-evaluation-tabs a.active{background:#1f5d8d;color:#fff}.cm-evaluation-frame{display:block;width:100%;height:calc(100vh - 125px);min-height:720px;border:1px solid #dce6ef;border-radius:16px;background:#f2f6fa;box-shadow:0 4px 18px rgba(15,23,42,.05)}@media(max-width:767px){.cm-evaluation-head{align-items:flex-start}.cm-evaluation-tabs{width:100%;overflow:auto;flex-wrap:nowrap}.cm-evaluation-tabs a{white-space:nowrap}.cm-evaluation-frame{height:calc(100vh - 205px);min-height:640px;border-radius:12px}}
</style>
<div class="cm-evaluation-head"><div><h3><i class="bi bi-bar-chart-line"></i> Theo dõi – Đánh giá</h3><div class="text-muted small">Tổng hợp và hồ sơ giáo viên trong cùng phân hệ Chuyên môn</div></div><nav class="cm-evaluation-tabs" aria-label="Các mục đánh giá"><a class="<?=$view==='overview'?'active':''?>" href="<?=BASE_URL?>danhgia.php?view=overview"><i class="bi bi-bar-chart-line"></i>Tổng hợp đánh giá</a><a class="<?=$view==='profile'?'active':''?>" href="<?=BASE_URL?>danhgia.php?view=profile"><i class="bi bi-person-lines-fill"></i>Hồ sơ đánh giá</a><?php if($isAdmin):?><a class="<?=$view==='settings'?'active':''?>" href="<?=BASE_URL?>danhgia.php?view=settings"><i class="bi bi-sliders"></i>Tiêu chí và thang điểm</a><?php endif;?></nav></div>
<iframe class="cm-evaluation-frame" src="<?=e($embedUrl)?>" title="Nội dung đánh giá giáo viên"></iframe>
<?php require_once 'includes/footer.php'; ?>
