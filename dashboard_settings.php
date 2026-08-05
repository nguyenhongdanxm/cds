<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/modules.php';
require_admin();

$catalog = cds_dashboard_widgets_catalog();
$settings = cds_dashboard_settings();
$homeControls = cds_home_controls();
$modulesCatalog = array_values(array_filter(get_ecosystem_modules(), fn($module) => ($module['status'] ?? '') !== 'soon'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_all';
    $enabled = array_values(array_filter(array_map('strval', $_POST['enabled'] ?? [])));
    $order = array_values(array_filter(array_map('strval', explode(',', $_POST['order'] ?? ''))));
    cds_dashboard_save($enabled, $order);

    $notices = $homeControls['notices'];
    if ($action === 'add_notice') {
        $text = trim((string)($_POST['notice_text'] ?? ''));
        if ($text !== '') {
            $notices[] = [
                'id' => 'notice_' . bin2hex(random_bytes(4)),
                'text' => $text,
                'type' => (string)($_POST['notice_type'] ?? 'info'),
                'active' => true,
                'created_at' => date('c'),
            ];
        }
    } elseif ($action === 'delete_notice') {
        $deleteId = (string)($_POST['notice_id'] ?? '');
        $notices = array_values(array_filter($notices, fn($row) => (string)($row['id'] ?? '') !== $deleteId));
    } else {
        $activeNoticeIds = array_map('strval', $_POST['notice_active'] ?? []);
        foreach ($notices as &$notice) $notice['active'] = in_array((string)($notice['id'] ?? ''), $activeNoticeIds, true);
        unset($notice);
    }

    cds_home_controls_save([
        'visible_modules' => array_map('strval', $_POST['visible_modules'] ?? []),
        'birthday_enabled' => !empty($_POST['birthday_enabled']),
        'notices' => $notices,
    ]);
    cds_audit_log('update', 'dashboard', ['target'=>'home_controls','action'=>$action]);
    flash($action === 'add_notice' ? 'Đã thêm thông báo mới.' : ($action === 'delete_notice' ? 'Đã xóa thông báo.' : 'Đã lưu cấu hình trang chủ.'));
    header('Location: dashboard_settings.php');
    exit;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cấu hình trang chủ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f4f7fb}.wrap{max-width:1080px;margin:auto}.card{border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 5px 20px rgba(15,23,42,.05)}
.widget-row,.module-row,.notice-row{display:grid;align-items:center;gap:12px;padding:14px 16px;margin-bottom:10px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}
.widget-row{grid-template-columns:34px 48px 1fr auto;cursor:grab}.module-row{grid-template-columns:48px 1fr auto}.notice-row{grid-template-columns:1fr auto auto}.widget-row.dragging{opacity:.45}.handle{color:#94a3b8}.icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#eff6ff;color:#2563eb;font-size:19px}.section-title{display:flex;align-items:center;gap:.65rem}.section-title i{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#eff6ff;color:#2563eb}.notice-preview{padding:.65rem .8rem;border-radius:12px}.notice-preview.info{background:#e0f2fe;color:#075985}.notice-preview.success{background:#dcfce7;color:#166534}.notice-preview.warning{background:#fef3c7;color:#92400e}.notice-preview.danger{background:#fee2e2;color:#991b1b}@media(max-width:700px){.notice-row{grid-template-columns:1fr}.widget-row{grid-template-columns:28px 42px 1fr auto}}
</style>
</head>
<body>
<?php $nav_title='Cấu hình trang chủ';$nav_icon='bi-sliders';$nav_color='#2563eb';$nav_module='admin';include __DIR__.'/includes/nav_top.php';?>
<main class="container py-4 wrap">
<?php show_flash(); ?>
<div class="d-flex justify-content-between align-items-start mb-3"><div><h3 class="mb-1">Cấu hình trang chủ</h3><p class="text-muted">Quản lý module, sinh nhật, thông báo và các khối nội dung dùng chung.</p></div><a href="admin.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Trang chủ</a></div>

<form method="post" class="d-grid gap-3">
<input type="hidden" name="action" value="save_all"><input type="hidden" name="order" id="widgetOrder">
<section class="card p-3 p-md-4">
  <div class="section-title mb-3"><i class="bi bi-grid-3x3-gap"></i><div><h5 class="mb-0">Module hiển thị ở trang chủ</h5><small class="text-muted">Ẩn module tại bộ chuyển module trên trang chủ; không làm mất quyền truy cập trực tiếp.</small></div></div>
  <?php foreach($modulesCatalog as $module): ?><div class="module-row"><span class="icon" style="color:<?= e($module['color']) ?>"><i class="bi <?= e($module['icon']) ?>"></i></span><div><strong><?= e($module['title']) ?></strong><small class="d-block text-muted"><?= e($module['subtitle']) ?></small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="visible_modules[]" value="<?= e($module['id']) ?>" <?= in_array($module['id'],$homeControls['visible_modules'],true)?'checked':'' ?>></div></div><?php endforeach; ?>
</section>

<section class="card p-3 p-md-4">
  <div class="section-title mb-3"><i class="bi bi-cake2"></i><div><h5 class="mb-0">Thông báo sinh nhật</h5><small class="text-muted">Bật hoặc ẩn toàn bộ thông báo sinh nhật trên trang chủ.</small></div></div>
  <div class="d-flex justify-content-between align-items-center border rounded-3 p-3"><div><strong>Hiển thị thông báo ngày sinh nhật</strong><small class="d-block text-muted">Khi tắt, trang chủ sẽ hiển thị câu chào thông thường.</small></div><div class="form-check form-switch fs-4"><input class="form-check-input" type="checkbox" name="birthday_enabled" value="1" <?= !empty($homeControls['birthday_enabled'])?'checked':'' ?>></div></div>
</section>

<section class="card p-3 p-md-4">
  <div class="section-title mb-3"><i class="bi bi-megaphone"></i><div><h5 class="mb-0">Các dòng thông báo</h5><small class="text-muted">Tích để bật hoặc bỏ tích để tạm ẩn từng thông báo.</small></div></div>
  <?php foreach($homeControls['notices'] as $notice): $type=in_array($notice['type']??'', ['info','success','warning','danger'],true)?$notice['type']:'info'; ?><div class="notice-row"><div class="notice-preview <?= e($type) ?>"><i class="bi bi-megaphone-fill me-1"></i><?= e($notice['text']) ?></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="notice_active[]" value="<?= e($notice['id']) ?>" <?= !empty($notice['active'])?'checked':'' ?>></div><button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete_notice" onclick="this.form.notice_id.value='<?= e($notice['id']) ?>';return confirm('Xóa thông báo này?')"><i class="bi bi-trash"></i></button></div><?php endforeach; ?>
  <?php if(!$homeControls['notices']): ?><div class="text-muted text-center py-3">Chưa có thông báo bổ sung.</div><?php endif; ?>
  <input type="hidden" name="notice_id" value="">
  <div class="border-top pt-3 mt-2"><label class="form-label fw-semibold">Đăng thêm dòng thông báo</label><textarea name="notice_text" class="form-control" rows="2" maxlength="500" placeholder="Nhập nội dung thông báo..."></textarea><div class="row g-2 mt-1"><div class="col-md-6"><select name="notice_type" class="form-select"><option value="info">Thông tin</option><option value="success">Tích cực</option><option value="warning">Lưu ý</option><option value="danger">Khẩn</option></select></div><div class="col-md-6 d-grid"><button class="btn btn-outline-primary" type="submit" name="action" value="add_notice"><i class="bi bi-plus-lg"></i> Đăng thông báo</button></div></div></div>
</section>

<section class="card p-3 p-md-4">
  <div class="section-title mb-3"><i class="bi bi-layout-wtf"></i><div><h5 class="mb-0">Các khối nội dung</h5><small class="text-muted">Bật, tắt và kéo thả để sắp xếp các khối thông tin.</small></div></div>
  <div id="widgetList"><?php foreach($settings['order'] as $id):if(!isset($catalog[$id]))continue;$w=$catalog[$id];?><div class="widget-row" draggable="true" data-id="<?= e($id) ?>"><i class="bi bi-grip-vertical handle"></i><span class="icon"><i class="bi <?= e($w['icon']) ?>"></i></span><div><strong><?= e($w['title']) ?></strong><small class="d-block text-muted"><?= $w['module']?'Theo quyền module '.$w['module']:'Dùng chung' ?></small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled[]" value="<?= e($id) ?>" <?= in_array($id,$settings['enabled'],true)?'checked':'' ?>></div></div><?php endforeach;?></div>
</section>
<div class="d-flex justify-content-end"><button class="btn btn-primary px-4"><i class="bi bi-floppy"></i> Lưu toàn bộ cấu hình</button></div>
</form>
</main>
<script>const list=document.getElementById('widgetList'),order=document.getElementById('widgetOrder');function sync(){order.value=[...list.children].map(x=>x.dataset.id).join(',')}sync();let dragged=null;list.addEventListener('dragstart',e=>{dragged=e.target.closest('.widget-row');dragged?.classList.add('dragging')});list.addEventListener('dragend',()=>{dragged?.classList.remove('dragging');dragged=null;sync()});list.addEventListener('dragover',e=>{e.preventDefault();if(!dragged)return;const items=[...list.querySelectorAll('.widget-row:not(.dragging)')],next=items.find(x=>e.clientY<x.getBoundingClientRect().top+x.offsetHeight/2);list.insertBefore(dragged,next||null)})</script>
</body></html>
