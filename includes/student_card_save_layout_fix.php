<?php
/** Giữ nguyên bố cục thẻ khi lưu mẫu, đổi bước và dựng lại DOM. */
if (defined('STUDENT_CARD_SAVE_LAYOUT_FIX')) return;
define('STUDENT_CARD_SAVE_LAYOUT_FIX', true);

function student_card_save_layout_fix_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</body>') === false) return $html;
    $addon = <<<'HTML'
<script id="studentCardSaveLayoutFixScript">
(function(){
  const sampleRoot=document.getElementById('samplePreview');
  const printRoot=document.getElementById('printPreview');
  if(!sampleRoot||!printRoot)return;

  const SELECTORS=['.logo','.agency','.school-name','.title','.photo','.student-name','.student-info','.front-qr','.rule-title','.rules','.back-qr','.back-note'];
  const STORE='cdsStudentCardDomLayoutSnapshotV1';
  let snapshot=null, locked=false, timer=0;

  function currentType(){
    return document.querySelector('.type-card.active')?.dataset.type ||
      (sampleRoot.querySelector('.card-face.horizontal')?'horizontal':'vertical');
  }
  function take(){
    const set=sampleRoot.querySelector('.fold-set');
    if(!set)return snapshot;
    const data={type:currentType(),faces:{}};
    ['front','back'].forEach(faceName=>{
      const face=set.querySelector('.card-face.'+faceName);if(!face)return;
      data.faces[faceName]={};
      SELECTORS.forEach(sel=>{
        const el=face.querySelector(sel);if(!el)return;
        data.faces[faceName][sel]={
          style:el.getAttribute('style')||'',
          nowrap:el.classList.contains('sce-nowrap')
        };
      });
    });
    snapshot=data;
    try{localStorage.setItem(STORE,JSON.stringify(data))}catch(e){}
    return data;
  }
  function load(){
    if(snapshot)return snapshot;
    try{snapshot=JSON.parse(localStorage.getItem(STORE)||'null')}catch(e){snapshot=null}
    return snapshot;
  }
  function applyTo(root,data){
    if(!root||!data||data.type!==currentType())return;
    root.querySelectorAll('.fold-set').forEach(set=>{
      ['front','back'].forEach(faceName=>{
        const face=set.querySelector('.card-face.'+faceName),saved=data.faces?.[faceName];
        if(!face||!saved)return;
        SELECTORS.forEach(sel=>{
          const el=face.querySelector(sel),item=saved[sel];if(!el||!item)return;
          if(item.style)el.setAttribute('style',item.style);else el.removeAttribute('style');
          el.classList.toggle('sce-nowrap',!!item.nowrap);
        });
      });
    });
  }
  function restore(){
    const data=load();if(!data)return;
    locked=true;
    applyTo(sampleRoot,data);
    applyTo(printRoot,data);
    requestAnimationFrame(()=>{locked=false});
  }
  function restoreSeveral(){
    clearTimeout(timer);
    [0,40,100,180].forEach(ms=>setTimeout(restore,ms));
  }
  function lockBeforeAction(){
    take();locked=true;
    setTimeout(()=>{locked=false;restoreSeveral()},20);
  }

  // Chụp bố cục ở capture phase, trước khi handler gốc gọi renderSample/showStep.
  document.getElementById('saveTemplate')?.addEventListener('click',lockBeforeAction,true);
  document.querySelectorAll('.step-btn').forEach(btn=>btn.addEventListener('click',lockBeforeAction,true));
  document.querySelectorAll('.type-card').forEach(btn=>btn.addEventListener('click',()=>{take();setTimeout(()=>{snapshot=null;try{localStorage.removeItem(STORE)}catch(e){}},0)},true));
  document.getElementById('printCards')?.addEventListener('click',()=>{take();restoreSeveral()},true);

  // Khi thẻ mẫu hoặc danh sách in vừa dựng lại, áp snapshot thay vì đo lại.
  const observer=new MutationObserver(()=>{if(!locked)restoreSeveral()});
  observer.observe(sampleRoot,{childList:true,subtree:true});
  observer.observe(printRoot,{childList:true,subtree:true});

  // Trước khi cửa sổ in mở, đảm bảo bản in nhận đúng bố cục cuối cùng.
  window.addEventListener('beforeprint',()=>{take();restore()});
  window.addEventListener('afterprint',restoreSeveral);

  // Khi quay lại bước thiết kế, khôi phục ngay sau khi vùng được hiện lại.
  document.addEventListener('click',e=>{
    const b=e.target.closest('.step-btn');
    if(b&&b.dataset.step==='design')setTimeout(restoreSeveral,20);
  });
})();
</script>
HTML;
    return preg_replace('/<\/body>/i', $addon . '</body>', $html, 1) ?? $html;
}
ob_start('student_card_save_layout_fix_filter');
