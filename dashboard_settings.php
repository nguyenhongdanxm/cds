<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/modules.php';
require_admin();

$catalog=cds_dashboard_widgets_catalog();
$settings=cds_dashboard_settings();
$homeControls=cds_home_controls();
$modulesCatalog=array_values(array_filter(get_ecosystem_modules(),fn($m)=>($m['status']??'')!=='soon'));
$pagesCatalog=cds_home_pages_catalog();
$themesCatalog=cds_home_themes_catalog();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'save_all';
    $enabled=array_values(array_filter(array_map('strval',$_POST['enabled']??[])));
    $order=array_values(array_filter(array_map('strval',explode(',',$_POST['order']??''))));
    cds_dashboard_save($enabled,$order);
    $notices=$homeControls['notices'];
    if($action==='add_notice'){
        $text=trim((string)($_POST['notice_text']??''));
        if($text!=='')$notices[]=['id'=>'notice_'.bin2hex(random_bytes(4)),'text'=>$text,'type'=>(string)($_POST['notice_type']??'info'),'active'=>true,'created_at'=>date('c')];
    }elseif($action==='delete_notice'){
        $deleteId=(string)($_POST['notice_id']??'');
        $notices=array_values(array_filter($notices,fn($r)=>(string)($r['id']??'')!==$deleteId));
    }else{
        $activeIds=array_map('strval',$_POST['notice_active']??[]);
        foreach($notices as &$notice)$notice['active']=in_array((string)($notice['id']??''),$activeIds,true);unset($notice);
    }
    $pageBlocks=[];foreach($pagesCatalog as $pageId=>$page)$pageBlocks[$pageId]=array_map('strval',$_POST['page_blocks'][$pageId]??[]);
    cds_home_controls_save([
        'visible_modules'=>array_map('strval',$_POST['visible_modules']??[]),
        'birthday_enabled'=>!empty($_POST['birthday_enabled']),
        'notices'=>$notices,
        'page_blocks'=>$pageBlocks,
        'theme'=>[
            'id'=>(string)($_POST['theme_id']??'default'),
            'primary'=>(string)($_POST['theme_primary']??''),
            'secondary'=>(string)($_POST['theme_secondary']??''),
            'accent'=>(string)($_POST['theme_accent']??''),
            'background'=>(string)($_POST['theme_background']??''),
            'event_title'=>(string)($_POST['event_title']??''),
        ],
    ]);
    cds_audit_log('update','dashboard',['target'=>'home_controls','action'=>$action]);
    flash($action==='add_notice'?'Đã thêm thông báo mới.':($action==='delete_notice'?'Đã xóa thông báo.':'Đã lưu cấu hình giao diện và trang chủ.'));
    header('Location: dashboard_settings.php');exit;
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Trung tâm cấu hình giao diện</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>
body{background:#f4f7fb}.wrap{max-width:1180px;margin:auto}.card{border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 5px 20px rgba(15,23,42,.05)}.section-title{display:flex;align-items:center;gap:.65rem}.section-title>i{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#eff6ff;color:#2563eb}.widget-row,.module-row,.notice-row,.block-row{display:grid;align-items:center;gap:12px;padding:13px 15px;margin-bottom:9px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.widget-row{grid-template-columns:34px 46px 1fr auto;cursor:grab}.module-row{grid-template-columns:46px 1fr auto}.notice-row{grid-template-columns:1fr auto auto}.block-row{grid-template-columns:1fr auto}.widget-row.dragging{opacity:.45}.handle{color:#94a3b8}.icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#eff6ff;color:#2563eb;font-size:19px}.theme-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.theme-card{position:relative;display:block;padding:1rem;border:2px solid #e2e8f0;border-radius:16px;background:#fff;cursor:pointer}.theme-card input{position:absolute;opacity:0}.theme-card:has(input:checked){border-color:#2563eb;box-shadow:0 0 0 3px #dbeafe}.theme-swatch{display:flex;height:28px;overflow:hidden;border-radius:9px;margin-bottom:.65rem}.theme-swatch span{flex:1}.page-tabs .nav-link{font-weight:700}.notice-preview{padding:.65rem .8rem;border-radius:12px}.notice-preview.info{background:#e0f2fe;color:#075985}.notice-preview.success{background:#dcfce7;color:#166534}.notice-preview.warning{background:#fef3c7;color:#92400e}.notice-preview.danger{background:#fee2e2;color:#991b1b}.sticky-save{position:sticky;bottom:12px;z-index:20;padding:.75rem;border:1px solid #dbe3ec;border-radius:16px;background:rgba(255,255,255,.94);box-shadow:0 8px 30px rgba(15,23,42,.15);backdrop-filter:blur(12px)}@media(max-width:800px){.theme-grid{grid-template-columns:1fr}.notice-row{grid-template-columns:1fr}.widget-row{grid-template-columns:28px 42px 1fr auto}}
</style></head><body>
<?php $nav_title='Cấu hình giao diện';$nav_icon='bi-palette2';$nav_color='#2563eb';$nav_module='admin';include __DIR__.'/includes/nav_top.php';?>
<main class="container py-4 wrap"><?php show_flash(); ?>
<div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h3 class="mb-1">Trung tâm cấu hình giao diện</h3><p class="text-muted mb-0">Chọn module, khối nội dung, thông báo và chủ đề hiển thị cho toàn hệ thống.</p></div><a href="admin.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Trang chủ</a></div>
<form method="post" class="d-grid gap-3"><input type="hidden" name="action" value="save_all"><input type="hidden" name="order" id="widgetOrder">

<section class="card p-3 p-md-4"><div class="section-title mb-3"><i class="bi bi-palette2"></i><div><h5 class="mb-0">Theme và chủ đề sự kiện</h5><small class="text-muted">Áp dụng đồng thời cho trang hệ sinh thái và trang chủ sau đăng nhập.</small></div></div>
<div class="theme-grid"><?php foreach($themesCatalog as $id=>$theme): ?><label class="theme-card"><input type="radio" name="theme_id" value="<?= e($id) ?>" <?= ($homeControls['theme']['id']??'default')===$id?'checked':'' ?> onchange="applyThemePreset('<?= e($id) ?>')"><div class="theme-swatch"><span style="background:<?= e($theme['primary']) ?>"></span><span style="background:<?= e($theme['secondary']) ?>"></span><span style="background:<?= e($theme['accent']) ?>"></span></div><strong><i class="bi <?= e($theme['icon']) ?> me-1"></i><?= e($theme['name']) ?></strong><small class="d-block text-muted mt-1"><?= e($theme['description']) ?></small></label><?php endforeach; ?></div>
<div class="row g-2 mt-2"><div class="col-6 col-md-3"><label class="form-label small">Màu chính</label><input type="color" class="form-control form-control-color w-100" id="themePrimary" name="theme_primary" value="<?= e($homeControls['theme']['primary']) ?>"></div><div class="col-6 col-md-3"><label class="form-label small">Màu phụ</label><input type="color" class="form-control form-control-color w-100" id="themeSecondary" name="theme_secondary" value="<?= e($homeControls['theme']['secondary']) ?>"></div><div class="col-6 col-md-3"><label class="form-label small">Màu nhấn</label><input type="color" class="form-control form-control-color w-100" id="themeAccent" name="theme_accent" value="<?= e($homeControls['theme']['accent']) ?>"></div><div class="col-6 col-md-3"><label class="form-label small">Màu nền</label><input type="color" class="form-control form-control-color w-100" id="themeBackground" name="theme_background" value="<?= e($homeControls['theme']['background']) ?>"></div><div class="col-12"><label class="form-label small">Dòng chủ đề sự kiện</label><input class="form-control" name="event_title" maxlength="120" value="<?= e($homeControls['theme']['event_title']??'') ?>" placeholder="Ví dụ: Chào mừng 20/11 – Tôn sư trọng đạo"></div></div></section>

<section class="card p-3 p-md-4"><div class="section-title mb-3"><i class="bi bi-layout-text-sidebar-reverse"></i><div><h5 class="mb-0">Khối hiển thị theo từng trang</h5><small class="text-muted">Bỏ tích để ẩn khối. Dữ liệu và chức năng bên trong không bị xóa.</small></div></div>
<ul class="nav nav-pills page-tabs mb-3" role="tablist"><?php $first=true;foreach($pagesCatalog as $pageId=>$page): ?><li class="nav-item"><button class="nav-link <?= $first?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#page-<?= e($pageId) ?>" type="button"><i class="bi <?= e($page['icon']) ?> me-1"></i><?= e($page['title']) ?></button></li><?php $first=false;endforeach; ?></ul>
<div class="tab-content"><?php $first=true;foreach($pagesCatalog as $pageId=>$page): ?><div class="tab-pane fade <?= $first?'show active':'' ?>" id="page-<?= e($pageId) ?>"><?php foreach($page['blocks'] as $blockId=>$block): ?><div class="block-row"><div><strong><?= e($block['title']) ?></strong><small class="d-block text-muted">Mã khối: <?= e($blockId) ?></small></div><div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" name="page_blocks[<?= e($pageId) ?>][]" value="<?= e($blockId) ?>" <?= in_array($blockId,$homeControls['page_blocks'][$pageId]??[],true)?'checked':'' ?>></div></div><?php endforeach; ?></div><?php $first=false;endforeach; ?></div></section>

<section class="card p-3 p-md-4"><div class="section-title mb-3"><i class="bi bi-grid-3x3-gap"></i><div><h5 class="mb-0">Module hiển thị</h5><small class="text-muted">Áp dụng cho bộ chuyển module và trang hệ sinh thái.</small></div></div><?php foreach($modulesCatalog as $module): ?><div class="module-row"><span class="icon" style="color:<?= e($module['color']) ?>"><i class="bi <?= e($module['icon']) ?>"></i></span><div><strong><?= e($module['title']) ?></strong><small class="d-block text-muted"><?= e($module['subtitle']) ?></small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="visible_modules[]" value="<?= e($module['id']) ?>" <?= in_array($module['id'],$homeControls['visible_modules'],true)?'checked':'' ?>></div></div><?php endforeach; ?></section>

<section class="card p-3 p-md-4"><div class="section-title mb-3"><i class="bi bi-cake2"></i><div><h5 class="mb-0">Sinh nhật và thông báo</h5><small class="text-muted">Quản lý thông tin nổi bật ở đầu trang chủ.</small></div></div><div class="d-flex justify-content-between align-items-center border rounded-3 p-3 mb-3"><div><strong>Hiển thị thông báo sinh nhật</strong><small class="d-block text-muted">Tắt để ẩn toàn bộ lời nhắc sinh nhật.</small></div><div class="form-check form-switch fs-4"><input class="form-check-input" type="checkbox" name="birthday_enabled" value="1" <?= !empty($homeControls['birthday_enabled'])?'checked':'' ?>></div></div>
<?php foreach($homeControls['notices'] as $notice):$type=in_array($notice['type']??'', ['info','success','warning','danger'],true)?$notice['type']:'info'; ?><div class="notice-row"><div class="notice-preview <?= e($type) ?>"><i class="bi bi-megaphone-fill me-1"></i><?= e($notice['text']) ?></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="notice_active[]" value="<?= e($notice['id']) ?>" <?= !empty($notice['active'])?'checked':'' ?>></div><button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete_notice" onclick="this.form.notice_id.value='<?= e($notice['id']) ?>';return confirm('Xóa thông báo này?')"><i class="bi bi-trash"></i></button></div><?php endforeach; ?><input type="hidden" name="notice_id" value=""><div class="border-top pt-3 mt-2"><label class="form-label fw-semibold">Đăng thêm dòng thông báo</label><textarea name="notice_text" class="form-control" rows="2" maxlength="500" placeholder="Nhập nội dung thông báo..."></textarea><div class="row g-2 mt-1"><div class="col-md-6"><select name="notice_type" class="form-select"><option value="info">Thông tin</option><option value="success">Tích cực</option><option value="warning">Lưu ý</option><option value="danger">Khẩn</option></select></div><div class="col-md-6 d-grid"><button class="btn btn-outline-primary" type="submit" name="action" value="add_notice"><i class="bi bi-plus-lg"></i> Đăng thông báo</button></div></div></div></section>

<section class="card p-3 p-md-4"><div class="section-title mb-3"><i class="bi bi-layout-wtf"></i><div><h5 class="mb-0">Bố cục widget mở rộng</h5><small class="text-muted">Giữ tương thích cấu hình widget hiện có; kéo thả để sắp xếp.</small></div></div><div id="widgetList"><?php foreach($settings['order'] as $id):if(!isset($catalog[$id]))continue;$w=$catalog[$id];?><div class="widget-row" draggable="true" data-id="<?= e($id) ?>"><i class="bi bi-grip-vertical handle"></i><span class="icon"><i class="bi <?= e($w['icon']) ?>"></i></span><div><strong><?= e($w['title']) ?></strong><small class="d-block text-muted"><?= $w['module']?'Theo quyền module '.$w['module']:'Dùng chung' ?></small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled[]" value="<?= e($id) ?>" <?= in_array($id,$settings['enabled'],true)?'checked':'' ?>></div></div><?php endforeach;?></div></section>
<div class="sticky-save d-flex justify-content-between align-items-center"><span class="text-muted small"><i class="bi bi-info-circle"></i> Thay đổi áp dụng ngay sau khi lưu.</span><button class="btn btn-primary px-4"><i class="bi bi-floppy"></i> Lưu toàn bộ cấu hình</button></div>
</form></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script>
const themePresets=<?= json_encode(array_map(fn($t)=>['primary'=>$t['primary'],'secondary'=>$t['secondary'],'accent'=>$t['accent'],'background'=>$t['background']],$themesCatalog),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
function applyThemePreset(id){const t=themePresets[id];if(!t)return;document.getElementById('themePrimary').value=t.primary;document.getElementById('themeSecondary').value=t.secondary;document.getElementById('themeAccent').value=t.accent;document.getElementById('themeBackground').value=t.background;}
const list=document.getElementById('widgetList'),order=document.getElementById('widgetOrder');function sync(){order.value=[...list.children].map(x=>x.dataset.id).join(',')}sync();let dragged=null;list.addEventListener('dragstart',e=>{dragged=e.target.closest('.widget-row');dragged?.classList.add('dragging')});list.addEventListener('dragend',()=>{dragged?.classList.remove('dragging');dragged=null;sync()});list.addEventListener('dragover',e=>{e.preventDefault();if(!dragged)return;const items=[...list.querySelectorAll('.widget-row:not(.dragging)')],next=items.find(x=>e.clientY<x.getBoundingClientRect().top+x.offsetHeight/2);list.insertBefore(dragged,next||null)})
</script></body></html>