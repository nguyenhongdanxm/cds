<?php
/**
 * Dọn các thanh điều hướng cũ bên trong nội dung Chuyên môn.
 * Sidebar desktop và menu đáy mobile là nguồn điều hướng duy nhất.
 */
?>
<style id="cdsCmLegacyCleanup">
/* Các tab cấp trang đã có đầy đủ trong sidebar/menu đáy. */
body > .container > .nav-tabs,
body > .container > .nav-pills,
body > .container > ul.nav-tabs,
body > .container > ul.nav-pills,
body > .container > nav.nav-tabs,
body > .container > nav.nav-pills,
.cm-legacy-menu-hidden {
  display:none!important;
}

/* Trang không còn thanh menu cũ thì bỏ khoảng trống dư phía trên. */
body > .container > .nav-tabs + *,
body > .container > .nav-pills + * {
  margin-top:0!important;
}
</style>
<script>
(function(){
  function cleanLegacyMenus(){
    var groups = [
      ['Thông báo chuyên môn','Văn bản kế hoạch','Chỉ tiêu'],
      ['Kế hoạch','Báo cáo','PCCM']
    ];
    var candidates = document.querySelectorAll(
      'body > .container > .d-flex, body > .container > .btn-group, body > .container > .row, body > .container > nav, body > .container > section'
    );

    candidates.forEach(function(node){
      if (node.closest('.cm-desktop-sidebar,.cm-mobile-bottom,.cm-mobile-more')) return;
      var controls = Array.from(node.querySelectorAll('a,button'));
      if (controls.length < 2 || controls.length > 8) return;
      var texts = controls.map(function(el){ return (el.textContent || '').replace(/\s+/g,' ').trim(); });
      var duplicate = groups.some(function(group){
        return group.filter(function(label){
          return texts.some(function(text){ return text === label || text.indexOf(label) !== -1; });
        }).length >= 2;
      });
      if (duplicate) node.classList.add('cm-legacy-menu-hidden');
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cleanLegacyMenus);
  else cleanLegacyMenus();
})();
</script>
