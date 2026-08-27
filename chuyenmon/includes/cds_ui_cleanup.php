<?php
/**
 * Dọn các thanh điều hướng cũ bên trong nội dung Chuyên môn.
 * Sidebar desktop và menu đáy mobile là nguồn điều hướng duy nhất.
 */
?>
<style id="cdsCmLegacyCleanup">
body > .container > .nav-tabs,
body > .container > .nav-pills,
body > .container > ul.nav-tabs,
body > .container > ul.nav-pills,
body > .container > nav.nav-tabs,
body > .container > nav.nav-pills,
.cm-legacy-menu-hidden {display:none!important;}
body > .container > .nav-tabs + *,body > .container > .nav-pills + * {margin-top:0!important;}
</style>
<script>
(function(){
  function cleanLegacyMenus(){
    var groups=[['Thông báo','Kế hoạch giáo dục','Chỉ tiêu'],['Kế hoạch','Báo cáo','PCCM']];
    var candidates=document.querySelectorAll('body > .container .d-flex, body > .container .btn-group, body > .container nav, body > .container section, body > .container header');
    candidates.forEach(function(node){
      if(node.closest('.cm-desktop-sidebar,.cm-mobile-bottom,.cm-mobile-more'))return;
      var controls=Array.from(node.querySelectorAll('a,button'));if(controls.length<2||controls.length>8)return;
      var texts=controls.map(function(el){return(el.textContent||'').replace(/\s+/g,' ').trim();});
      var duplicate=groups.some(function(group){return group.filter(function(label){return texts.some(function(text){return text===label;});}).length>=2;});
      if(duplicate)node.classList.add('cm-legacy-menu-hidden');
    });
    document.querySelectorAll('body > .container a, body > .container button').forEach(function(control){
      if((control.textContent||'').replace(/\s+/g,' ').trim()!=='Kế hoạch')return;
      var box=control.parentElement;while(box&&box!==document.body){var labels=Array.from(box.querySelectorAll('a,button')).map(function(el){return(el.textContent||'').replace(/\s+/g,' ').trim();});if(['Kế hoạch','Báo cáo','PCCM'].every(function(label){return labels.indexOf(label)!==-1;})){box.classList.add('cm-legacy-menu-hidden');break;}box=box.parentElement;}
    });
  }
  function schoolExport(){
    if(!/\/thoikhoabieu\.php$/i.test(location.pathname))return;
    var q=new URLSearchParams(location.search);if((q.get('tab')||'lookup')!=='lookup'||q.get('mode')!=='school')return;
    var week=q.get('week_id')||'',url='tkb_school_export.php'+(week?'?week_id='+encodeURIComponent(week):'');
    Array.from(document.querySelectorAll('button')).forEach(function(btn){
      var txt=(btn.textContent||'').replace(/\s+/g,' ').trim();
      if(/In TKB/i.test(txt)){btn.onclick=function(){location.href=url;};btn.innerHTML='<i class="bi bi-table"></i> Xuất bảng toàn trường';}
      if(/Chia sẻ ảnh/i.test(txt)){btn.onclick=function(){location.href=url;};btn.innerHTML='<i class="bi bi-image"></i> Xuất ảnh toàn trường';}
    });
  }
  function run(){cleanLegacyMenus();schoolExport();setTimeout(schoolExport,300);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
})();
</script>
