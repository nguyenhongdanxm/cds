<?php
/** Điều khiển nội dung và giao diện trang chủ dành cho quản trị. */
if (!defined('DATA_PATH') || !function_exists('load_json')) return;

function cds_home_controls_file(): string { return DATA_PATH . '/dashboard_home_controls.json'; }

function cds_home_themes_catalog(): array {
    return [
        'default'=>['name'=>'Mặc định CDS','description'=>'Xanh hiện đại, sử dụng quanh năm.','primary'=>'#0f4c81','secondary'=>'#2563eb','accent'=>'#38bdf8','background'=>'#eef4f8','icon'=>'bi-palette'],
        'spring'=>['name'=>'Mùa xuân – Tết','description'=>'Đỏ, vàng ấm áp cho dịp Tết và đầu xuân.','primary'=>'#b91c1c','secondary'=>'#dc2626','accent'=>'#fbbf24','background'=>'#fff7ed','icon'=>'bi-flower1'],
        'school'=>['name'=>'Khai giảng','description'=>'Xanh dương, vàng tươi cho năm học mới.','primary'=>'#1d4ed8','secondary'=>'#2563eb','accent'=>'#facc15','background'=>'#eff6ff','icon'=>'bi-mortarboard'],
        'teachers'=>['name'=>'Ngày Nhà giáo','description'=>'Tím trang trọng dành cho các hoạt động 20/11.','primary'=>'#6d28d9','secondary'=>'#7c3aed','accent'=>'#f59e0b','background'=>'#f5f3ff','icon'=>'bi-book-half'],
        'national'=>['name'=>'Ngày lễ lớn','description'=>'Đỏ đậm và vàng cho các ngày lễ, sự kiện chính trị.','primary'=>'#991b1b','secondary'=>'#dc2626','accent'=>'#facc15','background'=>'#fff7ed','icon'=>'bi-star-fill'],
        'green'=>['name'=>'Xanh môi trường','description'=>'Xanh lá cho sự kiện môi trường, hoạt động cộng đồng.','primary'=>'#166534','secondary'=>'#16a34a','accent'=>'#84cc16','background'=>'#f0fdf4','icon'=>'bi-tree-fill'],
        'custom'=>['name'=>'Màu tùy chỉnh','description'=>'Sử dụng các màu do quản trị lựa chọn.','primary'=>'#0f4c81','secondary'=>'#2563eb','accent'=>'#38bdf8','background'=>'#eef4f8','icon'=>'bi-sliders'],
    ];
}

function cds_home_pages_catalog(): array {
    return [
        'admin'=>[
            'title'=>'Trang chủ sau đăng nhập', 'icon'=>'bi-speedometer2',
            'blocks'=>[
                'welcome'=>['title'=>'Khối chào mừng, đồng hồ và sinh nhật','selector'=>'section.welcome-card'],
                'stats'=>['title'=>'Số liệu lớp, học sinh, giáo viên','selector'=>'section.stat-grid'],
                'quick_actions'=>['title'=>'Thao tác thường dùng','selector'=>'section.quick-section'],
                'professional_feed'=>['title'=>'Công việc Chuyên môn','selector'=>'section.feed-panel'],
                'observations'=>['title'=>'Lịch dự giờ sắp tới','selector'=>'section.observation-panel'],
                'teacher_leave'=>['title'=>'Lịch nghỉ giáo viên','selector'=>'section.leave-panel'],
                'noitru_operation'=>['title'=>'Vận hành Nội trú hôm nay','selector'=>'section.operation-panel'],
            ],
        ],
        'index'=>[
            'title'=>'Trang hệ sinh thái công khai', 'icon'=>'bi-globe2',
            'blocks'=>[
                'topbar'=>['title'=>'Thanh thời gian và đăng nhập','selector'=>'.topbar'],
                'hero'=>['title'=>'Tên trường và tiêu đề','selector'=>'.hero'],
                'ecosystem'=>['title'=>'Sơ đồ các module hệ sinh thái','selector'=>'.stage'],
                'footer'=>['title'=>'Chân trang','selector'=>'.site-footer'],
            ],
        ],
    ];
}

function cds_home_controls_defaults(): array {
    $moduleIds=[];
    if (function_exists('get_ecosystem_modules')) foreach (get_ecosystem_modules() as $module) if (($module['status']??'')!=='soon') $moduleIds[]=(string)($module['id']??'');
    if (!$moduleIds) $moduleIds=['tintuc','chuyenmon','csdl','noitru','thidua'];
    $pageBlocks=[];
    foreach (cds_home_pages_catalog() as $pageId=>$page) $pageBlocks[$pageId]=array_keys($page['blocks']);
    return [
        'visible_modules'=>array_values(array_filter($moduleIds)),
        'birthday_enabled'=>true,
        'notices'=>[],
        'page_blocks'=>$pageBlocks,
        'theme'=>['id'=>'default','primary'=>'#0f4c81','secondary'=>'#2563eb','accent'=>'#38bdf8','background'=>'#eef4f8','event_title'=>''],
    ];
}

