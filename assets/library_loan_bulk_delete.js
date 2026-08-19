document.addEventListener('DOMContentLoaded',function(){
  var u=new URL(location.href);if(!/thuvien\.php$/.test(u.pathname)||u.searchParams.get('tab')!=='library_loans')return;
  var tables=[].slice.call(document.querySelectorAll('table'));var table=tables.find(function(t){var txt=(t.querySelector('thead')||{}).textContent||'';return /Người mượn/.test(txt)&&/Trạng thái/.test(txt);});if(!table)return;
  var tbody=table.querySelector('tbody'),theadRow=table.querySelector('thead tr');if(!tbody||!theadRow)return;
  var rows=[].slice.call(tbody.querySelectorAll('tr'));var selectable=[];
  rows.forEach(function(tr){
    var delForm=[].slice.call(tr.querySelectorAll('form')).find(function(f){var a=f.querySelector('input[name="action"]');return a&&a.value==='delete_loan';});if(!delForm)return;
    var batch=delForm.querySelector('input[name="batch_id"]'),id=delForm.querySelector('input[name="id"]');var key=(batch&&batch.value)||(id&&id.value);if(!key)return;
    var td=document.createElement('td');td.className='text-center align-middle no-print';td.style.width='42px';td.innerHTML='<input class="form-check-input loan-bulk-check" type="checkbox" aria-label="Chọn phiếu mượn để xóa">';tr.insertBefore(td,tr.firstChild);tr.dataset.loanDeleteKey=key;tr._loanDeleteForm=delForm;selectable.push(tr);
  });
  if(!selectable.length)return;
  var th=document.createElement('th');th.className='text-center no-print';th.style.width='42px';th.innerHTML='<input class="form-check-input" id="loanBulkAll" type="checkbox" title="Chọn tất cả">';theadRow.insertBefore(th,theadRow.firstChild);
  var card=table.closest('.card');if(!card)return;
  var toolbar=document.createElement('div');toolbar.className='d-flex flex-wrap align-items-center gap-2 p-3 border-bottom no-print';toolbar.innerHTML='<button type="button" class="btn btn-sm btn-outline-secondary" id="loanBulkSelectAll"><i class="bi bi-check2-square"></i> Chọn tất cả</button><button type="button" class="btn btn-sm btn-outline-secondary" id="loanBulkClear"><i class="bi bi-square"></i> Bỏ chọn</button><button type="button" class="btn btn-sm btn-danger" id="loanBulkDelete" disabled><i class="bi bi-trash"></i> Xóa đã chọn <span class="badge text-bg-light ms-1" id="loanBulkCount">0</span></button><span class="small text-muted" id="loanBulkStatus">Có thể chọn nhiều phiếu mượn để xóa cùng lúc.</span>';
  card.insertBefore(toolbar,card.firstChild);
  var all=toolbar.parentNode.querySelector('#loanBulkAll'),checks=function(){return [].slice.call(tbody.querySelectorAll('.loan-bulk-check'));},count=toolbar.querySelector('#loanBulkCount'),del=toolbar.querySelector('#loanBulkDelete'),status=toolbar.querySelector('#loanBulkStatus');
  function refresh(){var cs=checks(),n=cs.filter(function(c){return c.checked;}).length;count.textContent=n;del.disabled=n===0;if(all){all.checked=n>0&&n===cs.length;all.indeterminate=n>0&&n<cs.length;}}
  checks().forEach(function(c){c.addEventListener('change',refresh);});
  function setAll(v){checks().forEach(function(c){c.checked=v;});refresh();}
  if(all)all.addEventListener('change',function(){setAll(all.checked);});toolbar.querySelector('#loanBulkSelectAll').onclick=function(){setAll(true);};toolbar.querySelector('#loanBulkClear').onclick=function(){setAll(false);};
  del.onclick=async function(){var selected=checks().filter(function(c){return c.checked;});if(!selected.length)return;if(!confirm('Xóa '+selected.length+' phiếu mượn đã chọn?\n\nThao tác này xóa toàn bộ các đầu sách thuộc từng phiếu và không thể hoàn tác.'))return;del.disabled=true;status.textContent='Đang xóa '+selected.length+' phiếu…';var done=0,failed=0;
    for(var i=0;i<selected.length;i++){var tr=selected[i].closest('tr'),form=tr&&tr._loanDeleteForm;if(!form){failed++;continue;}try{var fd=new FormData(form);var r=await fetch(location.href,{method:'POST',body:fd,credentials:'same-origin',redirect:'follow'});if(r.ok)done++;else failed++;}catch(e){failed++;}status.textContent='Đã xử lý '+(i+1)+'/'+selected.length+' phiếu…';}
    if(failed){alert('Đã xóa '+done+' phiếu; '+failed+' phiếu không xóa được. Trang sẽ tải lại để cập nhật dữ liệu.');}location.reload();
  };
  refresh();
});