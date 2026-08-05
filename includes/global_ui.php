<?php
/** Nạp CSS/JS giao diện dùng chung mà không phải sửa từng trang. */
if (defined('CDS_GLOBAL_UI_BUFFERED')) return;
define('CDS_GLOBAL_UI_BUFFERED', true);

function cds_global_ui_filter(string $html): string {
    if (stripos($html, '</head>') === false || stripos($html, '<html') === false) return $html;

    $asset = (defined('BASE_URL') ? BASE_URL : '/') . 'assets/cds-global-ui.css?v=20260805-1';
    $link = '<link rel="stylesheet" href="' . htmlspecialchars($asset, ENT_QUOTES, 'UTF-8') . '">';
    if (strpos($html, 'cds-global-ui.css') === false) {
        $html = preg_replace('/<\/head>/i', $link . '</head>', $html, 1) ?? $html;
    }

    $script = <<<'HTML'
<script id="cdsGlobalUiScript">
(function(){
  function prepareTables(){
    document.querySelectorAll('main table, .container table, .container-fluid table').forEach(function(table){
      if(table.closest('.table-responsive,.duty-matrix-wrap,.responsive-table')) return;
      if(table.scrollWidth <= table.parentElement.clientWidth) return;
      var wrap=document.createElement('div');wrap.className='table-responsive responsive-table';
      table.parentNode.insertBefore(wrap,table);wrap.appendChild(table);
    });
  }
  function markPublic(){if(location.pathname==='/'||/\/index\.php$/.test(location.pathname))document.body.classList.add('index-public')}
  function run(){markPublic();prepareTables()}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
  window.addEventListener('resize',function(){clearTimeout(window.__cdsResize);window.__cdsResize=setTimeout(prepareTables,180)},{passive:true});
})();
</script>
HTML;
    if (strpos($html, 'cdsGlobalUiScript') === false) {
        $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
    }
    return $html;
}

ob_start('cds_global_ui_filter');
