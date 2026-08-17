<?php
if (defined('CDS_MODULE_SWITCHER_RENDERED') || !function_exists('is_logged_in') || !is_logged_in()) return;
define('CDS_MODULE_SWITCHER_RENDERED', true);
if (!defined('URL_TIN_TUC')) define('URL_TIN_TUC', 'https://noitruxinman.edu.vn');
if (!defined('URL_CHUYEN_MON')) define('URL_CHUYEN_MON', '/chuyenmon/');
if (!defined('URL_CSDL')) define('URL_CSDL', '/csdl.php');
if (!defined('URL_NOITRU')) define('URL_NOITRU', '/noitru.php');
require_once __DIR__ . '/modules.php';
$switchUser = function_exists('current_user') ? current_user() : (function_exists('cds_user') ? cds_user() : ($_SESSION['cds_user'] ?? null));
if (!$switchUser) return;
$switchIsAdmin = ($switchUser['role'] ?? '') === 'admin';
$switchRootUrl = '/';
$switchRoutes = ['chuyenmon'=>'/chuyenmon/','vanban'=>'/vanban.php','thuvien'=>'/thuvien.php','csdl'=>'/csdl.php','hoclieu'=>'/hoclieu.php','noitru'=>'/noitru.php','thidua'=>'/thidua.php'];
$switchModules = [];
foreach (get_ecosystem_modules() as $module) {
    $id = (string)($module['id'] ?? '');
    if (($module['status'] ?? '') === 'soon') continue;
    if (isset($switchRoutes[$id])) $module['url'] = $switchRoutes[$id];
    $canView = function_exists('can_module') ? can_module($id, 'view') : true;
    if (($module['status'] ?? '') === 'link' || $switchIsAdmin || $canView) $switchModules[] = $module;
}
$switchCurrentUrl = (string)($_SERVER['REQUEST_URI'] ?? '');
$switchRequestPath = (string)parse_url($switchCurrentUrl, PHP_URL_PATH);
$switchCurrentPath = basename($switchRequestPath);
$switchAdminLinks = $switchIsAdmin ? [
    ['title'=>'Tài khoản và phân quyền','subtitle'=>'Quản lý người dùng, nhóm quyền','icon'=>'bi-people','url'=>$switchRootUrl.'users.php','color'=>'#7c3aed'],
    ['title'=>'Kho Google Drive','subtitle'=>'Kết nối và gán nơi lưu tệp','icon'=>'bi-google','url'=>$switchRootUrl.'admin.php?view=drive','color'=>'#0f9d58'],
    ['title'=>'Nhật ký hoạt động','subtitle'=>'Theo dõi thao tác trong hệ thống','icon'=>'bi-activity','url'=>$switchRootUrl.'activity.php','color'=>'#475569'],
] : [];
?>
<style>
.cds-launcher,.cds-launcher *{box-sizing:border-box}.cds-launcher{position:fixed;z-index:10050;right:18px;bottom:18px;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.cds-launcher-button{display:flex;align-items:center;gap:9px;min-height:48px;padding:0 17px;border:1px solid #ffffffb8;border-radius:15px;background:linear-gradient(135deg,#174f7e,#256ea6);color:#fff;box-shadow:0 10px 28px #0f274550;cursor:pointer;font-size:14px;font-weight:800}.cds-launcher-button:hover{transform:translateY(-1px);box-shadow:0 13px 32px #0f274560}.cds-launcher-button i{font-size:18px}.cds-launcher-button kbd{padding:2px 5px;border:1px solid #ffffff55;border-radius:5px;background:#ffffff18;color:#fff;font:600 10px/1.2 inherit}
.cds-launcher-backdrop{position:fixed;z-index:10049;inset:0;display:none;background:#0f172a80;backdrop-filter:blur(3px)}.cds-launcher.open .cds-launcher-backdrop{display:block}.cds-launcher-panel{position:fixed;z-index:10051;right:18px;bottom:78px;display:none;width:min(720px,calc(100vw - 36px));max-height:min(760px,calc(100vh - 100px));overflow:hidden;border:1px solid #dbe3ec;border-radius:20px;background:#f7f9fc;color:#172033;box-shadow:0 24px 80px #0f172a55}.cds-launcher.open .cds-launcher-panel{display:flex;flex-direction:column;animation:cdsLauncherIn .15s ease-out}
.cds-launcher-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:17px 18px 12px;background:#fff;border-bottom:1px solid #e5eaf0}.cds-launcher-head strong{display:block;font-size:18px}.cds-launcher-head small{display:block;margin-top:2px;color:#64748b;font-size:12px}.cds-launcher-close{width:38px;height:38px;border:0;border-radius:10px;background:#f1f5f9;color:#334155;cursor:pointer;font-size:22px}.cds-launcher-search{position:relative;padding:13px 18px 9px}.cds-launcher-search i{position:absolute;left:32px;top:27px;color:#64748b}.cds-launcher-search input{width:100%;min-height:47px;padding:0 42px;border:1px solid #d5dde7;border-radius:13px;background:#fff;color:#172033;font:600 14px inherit;outline:0}.cds-launcher-search input:focus{border-color:#2472aa;box-shadow:0 0 0 3px #2472aa1c}.cds-launcher-search span{position:absolute;right:30px;top:25px;color:#94a3b8;font-size:11px}
.cds-launcher-body{overflow:auto;padding:6px 18px 18px}.cds-launcher-section{margin-top:10px}.cds-launcher-section-title{display:flex;align-items:center;justify-content:space-between;margin:0 2px 8px;color:#64748b;font-size:11px;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.cds-launcher-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.cds-launcher-link{position:relative;display:flex;align-items:center;gap:10px;min-width:0;padding:11px;border:1px solid #e1e7ee;border-radius:13px;background:#fff;color:#1e293b;text-decoration:none;transition:.12s ease}.cds-launcher-link:hover{border-color:var(--cds-color,#2563eb);transform:translateY(-1px);box-shadow:0 7px 18px #0f172a12;color:#172033}.cds-launcher-link.active{border-color:var(--cds-color,#2563eb);background:#eef6ff}.cds-launcher-link>i{display:grid;place-items:center;flex:0 0 39px;width:39px;height:39px;border-radius:11px;background:#f1f5f9;color:var(--cds-color,#2563eb);font-size:18px}.cds-launcher-link span{min-width:0}.cds-launcher-link strong,.cds-launcher-link small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cds-launcher-link strong{font-size:13px}.cds-launcher-link small{margin-top:3px;color:#718096;font-size:10px}.cds-launcher-current{position:absolute;right:7px;top:6px;width:7px;height:7px;border-radius:50%;background:var(--cds-color,#2563eb)}.cds-launcher-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;text-align:center;font-size:13px}.cds-recent-grid:empty+.cds-launcher-empty{display:block}.cds-recent-grid:not(:empty)+.cds-launcher-empty{display:none}.cds-launcher-no-result{display:none;margin-top:10px}.cds-launcher.no-result .cds-launcher-no-result{display:block}.cds-launcher.no-result .cds-launcher-section:not(.recent-section){display:none}
@keyframes cdsLauncherIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:none}}@media(max-width:760px){.cds-launcher{right:12px;bottom:12px}.cds-launcher-button{min-height:46px;padding:0 14px}.cds-launcher-button kbd{display:none}.cds-launcher-panel{inset:10px;width:auto;max-height:none;border-radius:18px}.cds-launcher-grid{grid-template-columns:1fr 1fr}.cds-launcher-body{padding:5px 12px 18px}.cds-launcher-search{padding:12px}.cds-launcher-search i{left:26px;top:26px}.cds-launcher-search span{right:24px;top:24px}.cds-launcher-head{padding:14px}}@media(max-width:430px){.cds-launcher-grid{grid-template-columns:1fr}.cds-launcher-button span{display:none}.cds-launcher-button{width:48px;padding:0;justify-content:center;border-radius:50%}}
</style>
<div class="cds-launcher" id="cdsNavigationLauncher">
  <div class="cds-launcher-backdrop" data-cds-close></div>
  <button class="cds-launcher-button" type="button" data-cds-open aria-expanded="false"><i class="bi bi-grid-3x3-gap-fill"></i><span>Chuyển trang</span><kbd>Alt + M</kbd></button>
  <section class="cds-launcher-panel" role="dialog" aria-modal="true" aria-label="Bộ chuyển trang CDS">
    <header class="cds-launcher-head"><div><strong>Đi đến nhanh</strong><small><?=e($switchUser['name']??'')?> · Chỉ hiển thị chức năng được phép truy cập</small></div><button class="cds-launcher-close" type="button" data-cds-close aria-label="Đóng">×</button></header>
    <div class="cds-launcher-search"><i class="bi bi-search"></i><input type="search" data-cds-search placeholder="Tìm module hoặc chức năng..." autocomplete="off"><span>ESC để đóng</span></div>
    <div class="cds-launcher-body">
      <section class="cds-launcher-section recent-section"><div class="cds-launcher-section-title"><span>Trang vừa mở</span><button type="button" data-clear-recent style="border:0;background:none;color:#64748b;font-size:11px;cursor:pointer">Xóa lịch sử</button></div><div class="cds-launcher-grid cds-recent-grid" data-cds-recent></div><div class="cds-launcher-empty">Các trang vừa truy cập sẽ xuất hiện ở đây.</div></section>
      <section class="cds-launcher-section"><div class="cds-launcher-section-title"><span>Hệ sinh thái CDS</span><span><?=count($switchModules)+1?> mục</span></div><div class="cds-launcher-grid" data-cds-links>
        <a class="cds-launcher-link <?=$switchCurrentPath==='admin.php'&&!isset($_GET['view'])?'active':''?>" href="<?=e($switchRootUrl.'admin.php')?>" style="--cds-color:#2563eb" data-search="tổng quan trang chủ hệ sinh thái dashboard"><i class="bi bi-speedometer2"></i><span><strong>Tổng quan</strong><small>Trang điều hành hệ sinh thái</small></span><?php if($switchCurrentPath==='admin.php'&&!isset($_GET['view'])):?><em class="cds-launcher-current"></em><?php endif;?></a>
        <?php foreach($switchModules as $module):$moduleUrlPath=(string)parse_url($module['url']??'',PHP_URL_PATH);$modulePath=basename($moduleUrlPath);$active=$switchCurrentPath===$modulePath||($moduleUrlPath!=='/'&&str_ends_with($moduleUrlPath,'/')&&str_starts_with($switchRequestPath,$moduleUrlPath));?><a class="cds-launcher-link <?=$active?'active':''?>" href="<?=e($module['url'])?>" style="--cds-color:<?=e($module['color']??'#2563eb')?>" data-search="<?=e(($module['title']??'').' '.($module['subtitle']??'').' '.($module['id']??''))?>" <?=!empty($module['external'])?'target="_blank" rel="noopener"':''?>><i class="bi <?=e($module['icon']??'bi-grid')?>"></i><span><strong><?=e($module['title']??'')?></strong><small><?=e($module['subtitle']??'')?></small></span><?php if($active):?><em class="cds-launcher-current"></em><?php endif;?></a><?php endforeach;?>
      </div></section>
      <?php if($switchAdminLinks):?><section class="cds-launcher-section"><div class="cds-launcher-section-title"><span>Quản trị hệ thống</span></div><div class="cds-launcher-grid" data-cds-links><?php foreach($switchAdminLinks as $link):?><a class="cds-launcher-link" href="<?=e($link['url'])?>" style="--cds-color:<?=e($link['color'])?>" data-search="<?=e($link['title'].' '.$link['subtitle'])?>"><i class="bi <?=e($link['icon'])?>"></i><span><strong><?=e($link['title'])?></strong><small><?=e($link['subtitle'])?></small></span></a><?php endforeach;?></div></section><?php endif;?>
      <div class="cds-launcher-empty cds-launcher-no-result"><i class="bi bi-search"></i><br>Không tìm thấy chức năng phù hợp.</div>
    </div>
  </section>
</div>
<script>
(function(){
  var box=document.getElementById('cdsNavigationLauncher');if(!box)return;
  var openButton=box.querySelector('[data-cds-open]'),search=box.querySelector('[data-cds-search]'),recentBox=box.querySelector('[data-cds-recent]'),storageKey='cds_recent_pages_v2';
  function normalize(value){return String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()}
  function open(){box.classList.add('open');openButton.setAttribute('aria-expanded','true');setTimeout(function(){search.focus()},30)}
  function close(){box.classList.remove('open','no-result');openButton.setAttribute('aria-expanded','false');search.value='';filter('')}
  function filter(value){var query=normalize(value),visible=0;box.querySelectorAll('[data-cds-links] .cds-launcher-link').forEach(function(link){var match=!query||normalize(link.dataset.search+' '+link.textContent).includes(query);link.hidden=!match;if(match)visible++});box.classList.toggle('no-result',!!query&&visible===0)}
  function readRecent(){try{return JSON.parse(localStorage.getItem(storageKey)||'[]')}catch(e){return[]}}
  function writeRecent(rows){try{localStorage.setItem(storageKey,JSON.stringify(rows.slice(0,6)))}catch(e){}}
  function renderRecent(){recentBox.innerHTML='';readRecent().forEach(function(row){var link=document.createElement('a');link.className='cds-launcher-link';link.href=row.url;link.style.setProperty('--cds-color',row.color||'#2563eb');link.innerHTML='<i class="bi '+(row.icon||'bi-clock-history')+'"></i><span><strong></strong><small></small></span>';link.querySelector('strong').textContent=row.title;link.querySelector('small').textContent=row.module||'Trang gần đây';recentBox.appendChild(link)})}
  function rememberCurrent(){var path=location.pathname.split('/').pop()||'admin.php';if(/login|logout/i.test(path))return;var active=box.querySelector('[data-cds-links] .cds-launcher-link.active'),heading=document.querySelector('h1'),title=(heading?heading.textContent:document.title)||'CDS',module=active?active.querySelector('strong').textContent:'CDS',icon=active?active.querySelector('i').className.replace(/^bi\s+/,''):'bi-clock-history',color=active?active.style.getPropertyValue('--cds-color'):'#2563eb',url=location.pathname+location.search,rows=readRecent().filter(function(row){return row.url!==url});rows.unshift({title:title.trim().slice(0,80),module:module,url:url,icon:icon,color:color});writeRecent(rows)}
  openButton.addEventListener('click',function(e){e.stopPropagation();box.classList.contains('open')?close():open()});box.querySelectorAll('[data-cds-close]').forEach(function(el){el.addEventListener('click',close)});search.addEventListener('input',function(){filter(search.value)});box.querySelector('[data-clear-recent]').addEventListener('click',function(){writeRecent([]);renderRecent()});document.addEventListener('keydown',function(e){if(e.key==='Escape')close();if(e.altKey&&String(e.key).toLowerCase()==='m'){e.preventDefault();box.classList.contains('open')?close():open()}});rememberCurrent();renderRecent();
})();
</script>
