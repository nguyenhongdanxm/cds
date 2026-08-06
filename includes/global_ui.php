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

  function setupAssignmentSync(){
    if(!/\/noitru_assign\.php$/.test(location.pathname)||document.getElementById('cdsAssignSyncForm'))return;
    var head=document.querySelector('.nt-page-head');
    if(!head)return;
    var mode=new URLSearchParams(location.search).get('mode')==='meals'?'meals':'rooms';
    var label=mode==='rooms'?'phòng':'mâm';
    var actions=head.querySelector('.d-flex.gap-2.flex-wrap')||head.lastElementChild;
    if(!actions)return;
    var form=document.createElement('form');
    form.id='cdsAssignSyncForm';form.method='post';form.action='/noitru_assign_sync.php';form.className='d-inline';
    form.innerHTML='<input type="hidden" name="mode" value="'+mode+'"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-database-check"></i> Cập nhật '+label+' vào CSDL</button>';
    form.addEventListener('submit',function(e){
      if(!confirm('Cập nhật kết quả chia '+label+' hiện tại vào hồ sơ học sinh trong Cơ sở dữ liệu? Dữ liệu '+label+' cũ của các học sinh đã chia sẽ được thay thế.'))e.preventDefault();
    });
    actions.insertBefore(form,actions.firstChild);
  }

  function setupAssignmentFilters(){
    if(!/\/noitru_assign\.php$/.test(location.pathname))return;
    var bulkForm=document.getElementById('bulkAssignForm');
    var groups=document.querySelector('.assign-groups');
    if(!bulkForm||!groups||document.getElementById('cdsAssignFilters'))return;

    var rows=Array.from(groups.querySelectorAll('.assign-student'));
    if(!rows.length)return;
    var classes={};
    rows.forEach(function(row){
      var meta=(row.querySelector('.assign-meta')||{}).textContent||'';
      var className=(meta.split('·')[0]||'').trim();
      if(className)classes[className]=1;
      row.dataset.cdsClass=className;
      var normalized=meta.toLowerCase();
      row.dataset.cdsGender=/\bnữ\b/.test(normalized)?'female':(/\bnam\b/.test(normalized)?'male':'');
      row.dataset.cdsAssigned=row.closest('section')&&/chưa chia/i.test((row.closest('section').querySelector('.assign-group-head')||{}).textContent||'')?'no':'yes';
      row.dataset.cdsName=((row.querySelector('.assign-name')||{}).textContent||'').trim().toLowerCase();
    });

    var bar=document.createElement('div');
    bar.id='cdsAssignFilters';
    bar.className='assign-card p-3 mb-3';
    bar.innerHTML=''
      +'<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">'
      +'<h6 class="fw-bold mb-0"><i class="bi bi-funnel"></i> Lọc học sinh để gán nhanh</h6>'
      +'<span class="small text-muted" id="cdsAssignFilterCount"></span></div>'
      +'<div class="row g-2 align-items-end">'
      +'<div class="col-12 col-md-3"><label class="form-label small mb-0">Lớp</label><select id="cdsAssignClass" class="form-select form-select-sm"><option value="">Tất cả lớp</option></select></div>'
      +'<div class="col-6 col-md-2"><label class="form-label small mb-0">Giới tính</label><select id="cdsAssignGender" class="form-select form-select-sm"><option value="">Tất cả</option><option value="male">Nam</option><option value="female">Nữ</option></select></div>'
      +'<div class="col-6 col-md-2"><label class="form-label small mb-0">Trạng thái</label><select id="cdsAssignState" class="form-select form-select-sm"><option value="">Tất cả</option><option value="no">Chưa chia</option><option value="yes">Đã chia</option></select></div>'
      +'<div class="col-12 col-md-3"><label class="form-label small mb-0">Tìm học sinh</label><input id="cdsAssignSearch" class="form-control form-control-sm" placeholder="Nhập tên học sinh"></div>'
      +'<div class="col-6 col-md-1"><button type="button" id="cdsAssignSelectVisible" class="btn btn-sm btn-outline-primary w-100">Chọn</button></div>'
      +'<div class="col-6 col-md-1"><button type="button" id="cdsAssignClearSelection" class="btn btn-sm btn-outline-secondary w-100">Bỏ chọn</button></div>'
      +'</div>';
    bulkForm.closest('.assign-card').insertAdjacentElement('afterend',bar);

    var classSelect=document.getElementById('cdsAssignClass');
    Object.keys(classes).sort(function(a,b){return a.localeCompare(b,'vi',{numeric:true})}).forEach(function(name){
      var option=document.createElement('option');option.value=name;option.textContent=name;classSelect.appendChild(option);
    });
    var genderSelect=document.getElementById('cdsAssignGender');
    var stateSelect=document.getElementById('cdsAssignState');
    var searchInput=document.getElementById('cdsAssignSearch');
    var count=document.getElementById('cdsAssignFilterCount');

    function apply(){
      var c=classSelect.value,g=genderSelect.value,s=stateSelect.value,q=searchInput.value.trim().toLowerCase(),visible=0;
      rows.forEach(function(row){
        var show=(!c||row.dataset.cdsClass===c)&&(!g||row.dataset.cdsGender===g)&&(!s||row.dataset.cdsAssigned===s)&&(!q||row.dataset.cdsName.indexOf(q)>=0);
        row.style.display=show?'':'none';if(show)visible++;
      });
      groups.querySelectorAll('section.assign-card').forEach(function(section){
        var any=Array.from(section.querySelectorAll('.assign-student')).some(function(row){return row.style.display!=='none'});
        section.style.display=any?'':'none';
      });
      count.textContent='Đang hiện '+visible+' / '+rows.length+' học sinh';
    }
    [classSelect,genderSelect,stateSelect].forEach(function(el){el.addEventListener('change',apply)});
    searchInput.addEventListener('input',apply);
    document.getElementById('cdsAssignSelectVisible').addEventListener('click',function(){rows.forEach(function(row){if(row.style.display!=='none'){var cb=row.querySelector('input[type="checkbox"][name="student_ids[]"]');if(cb)cb.checked=true}})});
    document.getElementById('cdsAssignClearSelection').addEventListener('click',function(){rows.forEach(function(row){var cb=row.querySelector('input[type="checkbox"][name="student_ids[]"]');if(cb)cb.checked=false})});
    apply();
  }

  function run(){markPublic();prepareTables();setupAssignmentSync();setupAssignmentFilters()}
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
