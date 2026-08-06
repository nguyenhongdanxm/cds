<?php
/** Hiệu chỉnh phân công thủ công trong bảng Danh sách PCCM. */
if (!isset($current) || $current !== 'danhsach') return;

$manualFile = dirname(__DIR__) . '/data/manual_assignments.json';
$manualRows = [];
if (is_file($manualFile)) {
    $decoded = json_decode((string)file_get_contents($manualFile), true);
    if (is_array($decoded)) $manualRows = array_values(array_filter($decoded, 'is_array'));
}
$manualRowsJson = json_encode($manualRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<style>
/* Phân công tự chọn: cùng kích thước chip thường, dùng tím để phân biệt. */
td.cds-manual-teaching-cell .cds-manual-chip{
  display:inline-flex;align-items:center;gap:.28rem;margin:.16rem .2rem .16rem 0;
  padding:.24rem .55rem;border:1px dashed #7c3aed;border-radius:999px;
  background:#f5f0ff;color:#5b21b6;font-size:.82rem;font-weight:650;vertical-align:middle
}
td.cds-manual-teaching-cell .cds-manual-mark{
  margin-left:.15rem;padding:.08rem .28rem;border-radius:5px;background:#7c3aed;
  color:#fff;font-size:.62rem;font-weight:800
}
</style>
<script>
(function(){
  var rowsData=<?= $manualRowsJson ?>;
  function norm(v){return String(v==null?'':v).replace(/\s+/g,' ').trim()}
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})}
  function num(v){return parseFloat(String(v||'').replace(',','.'))||0}
  function fmt(v){
    v=Math.round((num(v)+Number.EPSILON)*10)/10;
    return Math.abs(v-Math.round(v))<.00001 ? v.toFixed(2) : v.toFixed(1).replace('.',',');
  }
  function findIndex(headers, pattern){
    for(var i=0;i<headers.length;i++) if(pattern.test(norm(headers[i].textContent))) return i;
    return -1;
  }
  function apply(){
    document.querySelectorAll('table').forEach(function(table){
      var headers=Array.from(table.querySelectorAll('thead th'));
      if(!headers.length)return;
      var teacherIdx=findIndex(headers,/^Giáo viên$/i);
      var teachingIdx=findIndex(headers,/^Phân công dạy$/i);
      var totalIdx=findIndex(headers,/^Số tiết$/i);
      if(teacherIdx<0||teachingIdx<0||totalIdx<0)return;

      table.querySelectorAll('tbody tr').forEach(function(tr){
        var cells=tr.cells;if(!cells||cells.length<=Math.max(teacherIdx,teachingIdx,totalIdx))return;
        var teacherCell=cells[teacherIdx], teachingCell=cells[teachingIdx], totalCell=cells[totalIdx];
        var teacher=norm((teacherCell.querySelector('strong,.fw-bold')||teacherCell).textContent);
        if(!teacher)return;
        var matches=rowsData.filter(function(r){return norm(r.teacher)===teacher});

        /* Xóa chip đã bị gắn nhầm ở bất kỳ cột nào, rồi dựng lại đúng cột. */
        tr.querySelectorAll('.cds-manual-chip').forEach(function(chip){chip.remove()});
        tr.querySelectorAll('[data-cds-manual-group]').forEach(function(group){group.remove()});
        if(!matches.length)return;

        teachingCell.classList.add('cds-manual-teaching-cell');
        var group=document.createElement('span');group.setAttribute('data-cds-manual-group','1');
        matches.forEach(function(r){
          var chip=document.createElement('span');chip.className='cds-manual-chip';
          chip.title='Phân công tự chọn'+(r.note?' – '+r.note:'');
          chip.innerHTML='<i class="bi bi-pencil-square"></i><span>'+esc(r.subject)+' '+esc(r.class_name||'')+' ('+String(Math.round(num(r.periods)*10)/10).replace('.',',')+'t)</span><span class="cds-manual-mark">TC</span>';
          group.appendChild(chip);
        });
        teachingCell.appendChild(group);

        var extra=matches.reduce(function(sum,r){return sum+num(r.periods)},0);
        if(extra<=0)return;

        /* Lưu nội dung gốc để không cộng lặp khi script chạy lại. */
        if(!totalCell.dataset.cdsManualOriginal) totalCell.dataset.cdsManualOriginal=totalCell.innerHTML;
        else totalCell.innerHTML=totalCell.dataset.cdsManualOriginal;

        var all=Array.from(totalCell.querySelectorAll('*')).filter(function(el){return el.children.length===0});
        var mainEl=null,diffEl=null;
        all.forEach(function(el){
          var t=norm(el.textContent);
          if(!mainEl&&/^\d+(?:[.,]\d+)?$/.test(t)) mainEl=el;
          if(!diffEl&&/[+-]?\d+(?:[.,]\d+)?\s*\/\s*\d+(?:[.,]\d+)?/.test(t)) diffEl=el;
        });
        if(!mainEl){
          var textNodes=Array.from(totalCell.childNodes).filter(function(n){return n.nodeType===3&&/\d/.test(n.nodeValue||'')});
          if(textNodes.length){
            var span=document.createElement('span');span.textContent=textNodes[0].nodeValue.trim();textNodes[0].replaceWith(span);mainEl=span;
          }
        }
        if(mainEl){
          var base=num(mainEl.textContent), updated=base+extra;
          mainEl.textContent=fmt(updated);
          if(diffEl){
            var m=norm(diffEl.textContent).match(/([+-]?\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)/);
            if(m){
              var quota=num(m[2]),difference=updated-quota;
              diffEl.textContent=(difference>0?'+':'')+fmt(difference)+' / '+String(m[2]).replace('.',',');
              diffEl.style.color=difference>0?'#dc3545':(difference<0?'#f0ad00':'#198754');
            }
          }
        }
      });
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(apply,0)});
  else setTimeout(apply,0);
  window.addEventListener('load',function(){setTimeout(apply,30)});
})();
</script>
