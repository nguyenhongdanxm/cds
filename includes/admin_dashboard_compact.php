<?php
/** Nâng cấp riêng giao diện admin.php, không thay đổi nghiệp vụ dashboard lõi. */
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'admin.php' || (string)($_GET['view'] ?? '') !== '') return;

$latestDocuments = [];
try {
    require_once __DIR__ . '/vanban_store.php';
    if (can_module('vanban', 'view') || can_perm('vb.quanly') || can_perm('vb.layso') || can_perm('vb.hosoluutru')) {
        $rows = vb_rows(VANBAN_DOCUMENTS_FILE);
        usort($rows, static function ($a, $b): int {
            $ad = (string)($a['updated_at'] ?? $a['created_at'] ?? $a['issued_date'] ?? '');
            $bd = (string)($b['updated_at'] ?? $b['created_at'] ?? $b['issued_date'] ?? '');
            return strcmp($bd, $ad);
        });
        foreach (array_slice($rows, 0, 5) as $row) {
            $path = (string)($row['file_path'] ?? '');
            $latestDocuments[] = [
                'title'=>(string)($row['title'] ?? 'Văn bản'),
                'symbol'=>(string)($row['symbol'] ?? ''),
                'issuer'=>(string)($row['issuer'] ?? ''),
                'issued_date'=>(string)($row['issued_date'] ?? ''),
                'url'=>$path !== '' ? vb_file_url($path) : (BASE_URL . 'vanban.php?tab=documents'),
            ];
        }
    }
} catch (Throwable $e) {
    $latestDocuments = [];
}
$latestDocumentsJson = json_encode($latestDocuments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$vanbanUrl = defined('BASE_URL') ? BASE_URL . 'vanban.php' : 'vanban.php';
$checkUrl = defined('BASE_URL') ? BASE_URL . 'noitru_exit_check.php' : 'noitru_exit_check.php';
$timetableUrl = defined('BASE_URL') ? BASE_URL . 'chuyenmon/thoikhoabieu.php' : 'chuyenmon/thoikhoabieu.php';
$lessonBookUrl = defined('BASE_URL') ? BASE_URL . 'chuyenmon/sodaubai.php' : 'chuyenmon/sodaubai.php';

ob_start(static function (string $html) use ($latestDocumentsJson, $vanbanUrl, $checkUrl, $timetableUrl, $lessonBookUrl): string {
    $script = <<<'HTML'
<style id="cds-dashboard-compact-v2">
.feed-row{min-width:0;max-width:100%;overflow:hidden}.feed-copy{min-width:0;overflow:hidden}.feed-copy strong{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.feed-copy small{display:-webkit-box!important;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;overflow-wrap:anywhere;word-break:break-word;line-height:1.35}.schedule-pill{flex:0 0 auto;max-width:94px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.latest-docs-panel .doc-list{display:grid}.latest-docs-panel .doc-row{display:grid;grid-template-columns:38px minmax(0,1fr) auto;align-items:center;gap:10px;min-height:58px;padding:8px 0;border-top:1px solid #edf2f7;color:var(--ink);text-decoration:none}.latest-docs-panel .doc-row>i{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#f2edff;color:#6f42c1;font-size:17px}.latest-docs-panel .doc-copy{min-width:0}.latest-docs-panel .doc-copy strong,.latest-docs-panel .doc-copy small{display:block}.latest-docs-panel .doc-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}.latest-docs-panel .doc-copy small{margin-top:3px;color:var(--muted);font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.latest-docs-panel .doc-row>span{font-size:10px;color:#75879a;white-space:nowrap}.quick-grid.cds-six-actions{display:grid!important;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.quick-grid.cds-six-actions a{min-width:0}.module-grid .cds-vanban-added{--module-color:#6f42c1}.stat-grid.cds-compact-stats .stat-card{min-height:86px;padding:13px 16px}.stat-grid.cds-compact-stats .stat-card>i{width:41px;height:41px;flex-basis:41px}.stat-grid.cds-compact-stats .stat-card strong{font-size:23px}
@media(max-width:900px){.quick-grid.cds-six-actions{grid-template-columns:repeat(3,minmax(0,1fr))}.content-grid{grid-template-columns:1fr!important}.feed-panel,.side-stack,.operation-panel{grid-column:1!important}.side-stack{display:grid!important;grid-template-columns:1fr 1fr;gap:12px}.latest-docs-panel{grid-column:1/-1}}
@media(max-width:680px){.dashboard{width:calc(100% - 18px)!important;padding-top:10px}.welcome-card{min-height:auto!important}.stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:7px!important;margin:10px 0!important}.stat-grid .stat-card{display:grid!important;place-items:center;text-align:center;gap:5px!important;min-height:82px!important;padding:9px 5px!important}.stat-grid .stat-card>i{width:34px!important;height:34px!important;flex-basis:34px!important;font-size:16px!important}.stat-grid .stat-card div{display:block!important;min-width:0}.stat-grid .stat-card span{display:block;font-size:10px!important}.stat-grid .stat-card strong{display:block;font-size:20px!important}.stat-grid .stat-card small{display:none!important}.quick-section{margin:12px 0!important}.quick-grid.cds-six-actions{grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.quick-grid.cds-six-actions a{display:flex!important;flex-direction:column;justify-content:center;gap:5px!important;min-height:74px!important;padding:8px 4px!important;text-align:center;font-size:11px!important}.quick-grid.cds-six-actions a>i:first-child{width:34px!important;height:34px!important;font-size:16px!important}.quick-grid.cds-six-actions a>i:last-child{display:none}.content-grid{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}.panel{padding:13px!important;border-radius:16px!important}.side-stack{grid-template-columns:1fr!important}.feed-row{align-items:flex-start!important;position:relative;padding:10px 2px!important}.feed-copy strong{padding-right:4px;white-space:normal!important;display:-webkit-box!important;-webkit-line-clamp:2;-webkit-box-orient:vertical}.schedule-pill{position:absolute;right:2px;bottom:6px;max-width:82px;font-size:8px!important}.feed-copy small{padding-right:88px}.module-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.module-grid>a{min-height:62px!important;padding:8px!important}.module-grid small{display:none!important}.latest-docs-panel .doc-row{grid-template-columns:34px minmax(0,1fr);gap:8px}.latest-docs-panel .doc-row>span{grid-column:2;font-size:9px}.latest-docs-panel .doc-row>i{width:34px;height:34px}.operation-grid{grid-template-columns:1fr!important}.operation-grid article{min-height:76px!important}}
</style>
<script id="cds-dashboard-compact-script">
(function(){
 const docs=__LATEST_DOCS__;
 const vanbanUrl=__VANBAN_URL__;
 const checkUrl=__CHECK_URL__;
 const timetableUrl=__TIMETABLE_URL__,lessonBookUrl=__LESSON_BOOK_URL__;
 function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
 const stats=document.querySelector('.stat-grid');if(stats)stats.classList.add('cds-compact-stats');
 const quick=document.querySelector('.quick-grid');
 if(quick){
   const links=Array.from(quick.querySelectorAll('a'));
   const find=(name)=>links.find(a=>(a.textContent||'').toLowerCase().includes(name));
   const attendance=find('điểm danh');
   const meals=find('báo ăn');
   let timetable=find('thời khóa biểu');
   const professional=find('chuyên môn');
   if(!timetable){
      timetable=document.createElement('a');
      timetable.style.setProperty('--quick-color','#2563eb');
      timetable.innerHTML='<i class="bi bi-calendar3"></i><span>Thời khóa biểu</span><i class="bi bi-arrow-up-right"></i>';
   }
   timetable.href=timetableUrl;
   let lessonBook=links.find(a=>(a.textContent||'').toLowerCase().includes('sổ đầu bài'));
   if(!lessonBook){lessonBook=document.createElement('a');lessonBook.href=lessonBookUrl;lessonBook.style.setProperty('--quick-color','#1f4e79');lessonBook.innerHTML='<i class="bi bi-journal-check"></i><span>Sổ đầu bài</span><i class="bi bi-arrow-up-right"></i>';}
   const wanted=[attendance,meals,timetable,lessonBook,professional].filter(Boolean);
   let check=links.find(a=>(a.textContent||'').toLowerCase().trim()==='kiểm tra');
   if(!check){check=document.createElement('a');check.href=checkUrl;check.style.setProperty('--quick-color','#0f766e');check.innerHTML='<i class="bi bi-qr-code-scan"></i><span>Kiểm tra</span><i class="bi bi-arrow-up-right"></i>';}
   wanted.push(check);quick.replaceChildren(...wanted);quick.classList.add('cds-six-actions');
 }
 const side=document.querySelector('.side-stack');
 if(side&&!document.querySelector('.latest-docs-panel')){
   const panel=document.createElement('section');panel.className='panel latest-docs-panel';
   let rows='';
   docs.forEach(d=>{const meta=[d.symbol,d.issuer].filter(Boolean).join(' · ');const date=d.issued_date?d.issued_date.split('-').reverse().join('/'):'';rows+='<a class="doc-row" href="'+esc(d.url)+'"><i class="bi bi-file-earmark-text-fill"></i><span class="doc-copy"><strong>'+esc(d.title)+'</strong><small>'+esc(meta||'Văn bản cập nhật')+'</small></span><span>'+esc(date)+'</span></a>'});
   if(!rows)rows='<div class="mini-empty"><i class="bi bi-file-earmark"></i> Chưa có văn bản cập nhật</div>';
   panel.innerHTML='<div class="panel-head"><div><span class="section-kicker">Văn bản mới</span><h2>5 văn bản cập nhật gần nhất</h2></div><a href="'+esc(vanbanUrl)+'?tab=documents">Xem tất cả</a></div><div class="doc-list">'+rows+'</div>';
   side.prepend(panel);
 }
 const moduleGrid=document.querySelector('.module-grid');
 if(moduleGrid&&!Array.from(moduleGrid.querySelectorAll('a')).some(a=>(a.textContent||'').trim().toLowerCase().startsWith('văn bản'))){
   const a=document.createElement('a');a.href=vanbanUrl;a.className='cds-vanban-added';a.style.setProperty('--module-color','#6f42c1');a.innerHTML='<i class="bi bi-file-earmark-text"></i><span><strong>Văn bản</strong><small>Văn thư nội bộ · văn bản cập nhật</small></span><i class="bi bi-chevron-right"></i>';moduleGrid.appendChild(a);
 }
})();
</script>
HTML;
    $script = str_replace('__LATEST_DOCS__', $latestDocumentsJson ?: '[]', $script);
    $script = str_replace('__VANBAN_URL__', json_encode($vanbanUrl, JSON_UNESCAPED_SLASHES), $script);
    $script = str_replace('__CHECK_URL__', json_encode($checkUrl, JSON_UNESCAPED_SLASHES), $script);
    $script = str_replace('__TIMETABLE_URL__', json_encode($timetableUrl, JSON_UNESCAPED_SLASHES), $script);
    $script = str_replace('__LESSON_BOOK_URL__', json_encode($lessonBookUrl, JSON_UNESCAPED_SLASHES), $script);
    return str_replace('</body>', $script . '</body>', $html);
});
