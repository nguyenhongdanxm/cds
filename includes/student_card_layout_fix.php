<?php
/** Hiệu chỉnh bố cục thẻ dọc, độ tương phản và nút lọc học sinh. */
if (defined('STUDENT_CARD_LAYOUT_FIX')) return;
define('STUDENT_CARD_LAYOUT_FIX', true);

function student_card_layout_fix_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</body>') === false) return $html;
    $addon = <<<'HTML'
<style id="studentCardLayoutFixStyle">
/* Thẻ dọc 86 x 54 mm: thu gọn và giữ toàn bộ nội dung trong khung. */
.sc-duplex-sheet{font-family:Arial,"Helvetica Neue",sans-serif}
.sc-face{width:54mm!important;height:86mm!important;color:#132238!important}
.sc-face-content{padding:2.4mm 3mm 3.8mm!important;justify-content:flex-start!important}
.sc-wave-top{height:10.5mm!important}
.sc-wave-bottom{height:7mm!important}
.sc-logo-print{width:10.5mm!important;height:10.5mm!important;margin-top:.1mm!important}
.sc-agency{font-size:5.7pt!important;line-height:1.05!important;margin-top:.45mm!important;color:#17233b!important}
.sc-school-name{font-size:6.4pt!important;line-height:1.08!important;max-width:47mm!important;color:#17233b!important}
.sc-card-title2{
  width:100%!important;
  font-family:Arial,"Helvetica Neue",sans-serif!important;
  font-size:13.2pt!important;
  line-height:1!important;
  letter-spacing:.01em!important;
  white-space:nowrap!important;
  color:#d71920!important;
  margin:2.2mm 0 1.5mm!important;
  text-shadow:0 .25mm .25mm rgba(255,255,255,.75)
}
.sc-photo2{width:21.5mm!important;height:28.7mm!important;border-radius:1.4mm!important}
.sc-student-name2{
  max-width:47mm!important;
  font-size:8.7pt!important;
  line-height:1.05!important;
  margin-top:1.2mm!important;
  color:var(--sc-main)!important;
  white-space:nowrap!important;
  overflow:hidden!important;
  text-overflow:ellipsis!important
}
.sc-info2{font-size:6.6pt!important;line-height:1.22!important;margin-top:.65mm!important;color:#17233b!important}
.sc-info2>div{display:inline-block;margin:0 .7mm .25mm}
.sc-qr2{width:14.5mm!important;height:14.5mm!important;margin-top:.7mm!important;margin-bottom:4.2mm!important}
.sc-qr2 img,.sc-qr2 canvas{width:14.5mm!important;height:14.5mm!important}
.sc-rule-title{font-size:8.8pt!important;line-height:1.05!important;padding:1.1mm 3mm!important;margin:.8mm 0 1.8mm!important;color:#fff!important}
.sc-rules{font-size:6.25pt!important;line-height:1.3!important;color:#17233b!important;overflow:hidden!important;max-height:49mm!important}
.sc-back .sc-qr2{margin-top:auto!important;margin-bottom:.8mm!important}
.sc-back-note{font-size:5.8pt!important;line-height:1.2!important;margin:0 0 3.6mm!important;color:#26364c!important}
.sc-fold:after{font-size:5.2pt!important}

/* Bộ lọc rõ ràng, có nút thực thi và thông báo kết quả. */
#scFilterActionRow{display:flex;gap:.5rem;align-items:center;margin-top:.55rem;flex-wrap:wrap}
#scApplyFilter{font-weight:700}
#scFilterResult{font-size:.78rem;color:#52667d}

@media(max-width:700px){
  .sc-duplex-sheet{transform-origin:top center}
}
@media print{
  .sc-face-content{padding:2.4mm 3mm 3.8mm!important}
  .sc-card-title2{color:#d71920!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .sc-wave-top,.sc-wave-bottom,.sc-rule-title{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>
<script id="studentCardLayoutFixScript">
(function(){
  function visibleStudentCount(){
    return Array.from(document.querySelectorAll('.student-choice')).filter(function(row){
      return row.style.display !== 'none';
    }).length;
  }
  function updateCount(){
    var out=document.getElementById('scFilterResult');
    if(out) out.textContent='Đang hiển thị '+visibleStudentCount()+' học sinh';
  }
  function applyFilter(){
    ['gradeFilter','classFilter','photoFilter'].forEach(function(id){
      var el=document.getElementById(id);if(el)el.dispatchEvent(new Event('change',{bubbles:true}));
    });
    var search=document.getElementById('searchFilter');
    if(search)search.dispatchEvent(new Event('input',{bubbles:true}));
    setTimeout(updateCount,0);
  }
  function setup(){
    if(!/\/csdl_student_cards\.php$/.test(location.pathname)||document.getElementById('scApplyFilter'))return;
    var search=document.getElementById('searchFilter');
    var selectVisible=document.getElementById('selectVisible');
    if(!search||!selectVisible)return;
    var row=document.createElement('div');row.id='scFilterActionRow';
    row.innerHTML='<button type="button" id="scApplyFilter" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Lọc / Tìm</button><span id="scFilterResult"></span>';
    selectVisible.parentElement.insertBefore(row,selectVisible.parentElement.firstChild);
    document.getElementById('scApplyFilter').addEventListener('click',applyFilter);
    search.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();applyFilter();}});
    ['gradeFilter','classFilter','photoFilter'].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener('change',function(){setTimeout(updateCount,0)});});
    search.addEventListener('input',function(){setTimeout(updateCount,0)});
    updateCount();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup);else setup();
})();
</script>
HTML;
    return preg_replace('/<\/body>/i', $addon . '</body>', $html, 1) ?? $html;
}
ob_start('student_card_layout_fix_filter');
