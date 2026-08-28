(function(){
  function qs(){return new URLSearchParams(location.search)}
  function num(v){var n=parseFloat(String(v||'').replace(/[^0-9.,-]/g,'').replace(',','.'));return isFinite(n)?Math.round(n):0}

  function applyTwentyPercentLowStock(){
    var table=document.getElementById('medicineTable'); if(!table)return;
    var lowCount=0;
    table.querySelectorAll('tbody tr[data-medicine-name]').forEach(function(row){
      var cells=row.children; if(cells.length<7)return;
      var imported=num(cells[4].textContent), remain=num(cells[6].textContent);
      var threshold=Math.round(imported*0.20);
      var isLow=imported>0 && remain<threshold;
      var stock=cells[6].querySelector('.health-stock');
      if(stock){
        stock.classList.toggle('low',isLow);
        stock.title=imported>0?('Ngưỡng sắp hết: dưới 20% tổng đã nhập = dưới '+threshold):'Chưa có số liệu nhập kho';
      }
      row.dataset.lowStock=isLow?'1':'0';
      row.dataset.lowThreshold=String(threshold);
      if(isLow)lowCount++;
    });
    var cards=document.querySelectorAll('.health-inventory-stats a');
    if(cards[1]){
      var strong=cards[1].querySelector('strong'); if(strong)strong.textContent=String(lowCount);
      var span=cards[1].querySelector('span'); if(span)span.textContent='Sắp hết (<20%)';
      cards[1].title='Thuốc có tồn hiện tại dưới 20% tổng số lượng đã nhập';
    }
  }

  function hideManualLowStockField(){
    var input=document.getElementById('medicineLowStock'); if(!input)return;
    var col=input.closest('.col-6');
    if(col){
      col.innerHTML='<label class="form-label">Ngưỡng sắp hết</label><div class="form-control bg-light text-muted">Tự động dưới 20% tổng đã nhập</div>';
    }
  }

  function addBulkDelete(){
    var table=document.getElementById('medicineTable'); if(!table)return;
    var canDelete=!!table.querySelector('input[name="action"][value="medicine_delete"]');
    if(!canDelete||document.getElementById('medicineBulkDeleteForm'))return;

    var headRow=table.querySelector('thead tr');
    if(headRow){
      var th=document.createElement('th'); th.style.width='42px'; th.className='text-center';
      th.innerHTML='<input class="form-check-input" type="checkbox" id="medicineSelectAll" title="Chọn tất cả thuốc đang hiển thị">';
      headRow.insertBefore(th,headRow.firstChild);
    }
    table.querySelectorAll('tbody tr[data-medicine-name]').forEach(function(row){
      var deleteId=row.querySelector('form input[name="id"]'); if(!deleteId)return;
      var td=document.createElement('td');td.className='text-center';
      td.innerHTML='<input class="form-check-input medicine-row-check" type="checkbox" value="'+String(deleteId.value).replace(/"/g,'&quot;')+'" aria-label="Chọn thuốc để xóa">';
      row.insertBefore(td,row.firstChild);
    });
    var empty=table.querySelector('tbody tr:not([data-medicine-name])'); if(empty&&empty.children[0])empty.children[0].colSpan=(parseInt(empty.children[0].colSpan||8,10)+1);

    var inventoryHead=document.querySelector('.health-inventory-head'); if(!inventoryHead)return;
    var bar=document.createElement('form'); bar.id='medicineBulkDeleteForm';bar.method='post';bar.action=(window.BASE_URL||'/')+'includes/noitru_medicine_bulk_delete.php';
    bar.className='d-none align-items-center gap-2 flex-wrap mt-2 p-2 border rounded bg-light';
    bar.innerHTML='<span class="fw-semibold text-danger"><i class="bi bi-check2-square"></i> Đã chọn <strong id="medicineSelectedCount">0</strong> thuốc</span><div id="medicineBulkIds"></div><button class="btn btn-danger btn-sm ms-auto" type="submit"><i class="bi bi-trash"></i> Xóa đã chọn</button>';
    inventoryHead.insertAdjacentElement('afterend',bar);

    function visibleChecks(){return Array.from(table.querySelectorAll('.medicine-row-check')).filter(function(cb){return !cb.closest('tr').hidden})}
    function refresh(){
      var selected=Array.from(table.querySelectorAll('.medicine-row-check:checked'));
      document.getElementById('medicineSelectedCount').textContent=String(selected.length);
      var holder=document.getElementById('medicineBulkIds');holder.innerHTML='';
      selected.forEach(function(cb){var i=document.createElement('input');i.type='hidden';i.name='medicine_ids[]';i.value=cb.value;holder.appendChild(i)});
      bar.classList.toggle('d-none',selected.length===0);bar.classList.toggle('d-flex',selected.length>0);
      var all=document.getElementById('medicineSelectAll'), vis=visibleChecks();
      if(all){all.checked=vis.length>0&&vis.every(function(cb){return cb.checked});all.indeterminate=vis.some(function(cb){return cb.checked})&&!all.checked;}
    }
    document.getElementById('medicineSelectAll')?.addEventListener('change',function(){visibleChecks().forEach(function(cb){cb.checked=this.checked},this);refresh()});
    table.addEventListener('change',function(e){if(e.target.classList.contains('medicine-row-check'))refresh()});
    bar.addEventListener('submit',function(e){var n=table.querySelectorAll('.medicine-row-check:checked').length;if(!n||!confirm('Bạn có chắc muốn xóa '+n+' loại thuốc đã chọn?\n\nLịch sử nhập/xuất thuốc vẫn được giữ nguyên.'))e.preventDefault()});
    window.ntMedicineBulkRefresh=refresh;
  }

  function addExcelImport(){
    var head=document.querySelector('.health-inventory-head'); if(!head||document.getElementById('medicineExcelImportBtn'))return;
    var actions=head.querySelector('div:last-child')||head;
    var btn=document.createElement('button'); btn.type='button'; btn.id='medicineExcelImportBtn'; btn.className='btn btn-outline-primary'; btn.innerHTML='<i class="bi bi-file-earmark-excel"></i> Nhập Excel';
    btn.setAttribute('data-bs-toggle','modal'); btn.setAttribute('data-bs-target','#medicineExcelImportModal'); actions.insertBefore(btn,actions.firstChild);
    var modal=document.createElement('div'); modal.className='modal fade'; modal.id='medicineExcelImportModal'; modal.tabIndex=-1;
    modal.innerHTML='<div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content" method="post" action="'+(window.BASE_URL||'/')+'noitru_medicine_excel.php" enctype="multipart/form-data">'
      +'<div class="modal-header"><div><h5 class="modal-title"><i class="bi bi-file-earmark-excel text-success"></i> Nhập kho thuốc bằng Excel</h5><div class="small text-muted mt-1">Hệ thống kiểm tra toàn bộ file trước khi ghi dữ liệu.</div></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>'
      +'<div class="modal-body"><div class="alert alert-info"><strong>Cấu trúc file:</strong> STT · Tên thuốc · Đơn vị · Số lượng · Hạn sử dụng · Ghi chú.<br><span class="small">Ngưỡng <strong>Sắp hết</strong> được tính tự động khi tồn còn <strong>dưới 20% tổng số lượng đã nhập</strong> của từng thuốc. Nếu thuốc cùng <strong>Tên thuốc + Đơn vị</strong> đã có, hệ thống cộng thêm số lượng vào tồn kho.</span></div>'
      +'<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-success" href="'+(window.BASE_URL||'/')+'noitru_medicine_excel.php?template=1"><i class="bi bi-download"></i> Tải file mẫu Excel</a></div>'
      +'<label class="form-label fw-bold">Chọn file kho thuốc (.xlsx)</label><input class="form-control" type="file" name="medicine_excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><div class="form-text mt-2">Nếu có bất kỳ dòng lỗi nào, hệ thống sẽ không nhập dữ liệu và báo rõ dòng cần sửa.</div>'
      +'</div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" type="submit"><i class="bi bi-shield-check"></i> Kiểm tra và nhập</button></div></form></div>';
    document.body.appendChild(modal);
  }

  function init(){
    if(qs().get('tab')!=='health'||qs().get('health_view')!=='inventory')return;
    addExcelImport();
    applyTwentyPercentLowStock();
    hideManualLowStockField();
    addBulkDelete();
    var search=document.getElementById('medicineSearch');
    if(search)search.addEventListener('input',function(){setTimeout(function(){if(window.ntMedicineBulkRefresh)window.ntMedicineBulkRefresh()},0)});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
