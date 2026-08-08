<?php
/** Giao diện gọn cho danh sách giáo viên PCCM; không thay đổi dữ liệu hay xử lý biểu mẫu. */
if (!isset($current) || $current !== 'giaovien') return;
?>
<style id="cdsTeacherCompactStyle">
.cds-teacher-tools{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin:0 0 .85rem;padding:.75rem 1rem;border:1px solid #bfdbfe;border-radius:12px;background:#eff6ff;color:#173f65}
.cds-teacher-tools strong{display:block}.cds-teacher-tools small{color:#64748b}.cds-teacher-actions{display:flex;flex-wrap:wrap;gap:.5rem}
body:not(.cds-teacher-advanced) .cds-teacher-advanced-col,body:not(.cds-teacher-advanced) .cds-teacher-advanced-only{display:none!important}
body:not(.cds-teacher-advanced) table[data-cds-teacher-table] select[multiple]{display:none!important}
body:not(.cds-teacher-advanced) table[data-cds-teacher-table] td{padding-top:.65rem;padding-bottom:.65rem;vertical-align:middle}
body:not(.cds-teacher-advanced) table[data-cds-teacher-table] tr{height:auto!important}
body:not(.cds-teacher-advanced) table[data-cds-teacher-table] .badge{white-space:normal;text-align:left;line-height:1.25}
@media(max-width:767px){.cds-teacher-tools{align-items:flex-start}.cds-teacher-actions{width:100%}.cds-teacher-actions .btn{flex:1}}
</style>
<script>
(function(){
  function norm(v){return String(v||'').replace(/\s+/g,' ').trim()}
  function headerIndex(headers,label){for(var i=0;i<headers.length;i++)if(norm(headers[i].textContent).toUpperCase()===label)return i;return-1}
  function markColumn(table,index){if(index<0)return;var rows=table.rows;for(var i=0;i<rows.length;i++)if(rows[i].cells[index])rows[i].cells[index].classList.add('cds-teacher-advanced-col')}
  function setup(){
    var table=null;
    document.querySelectorAll('table').forEach(function(candidate){var text=norm(candidate.querySelector('thead')?.textContent);if(!table&&/Họ tên/i.test(text)&&/Chuyên môn/i.test(text))table=candidate});
    if(!table)return;
    table.dataset.cdsTeacherTable='1';
    var headers=Array.from(table.querySelectorAll('thead th'));
    ['XH','TN','THCS','THPT','TS','HT','PHT'].forEach(function(label){markColumn(table,headerIndex(headers,label))});

    document.querySelectorAll('label').forEach(function(label){var text=norm(label.textContent).toUpperCase();if(['KHXH','KHTN','THCS','THPT','TẬP SỰ','HIỆU TRƯỞNG','PHÓ HIỆU TRƯỞNG'].indexOf(text)<0)return;(label.closest('.form-check')||label).classList.add('cds-teacher-advanced-only')});

    var addPanel=null;
    document.querySelectorAll('.card,.col,.col-md-3,.col-lg-3,section').forEach(function(node){if(addPanel)return;var title=norm(node.querySelector('.card-header,h3,h4,h5')?.textContent);if(/^Thêm giáo viên$/i.test(title))addPanel=node.classList.contains('card')?node:(node.querySelector('.card')||node)});
    if(addPanel)addPanel.classList.add('cds-teacher-advanced-only');

    var tools=document.createElement('div');tools.className='cds-teacher-tools';
    tools.innerHTML='<div><strong><i class="bi bi-database-check me-1"></i> Danh sách đồng bộ từ CSDL</strong><small>Chế độ gọn chỉ ẩn các trường kỹ thuật; toàn bộ cấu hình PCCM và phân công vẫn được giữ nguyên.</small></div><div class="cds-teacher-actions"><a class="btn btn-sm btn-outline-primary" href="/csdl.php?tab=teachers"><i class="bi bi-box-arrow-up-right"></i> Quản lý tại CSDL</a><button class="btn btn-sm btn-primary" type="button" id="cdsTeacherAdvanced"><i class="bi bi-sliders"></i> <span>Cấu hình nâng cao</span></button></div>';
    var host=table.closest('.card')||table.parentElement;host.parentElement.insertBefore(tools,host);
    var button=tools.querySelector('#cdsTeacherAdvanced');
    function render(){var advanced=document.body.classList.contains('cds-teacher-advanced');button.classList.toggle('btn-primary',!advanced);button.classList.toggle('btn-warning',advanced);button.querySelector('span').textContent=advanced?'Thu gọn danh sách':'Cấu hình nâng cao'}
    button.addEventListener('click',function(){document.body.classList.toggle('cds-teacher-advanced');render()});
    render();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup);else setup();
})();
</script>
