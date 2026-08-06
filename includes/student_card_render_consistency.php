<?php
/** Đồng bộ chính xác thẻ mẫu với bản in và bổ sung mã số học sinh. */
if (defined('STUDENT_CARD_RENDER_CONSISTENCY')) return;
define('STUDENT_CARD_RENDER_CONSISTENCY', true);

function student_card_render_consistency_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</body>') === false) return $html;
    $addon = <<<'HTML'
<style id="studentCardRenderConsistencyStyle">
.student-code{font-size:inherit;line-height:inherit;color:inherit;text-align:inherit}
/* Cover dùng đường cong liền, không dùng lớp xoay/chéo gây răng cưa khi PDF */
.card-face .decor-top{
  height:18mm!important;
  background:linear-gradient(90deg,var(--main) 0%,var(--main) 45%,var(--second) 100%)!important;
  border-radius:0 0 58% 14%!important;
  overflow:hidden!important;
}
.card-face .decor-top::before{
  content:"";position:absolute;left:0;right:0;bottom:0;height:2.2mm;
  background:var(--accent);border-radius:70% 0 0 0;
}
.card-face .decor-top::after{display:none!important;content:none!important}
.card-face .decor-bottom{
  height:10mm!important;
  background:linear-gradient(90deg,var(--main),var(--second))!important;
  border-radius:62% 18% 0 0!important;
  overflow:hidden!important;
}
.card-face .decor-bottom::before{
  content:"";position:absolute;left:10%;right:-2%;top:0;height:2.3mm;
  background:var(--accent);border-radius:80% 0 45% 0;opacity:.92;
}
.card-face .decor-bottom::after{display:none!important;content:none!important}
.card-face,.card-face *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
@media print{
  html,body,.card-face,.card-face *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important}
  .fold-line::after{display:none!important;content:none!important}
  .fold-line{border-left:1px dashed #64748b!important}
  .decor-top,.decor-bottom,.rule-title{filter:none!important;transform:none!important}
}
</style>
<script id="studentCardRenderConsistencyScript">
(function(){
  const sampleRoot=document.getElementById('samplePreview');
  const printRoot=document.getElementById('printPreview');
  const fieldOptions=document.getElementById('fieldOptions');
  if(!sampleRoot||!printRoot||!fieldOptions)return;

  const selectors=['.logo','.agency','.school-name','.title','.photo','.student-name','.student-info','.front-qr','.rule-title','.rules','.back-qr','.back-note'];
  let syncing=false,timer=0;

  function savedTemplate(){
    try{return JSON.parse(localStorage.getItem('cdsCardTemplateV2')||'{}')}catch(e){return{}}
  }
  function ensureCodeToggle(){
    if(fieldOptions.querySelector('[data-key="showCode"]'))return;
    const cfg=savedTemplate();
    const label=document.createElement('label');
    label.className='col-6 form-check small';
    label.innerHTML='<input class="form-check-input field-toggle" data-key="showCode" type="checkbox" '+(cfg.showCode===false?'':'checked')+'><span class="form-check-label">Mã số học sinh</span>';
    fieldOptions.appendChild(label);
  }
  function codeFromRow(input){
    const small=input&&input.closest('.student-row')?.querySelector('small');
    if(!small)return'';
    const parts=small.textContent.split('·').map(x=>x.trim()).filter(Boolean);
    return parts.length>1?parts[1].replace(/\s*(Có ảnh|Chưa ảnh).*$/i,'').trim():'';
  }
  function showCode(){
    const cb=fieldOptions.querySelector('[data-key="showCode"]');
    return !cb||cb.checked;
  }
  function appendCode(info,code){
    if(!info)return;
    let el=info.querySelector('.student-code');
    if(!showCode()){if(el)el.remove();return}
    if(!el){el=document.createElement('div');el.className='student-code';info.appendChild(el)}
    el.innerHTML='Mã HS: <b>'+escapeHtml(code||'—')+'</b>';
  }
  function escapeHtml(v){return String(v||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
  function addCodes(){
    appendCode(sampleRoot.querySelector('.student-info'),'XM25010001');
    const checked=[...document.querySelectorAll('.print-student:checked')];
    printRoot.querySelectorAll('.fold-set').forEach((card,i)=>appendCode(card.querySelector('.student-info'),codeFromRow(checked[i])));
  }
  function copyElementStyle(source,target){
    if(!source||!target)return;
    target.style.cssText=source.style.cssText;
    target.classList.toggle('sce-nowrap',source.classList.contains('sce-nowrap'));
  }
  function syncPrintFromSample(){
    if(syncing)return;syncing=true;
    ensureCodeToggle();addCodes();
    const sample=sampleRoot.querySelector('.fold-set');
    if(sample){
      printRoot.querySelectorAll('.fold-set').forEach(card=>{
        ['front','back'].forEach(faceName=>{
          const sf=sample.querySelector('.card-face.'+faceName),tf=card.querySelector('.card-face.'+faceName);
          if(!sf||!tf)return;
          selectors.forEach(sel=>copyElementStyle(sf.querySelector(sel),tf.querySelector(sel)));
        });
      });
    }
    syncing=false;
  }
  function schedule(){clearTimeout(timer);timer=setTimeout(syncPrintFromSample,60)}
  new MutationObserver(schedule).observe(sampleRoot,{childList:true,subtree:true,attributes:true,attributeFilter:['style','class']});
  new MutationObserver(schedule).observe(printRoot,{childList:true,subtree:true});
  new MutationObserver(()=>{ensureCodeToggle();schedule()}).observe(fieldOptions,{childList:true});
  fieldOptions.addEventListener('change',e=>{if(e.target.matches('[data-key="showCode"]'))schedule()});
  document.getElementById('printCards')?.addEventListener('click',()=>{setTimeout(syncPrintFromSample,25)},true);
  ensureCodeToggle();schedule();
})();
</script>
HTML;
    return preg_replace('/<\/body>/i', $addon . '</body>', $html, 1) ?? $html;
}
ob_start('student_card_render_consistency_filter');
