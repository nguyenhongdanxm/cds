<?php
/**
 * Hỗ trợ số tiết lẻ đến 0,1 trong Phân công chuyên môn.
 * File được chép vào chuyenmon/includes và nạp từ header khi deploy.
 */
?>
<script id="cdsFractionalPeriods">
(function(){
  function isPeriodField(el){
    if(!el || el.tagName !== 'INPUT') return false;
    var type=(el.getAttribute('type')||'').toLowerCase();
    if(type !== 'number' && type !== 'text') return false;
    var key=[el.name||'',el.id||'',el.getAttribute('placeholder')||'',el.getAttribute('aria-label')||''].join(' ').toLowerCase();
    if(/(?:^|[_\-\s])(so[_\-]?tiet|tiet|dinh[_\-]?muc|tiet[_\-]?tuan|so[_\-]?tuan)(?:$|[_\-\s])/.test(key)) return true;
    var label='';
    if(el.id){var lb=document.querySelector('label[for="'+CSS.escape(el.id)+'"]');if(lb)label=lb.textContent||'';}
    if(!label){var parent=el.closest('label,.form-group,.mb-3,.col,.row');if(parent)label=parent.textContent||'';}
    return /số\s*tiết|tiết\s*\/\s*tuần|định\s*mức/i.test(label);
  }
  function prepare(root){
    (root||document).querySelectorAll('input').forEach(function(el){
      if(!isPeriodField(el))return;
      el.setAttribute('step','0.1');
      if(!el.hasAttribute('inputmode'))el.setAttribute('inputmode','decimal');
      el.addEventListener('blur',function(){
        var v=(el.value||'').trim().replace(',','.');
        if(v==='')return;
        var n=Number(v);if(!Number.isFinite(n))return;
        el.value=(Math.round(n*10)/10).toString();
      });
    });
  }
  prepare(document);
  new MutationObserver(function(list){list.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1)prepare(n);});});}).observe(document.documentElement,{childList:true,subtree:true});
  document.addEventListener('submit',function(e){
    e.target.querySelectorAll('input').forEach(function(el){if(isPeriodField(el))el.value=(el.value||'').replace(',','.');});
  },true);
})();
</script>