function cds_home_controls(): array {
    $defaults=cds_home_controls_defaults();
    $saved=load_json(cds_home_controls_file(),[]); if(!is_array($saved))$saved=[];
    $result=array_replace_recursive($defaults,$saved);
    $result['visible_modules']=array_values(array_unique(array_filter(array_map('strval',is_array($result['visible_modules']??null)?$result['visible_modules']:[]))));
    $result['birthday_enabled']=!array_key_exists('birthday_enabled',$result)||!empty($result['birthday_enabled']);
    $result['notices']=array_values(array_filter(is_array($result['notices']??null)?$result['notices']:[],fn($r)=>is_array($r)&&trim((string)($r['text']??''))!==''));
    foreach(cds_home_pages_catalog() as $pageId=>$page){$savedBlocks=$result['page_blocks'][$pageId]??array_keys($page['blocks']);$result['page_blocks'][$pageId]=array_values(array_intersect(array_keys($page['blocks']),array_map('strval',(array)$savedBlocks)));}
    $themes=cds_home_themes_catalog();$themeId=(string)($result['theme']['id']??'default');if(!isset($themes[$themeId]))$themeId='default';$result['theme']['id']=$themeId;
    return $result;
}

function cds_home_controls_color($value,$fallback): string { $v=trim((string)$value);return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?$v:$fallback; }

function cds_home_controls_save(array $data): void {
    $defaults=cds_home_controls_defaults();
    $visible=array_values(array_intersect($defaults['visible_modules'],array_values(array_unique(array_map('strval',$data['visible_modules']??[])))));
    $notices=[];foreach(($data['notices']??[]) as $row){if(!is_array($row))continue;$text=trim((string)($row['text']??''));if($text==='')continue;$type=(string)($row['type']??'info');if(!in_array($type,['info','success','warning','danger'],true))$type='info';$notices[]=['id'=>trim((string)($row['id']??''))?:('notice_'.bin2hex(random_bytes(4))),'text'=>function_exists('mb_substr')?mb_substr($text,0,500,'UTF-8'):substr($text,0,500),'type'=>$type,'active'=>!empty($row['active']),'created_at'=>(string)($row['created_at']??date('c'))];}
    $pageBlocks=[];foreach(cds_home_pages_catalog() as $pageId=>$page)$pageBlocks[$pageId]=array_values(array_intersect(array_keys($page['blocks']),array_map('strval',$data['page_blocks'][$pageId]??[])));
    $themes=cds_home_themes_catalog();$themeId=(string)($data['theme']['id']??'default');if(!isset($themes[$themeId]))$themeId='default';$base=$themes[$themeId];
    $theme=['id'=>$themeId,'primary'=>cds_home_controls_color($data['theme']['primary']??$base['primary'],$base['primary']),'secondary'=>cds_home_controls_color($data['theme']['secondary']??$base['secondary'],$base['secondary']),'accent'=>cds_home_controls_color($data['theme']['accent']??$base['accent'],$base['accent']),'background'=>cds_home_controls_color($data['theme']['background']??$base['background'],$base['background']),'event_title'=>trim((string)($data['theme']['event_title']??''))];
    save_json(cds_home_controls_file(),['visible_modules'=>$visible,'birthday_enabled'=>!empty($data['birthday_enabled']),'notices'=>array_slice($notices,-20),'page_blocks'=>$pageBlocks,'theme'=>$theme,'updated_at'=>date('c')]);
}

function cds_home_controls_escape(string $v): string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function cds_home_controls_notice_html(array $notices): string {$active=array_values(array_filter($notices,fn($r)=>!empty($r['active'])&&trim((string)($r['text']??''))!==''));if(!$active)return'';$icons=['info'=>'bi-info-circle-fill','success'=>'bi-check-circle-fill','warning'=>'bi-exclamation-triangle-fill','danger'=>'bi-exclamation-octagon-fill'];$html='<div class="home-admin-notices" style="display:grid;gap:.5rem;margin-top:.75rem">';foreach($active as $r){$t=in_array($r['type']??'',array_keys($icons),true)?$r['type']:'info';$html.='<div style="display:flex;align-items:flex-start;gap:.55rem;padding:.65rem .8rem;border-radius:12px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22)"><i class="bi '.$icons[$t].'"></i><span>'.cds_home_controls_escape((string)$r['text']).'</span></div>'; }return$html.'</div>';}

