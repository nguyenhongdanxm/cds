(function(){
  function qs(){return new URLSearchParams(location.search)}
  function init(){
    if(qs().get('tab')!=='health'||qs().get('health_view')!=='inventory')return;
    var head=document.querySelector('.health-inventory-head'); if(!head||document.getElementById('medicineExcelImportBtn'))return;
    var actions=head.querySelector('div:last-child')||head;
    var btn=document.createElement('button'); btn.type='button'; btn.id='medicineExcelImportBtn'; btn.className='btn btn-outline-primary'; btn.innerHTML='<i class="bi bi-file-earmark-excel"></i> Nhập Excel';
    btn.setAttribute('data-bs-toggle','modal'); btn.setAttribute('data-bs-target','#medicineExcelImportModal');
    actions.insertBefore(btn,actions.firstChild);

    var modal=document.createElement('div'); modal.className='modal fade'; modal.id='medicineExcelImportModal'; modal.tabIndex=-1;
    modal.innerHTML='<div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content" method="post" action="'+(window.BASE_URL||'/')+'noitru_medicine_excel.php" enctype="multipart/form-data">'
      +'<div class="modal-header"><div><h5 class="modal-title"><i class="bi bi-file-earmark-excel text-success"></i> Nhập kho thuốc bằng Excel</h5><div class="small text-muted mt-1">Hệ thống kiểm tra toàn bộ file trước khi ghi dữ liệu.</div></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>'
      +'<div class="modal-body">'
      +'<div class="alert alert-info"><strong>Cấu trúc file:</strong> STT · Tên thuốc · Đơn vị · Số lượng · Hạn sử dụng · Ngưỡng sắp hết · Ghi chú.<br><span class="small">Nếu thuốc cùng <strong>Tên thuốc + Đơn vị</strong> đã có, hệ thống cập nhật thông tin và cộng số lượng vào tồn kho; không tạo bản ghi trùng.</span></div>'
      +'<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-success" href="'+(window.BASE_URL||'/')+'noitru_medicine_excel.php?template=1"><i class="bi bi-download"></i> Tải file mẫu Excel</a></div>'
      +'<label class="form-label fw-bold">Chọn file kho thuốc (.xlsx)</label><input class="form-control" type="file" name="medicine_excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>'
      +'<div class="form-text mt-2">Nếu có bất kỳ dòng lỗi nào, hệ thống sẽ không nhập dữ liệu và báo rõ dòng cần sửa.</div>'
      +'</div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" type="submit"><i class="bi bi-shield-check"></i> Kiểm tra và nhập</button></div></form></div>';
    document.body.appendChild(modal);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