function cds_home_controls_remove_selector(string $html,string $selector): string {
    $class=ltrim($selector,'.');
    if(str_starts_with($selector,'.')) return preg_replace('/<([a-z0-9]+)([^>]*class="[^"]*\b'.preg_quote($class,'/').'\b[^"]*"[^>]*)>.*?<\/\1>/si','',$html,1)??$html;
    if(preg_match('/^([a-z0-9]+)\.([a-z0-9_-]+)$/i',$selector,$m)) return preg_replace('/<'.$m[1].'([^>]*class="[^"]*\b'.preg_quote($m[2],'/').'\b[^"]*"[^>]*)>.*?<\/'.$m[1].'>/si','',$html,1)??$html;
    return $html;
}

function cds_home_controls_theme_css(array $settings,string $page): string {
    $t=$settings['theme'];$event=trim((string)($t['event_title']??''));
    $css='<style id="cds-active-theme">:root{--cds-theme-primary:'.$t['primary'].';--cds-theme-secondary:'.$t['secondary'].';--cds-theme-accent:'.$t['accent'].';--cds-theme-background:'.$t['background'].'}';
    if($page==='admin')$css.='body{background:var(--cds-theme-background)!important}.app-header,.welcome-card{background:linear-gradient(135deg,var(--cds-theme-primary),var(--cds-theme-secondary))!important}.app-header .school-brand,.app-header .school-brand strong,.app-header .user-picker summary,.app-header .user-copy strong,.app-header .user-picker summary>i{color:#fff!important}.app-header .school-brand small,.app-header .user-copy small{color:rgba(255,255,255,.76)!important}.school-mark,.section-kicker{color:var(--cds-theme-accent)!important}.app-header .school-mark{background:rgba(255,255,255,.13)!important;color:var(--cds-theme-accent)!important;box-shadow:none}.quick-grid a{border-top-color:var(--cds-theme-secondary)!important}';
    else $css.='body{background-color:var(--cds-theme-primary)!important;background-image:radial-gradient(ellipse 110% 85% at 50% 42%,var(--cds-theme-secondary) 0%,var(--cds-theme-primary) 55%,#020f24 100%)!important}.auth-btn.admin{border-color:var(--cds-theme-accent)!important}.hero .line2{background:linear-gradient(90deg,#fff,var(--cds-theme-accent),#fff);-webkit-background-clip:text;background-clip:text;color:transparent}';
    $css.='</style>';
    if($event!=='')$css.='<div class="cds-event-ribbon" style="position:fixed;top:0;left:50%;transform:translateX(-50%);z-index:2000;padding:.28rem 1rem;border-radius:0 0 12px 12px;background:'.$t['accent'].';color:#172033;font-weight:800;font-size:.75rem;box-shadow:0 4px 14px rgba(0,0,0,.18)">'.cds_home_controls_escape($event).'</div>';
    return $css;
}

function cds_home_controls_filter_page(string $html): string {
    $settings=cds_home_controls();$script=basename((string)($_SERVER['SCRIPT_NAME']??''));$page=$script==='index.php'?'index':'admin';
    if($page==='admin'){
        if(empty($settings['birthday_enabled']))$html=preg_replace('/<div class="birthday-line">.*?<\/form><\/div>/s','',$html,1)??$html;
        $notice=cds_home_controls_notice_html($settings['notices']);if($notice!=='')$html=preg_replace('/(<div class="welcome-copy">.*?<h1>.*?<\/h1>)/s','$1'.$notice,$html,1)??$html;
        $html=preg_replace('/(<div class="user-menu">)/','$1<a href="dashboard_settings.php"><i class="bi bi-sliders"></i>Cấu hình trang chủ</a>',$html,1)??$html;
    }
    $enabled=array_flip($settings['page_blocks'][$page]??[]);$catalog=cds_home_pages_catalog()[$page]['blocks']??[];foreach($catalog as $id=>$meta)if(!isset($enabled[$id]))$html=cds_home_controls_remove_selector($html,$meta['selector']);
    $visible=array_flip($settings['visible_modules']);if(function_exists('get_ecosystem_modules'))foreach(get_ecosystem_modules() as $module){$id=(string)($module['id']??'');if($id!==''&&!isset($visible[$id])){$url=cds_home_controls_escape((string)($module['url']??''));if($url!=='')$html=preg_replace('/<a href="'.preg_quote($url,'/').'"[^>]*>.*?<\/a>/s','',$html)??$html;}}
    $theme=cds_home_controls_theme_css($settings,$page);return preg_replace('/<\/body>/i',$theme.'</body>',$html,1)??($html.$theme);
}

$homeScript=basename((string)($_SERVER['SCRIPT_NAME']??''));
if(in_array($homeScript,['admin.php','index.php'],true)&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    require_once __DIR__.'/modules.php';
    ob_start('cds_home_controls_filter_page');
}
